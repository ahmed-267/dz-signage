<?php

namespace App\Support\Ai\Contracts;

use App\Support\Ai\Dto\ImageGenerationResult;

interface AiImageProvider
{
    /**
     * @param  array{
     *     aspect?: string,
     *     width?: int,
     *     height?: int,
     *     style?: string|null,
     *     style_instruction?: string|null,
     *     industry?: string|null,
     *     brand_mood?: string|null,
     *     refinement?: string|null,
     *     n?: int|null,
     *     variants?: int|null
     * }  $options
     */
    public function generate(string $prompt, array $options = []): ImageGenerationResult;

    public function model(): string;

    public function name(): string;
}
