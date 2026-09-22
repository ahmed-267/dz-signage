<?php

namespace App\Support\Ai\Contracts;

use App\Support\Ai\CreativeBrief;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Ai\Dto\TextGenerationResult;

interface AiTextProvider
{
    /**
     * @param  array{
     *     purpose?: string|null,
     *     mode?: string|null,
     *     tone?: string|null,
     *     length?: string|null,
     *     max_chars?: int|null,
     *     variants?: int|null
     * }  $options
     */
    public function generate(string $prompt, array $options = []): TextGenerationResult;

    /**
     * @param  array{
     *     action?: string,
     *     max_chars?: int|null,
     *     variants?: int|null
     * }  $options
     */
    public function rewrite(string $text, array $options = []): TextGenerationResult;

    public function generateDesignPlan(CreativeBrief $brief, ?string $archetype = null): DesignPlanResult;

    public function model(): string;

    public function name(): string;
}
