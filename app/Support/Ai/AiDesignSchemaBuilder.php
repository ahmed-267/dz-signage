<?php

namespace App\Support\Ai;

use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Models\BrandKit;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Rendering\LayoutSchema;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\LayoutSchemaValidator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns an untrusted AI design plan into a validated LayoutSchema v1.
 */
final class AiDesignSchemaBuilder
{
    private const ALLOWED_TYPES = ['text', 'shape', 'image', 'logo', 'widget'];

    /**
     * @param  list<string>|null  $brandColors
     * @return array<string, mixed>
     */
    public static function build(
        DesignPlanResult $plan,
        TemplateOrientation $orientation,
        ?TemplateTheme $theme = null,
        ?array $brandColors = null,
        ?CreativeBrief $brief = null,
        ?string $headingFont = null,
        ?string $bodyFont = null,
        ?int $logoMediaAssetId = null,
    ): array {
        $theme ??= TemplateTheme::Blank;
        $blank = LayoutSchema::blank($orientation, $theme);
        $canvasW = (int) $blank['canvas']['width'];
        $canvasH = (int) $blank['canvas']['height'];

        if ($brief !== null) {
            $brandColors = $brief->brandColors !== [] ? $brief->brandColors : $brandColors;
            $headingFont ??= $brief->headingFont();
            $bodyFont ??= $brief->bodyFont();
            $logoMediaAssetId ??= $brief->logoMediaAssetId;
        }

        $headingFont ??= 'Outfit';
        $bodyFont ??= 'system-ui';
        $headingStack = BrandKit::fontStack($headingFont);
        $bodyStack = BrandKit::fontStack($bodyFont);

        if (is_array($plan->background) && ($plan->background['type'] ?? null) === 'color') {
            $value = (string) ($plan->background['value'] ?? '');
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                $blank['canvas']['background'] = [
                    'type' => 'color',
                    'value' => $value,
                ];
            }
        }

        $allowedWidgets = config('ai.design.allowed_widgets', []);
        $maxElements = (int) config('ai.design.max_elements', 14);
        $elements = [];
        $z = 1;

        foreach (array_slice($plan->elements, 0, $maxElements) as $raw) {
            $type = strtolower((string) ($raw['type'] ?? ''));
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                continue;
            }

            if ($type === 'widget') {
                $widgetType = strtolower((string) ($raw['widgetType'] ?? ''));
                if (! in_array($widgetType, $allowedWidgets, true)) {
                    continue;
                }
            }

            $width = self::clamp((float) ($raw['width'] ?? 200), 40, $canvasW);
            $height = self::clamp((float) ($raw['height'] ?? 80), 40, $canvasH);
            $x = self::clamp((float) ($raw['x'] ?? 0), 0, max(0, $canvasW - $width));
            $y = self::clamp((float) ($raw['y'] ?? 0), 0, max(0, $canvasH - $height));

            $element = [
                'id' => 'el-'.Str::lower(Str::random(10)),
                'type' => $type,
                'name' => self::safeName((string) ($raw['name'] ?? $type)),
                'x' => (int) round($x),
                'y' => (int) round($y),
                'width' => (int) round($width),
                'height' => (int) round($height),
                'rotation' => 0,
                'zIndex' => (int) ($raw['zIndex'] ?? $z),
                'locked' => false,
                'editable' => true,
                'props' => [],
            ];

            $element['props'] = match ($type) {
                'text' => self::textProps($raw, $brandColors, $headingStack, $bodyStack, (int) round($width), (int) round($height)),
                'shape' => [
                    'shape' => 'rectangle',
                    'fill' => self::safeColor((string) ($raw['fill'] ?? '#1e293b'), $brandColors[0] ?? '#1e293b'),
                    ...(isset($raw['opacity']) && is_numeric($raw['opacity'])
                        ? ['opacity' => max(0.0, min(1.0, (float) $raw['opacity']))]
                        : []),
                ],
                'image' => [
                    'mediaAssetId' => null,
                    'objectFit' => 'cover',
                ],
                'logo' => [
                    'mediaAssetId' => $logoMediaAssetId,
                    'objectFit' => 'contain',
                ],
                'widget' => [
                    'widgetType' => strtolower((string) $raw['widgetType']),
                    'config' => self::safeWidgetConfig(strtolower((string) $raw['widgetType'])),
                ],
            };

            $elements[] = $element;
            $z++;
        }

        if ($elements === []) {
            $elements = self::fallbackElements($canvasW, $canvasH, $plan->suggestedName, $headingStack);
        }

        $blank['elements'] = $elements;

        try {
            $validated = LayoutSchemaValidator::validate(
                LayoutSchemaNormalizer::normalize($blank),
            );
        } catch (ValidationException $e) {
            throw AiException::invalidOutput('The generated design could not be validated.');
        }

        $normalized = DesignQualityValidator::normalize($validated);
        // Single deterministic refine pass (margins + text fit) before quality gate.
        $normalized = DesignQualityValidator::refine($normalized);
        $quality = DesignQualityValidator::validate($normalized);

        if (! $quality['ok']) {
            throw AiException::invalidOutput(
                'The generated design failed quality checks: '.implode(' ', array_slice($quality['issues'], 0, 3)),
            );
        }

        try {
            return LayoutSchemaValidator::validate(
                LayoutSchemaNormalizer::normalize($quality['schema']),
            );
        } catch (ValidationException $e) {
            throw AiException::invalidOutput('The generated design could not be validated.');
        }
    }

    /**
     * Adapt a copied Template schema to the creative brief (copy + brand colours + logo).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function adaptTemplateSchema(array $schema, CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        if (is_array($schema['canvas'] ?? null)) {
            $schema['canvas']['background'] = [
                'type' => 'color',
                'value' => $bg,
            ];
        }

        $headingStack = BrandKit::fontStack($brief->headingFont());
        $bodyStack = BrandKit::fontStack($brief->bodyFont());
        $textColor = $brief->textColor();
        $accent = $brief->accentColor();

        if (! is_array($schema['elements'] ?? null)) {
            $schema['elements'] = [];
        }

        foreach ($schema['elements'] as &$el) {
            if (! is_array($el)) {
                continue;
            }

            $type = (string) ($el['type'] ?? '');
            $name = strtolower((string) ($el['name'] ?? ''));

            if ($type === 'text') {
                $props = is_array($el['props'] ?? null) ? $el['props'] : [];
                if (str_contains($name, 'headline') || str_contains($name, 'title') || str_contains($name, 'heading')) {
                    $props['text'] = $brief->headline !== '' ? $brief->headline : ($props['text'] ?? 'Headline');
                    $props['color'] = $textColor;
                    $props['fontFamily'] = $headingStack;
                } elseif (str_contains($name, 'sub') || str_contains($name, 'subtitle')) {
                    $props['text'] = $brief->subheadline !== '' ? $brief->subheadline : ($props['text'] ?? '');
                    $props['color'] = $textColor;
                    $props['fontFamily'] = $bodyStack;
                } elseif (str_contains($name, 'cta') || str_contains($name, 'button')) {
                    $props['text'] = $brief->cta !== '' ? $brief->cta : ($props['text'] ?? 'Learn more');
                    $props['color'] = $accent;
                    $props['fontFamily'] = $headingStack;
                } elseif (str_contains($name, 'body') || str_contains($name, 'copy')) {
                    $props['text'] = $brief->body !== '' ? $brief->body : ($props['text'] ?? '');
                    $props['color'] = $textColor;
                    $props['fontFamily'] = $bodyStack;
                }
                $el['props'] = $props;
            }

            if ($type === 'logo' && $brief->logoMediaAssetId !== null) {
                $props = is_array($el['props'] ?? null) ? $el['props'] : [];
                $props['mediaAssetId'] = $brief->logoMediaAssetId;
                $props['objectFit'] = $props['objectFit'] ?? 'contain';
                $el['props'] = $props;
            }

            if ($type === 'shape') {
                $props = is_array($el['props'] ?? null) ? $el['props'] : [];
                if (str_contains($name, 'accent') || str_contains($name, 'cta')) {
                    $props['fill'] = $accent;
                }
                $el['props'] = $props;
            }
        }
        unset($el);

        try {
            $validated = LayoutSchemaValidator::validate(
                LayoutSchemaNormalizer::normalize($schema),
            );
        } catch (ValidationException) {
            throw AiException::invalidOutput('The adapted template design could not be validated.');
        }

        $normalized = DesignQualityValidator::normalize($validated);
        $normalized = DesignQualityValidator::refine($normalized);
        $quality = DesignQualityValidator::validate($normalized);

        // Template structures may intentionally fail some AI quality heuristics;
        // keep the normalized schema even when soft issues remain.
        try {
            return LayoutSchemaValidator::validate(
                LayoutSchemaNormalizer::normalize($quality['schema']),
            );
        } catch (ValidationException) {
            return $normalized;
        }
    }

    /**
     * Bind media asset IDs into unbound image (then video-capable image) slots.
     *
     * @param  array<string, mixed>  $schema
     * @param  list<int>  $mediaAssetIds
     * @return array<string, mixed>
     */
    public static function bindMediaAssets(array $schema, array $mediaAssetIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $mediaAssetIds), static fn (int $id) => $id > 0));
        if ($ids === [] || ! is_array($schema['elements'] ?? null)) {
            return $schema;
        }

        $index = 0;
        foreach ($schema['elements'] as &$el) {
            if ($index >= count($ids)) {
                break;
            }
            if (($el['type'] ?? '') !== 'image') {
                continue;
            }
            $props = is_array($el['props'] ?? null) ? $el['props'] : [];
            if (! empty($props['mediaAssetId'])) {
                continue;
            }
            $props['mediaAssetId'] = $ids[$index];
            $props['objectFit'] = $props['objectFit'] ?? 'cover';
            $el['props'] = $props;
            $index++;
        }
        unset($el);

        return $schema;
    }

    /**
     * Whether the schema still has an image element without media.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function hasUnboundImageSlot(array $schema): bool
    {
        foreach ($schema['elements'] ?? [] as $el) {
            if (($el['type'] ?? '') === 'image' && empty($el['props']['mediaAssetId'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build from a CreativeBrief using an archetype plan, with one alternate retry on quality failure.
     *
     * Geometry always comes from DesignArchetypes. Provider plans may only contribute
     * suggestedName, background colour, and text/colour copy matched by element role.
     *
     * @return array{schema: array<string, mixed>, archetype: string, plan: DesignPlanResult}
     */
    public static function buildFromBrief(CreativeBrief $brief, ?string $archetype = null, ?DesignPlanResult $providerPlan = null): array
    {
        $orientation = TemplateOrientation::from($brief->orientation);
        $primary = $archetype ?? DesignArchetypes::selectFor($brief);
        $candidates = DesignArchetypes::variantsFor($brief, 3);
        if (! in_array($primary, $candidates, true)) {
            array_unshift($candidates, $primary);
        }
        $candidates = array_values(array_unique($candidates));

        $lastError = null;
        $attempt = 0;

        foreach ($candidates as $candidate) {
            if ($attempt >= 2) {
                break;
            }
            $attempt++;

            try {
                $geoPlan = DesignArchetypes::plan($brief, $candidate);

                if ($attempt === 1 && $providerPlan !== null) {
                    $candidatePlan = self::mergeProviderCopyOntoArchetype($geoPlan, $providerPlan);
                } else {
                    $candidatePlan = $geoPlan;
                }

                $schema = self::build(
                    $candidatePlan,
                    $orientation,
                    brandColors: $brief->brandColors,
                    brief: $brief,
                );

                return [
                    'schema' => $schema,
                    'archetype' => $candidate,
                    'plan' => $candidatePlan,
                ];
            } catch (AiException $e) {
                $lastError = $e;
            }
        }

        throw $lastError;
    }

    /**
     * Keep archetype x/y/width/height; overlay provider text, colours, and name.
     */
    public static function mergeProviderCopyOntoArchetype(
        DesignPlanResult $archetypePlan,
        DesignPlanResult $providerPlan,
    ): DesignPlanResult {
        $copyByRole = self::extractCopyByRole($providerPlan->elements);

        $elements = [];
        foreach ($archetypePlan->elements as $el) {
            $type = strtolower((string) ($el['type'] ?? ''));
            $role = self::elementRole((string) ($el['name'] ?? ''), $type);

            if ($type === 'text' && $role !== null && isset($copyByRole[$role])) {
                $overlay = $copyByRole[$role];
                if (isset($overlay['text']) && is_string($overlay['text']) && trim($overlay['text']) !== '') {
                    $el['text'] = trim($overlay['text']);
                }
                if (isset($overlay['color']) && is_string($overlay['color'])
                    && preg_match('/^#[0-9A-Fa-f]{6}$/', $overlay['color']) === 1) {
                    $el['color'] = $overlay['color'];
                }
                if (isset($overlay['fontWeight']) && is_string($overlay['fontWeight'])) {
                    $el['fontWeight'] = $overlay['fontWeight'];
                }
                if (isset($overlay['align']) && is_string($overlay['align'])) {
                    $el['align'] = $overlay['align'];
                }
                // Never take provider fontSize for layout — archetypes own hierarchy.
            }

            if ($type === 'shape' && isset($copyByRole['_accent_fill'])
                && (str_contains(strtolower((string) ($el['name'] ?? '')), 'accent')
                    || str_contains(strtolower((string) ($el['name'] ?? '')), 'cta')
                    || str_contains(strtolower((string) ($el['name'] ?? '')), 'date'))) {
                $el['fill'] = $copyByRole['_accent_fill'];
            }

            $elements[] = $el;
        }

        $background = $archetypePlan->background;
        if (is_array($providerPlan->background)
            && ($providerPlan->background['type'] ?? null) === 'color'
            && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($providerPlan->background['value'] ?? '')) === 1) {
            $background = $providerPlan->background;
        }

        return new DesignPlanResult(
            elements: $elements,
            background: $background,
            suggestedName: $providerPlan->suggestedName ?? $archetypePlan->suggestedName,
            inputTokens: $providerPlan->inputTokens,
            outputTokens: $providerPlan->outputTokens,
            model: $providerPlan->model ?? $archetypePlan->model,
            archetype: $archetypePlan->archetype ?? $providerPlan->archetype,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array<string, array<string, mixed>|string>
     */
    private static function extractCopyByRole(array $elements): array
    {
        $byRole = [];

        foreach ($elements as $el) {
            $type = strtolower((string) ($el['type'] ?? ''));
            $name = (string) ($el['name'] ?? '');
            $role = self::elementRole($name, $type);

            if ($type === 'text' && $role !== null) {
                $text = trim(strip_tags((string) ($el['text'] ?? $el['props']['text'] ?? '')));
                if ($text === '') {
                    continue;
                }
                $byRole[$role] = [
                    'text' => $text,
                    'color' => $el['color'] ?? $el['props']['color'] ?? null,
                    'fontWeight' => $el['fontWeight'] ?? $el['props']['fontWeight'] ?? null,
                    'align' => $el['align'] ?? $el['props']['align'] ?? null,
                ];
            }

            if ($type === 'shape') {
                $fill = (string) ($el['fill'] ?? $el['props']['fill'] ?? '');
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $fill) === 1
                    && (str_contains(strtolower($name), 'accent')
                        || str_contains(strtolower($name), 'cta')
                        || str_contains(strtolower($name), 'date'))) {
                    $byRole['_accent_fill'] = $fill;
                }
            }
        }

        // Flat copy bag from providers that return copy-only JSON keys on a synthetic element.
        foreach (['headline', 'subheadline', 'body', 'cta', 'eyebrow', 'date', 'day'] as $key) {
            foreach ($elements as $el) {
                if (isset($el[$key]) && is_string($el[$key]) && trim($el[$key]) !== '' && ! isset($byRole[$key])) {
                    $byRole[$key] = ['text' => trim($el[$key])];
                }
            }
        }

        return $byRole;
    }

    private static function elementRole(string $name, string $type): ?string
    {
        if ($type !== 'text') {
            return null;
        }

        $n = strtolower($name);

        return match (true) {
            str_contains($n, 'headline') || str_contains($n, 'heading') || $n === 'title' => 'headline',
            str_contains($n, 'subhead') || str_contains($n, 'subtitle') || str_contains($n, 'sub-') => 'subheadline',
            str_contains($n, 'cta') || str_contains($n, 'button') => 'cta',
            str_contains($n, 'eyebrow') || str_contains($n, 'kicker') => 'eyebrow',
            $n === 'date' || str_contains($n, 'date label') => 'date',
            $n === 'day' || str_contains($n, 'day number') => 'day',
            str_contains($n, 'body') || str_contains($n, 'copy') || str_contains($n, 'card a body') => 'body',
            str_contains($n, 'item') || str_contains($n, 'price') => null,
            default => str_contains($n, 'card') && str_contains($n, 'title') ? 'eyebrow' : null,
        };
    }

    /**
     * Deterministic landscape/portrait plan used by the fake provider path.
     *
     * @deprecated Prefer DesignArchetypes::plan(CreativeBrief)
     */
    public static function fakePlan(string $prompt, string $purpose): DesignPlanResult
    {
        $brief = new CreativeBrief(
            prompt: $prompt,
            purpose: $purpose,
            style: 'professional',
            orientation: 'landscape',
            industry: 'other',
            useBrandKit: false,
            headline: mb_strtoupper(mb_substr(trim($prompt) !== '' ? $prompt : $purpose, 0, 40)),
            subheadline: 'Limited time offer',
            cta: 'Shop now',
            body: mb_substr($prompt, 0, 100),
            brandColors: ['#0F172A', '#334155', '#0D9488', '#0B1220', '#F8FAFC'],
            brandFonts: ['heading' => 'Outfit', 'body' => 'system-ui'],
            meta: ['suggested_name' => 'AI '.$purpose],
        );

        return DesignArchetypes::plan($brief, DesignArchetypes::selectFor($brief));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>|null  $brandColors
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>|null  $brandColors
     * @return array<string, mixed>
     */
    private static function textProps(
        array $raw,
        ?array $brandColors,
        string $headingStack,
        string $bodyStack,
        int $boxWidth = 400,
        int $boxHeight = 120,
    ): array {
        $maxChars = (int) config('ai.design.max_text_chars', 120);
        $minHeadline = (int) config('ai.design.min_headline_font', 48);
        $minBody = (int) config('ai.design.min_body_font', 28);

        $text = trim(strip_tags((string) ($raw['text'] ?? '')));
        $fontSize = self::clamp((float) ($raw['fontSize'] ?? 64), 24, 200);
        $name = strtolower((string) ($raw['name'] ?? ''));
        $isHeadline = str_contains($name, 'headline')
            || str_contains($name, 'title')
            || $name === 'day'
            || str_contains($name, 'day number')
            || $fontSize >= 56;
        $minFont = $isHeadline ? $minHeadline : $minBody;
        $maxFont = $isHeadline ? 200 : 72;

        $fitted = TextFit::fit(
            $text !== '' ? $text : ($isHeadline ? 'Headline' : 'Details'),
            $fontSize,
            (float) $boxWidth,
            (float) $boxHeight,
            $minFont,
            $maxFont,
            maxChars: $isHeadline ? min(48, $maxChars) : $maxChars,
        );

        return [
            'text' => $fitted['text'] !== '' ? $fitted['text'] : ($isHeadline ? 'Headline' : 'Details'),
            'color' => self::safeColor((string) ($raw['color'] ?? '#ffffff'), $brandColors[4] ?? $brandColors[1] ?? '#ffffff'),
            'fontSize' => $fitted['fontSize'],
            'fontWeight' => in_array((string) ($raw['fontWeight'] ?? '700'), ['400', '500', '600', '700', '800'], true)
                ? (string) $raw['fontWeight']
                : '700',
            'align' => in_array((string) ($raw['align'] ?? 'left'), ['left', 'center', 'right'], true)
                ? (string) $raw['align']
                : 'left',
            'fontFamily' => $isHeadline ? $headingStack : $bodyStack,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function safeWidgetConfig(string $widgetType): array
    {
        return match ($widgetType) {
            'clock' => [
                'timezone' => 'UTC',
                'format' => 'HH:mm',
                'showDate' => false,
                'showSeconds' => false,
                'showWeekday' => false,
            ],
            'countdown' => [
                'timezone' => 'UTC',
                'targetAt' => now()->addDays(7)->utc()->format('Y-m-d\\TH:i:s\\Z'),
                'label' => 'Starts in',
            ],
            'alert' => ['message' => 'Attention', 'severity' => 'info'],
            'info_card' => ['title' => 'Info', 'body' => 'Details'],
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fallbackElements(int $canvasW, int $canvasH, ?string $name, string $headingStack): array
    {
        return [
            [
                'id' => 'el-'.Str::lower(Str::random(10)),
                'type' => 'shape',
                'name' => 'Backdrop',
                'x' => 0,
                'y' => 0,
                'width' => $canvasW,
                'height' => $canvasH,
                'rotation' => 0,
                'zIndex' => 1,
                'locked' => false,
                'editable' => true,
                'props' => [
                    'shape' => 'rectangle',
                    'fill' => '#0F172A',
                ],
            ],
            [
                'id' => 'el-'.Str::lower(Str::random(10)),
                'type' => 'text',
                'name' => 'Headline',
                'x' => (int) round($canvasW * 0.08),
                'y' => (int) round($canvasH * 0.32),
                'width' => (int) round($canvasW * 0.7),
                'height' => (int) round($canvasH * 0.18),
                'rotation' => 0,
                'zIndex' => 2,
                'locked' => false,
                'editable' => true,
                'props' => [
                    'text' => $name ?: 'New design',
                    'color' => '#ffffff',
                    'fontSize' => 72,
                    'fontWeight' => '700',
                    'align' => 'left',
                    'fontFamily' => $headingStack,
                ],
            ],
            [
                'id' => 'el-'.Str::lower(Str::random(10)),
                'type' => 'text',
                'name' => 'CTA',
                'x' => (int) round($canvasW * 0.08),
                'y' => (int) round($canvasH * 0.55),
                'width' => (int) round($canvasW * 0.25),
                'height' => (int) round($canvasH * 0.08),
                'rotation' => 0,
                'zIndex' => 3,
                'locked' => false,
                'editable' => true,
                'props' => [
                    'text' => 'Learn more',
                    'color' => '#0D9488',
                    'fontSize' => 36,
                    'fontWeight' => '700',
                    'align' => 'left',
                    'fontFamily' => $headingStack,
                ],
            ],
        ];
    }

    private static function safeColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $fallback;
    }

    private static function safeName(string $name): string
    {
        $clean = trim(strip_tags($name));

        return $clean !== '' ? mb_substr($clean, 0, 80) : 'Element';
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
