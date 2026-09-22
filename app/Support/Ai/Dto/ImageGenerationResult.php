<?php

namespace App\Support\Ai\Dto;

final readonly class ImageGenerationResult
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{binary: string, mimeType: string, extension: string}>  $variants
     */
    public function __construct(
        public string $binary,
        public string $mimeType,
        public string $extension,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $model = null,
        public array $meta = [],
        public array $variants = [],
    ) {}

    /**
     * @return list<array{binary: string, mimeType: string, extension: string}>
     */
    public function allVariants(): array
    {
        if ($this->variants !== []) {
            return $this->variants;
        }

        return [[
            'binary' => $this->binary,
            'mimeType' => $this->mimeType,
            'extension' => $this->extension,
        ]];
    }
}
