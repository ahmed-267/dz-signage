<?php

namespace App\Support\Ai;

use App\Models\BrandKit;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Builds a CreativeBrief from user intent + workspace Brand Kit (server-side).
 */
final class CreativeBriefBuilder
{
    /**
     * @param  array{
     *     purpose?: string,
     *     style?: string,
     *     orientation?: string,
     *     use_brand_kit?: bool,
     *     brand_colors?: list<string>|null,
     *     name?: string|null
     * }  $options
     */
    public static function build(Workspace $workspace, string $prompt, array $options = []): CreativeBrief
    {
        $prompt = trim($prompt);
        $purpose = (string) ($options['purpose'] ?? 'promotion');
        $style = (string) ($options['style'] ?? 'professional');
        $styles = config('ai.design.styles', []);
        if (! in_array($style, $styles, true)) {
            $style = 'professional';
        }

        $orientation = (string) ($options['orientation'] ?? 'landscape');
        if (! in_array($orientation, ['landscape', 'portrait'], true)) {
            $orientation = 'landscape';
        }

        $useBrandKit = (bool) ($options['use_brand_kit'] ?? true);
        $industry = $workspace->industry->value;
        $industryLabel = $workspace->industry->label();

        $brandName = null;
        $brandTagline = null;
        $brandColors = [];
        $brandFonts = null;
        $logoId = null;

        if ($useBrandKit) {
            /** @var BrandKit|null $kit */
            $kit = $workspace->brandKit;
            if ($kit) {
                $brandName = $kit->name ?: $workspace->name;
                $brandTagline = $kit->tagline;
                $brandColors = $kit->brandColors();
                $brandFonts = [
                    'heading' => $kit->heading_font,
                    'body' => $kit->body_font,
                ];
                $logoId = $kit->logo_media_asset_id;
            } else {
                $brandName = $workspace->name;
                $brandColors = [
                    BrandKit::DEFAULT_PRIMARY,
                    BrandKit::DEFAULT_SECONDARY,
                    BrandKit::DEFAULT_ACCENT,
                    BrandKit::DEFAULT_BACKGROUND,
                    BrandKit::DEFAULT_TEXT,
                ];
                $brandFonts = [
                    'heading' => BrandKit::DEFAULT_HEADING_FONT,
                    'body' => BrandKit::DEFAULT_BODY_FONT,
                ];
            }
        }

        $overrideColors = $options['brand_colors'] ?? null;
        if (is_array($overrideColors) && $overrideColors !== []) {
            $brandColors = array_values(array_filter(
                $overrideColors,
                fn (string $c) => preg_match('/^#[0-9A-Fa-f]{6}$/', $c) === 1,
            ));
        }

        if ($brandColors === []) {
            $brandColors = self::paletteForStyle($style);
        }

        $copy = self::deriveCopy($prompt, $purpose, $brandName, $industryLabel);

        return new CreativeBrief(
            prompt: $prompt,
            purpose: $purpose,
            style: $style,
            orientation: $orientation,
            industry: $industry,
            useBrandKit: $useBrandKit,
            brandName: $brandName,
            brandTagline: $brandTagline,
            brandColors: $brandColors,
            brandFonts: $brandFonts,
            logoMediaAssetId: $logoId,
            headline: $copy['headline'],
            subheadline: $copy['subheadline'],
            cta: $copy['cta'],
            body: $copy['body'],
            mood: self::moodForStyle($style),
            meta: [
                'industry_label' => $industryLabel,
                'suggested_name' => trim((string) ($options['name'] ?? '')) !== ''
                    ? (string) $options['name']
                    : self::suggestedName($purpose, $copy['headline']),
            ],
        );
    }

    /**
     * @return array{headline: string, subheadline: string, cta: string, body: string}
     */
    private static function deriveCopy(string $prompt, string $purpose, ?string $brandName, string $industryLabel): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $prompt) ?? $prompt);
        $headline = self::signageHeadline($clean, $purpose);

        $subheadline = match ($purpose) {
            'menu' => 'Today\'s favourites · '.$industryLabel,
            'event' => self::eventSubheadline($clean),
            'welcome' => $brandName ? 'Welcome to '.$brandName : 'Welcome',
            'announcement' => 'Please take a moment to read',
            'information' => 'Everything you need at a glance',
            default => $brandName ? $brandName.' · '.$industryLabel : 'Limited time offer',
        };

        $cta = match ($purpose) {
            'menu' => 'Order now',
            'event' => self::eventCta($clean),
            'welcome' => 'Explore',
            'announcement' => 'Learn more',
            'information' => 'Find out more',
            default => 'Shop now',
        };

        $body = TextFit::shortenForSignage(
            $clean !== '' ? $clean : $subheadline,
            100,
            14,
        );

        return compact('headline', 'subheadline', 'cta', 'body');
    }

    /**
     * Prefer short memorable signage lines — never dump the full prompt in ALL CAPS.
     */
    private static function signageHeadline(string $prompt, string $purpose): string
    {
        if ($prompt === '') {
            return match ($purpose) {
                'event' => 'MATCH DAY',
                'menu' => 'TODAY\'S MENU',
                'welcome' => 'WELCOME',
                'announcement' => 'ANNOUNCEMENT',
                'information' => 'AT A GLANCE',
                default => 'NOW SHOWING',
            };
        }

        $stripped = $prompt;
        $prefixes = [
            '/^(please\s+)?(create|make|design|generate|build|write)\s+(a|an|the)?\s*/iu',
            '/^(screen|poster|banner|signage|layout|design)\s+(for|about|of)?\s*/iu',
            '/^(a|an|the)\s+(digital\s+)?(screen|poster|banner|sign)\s+(for|about|of)?\s*/iu',
            '/^(for\s+)?(the\s+)?/iu',
        ];
        foreach ($prefixes as $pattern) {
            $stripped = trim(preg_replace($pattern, '', $stripped) ?? $stripped);
        }

        // Sports / matchups: "Manchester United vs Liverpool"
        $matchup = self::extractMatchupHeadline($stripped);
        if ($matchup !== null) {
            return $matchup;
        }

        // Percentage / sale hooks
        if (preg_match('/(\d+)\s*%\s*(off)?/iu', $stripped, $m) === 1) {
            $off = isset($m[2]) ? ' OFF' : '%';
            $hook = $m[1].($off === ' OFF' ? '% OFF' : '%');
            if (preg_match('/\b(sale|offer|deal|brunch|lunch|dinner|weekend|summer|winter|spring|autumn|fall)\b/iu', $stripped, $theme) === 1) {
                return mb_strtoupper($theme[1].' '.$hook);
            }

            return mb_strtoupper($hook);
        }

        // Event / sports keywords → short punchy line
        if ($purpose === 'event' || preg_match('/\b(match|fixture|kick[- ]?off|game day|matchday|tournament|final|derby)\b/iu', $stripped) === 1) {
            if (preg_match('/\b(match\s*day|game\s*day|derby\s*day|final)\b/iu', $stripped, $m) === 1) {
                return mb_strtoupper(preg_replace('/\s+/', ' ', $m[1]) ?? 'MATCH DAY');
            }
            $words = self::significantWords($stripped, 5);
            if ($words !== []) {
                return mb_strtoupper(implode(' ', $words));
            }

            return 'MATCH DAY';
        }

        $words = self::significantWords($stripped, 6);
        $line = implode(' ', $words);
        if ($line === '') {
            $line = implode(' ', array_slice(explode(' ', $stripped), 0, 5));
        }

        $line = TextFit::shortenForSignage($line, 36, 6);

        // Short punchy lines read better in uppercase on TV; keep mixed case if long.
        if (mb_strlen($line) <= 28) {
            return mb_strtoupper($line);
        }

        return $line;
    }

    private static function extractMatchupHeadline(string $text): ?string
    {
        if (preg_match('/^(.*?)\s+(?:vs\.?|versus)\s+(.*)$/iu', $text, $m) !== 1) {
            return null;
        }

        $stop = [
            'match', 'game', 'fixture', 'derby', 'on', 'this', 'next', 'at', 'kick', 'kickoff',
            'live', 'tonight', 'today', 'tomorrow', 'saturday', 'sunday', 'monday', 'tuesday',
            'wednesday', 'thursday', 'friday', 'weekend', 'evening', 'afternoon', 'morning',
            'the', 'a', 'an', 'for', 'with',
        ];

        $leftWords = preg_split('/\s+/u', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightWords = preg_split('/\s+/u', trim($m[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $left = [];
        foreach (array_reverse($leftWords) as $word) {
            $clean = trim($word, " \t.,!?:;\"'()[]");
            if ($clean === '' || in_array(mb_strtolower($clean), $stop, true)) {
                if ($left !== []) {
                    break;
                }

                continue;
            }
            array_unshift($left, $clean);
            if (count($left) >= 3) {
                break;
            }
        }

        $right = [];
        foreach ($rightWords as $word) {
            $clean = trim($word, " \t.,!?:;\"'()[]");
            if ($clean === '' || in_array(mb_strtolower($clean), $stop, true)) {
                break;
            }
            $right[] = $clean;
            if (count($right) >= 2) {
                break;
            }
        }

        if ($left === [] || $right === []) {
            return null;
        }

        return mb_strtoupper(implode(' ', $left).' VS '.implode(' ', $right));
    }

    /**
     * @return list<string>
     */
    private static function significantWords(string $text, int $max): array
    {
        $stop = [
            'a', 'an', 'the', 'and', 'or', 'for', 'with', 'this', 'that', 'our', 'your',
            'from', 'into', 'onto', 'about', 'screen', 'design', 'poster', 'banner',
            'create', 'make', 'please', 'using', 'show', 'showing', 'display', 'digital',
            'signage', 'layout', 'want', 'need', 'like', 'just', 'very', 'really',
        ];

        $raw = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($raw as $word) {
            $clean = trim($word, " \t\n\r\0\x0B.,!?:;\"'()[]");
            if ($clean === '') {
                continue;
            }
            if (in_array(mb_strtolower($clean), $stop, true) && ! preg_match('/\d/', $clean)) {
                continue;
            }
            $out[] = $clean;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    private static function eventSubheadline(string $prompt): string
    {
        if (preg_match('/\b(saturday|sunday|monday|tuesday|wednesday|thursday|friday|tonight|today|tomorrow|this weekend|this week)\b/iu', $prompt, $m) === 1) {
            return 'Live · '.Str::title(mb_strtolower($m[1]));
        }
        if (preg_match('/\b(\d{1,2}[:.]\d{2}\s*(am|pm)?)\b/iu', $prompt, $m) === 1) {
            return 'Starts '.$m[1];
        }

        return 'Don\'t miss it · limited seats';
    }

    private static function eventCta(string $prompt): string
    {
        if (preg_match('/\b(ticket|book|rsvp|register)\b/iu', $prompt) === 1) {
            return 'Get tickets';
        }
        if (preg_match('/\b(watch|stream|live)\b/iu', $prompt) === 1) {
            return 'Watch live';
        }

        return 'Join us';
    }

    /**
     * @return list<string>
     */
    private static function paletteForStyle(string $style): array
    {
        return match ($style) {
            'minimal' => ['#F8FAFC', '#E2E8F0', '#0F172A', '#FFFFFF', '#0F172A'],
            'bold' => ['#111827', '#F97316', '#EF4444', '#0B1220', '#F8FAFC'],
            'elegant' => ['#1C1917', '#A8A29E', '#D4AF37', '#0C0A09', '#FAFAF9'],
            'vibrant' => ['#312E81', '#EC4899', '#22D3EE', '#1E1B4B', '#FDF4FF'],
            default => ['#0F172A', '#334155', '#0D9488', '#0B1220', '#F8FAFC'],
        };
    }

    private static function moodForStyle(string $style): string
    {
        return match ($style) {
            'minimal' => 'calm',
            'bold' => 'energetic',
            'elegant' => 'premium',
            'vibrant' => 'lively',
            default => 'professional',
        };
    }

    private static function suggestedName(string $purpose, string $headline): string
    {
        $short = Str::limit(trim($headline), 36, '');

        return $short !== '' ? 'AI · '.$short : 'AI '.$purpose;
    }
}
