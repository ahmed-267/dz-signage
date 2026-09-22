<?php

namespace App\Support\Ai\Providers;

use App\Support\Ai\Contracts\AiTextProvider;
use App\Support\Ai\CreativeBrief;
use App\Support\Ai\DesignArchetypes;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Ai\Dto\TextGenerationResult;

/**
 * Deterministic high-quality text + design plans for tests and local development.
 */
final class FakeAiTextProvider implements AiTextProvider
{
    public function generate(string $prompt, array $options = []): TextGenerationResult
    {
        $length = (string) ($options['length'] ?? 'short');
        $mode = $this->resolveMode($options);
        $max = (int) ($options['max_chars'] ?? config('ai.limits.text_max_chars', 400));
        $variantCount = max(1, min(3, (int) ($options['variants'] ?? 1)));

        $variants = [];
        for ($i = 0; $i < $variantCount; $i++) {
            $variants[] = mb_substr($this->copyFor($prompt, $mode, $length, $i), 0, $max);
        }

        return new TextGenerationResult(
            text: $variants[0],
            inputTokens: 12,
            outputTokens: 18 * $variantCount,
            model: $this->model(),
            texts: $variants,
        );
    }

    public function rewrite(string $text, array $options = []): TextGenerationResult
    {
        $action = (string) ($options['action'] ?? 'simplify');
        $max = (int) ($options['max_chars'] ?? config('ai.limits.text_max_chars', 400));
        $source = trim($text);
        $variantCount = max(1, min(3, (int) ($options['variants'] ?? 1)));

        $base = match ($action) {
            'shorten' => mb_substr($source, 0, max(12, (int) (mb_strlen($source) * 0.55))),
            'expand' => $source.' — clear, confident, and easy to read from a distance.',
            'make_professional' => mb_strtoupper(mb_substr($source, 0, 1)).mb_substr(rtrim($source, '.'), 1).'.',
            'make_friendlier' => 'Hey — '.$source,
            'make_promotional' => 'DON\'T MISS OUT: '.$source,
            'fix_grammar' => preg_replace('/\s+/', ' ', $source) ?? $source,
            default => $source,
        };

        $variants = [];
        for ($i = 0; $i < $variantCount; $i++) {
            $suffix = $i === 0 ? '' : match ($i) {
                1 => ' · Now showing',
                default => ' · Limited time',
            };
            $variants[] = mb_substr(trim((string) $base).$suffix, 0, $max);
        }

        return new TextGenerationResult(
            text: $variants[0],
            inputTokens: 8,
            outputTokens: 10 * $variantCount,
            model: $this->model(),
            texts: $variants,
        );
    }

    public function generateDesignPlan(CreativeBrief $brief, ?string $archetype = null): DesignPlanResult
    {
        $archetype ??= DesignArchetypes::selectFor($brief);
        $plan = DesignArchetypes::plan($brief, $archetype);

        return new DesignPlanResult(
            elements: $plan->elements,
            background: $plan->background,
            suggestedName: $plan->suggestedName,
            inputTokens: 40,
            outputTokens: 120,
            model: $this->model(),
            archetype: $archetype,
        );
    }

    public function model(): string
    {
        return 'fake-text-v2';
    }

    public function name(): string
    {
        return 'fake';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveMode(array $options): string
    {
        if (! empty($options['mode']) && is_string($options['mode'])) {
            return $options['mode'];
        }

        $purpose = (string) ($options['purpose'] ?? 'general');
        $map = config('ai.text.purpose_to_mode', []);

        return $map[$purpose] ?? (in_array($purpose, config('ai.text.modes', []), true) ? $purpose : 'information');
    }

    private function copyFor(string $prompt, string $mode, string $length, int $variantIndex): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $prompt) ?? $prompt);
        $snippet = $clean !== '' ? implode(' ', array_slice(explode(' ', $clean), 0, 8)) : 'your offer';

        $bases = match ($mode) {
            'headline' => [
                mb_strtoupper($snippet),
                'NOW SHOWING: '.mb_strtoupper(mb_substr($snippet, 0, 28)),
                mb_strtoupper(mb_substr($snippet, 0, 24)).' — TODAY',
            ],
            'promotion' => [
                'LIMITED OFFER — '.$snippet,
                'Save more on '.$snippet,
                'This week only: '.$snippet,
            ],
            'cta' => [
                'Shop now',
                'Discover more',
                'Get started',
            ],
            'announcement' => [
                'Important update: '.$snippet,
                'Please note — '.$snippet,
                'Announcement: '.$snippet,
            ],
            'event' => [
                'Join us: '.$snippet,
                'Don\'t miss '.$snippet,
                'This weekend — '.$snippet,
            ],
            'menu_product' => [
                'Featured: '.$snippet,
                'Chef\'s pick — '.$snippet,
                'Try our '.$snippet,
            ],
            'welcome' => [
                'Welcome',
                'Welcome in',
                'Glad you\'re here',
            ],
            default => [
                $snippet,
                'Good to know: '.$snippet,
                'At a glance — '.$snippet,
            ],
        };

        $text = $bases[$variantIndex % count($bases)];

        return match ($length) {
            'detailed' => $text.'. Clear, readable, and ready for your screens.',
            'medium' => $text.'.',
            default => $text,
        };
    }
}
