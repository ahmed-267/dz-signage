<?php

namespace App\Support\Ai;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Scores workspace MediaAssets against a creative prompt for reuse in designs.
 * Workspace-scoped only — never returns assets from other workspaces.
 */
final class MediaRelevance
{
    /**
     * @param  array{
     *     limit?: int,
     *     types?: list<string>|null,
     *     min_score?: float
     * }  $options
     * @return list<array{asset: MediaAsset, score: float, reasons: list<string>}>
     */
    public static function topMatches(Workspace $workspace, string $prompt, array $options = []): array
    {
        $limit = max(1, min(20, (int) ($options['limit'] ?? 5)));
        $minScore = (float) ($options['min_score'] ?? config('ai.agent.media_min_score', 0.25));
        /** @var list<string> $types */
        $types = $options['types'] ?? [MediaType::Image->value, MediaType::Video->value];

        $typeEnums = [];
        foreach ($types as $type) {
            $enum = MediaType::tryFrom($type);
            if ($enum !== null) {
                $typeEnums[] = $enum;
            }
        }

        if ($typeEnums === []) {
            $typeEnums = [MediaType::Image, MediaType::Video];
        }

        $keywords = self::keywords($prompt);
        if ($keywords === []) {
            return [];
        }

        /** @var Collection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('type', array_map(static fn (MediaType $t) => $t->value, $typeEnums))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $scored = [];
        foreach ($assets as $asset) {
            $result = self::score($asset, $prompt, $keywords);
            if ($result['score'] >= $minScore) {
                $scored[] = [
                    'asset' => $asset,
                    'score' => $result['score'],
                    'reasons' => $result['reasons'],
                ];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param  list<string>|null  $keywords
     * @return array{score: float, reasons: list<string>}
     */
    public static function score(MediaAsset $asset, string $prompt, ?array $keywords = null): array
    {
        $keywords ??= self::keywords($prompt);
        if ($keywords === []) {
            return ['score' => 0.0, 'reasons' => []];
        }

        $haystacks = [
            'name' => mb_strtolower((string) $asset->name),
            'ai_prompt' => mb_strtolower((string) (is_array($asset->metadata) ? ($asset->metadata['ai_prompt'] ?? '') : '')),
            'filename' => mb_strtolower((string) ($asset->original_filename ?? '')),
        ];

        $matched = [];
        $weight = 0.0;

        foreach ($keywords as $keyword) {
            $hit = false;
            if ($haystacks['name'] !== '' && str_contains($haystacks['name'], $keyword)) {
                $weight += 0.35;
                $matched[] = 'name:'.$keyword;
                $hit = true;
            }
            if ($haystacks['ai_prompt'] !== '' && str_contains($haystacks['ai_prompt'], $keyword)) {
                $weight += 0.4;
                $matched[] = 'ai_prompt:'.$keyword;
                $hit = true;
            }
            if (! $hit && $haystacks['filename'] !== '' && str_contains($haystacks['filename'], $keyword)) {
                $weight += 0.2;
                $matched[] = 'filename:'.$keyword;
            }
        }

        // Prefer image/video for design binding.
        $typeBonus = match ($asset->type) {
            MediaType::Image => 0.15,
            MediaType::Video => 0.1,
            MediaType::Logo => 0.05,
            default => 0.0,
        };

        $coverage = min(1.0, count(array_unique(array_map(
            static fn (string $m): string => explode(':', $m)[1] ?? $m,
            $matched,
        ))) / count($keywords));

        $score = min(1.0, ($weight * 0.55) + ($coverage * 0.3) + $typeBonus);

        return [
            'score' => round($score, 4),
            'reasons' => array_values(array_unique($matched)),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keywords(string $prompt): array
    {
        $clean = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $prompt) ?? $prompt));
        $parts = preg_split('/\s+/', $clean) ?: [];

        $stop = [
            'a', 'an', 'the', 'and', 'or', 'for', 'to', 'of', 'in', 'on', 'at', 'with',
            'this', 'that', 'our', 'your', 'make', 'create', 'generate', 'design', 'screen',
            'playlist', 'schedule', 'using', 'from', 'into', 'please', 'new', 'my',
        ];

        $keywords = [];
        foreach ($parts as $part) {
            $part = trim($part, '-');
            if ($part === '' || mb_strlen($part) < 3 || in_array($part, $stop, true)) {
                continue;
            }
            $keywords[] = $part;
        }

        return array_values(array_unique($keywords));
    }
}
