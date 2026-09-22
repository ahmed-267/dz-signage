<?php

namespace App\Support\Ai\Providers;

use App\Support\Ai\AiException;
use App\Support\Ai\Contracts\AiImageProvider;
use App\Support\Ai\Dto\ImageGenerationResult;
use App\Support\Ai\ImagePromptEnhancer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAiCompatibleImageProvider implements AiImageProvider
{
    public function generate(string $prompt, array $options = []): ImageGenerationResult
    {
        $key = (string) config('ai.openai.api_key');
        if ($key === '') {
            throw AiException::unavailable();
        }

        $width = (int) ($options['width'] ?? 1920);
        $height = (int) ($options['height'] ?? 1080);
        $n = max(1, min(3, (int) ($options['n'] ?? $options['variants'] ?? 1)));
        $enhanced = ImagePromptEnhancer::enhance($prompt, $options);
        $size = $this->nearestSize($width, $height);

        try {
            $response = Http::baseUrl((string) config('ai.openai.base_url'))
                ->withToken($key)
                ->acceptJson()
                ->timeout((int) config('ai.openai.timeout', 60))
                ->when(
                    filled(config('ai.openai.organization')),
                    fn ($http) => $http->withHeaders([
                        'OpenAI-Organization' => (string) config('ai.openai.organization'),
                    ]),
                )
                ->post('/images/generations', [
                    'model' => $this->model(),
                    'prompt' => $enhanced,
                    'size' => $size,
                    'n' => $n,
                ]);
        } catch (ConnectionException $e) {
            throw AiException::timeout(previous: $e);
        } catch (Throwable $e) {
            throw new AiException('The AI provider could not be reached.', 'provider_error', true, 0, $e);
        }

        if ($response->status() === 429) {
            throw AiException::rateLimited();
        }

        if (in_array($response->status(), [400, 422], true)) {
            throw AiException::refused();
        }

        if (! $response->successful()) {
            throw AiException::unavailable();
        }

        $data = data_get($response->json(), 'data');
        if (! is_array($data) || $data === []) {
            throw AiException::invalidOutput();
        }

        $maxBytes = (int) config('ai.limits.image_max_bytes', 10 * 1024 * 1024);
        $variants = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $b64 = $item['b64_json'] ?? null;
            $url = $item['url'] ?? null;

            if (is_string($b64) && $b64 !== '') {
                $binary = base64_decode($b64, true);
                if ($binary === false || $binary === '') {
                    continue;
                }
            } elseif (is_string($url) && $url !== '') {
                $binary = $this->download($url);
            } else {
                continue;
            }

            if (strlen($binary) > $maxBytes) {
                throw AiException::storage('Generated image exceeds the allowed size.');
            }

            $mime = $this->detectMime($binary);
            $variants[] = [
                'binary' => $binary,
                'mimeType' => $mime,
                'extension' => $mime === 'image/jpeg' ? 'jpg' : 'png',
            ];
        }

        if ($variants === []) {
            throw AiException::invalidOutput();
        }

        return new ImageGenerationResult(
            binary: $variants[0]['binary'],
            mimeType: $variants[0]['mimeType'],
            extension: $variants[0]['extension'],
            width: $width,
            height: $height,
            model: $this->model(),
            meta: [
                'enhanced_prompt' => mb_substr($enhanced, 0, 500),
                'refinement' => $options['refinement'] ?? null,
            ],
            variants: $variants,
        );
    }

    public function model(): string
    {
        return (string) config('ai.openai.image_model');
    }

    public function name(): string
    {
        return 'openai_compatible';
    }

    private function nearestSize(int $width, int $height): string
    {
        $ratio = $width / max(1, $height);

        return match (true) {
            $ratio > 1.2 => '1536x1024',
            $ratio < 0.8 => '1024x1536',
            default => '1024x1024',
        };
    }

    private function download(string $url): string
    {
        try {
            $response = Http::timeout((int) config('ai.openai.timeout', 60))->get($url);
        } catch (Throwable $e) {
            throw AiException::storage(previous: $e);
        }

        if (! $response->successful()) {
            throw AiException::storage();
        }

        $body = $response->body();
        if ($body === '') {
            throw AiException::invalidOutput();
        }

        return $body;
    }

    private function detectMime(string $binary): string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($binary, "\x89PNG")) {
            return 'image/png';
        }

        if (str_starts_with($binary, 'RIFF') && str_contains(substr($binary, 0, 16), 'WEBP')) {
            return 'image/webp';
        }

        return 'image/png';
    }
}
