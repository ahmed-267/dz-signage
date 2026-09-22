<?php

namespace App\Support\Ai;

use App\Actions\Media\CreateMediaAsset;
use App\Actions\ScreenDesigns\CreateBlankScreenDesign;
use App\Actions\ScreenDesigns\CreateScreenDesignFromTemplate;
use App\Actions\ScreenDesigns\SaveScreenDesignDraft;
use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use App\Enums\MediaType;
use App\Enums\TemplateOrientation;
use App\Models\AiGeneration;
use App\Models\MediaAsset;
use App\Models\ScreenDesign;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Platform\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Central AI content orchestration. Controllers call this — never providers directly.
 */
final class AiContentService
{
    public function __construct(
        private readonly CreateMediaAsset $createMediaAsset,
        private readonly CreateBlankScreenDesign $createBlankScreenDesign,
        private readonly CreateScreenDesignFromTemplate $createScreenDesignFromTemplate,
        private readonly SaveScreenDesignDraft $saveScreenDesignDraft,
    ) {}

    /**
     * @param  array{
     *     purpose?: string|null,
     *     mode?: string|null,
     *     tone?: string|null,
     *     length?: string|null,
     *     variants?: int|null,
     *     idempotency_key?: string|null
     * }  $options
     * @return array{generation: AiGeneration, text: string, texts: list<string>}
     */
    public function generateText(User $user, Workspace $workspace, string $prompt, array $options = []): array
    {
        $this->assertAvailable();
        $this->assertRateLimits($user, $workspace);
        $prompt = $this->normalizePrompt($prompt);

        $variants = max(1, min(
            (int) config('ai.limits.text_variants_max', 3),
            (int) ($options['variants'] ?? config('ai.text.default_variants', 3)),
        ));

        $generation = $this->startGeneration(
            $user,
            $workspace,
            AiGenerationType::Text,
            $prompt,
            [
                ...$options,
                'variants' => $variants,
            ],
            $options['idempotency_key'] ?? null,
        );

        if ($generation->status === AiGenerationStatus::Completed) {
            $texts = $this->textsFromOutput($generation->output);
            if ($texts !== []) {
                return [
                    'generation' => $generation,
                    'text' => $texts[0],
                    'texts' => $texts,
                ];
            }
        }

        try {
            $result = AiAvailability::textProvider()->generate($prompt, [
                'purpose' => $options['purpose'] ?? null,
                'mode' => $options['mode'] ?? null,
                'tone' => $options['tone'] ?? null,
                'length' => $options['length'] ?? 'short',
                'variants' => $variants,
                'max_chars' => (int) config('ai.limits.text_max_chars', 400),
            ]);

            $texts = $result->allTexts();

            $generation->forceFill([
                'status' => AiGenerationStatus::Completed,
                'model' => $result->model,
                'output' => ['text' => $texts[0], 'texts' => $texts],
                'usage_input_tokens' => $result->inputTokens,
                'usage_output_tokens' => $result->outputTokens,
                'completed_at' => now(),
            ])->save();

            return [
                'generation' => $generation->fresh() ?? $generation,
                'text' => $texts[0],
                'texts' => $texts,
            ];
        } catch (AiException $e) {
            $this->failGeneration($generation, $e);
            throw $e;
        }
    }

    /**
     * @param  array{
     *     action?: string,
     *     variants?: int|null,
     *     idempotency_key?: string|null
     * }  $options
     * @return array{generation: AiGeneration, text: string, texts: list<string>}
     */
    public function rewriteText(User $user, Workspace $workspace, string $text, array $options = []): array
    {
        $this->assertAvailable();
        $this->assertRateLimits($user, $workspace);

        $text = trim($text);
        if ($text === '') {
            throw ValidationException::withMessages(['text' => 'Text is required.']);
        }

        $maxPrompt = (int) config('ai.limits.prompt_max', 2000);
        if (mb_strlen($text) > $maxPrompt) {
            throw ValidationException::withMessages(['text' => 'Text is too long.']);
        }

        $action = (string) ($options['action'] ?? 'simplify');
        if (! in_array($action, config('ai.text.rewrite_actions', []), true)) {
            throw ValidationException::withMessages(['action' => 'Invalid rewrite action.']);
        }

        $variants = max(1, min(
            (int) config('ai.limits.text_variants_max', 3),
            (int) ($options['variants'] ?? 1),
        ));

        $generation = $this->startGeneration(
            $user,
            $workspace,
            AiGenerationType::Text,
            $text,
            ['action' => $action, 'mode' => 'rewrite', 'variants' => $variants],
            $options['idempotency_key'] ?? null,
        );

        if ($generation->status === AiGenerationStatus::Completed) {
            $texts = $this->textsFromOutput($generation->output);
            if ($texts !== []) {
                return [
                    'generation' => $generation,
                    'text' => $texts[0],
                    'texts' => $texts,
                ];
            }
        }

        try {
            $result = AiAvailability::textProvider()->rewrite($text, [
                'action' => $action,
                'variants' => $variants,
                'max_chars' => (int) config('ai.limits.text_max_chars', 400),
            ]);

            $texts = $result->allTexts();

            $generation->forceFill([
                'status' => AiGenerationStatus::Completed,
                'model' => $result->model,
                'output' => ['text' => $texts[0], 'texts' => $texts, 'mode' => 'rewrite', 'action' => $action],
                'usage_input_tokens' => $result->inputTokens,
                'usage_output_tokens' => $result->outputTokens,
                'completed_at' => now(),
            ])->save();

            return [
                'generation' => $generation->fresh() ?? $generation,
                'text' => $texts[0],
                'texts' => $texts,
            ];
        } catch (AiException $e) {
            $this->failGeneration($generation, $e);
            throw $e;
        }
    }

    /**
     * @param  array{
     *     aspect?: string,
     *     style?: string|null,
     *     width?: int|null,
     *     height?: int|null,
     *     variants?: int|null,
     *     refinement?: string|null,
     *     industry?: string|null,
     *     idempotency_key?: string|null
     * }  $options
     * @return array{generation: AiGeneration, preview_url: string, variants: list<array{index: int, preview_url: string}>}
     */
    public function generateImage(User $user, Workspace $workspace, string $prompt, array $options = []): array
    {
        $this->assertAvailable();
        $this->assertRateLimits($user, $workspace);
        $prompt = $this->normalizePrompt($prompt);

        $aspectKey = (string) ($options['aspect'] ?? config('ai.image.default_aspect', 'landscape'));
        $aspect = AiAvailability::aspect($aspectKey);
        $width = (int) ($options['width'] ?? $aspect['width']);
        $height = (int) ($options['height'] ?? $aspect['height']);

        $styleKey = isset($options['style']) ? (string) $options['style'] : null;
        $styles = config('ai.image.styles', []);
        $styleInstruction = ($styleKey && isset($styles[$styleKey])) ? (string) $styles[$styleKey] : null;

        $variantCount = max(1, min(
            (int) config('ai.limits.image_variants_max', 3),
            (int) ($options['variants'] ?? config('ai.image.default_variants', 2)),
        ));

        $refinement = isset($options['refinement']) ? (string) $options['refinement'] : null;
        if ($refinement !== null && ! array_key_exists($refinement, config('ai.image.refinements', []))) {
            throw ValidationException::withMessages(['refinement' => 'Invalid refinement.']);
        }

        $generation = $this->startGeneration(
            $user,
            $workspace,
            AiGenerationType::Image,
            $prompt,
            [
                'aspect' => $aspectKey,
                'style' => $styleKey,
                'width' => $width,
                'height' => $height,
                'variants' => $variantCount,
                'refinement' => $refinement,
            ],
            $options['idempotency_key'] ?? null,
        );

        if ($generation->status === AiGenerationStatus::Completed && $generation->hasTempFile()) {
            return $this->imageResponse($generation);
        }

        try {
            $result = AiAvailability::imageProvider()->generate($prompt, [
                'aspect' => $aspectKey,
                'width' => $width,
                'height' => $height,
                'style' => $styleKey,
                'style_instruction' => $styleInstruction,
                'industry' => $options['industry'] ?? $workspace->industry->label(),
                'brand_mood' => $styleKey,
                'refinement' => $refinement,
                'n' => $variantCount,
                'variants' => $variantCount,
            ]);

            $stored = [];
            foreach ($result->allVariants() as $index => $variant) {
                $path = $this->storeTempBinary(
                    $workspace->id,
                    $variant['binary'],
                    $variant['extension'],
                );
                $stored[] = [
                    'index' => $index,
                    'path' => $path,
                    'mime_type' => $variant['mimeType'],
                    'extension' => $variant['extension'],
                ];
            }

            $primary = $stored[0];

            $generation->forceFill([
                'status' => AiGenerationStatus::Completed,
                'model' => $result->model,
                'temp_disk' => (string) config('ai.temp.disk'),
                'temp_path' => $primary['path'],
                'output' => [
                    'mime_type' => $primary['mime_type'],
                    'extension' => $primary['extension'],
                    'width' => $result->width,
                    'height' => $result->height,
                    'aspect' => $aspectKey,
                    'style' => $styleKey,
                    'refinement' => $refinement,
                    'selected_index' => 0,
                    'variants' => $stored,
                    'enhanced_prompt' => $result->meta['enhanced_prompt'] ?? null,
                ],
                'completed_at' => now(),
            ])->save();

            return $this->imageResponse($generation->fresh() ?? $generation);
        } catch (AiException $e) {
            $this->failGeneration($generation, $e);
            throw $e;
        }
    }

    public function saveImageToMedia(
        User $user,
        Workspace $workspace,
        AiGeneration $generation,
        ?string $name = null,
        ?int $variantIndex = null,
    ): MediaAsset {
        $this->assertGenerationOwnership($generation, $workspace);

        if ($generation->type !== AiGenerationType::Image) {
            throw ValidationException::withMessages(['generation' => 'Only image generations can be saved to Media.']);
        }

        if ($generation->media_asset_id) {
            $existing = $generation->mediaAsset;
            if ($existing) {
                return $existing;
            }
        }

        if ($generation->status !== AiGenerationStatus::Completed) {
            throw ValidationException::withMessages(['generation' => 'This generation has no image to save.']);
        }

        $disk = (string) $generation->temp_disk;
        $path = (string) $generation->temp_path;
        $mime = (string) ($generation->output['mime_type'] ?? 'image/png');
        $extension = (string) ($generation->output['extension'] ?? pathinfo($path, PATHINFO_EXTENSION) ?: 'png');

        $variants = is_array($generation->output['variants'] ?? null) ? $generation->output['variants'] : [];
        if ($variantIndex !== null && isset($variants[$variantIndex]['path'])) {
            $path = (string) $variants[$variantIndex]['path'];
            $mime = (string) ($variants[$variantIndex]['mime_type'] ?? $mime);
            $extension = (string) ($variants[$variantIndex]['extension'] ?? $extension);
        }

        if ($disk === '' || $path === '' || ! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages(['generation' => 'This generation has no image to save.']);
        }

        $binary = Storage::disk($disk)->get($path);
        if (! is_string($binary) || $binary === '') {
            throw AiException::storage();
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ai-media-');
        if ($tmp === false) {
            throw AiException::storage();
        }

        file_put_contents($tmp, $binary);
        $upload = new UploadedFile(
            $tmp,
            'ai-'.Str::slug(mb_substr($generation->prompt, 0, 40)).'.'.$extension,
            $mime,
            null,
            true,
        );

        try {
            $asset = $this->createMediaAsset->handle($user, $workspace, [
                'type' => MediaType::Image,
                'name' => $name ?: $this->defaultImageName($generation->prompt),
                'file' => $upload,
                'width' => $generation->output['width'] ?? null,
                'height' => $generation->output['height'] ?? null,
            ]);

            $asset->forceFill([
                'metadata' => array_merge(
                    is_array($asset->metadata) ? $asset->metadata : [],
                    [
                        'source' => 'ai',
                        'ai_generation_id' => $generation->id,
                        'ai_provider' => $generation->provider,
                        'ai_model' => $generation->model,
                        'ai_prompt' => mb_substr($generation->prompt, 0, 500),
                        'ai_variant_index' => $variantIndex ?? 0,
                    ],
                ),
            ])->save();

            $output = is_array($generation->output) ? $generation->output : [];
            $output['selected_index'] = $variantIndex ?? 0;
            $output['accepted'] = true;

            $generation->forceFill([
                'media_asset_id' => $asset->id,
                'output' => $output,
            ])->save();
            $generation->deleteTempFile();
            $variantList = [];
            foreach ($variants as $variant) {
                if (is_array($variant)) {
                    $variantList[] = $variant;
                }
            }
            $this->deleteVariantTempFiles($generation, $disk, $variantList, $path);

            AuditLogger::record(
                $user,
                'ai.media_saved',
                'media_asset',
                $asset->id,
                $workspace->id,
                ['generation_id' => $generation->id, 'type' => 'image'],
            );

            return $asset->fresh() ?? $asset;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Direct design generation (variant_count=1) or concept pack (variant_count>1).
     *
     * @param  array{
     *     orientation?: string,
     *     purpose?: string,
     *     style?: string,
     *     name?: string|null,
     *     brand_colors?: list<string>|null,
     *     use_brand_kit?: bool,
     *     generate_matching_image?: bool,
     *     prefer_existing_media?: bool,
     *     prefer_templates?: bool,
     *     variant_count?: int,
     *     concept_count?: int,
     *     idempotency_key?: string|null
     * }  $options
     * @return array{
     *     generation: AiGeneration,
     *     design?: ScreenDesign,
     *     concepts?: list<array<string, mixed>>
     * }
     */
    public function generateDesign(User $user, Workspace $workspace, string $prompt, array $options = []): array
    {
        $this->assertAvailable();
        $this->assertRateLimits($user, $workspace);
        $prompt = $this->normalizePrompt($prompt);

        if (! isset($options['orientation'], $options['purpose'])) {
            throw ValidationException::withMessages([
                'orientation' => 'Orientation and purpose are required.',
            ]);
        }

        $orientation = TemplateOrientation::from((string) $options['orientation']);
        $purpose = (string) $options['purpose'];
        if (! in_array($purpose, config('ai.design.purposes', []), true)) {
            throw ValidationException::withMessages(['purpose' => 'Invalid design purpose.']);
        }

        $variantCount = max(1, min(
            (int) config('ai.limits.design_concepts_max', 3),
            (int) ($options['variant_count'] ?? $options['concept_count'] ?? 1),
        ));

        if ($variantCount > 1) {
            return $this->createDesignConcepts($user, $workspace, $prompt, [
                ...$options,
                'orientation' => $orientation->value,
                'purpose' => $purpose,
                'concept_count' => $variantCount,
            ]);
        }

        $workspace->loadMissing('brandKit');
        $brief = CreativeBriefBuilder::build($workspace, $prompt, [
            'purpose' => $purpose,
            'style' => $options['style'] ?? 'professional',
            'orientation' => $orientation->value,
            'use_brand_kit' => $options['use_brand_kit'] ?? true,
            'brand_colors' => $options['brand_colors'] ?? null,
            'name' => $options['name'] ?? null,
        ]);

        $preferMedia = (bool) ($options['prefer_existing_media'] ?? true);
        $preferTemplates = (bool) ($options['prefer_templates'] ?? true);
        $forceMatchingImage = (bool) ($options['generate_matching_image'] ?? false);

        $mediaMatches = $preferMedia
            ? MediaRelevance::topMatches($workspace, $prompt, ['limit' => 3])
            : [];
        $mediaIds = array_map(
            static fn (array $row): int => (int) $row['asset']->id,
            $mediaMatches,
        );

        $templateMatch = null;
        if ($preferTemplates) {
            $templateMatches = TemplateRelevance::topMatches($prompt, [
                'purpose' => $purpose,
                'industry' => $workspace->industry->value,
                'orientation' => $orientation->value,
                'limit' => 1,
            ]);
            $best = $templateMatches[0] ?? null;
            if ($best !== null && TemplateRelevance::isHighConfidence((float) $best['score'])) {
                $templateMatch = $best;
            }
        }

        $generation = $this->startGeneration(
            $user,
            $workspace,
            AiGenerationType::Design,
            $prompt,
            [
                'orientation' => $orientation->value,
                'purpose' => $purpose,
                'style' => $brief->style,
                'use_brand_kit' => $brief->useBrandKit,
                'generate_matching_image' => $forceMatchingImage,
                'prefer_existing_media' => $preferMedia,
                'prefer_templates' => $preferTemplates,
                'brand_colors' => $brief->brandColors,
                'matched_media_ids' => $mediaIds,
                'matched_template_id' => $templateMatch['template']->id ?? null,
            ],
            $options['idempotency_key'] ?? null,
        );

        if ($generation->status === AiGenerationStatus::Completed && $generation->screen_design_id) {
            $design = $generation->screenDesign;
            if ($design) {
                return ['generation' => $generation, 'design' => $design];
            }
        }

        try {
            $source = 'archetype';
            $archetype = null;
            $planModel = 'design-pipeline-v2';
            $inputTokens = null;
            $outputTokens = null;
            /** @var Template|null $usedTemplate */
            $usedTemplate = $templateMatch['template'] ?? null;
            $schema = null;

            if ($usedTemplate !== null) {
                // Preview adaptation from the published template schema without creating yet.
                $publishedSchema = is_array($usedTemplate->publishedVersion?->schema)
                    ? $usedTemplate->publishedVersion->schema
                    : [];
                $schema = AiDesignSchemaBuilder::adaptTemplateSchema($publishedSchema, $brief);
                $schema = AiDesignSchemaBuilder::bindMediaAssets($schema, $mediaIds);
                $source = 'template';
                $archetype = 'template:'.($usedTemplate->slug ?: $usedTemplate->id);
            } else {
                $built = $this->buildDesignFromBrief($brief);
                $schema = AiDesignSchemaBuilder::bindMediaAssets($built['schema'], $mediaIds);
                $archetype = $built['archetype'];
                $planModel = $built['plan']->model;
                $inputTokens = $built['plan']->inputTokens;
                $outputTokens = $built['plan']->outputTokens;
            }

            $matchingImageId = null;
            $shouldGenerateImage = $forceMatchingImage
                || ($mediaIds === [] && AiDesignSchemaBuilder::hasUnboundImageSlot($schema));

            if ($shouldGenerateImage) {
                $matchingImageId = $this->generateMatchingImageAsset($user, $workspace, $brief);
                $schema = $this->bindMatchingImage($schema, $matchingImageId);
            }

            $name = trim((string) ($options['name'] ?? '')) !== ''
                ? (string) $options['name']
                : (string) ($brief->meta['suggested_name'] ?? 'AI Design');

            $design = DB::transaction(function () use (
                $user,
                $workspace,
                $name,
                $orientation,
                $schema,
                $generation,
                $brief,
                $matchingImageId,
                $mediaIds,
                $source,
                $archetype,
                $planModel,
                $inputTokens,
                $outputTokens,
                $usedTemplate,
                $templateMatch,
            ) {
                if ($usedTemplate !== null) {
                    $design = $this->createScreenDesignFromTemplate->handle($user, $workspace, $usedTemplate);
                    if (trim($name) !== '' && $design->name !== $name) {
                        $design->forceFill(['name' => $name, 'updated_by' => $user->id])->save();
                    }
                } else {
                    $design = $this->createBlankScreenDesign->handle($user, $workspace, $name, $orientation);
                }

                $this->saveScreenDesignDraft->handle($user, $design, ['schema' => $schema]);

                $generation->forceFill([
                    'status' => AiGenerationStatus::Completed,
                    'model' => $planModel,
                    'screen_design_id' => $design->id,
                    'output' => [
                        'element_count' => count($schema['elements'] ?? []),
                        'orientation' => $orientation->value,
                        'purpose' => $brief->purpose,
                        'style' => $brief->style,
                        'archetype' => $archetype,
                        'source' => $source,
                        'template_id' => $usedTemplate?->id,
                        'template_score' => $templateMatch['score'] ?? null,
                        'bound_media_ids' => $mediaIds,
                        'brief' => $brief->toArray(),
                        'accepted' => true,
                        'matching_image_id' => $matchingImageId,
                        'regenerated' => false,
                        'discarded' => false,
                    ],
                    'usage_input_tokens' => $inputTokens,
                    'usage_output_tokens' => $outputTokens,
                    'completed_at' => now(),
                ])->save();

                return $design->fresh(['versions', 'sourceTemplate']) ?? $design;
            });

            AuditLogger::record(
                $user,
                'ai.design_created',
                'screen_design',
                $design->id,
                $workspace->id,
                [
                    'generation_id' => $generation->id,
                    'archetype' => $archetype,
                    'source' => $source,
                ],
            );

            return ['generation' => $generation->fresh() ?? $generation, 'design' => $design];
        } catch (AiException $e) {
            $this->failGeneration($generation, $e);
            throw $e;
        }
    }

    /**
     * @param  array{
     *     orientation?: string,
     *     purpose?: string,
     *     style?: string,
     *     name?: string|null,
     *     brand_colors?: list<string>|null,
     *     use_brand_kit?: bool,
     *     generate_matching_image?: bool,
     *     concept_count?: int,
     *     idempotency_key?: string|null
     * }  $options
     * @return array{generation: AiGeneration, concepts: list<array<string, mixed>>}
     */
    public function generateDesignConcepts(User $user, Workspace $workspace, string $prompt, array $options = []): array
    {
        $this->assertAvailable();
        $this->assertRateLimits($user, $workspace);
        $prompt = $this->normalizePrompt($prompt);

        return $this->createDesignConcepts($user, $workspace, $prompt, $options);
    }

    /**
     * @param  array{
     *     orientation?: string,
     *     purpose?: string,
     *     style?: string,
     *     name?: string|null,
     *     brand_colors?: list<string>|null,
     *     use_brand_kit?: bool,
     *     generate_matching_image?: bool,
     *     concept_count?: int,
     *     idempotency_key?: string|null
     * }  $options
     * @return array{generation: AiGeneration, concepts: list<array<string, mixed>>}
     */
    private function createDesignConcepts(User $user, Workspace $workspace, string $prompt, array $options = []): array
    {
        $workspace->loadMissing('brandKit');
        $brief = CreativeBriefBuilder::build($workspace, $prompt, [
            'purpose' => $options['purpose'] ?? 'promotion',
            'style' => $options['style'] ?? 'professional',
            'orientation' => $options['orientation'] ?? 'landscape',
            'use_brand_kit' => $options['use_brand_kit'] ?? true,
            'brand_colors' => $options['brand_colors'] ?? null,
            'name' => $options['name'] ?? null,
        ]);

        $count = max(1, min(
            (int) config('ai.limits.design_concepts_max', 3),
            (int) ($options['concept_count'] ?? config('ai.design.default_concepts', 3)),
        ));

        $generation = $this->startGeneration(
            $user,
            $workspace,
            AiGenerationType::Design,
            $prompt,
            [
                'orientation' => $brief->orientation,
                'purpose' => $brief->purpose,
                'style' => $brief->style,
                'use_brand_kit' => $brief->useBrandKit,
                'generate_matching_image' => (bool) ($options['generate_matching_image'] ?? false),
                'concept_count' => $count,
                'mode' => 'concepts',
            ],
            $options['idempotency_key'] ?? null,
        );

        if ($generation->status === AiGenerationStatus::Completed && is_array($generation->output['concepts'] ?? null)) {
            $cachedConcepts = [];
            foreach ($generation->output['concepts'] as $concept) {
                if (is_array($concept)) {
                    $cachedConcepts[] = $concept;
                }
            }

            return [
                'generation' => $generation,
                'concepts' => $this->publicConcepts($cachedConcepts),
            ];
        }

        try {
            $archetypes = DesignArchetypes::variantsFor($brief, $count);
            $concepts = [];

            foreach ($archetypes as $index => $archetype) {
                $built = AiDesignSchemaBuilder::buildFromBrief($brief, $archetype);
                $schema = $built['schema'];
                $concepts[] = [
                    'index' => $index,
                    'id' => 'concept-'.$index,
                    'name' => DesignArchetypes::label($archetype),
                    'archetype' => $archetype,
                    'purpose' => $brief->purpose,
                    'style' => $brief->style,
                    'orientation' => $brief->orientation,
                    'suggested_name' => $built['plan']->suggestedName,
                    'element_count' => count($schema['elements'] ?? []),
                    'preview' => [
                        'background' => $schema['canvas']['background'] ?? null,
                        'headline' => $this->extractHeadline($schema),
                        'cta' => $this->extractCta($schema),
                        'element_types' => array_values(array_unique(array_map(
                            fn ($el) => (string) ($el['type'] ?? ''),
                            is_array($schema['elements'] ?? null) ? $schema['elements'] : [],
                        ))),
                    ],
                    'schema' => $schema,
                ];
            }

            if ($concepts === []) {
                throw AiException::invalidOutput('No design concepts could be generated.');
            }

            $generation->forceFill([
                'status' => AiGenerationStatus::Completed,
                'model' => 'design-concepts-v1',
                'output' => [
                    'mode' => 'concepts',
                    'concepts' => $concepts,
                    'brief' => $brief->toArray(),
                    'accepted' => false,
                    'discarded' => false,
                    'regenerated' => false,
                    'generate_matching_image' => (bool) ($options['generate_matching_image'] ?? false),
                ],
                'completed_at' => now(),
            ])->save();

            return [
                'generation' => $generation->fresh() ?? $generation,
                'concepts' => $this->publicConcepts($concepts),
            ];
        } catch (AiException $e) {
            $this->failGeneration($generation, $e);
            throw $e;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $concepts
     * @return list<array<string, mixed>>
     */
    private function publicConcepts(array $concepts): array
    {
        return array_map(function (array $c) {
            return [
                'index' => $c['index'] ?? 0,
                'id' => $c['id'] ?? ('concept-'.($c['index'] ?? 0)),
                'name' => $c['name'] ?? 'Concept',
                'archetype' => $c['archetype'] ?? null,
                'purpose' => $c['purpose'] ?? null,
                'style' => $c['style'] ?? null,
                'orientation' => $c['orientation'] ?? null,
                'suggested_name' => $c['suggested_name'] ?? null,
                'element_count' => $c['element_count'] ?? 0,
                'preview' => $c['preview'] ?? null,
            ];
        }, $concepts);
    }

    /**
     * Materialise a previously generated design concept into a Screen Design draft.
     *
     * @return array{generation: AiGeneration, design: ScreenDesign}
     */
    public function acceptDesignConcept(
        User $user,
        Workspace $workspace,
        AiGeneration $generation,
        int $conceptIndex,
        ?string $name = null,
    ): array {
        $this->assertGenerationOwnership($generation, $workspace);

        if ($generation->type !== AiGenerationType::Design) {
            throw ValidationException::withMessages(['generation' => 'Only design generations can be accepted.']);
        }

        if ($generation->screen_design_id) {
            $design = $generation->screenDesign;
            if ($design) {
                return ['generation' => $generation, 'design' => $design];
            }
        }

        $concepts = is_array($generation->output['concepts'] ?? null) ? $generation->output['concepts'] : [];
        $concept = null;
        foreach ($concepts as $c) {
            if (is_array($c) && (int) ($c['index'] ?? -1) === $conceptIndex) {
                $concept = $c;
                break;
            }
        }

        if ($concept === null || ! is_array($concept['schema'] ?? null)) {
            throw ValidationException::withMessages(['concept_index' => 'Unknown design concept.']);
        }

        $orientation = TemplateOrientation::from((string) ($concept['orientation'] ?? $generation->options['orientation'] ?? 'landscape'));
        $schema = $concept['schema'];
        $matchingImageId = null;

        $mediaMatches = MediaRelevance::topMatches($workspace, $generation->prompt, ['limit' => 3]);
        $mediaIds = array_map(
            static fn (array $row): int => (int) $row['asset']->id,
            $mediaMatches,
        );
        $schema = AiDesignSchemaBuilder::bindMediaAssets($schema, $mediaIds);

        $forceMatchingImage = ! empty($generation->output['generate_matching_image']);
        $shouldGenerateImage = $forceMatchingImage
            || ($mediaIds === [] && AiDesignSchemaBuilder::hasUnboundImageSlot($schema));

        if ($shouldGenerateImage) {
            $briefData = is_array($generation->output['brief'] ?? null) ? $generation->output['brief'] : [];
            $brief = CreativeBriefBuilder::build($workspace, $generation->prompt, [
                'purpose' => $briefData['purpose'] ?? $concept['purpose'] ?? 'promotion',
                'style' => $briefData['style'] ?? $concept['style'] ?? 'professional',
                'orientation' => $orientation->value,
                'use_brand_kit' => $briefData['use_brand_kit'] ?? true,
            ]);
            $matchingImageId = $this->generateMatchingImageAsset($user, $workspace, $brief);
            $schema = $this->bindMatchingImage($schema, $matchingImageId);
        }

        $designName = trim((string) ($name ?? '')) !== ''
            ? (string) $name
            : (string) ($concept['suggested_name'] ?? 'AI Design');

        $design = DB::transaction(function () use ($user, $workspace, $designName, $orientation, $schema, $generation, $concept, $conceptIndex, $matchingImageId) {
            $design = $this->createBlankScreenDesign->handle($user, $workspace, $designName, $orientation);
            $this->saveScreenDesignDraft->handle($user, $design, ['schema' => $schema]);

            $output = is_array($generation->output) ? $generation->output : [];
            $output['accepted'] = true;
            $output['accepted_index'] = $conceptIndex;
            $output['archetype'] = $concept['archetype'] ?? null;
            $output['matching_image_id'] = $matchingImageId;
            // Drop full schemas from stored concepts after accept to keep rows lean.
            $output['concepts'] = array_map(function (array $c) {
                unset($c['schema']);

                return $c;
            }, is_array($output['concepts'] ?? null) ? $output['concepts'] : []);

            $generation->forceFill([
                'screen_design_id' => $design->id,
                'output' => $output,
                'completed_at' => now(),
            ])->save();

            return $design->fresh(['versions']) ?? $design;
        });

        AuditLogger::record(
            $user,
            'ai.design_accepted',
            'screen_design',
            $design->id,
            $workspace->id,
            ['generation_id' => $generation->id, 'concept_index' => $conceptIndex],
        );

        return ['generation' => $generation->fresh() ?? $generation, 'design' => $design];
    }

    /**
     * @return array{schema: array<string, mixed>, archetype: string, plan: DesignPlanResult}
     */
    private function buildDesignFromBrief(CreativeBrief $brief): array
    {
        $archetype = DesignArchetypes::selectFor($brief);
        $provider = AiAvailability::textProvider();
        $providerPlan = $provider->generateDesignPlan($brief, $archetype);

        return AiDesignSchemaBuilder::buildFromBrief($brief, $archetype, $providerPlan);
    }

    private function generateMatchingImageAsset(User $user, Workspace $workspace, CreativeBrief $brief): ?int
    {
        try {
            $aspect = $brief->orientation === 'portrait' ? 'portrait' : 'landscape';
            $dims = AiAvailability::aspect($aspect);
            $result = AiAvailability::imageProvider()->generate($brief->prompt, [
                'aspect' => $aspect,
                'width' => $dims['width'],
                'height' => $dims['height'],
                'style' => $brief->style === 'bold' ? 'vibrant' : ($brief->style === 'minimal' ? 'minimal' : 'professional'),
                'industry' => $brief->meta['industry_label'] ?? $brief->industry,
                'brand_mood' => $brief->mood,
                'n' => 1,
            ]);

            $path = $this->storeTempBinary($workspace->id, $result->binary, $result->extension);
            $disk = (string) config('ai.temp.disk');
            $binary = Storage::disk($disk)->get($path);
            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'ai-match-');
            if ($tmp === false) {
                return null;
            }
            file_put_contents($tmp, $binary);
            $upload = new UploadedFile(
                $tmp,
                'ai-match-'.Str::slug(mb_substr($brief->headline, 0, 24)).'.'.$result->extension,
                $result->mimeType,
                null,
                true,
            );

            try {
                $asset = $this->createMediaAsset->handle($user, $workspace, [
                    'type' => MediaType::Image,
                    'name' => 'AI · '.$brief->headline,
                    'file' => $upload,
                    'width' => $result->width,
                    'height' => $result->height,
                ]);

                return $asset->id;
            } finally {
                @unlink($tmp);
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function bindMatchingImage(array $schema, ?int $mediaAssetId): array
    {
        if ($mediaAssetId === null || ! is_array($schema['elements'] ?? null)) {
            return $schema;
        }

        foreach ($schema['elements'] as &$el) {
            if (($el['type'] ?? '') === 'image' && empty($el['props']['mediaAssetId'])) {
                $el['props']['mediaAssetId'] = $mediaAssetId;
                break;
            }
        }
        unset($el);

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function extractHeadline(array $schema): string
    {
        foreach ($schema['elements'] ?? [] as $el) {
            if (($el['type'] ?? '') === 'text' && str_contains(strtolower((string) ($el['name'] ?? '')), 'headline')) {
                return (string) ($el['props']['text'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function extractCta(array $schema): string
    {
        foreach ($schema['elements'] ?? [] as $el) {
            if (($el['type'] ?? '') === 'text' && str_contains(strtolower((string) ($el['name'] ?? '')), 'cta')) {
                return (string) ($el['props']['text'] ?? '');
            }
        }

        return '';
    }

    /**
     * @return array{generation: AiGeneration, preview_url: string, variants: list<array{index: int, preview_url: string}>}
     */
    private function imageResponse(AiGeneration $generation): array
    {
        $variants = [];
        $stored = is_array($generation->output['variants'] ?? null) ? $generation->output['variants'] : [];

        if ($stored === []) {
            $variants[] = [
                'index' => 0,
                'preview_url' => route('app.ai.generations.preview', $generation),
            ];
        } else {
            foreach ($stored as $item) {
                $index = (int) ($item['index'] ?? 0);
                $variants[] = [
                    'index' => $index,
                    'preview_url' => route('app.ai.generations.preview', ['generation' => $generation, 'variant' => $index]),
                ];
            }
        }

        return [
            'generation' => $generation,
            'preview_url' => $variants[0]['preview_url'],
            'variants' => $variants,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $output
     * @return list<string>
     */
    private function textsFromOutput(?array $output): array
    {
        if (! is_array($output)) {
            return [];
        }

        if (is_array($output['texts'] ?? null) && $output['texts'] !== []) {
            return array_values(array_filter($output['texts'], fn ($t) => is_string($t) && $t !== ''));
        }

        if (is_string($output['text'] ?? null) && $output['text'] !== '') {
            return [(string) $output['text']];
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function deleteVariantTempFiles(AiGeneration $generation, string $disk, array $variants, string $keptPath): void
    {
        foreach ($variants as $variant) {
            $path = (string) ($variant['path'] ?? '');
            if ($path !== '' && $path !== $keptPath) {
                Storage::disk($disk)->delete($path);
            }
        }

        // Primary temp already cleared by deleteTempFile; ensure output paths nulled.
        $output = is_array($generation->output) ? $generation->output : [];
        $output['variants'] = [];
        $generation->forceFill(['output' => $output])->save();
    }

    private function assertAvailable(): void
    {
        if (! AiAvailability::isConfigured()) {
            throw AiException::unavailable();
        }
    }

    private function assertRateLimits(User $user, Workspace $workspace): void
    {
        $userKey = 'ai-user:'.$user->id;
        $workspaceKey = 'ai-workspace:'.$workspace->id;

        $userLimit = (int) config('ai.limits.per_user_per_minute', 10);
        $workspaceLimit = (int) config('ai.limits.per_workspace_per_hour', 60);

        if (RateLimiter::tooManyAttempts($userKey, $userLimit)) {
            throw AiException::rateLimited();
        }

        if (RateLimiter::tooManyAttempts($workspaceKey, $workspaceLimit)) {
            throw AiException::rateLimited();
        }

        RateLimiter::hit($userKey, 60);
        RateLimiter::hit($workspaceKey, 3600);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function startGeneration(
        User $user,
        Workspace $workspace,
        AiGenerationType $type,
        string $prompt,
        array $options,
        ?string $idempotencyKey,
    ): AiGeneration {
        $idempotencyKey = filled($idempotencyKey) ? mb_substr((string) $idempotencyKey, 0, 64) : null;

        if ($idempotencyKey) {
            $existing = AiGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $provider = AiAvailability::textProvider();

        return AiGeneration::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'type' => $type,
            'status' => AiGenerationStatus::Processing,
            'provider' => match ($type) {
                AiGenerationType::Image => AiAvailability::imageProvider()->name(),
                default => $provider->name(),
            },
            'model' => null,
            'prompt' => $prompt,
            'options' => $options,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function failGeneration(AiGeneration $generation, AiException $e): void
    {
        $generation->forceFill([
            'status' => AiGenerationStatus::Failed,
            'error_code' => $e->codeKey,
            'error_message' => mb_substr($e->getMessage(), 0, 500),
            'completed_at' => now(),
        ])->save();
    }

    private function normalizePrompt(string $prompt): string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw ValidationException::withMessages(['prompt' => 'A prompt is required.']);
        }

        $max = (int) config('ai.limits.prompt_max', 2000);
        if (mb_strlen($prompt) > $max) {
            throw ValidationException::withMessages(['prompt' => "Prompt must be {$max} characters or fewer."]);
        }

        return $prompt;
    }

    private function storeTempBinary(int $workspaceId, string $binary, string $extension): string
    {
        $disk = (string) config('ai.temp.disk');
        $path = 'workspaces/'.$workspaceId.'/ai-temp/'.Str::uuid().'.'.$extension;

        if (! Storage::disk($disk)->put($path, $binary)) {
            throw AiException::storage();
        }

        return $path;
    }

    private function assertGenerationOwnership(AiGeneration $generation, Workspace $workspace): void
    {
        if ((int) $generation->workspace_id !== (int) $workspace->id) {
            abort(404);
        }
    }

    private function defaultImageName(string $prompt): string
    {
        $slug = Str::limit(trim($prompt), 48, '');

        return $slug !== '' ? 'AI · '.$slug : 'AI Image';
    }
}
