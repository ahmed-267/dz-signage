<?php

namespace App\Support\Ai\Dto;

final readonly class TextGenerationResult
{
    /**
     * @param  list<string>  $texts
     */
    public function __construct(
        public string $text,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?string $model = null,
        public array $texts = [],
    ) {}

    /**
     * @return list<string>
     */
    public function allTexts(): array
    {
        if ($this->texts !== []) {
            return $this->texts;
        }

        return [$this->text];
    }
}
