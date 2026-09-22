<?php

namespace App\Support\Ai\Providers;

use App\Support\Ai\AiException;
use App\Support\Ai\Contracts\AiImageProvider;
use App\Support\Ai\Dto\ImageGenerationResult;
use App\Support\Ai\ImagePromptEnhancer;
use App\Support\Demo\DemoImageFactory;

/**
 * Generates composed demo PNGs locally so tests never call paid APIs.
 */
final class FakeAiImageProvider implements AiImageProvider
{
    public function generate(string $prompt, array $options = []): ImageGenerationResult
    {
        $width = max(64, min(3840, (int) ($options['width'] ?? 1920)));
        $height = max(64, min(3840, (int) ($options['height'] ?? 1080)));
        $n = max(1, min(3, (int) ($options['n'] ?? $options['variants'] ?? 1)));

        $enhanced = ImagePromptEnhancer::enhance($prompt, $options);
        $hash = md5($enhanced.$width.'x'.$height);
        $palette = [
            [[15, 23, 42], [34, 211, 238]],
            [[30, 41, 59], [251, 146, 60]],
            [[8, 51, 68], [52, 211, 153]],
            [[67, 56, 202], [196, 181, 253]],
            [[146, 64, 14], [253, 186, 116]],
            [[22, 101, 52], [134, 239, 172]],
        ];

        $variants = [];
        for ($i = 0; $i < $n; $i++) {
            $pick = $palette[(hexdec(substr($hash, 0, 2)) + $i) % count($palette)];
            try {
                $title = 'AI '.mb_substr(trim($prompt) !== '' ? $prompt : 'image', 0, 36);
                $subtitle = $i === 0
                    ? 'Generated locally · signage-ready'
                    : 'Variant '.($i + 1).' · no text overlay';
                $binary = DemoImageFactory::compose(
                    $title,
                    $subtitle,
                    $pick[0],
                    $pick[1],
                    $width,
                    $height,
                );
            } catch (\Throwable $e) {
                throw AiException::storage($e->getMessage());
            }

            $variants[] = [
                'binary' => $binary,
                'mimeType' => 'image/png',
                'extension' => 'png',
            ];
        }

        return new ImageGenerationResult(
            binary: $variants[0]['binary'],
            mimeType: 'image/png',
            extension: 'png',
            width: $width,
            height: $height,
            model: $this->model(),
            meta: [
                'fake' => true,
                'enhanced_prompt' => mb_substr($enhanced, 0, 500),
                'refinement' => $options['refinement'] ?? null,
            ],
            variants: $variants,
        );
    }

    public function model(): string
    {
        return 'fake-image-v2';
    }

    public function name(): string
    {
        return 'fake';
    }
}
