<?php

namespace App\Support\Ai;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Models\Template;
use Illuminate\Support\Collection;

/**
 * Scores published platform Templates for Use Template in AI design generation.
 */
final class TemplateRelevance
{
    /**
     * @param  array{
     *     purpose?: string|null,
     *     industry?: string|null,
     *     orientation?: string|null,
     *     limit?: int,
     *     min_score?: float
     * }  $context
     * @return list<array{template: Template, score: float, reasons: list<string>}>
     */
    public static function topMatches(string $prompt, array $context = []): array
    {
        $limit = max(1, min(10, (int) ($context['limit'] ?? 3)));
        $minScore = (float) ($context['min_score'] ?? config('ai.agent.template_min_score', 0.35));

        $purpose = (string) ($context['purpose'] ?? 'promotion');
        $industry = isset($context['industry']) ? (string) $context['industry'] : null;
        $orientation = TemplateOrientation::tryFrom((string) ($context['orientation'] ?? 'landscape'))
            ?? TemplateOrientation::Landscape;

        /** @var Collection<int, Template> $templates */
        $templates = Template::query()
            ->platform()
            ->where('status', TemplateStatus::Published)
            ->whereNotNull('published_version_id')
            ->with('publishedVersion')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $keywords = MediaRelevance::keywords($prompt);
        $scored = [];

        foreach ($templates as $template) {
            $result = self::score($template, [
                'purpose' => $purpose,
                'industry' => $industry,
                'orientation' => $orientation->value,
                'keywords' => $keywords,
            ]);

            if ($result['score'] >= $minScore) {
                $scored[] = [
                    'template' => $template,
                    'score' => $result['score'],
                    'reasons' => $result['reasons'],
                ];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param  array{
     *     purpose?: string|null,
     *     industry?: string|null,
     *     orientation?: string|null,
     *     keywords?: list<string>
     * }  $context
     * @return array{score: float, reasons: list<string>}
     */
    public static function score(Template $template, array $context = []): array
    {
        $purpose = (string) ($context['purpose'] ?? 'promotion');
        $industry = isset($context['industry']) ? (string) $context['industry'] : null;
        $orientation = (string) ($context['orientation'] ?? 'landscape');
        $keywords = is_array($context['keywords'] ?? null) ? $context['keywords'] : [];

        $score = 0.0;
        $reasons = [];

        if ($template->orientation->value === $orientation) {
            $score += 0.25;
            $reasons[] = 'orientation';
        }

        $categoryMatch = self::purposeCategories($purpose);
        if (in_array($template->category, $categoryMatch, true)) {
            $score += 0.35;
            $reasons[] = 'category:'.$template->category->value;
        }

        $templateIndustry = $template->industry !== null ? mb_strtolower((string) $template->industry) : '';
        if ($industry !== null && $industry !== '' && $templateIndustry !== '') {
            if ($templateIndustry === mb_strtolower($industry)) {
                $score += 0.25;
                $reasons[] = 'industry';
            }
        }

        $haystack = mb_strtolower(trim(
            $template->name.' '.($template->description ?? '').' '.$template->category->label(),
        ));

        $keywordHits = 0;
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                $keywordHits++;
            }
        }

        if ($keywordHits > 0 && $keywords !== []) {
            $score += min(0.3, 0.1 * $keywordHits);
            $reasons[] = 'keywords:'.$keywordHits;
        }

        return [
            'score' => round(min(1.0, $score), 4),
            'reasons' => $reasons,
        ];
    }

    public static function isHighConfidence(float $score): bool
    {
        return $score >= (float) config('ai.agent.template_confidence_min', 0.55);
    }

    /**
     * @return list<TemplateCategory>
     */
    private static function purposeCategories(string $purpose): array
    {
        return match ($purpose) {
            'menu' => [TemplateCategory::Menu, TemplateCategory::Hospitality],
            'event' => [TemplateCategory::Event],
            'welcome' => [TemplateCategory::Welcome],
            'announcement' => [TemplateCategory::Announcement, TemplateCategory::Notice],
            'information' => [TemplateCategory::Notice, TemplateCategory::Timetable, TemplateCategory::Corporate],
            default => [TemplateCategory::Promo, TemplateCategory::Retail, TemplateCategory::Other],
        };
    }
}
