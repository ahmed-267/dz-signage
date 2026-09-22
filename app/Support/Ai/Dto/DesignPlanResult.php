<?php

namespace App\Support\Ai\Dto;

/**
 * Intermediate design plan from the model — never persisted raw.
 */
final readonly class DesignPlanResult
{
    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array{type?: string, value?: string}|null  $background
     */
    public function __construct(
        public array $elements,
        public ?array $background = null,
        public ?string $suggestedName = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?string $model = null,
        public ?string $archetype = null,
    ) {}
}
