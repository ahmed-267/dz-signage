<?php

namespace App\Support\Ai\Providers;

use App\Support\Ai\AiException;
use App\Support\Ai\Contracts\AiTextProvider;
use App\Support\Ai\CreativeBrief;
use App\Support\Ai\DesignArchetypes;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Ai\Dto\TextGenerationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAiCompatibleTextProvider implements AiTextProvider
{
    public function generate(string $prompt, array $options = []): TextGenerationResult
    {
        $mode = $this->resolveMode($options);
        $tone = (string) ($options['tone'] ?? 'professional');
        $length = (string) ($options['length'] ?? 'short');
        $max = (int) ($options['max_chars'] ?? config('ai.limits.text_max_chars', 400));
        $variantCount = max(1, min(3, (int) ($options['variants'] ?? 1)));

        $modeGuide = match ($mode) {
            'headline' => 'Write a punchy digital-signage HEADLINE. Prefer 3–8 words. Uppercase is welcome when it improves impact.',
            'promotion' => 'Write promotional signage copy with a clear offer and urgency — still scannable from 3+ metres.',
            'cta' => 'Write a short call-to-action (2–5 words) suitable for a button or banner.',
            'announcement' => 'Write a clear announcement for lobby or corridor screens.',
            'event' => 'Write event copy that highlights what / when / why to attend.',
            'menu_product' => 'Write appetising product or menu copy for digital boards.',
            'welcome' => 'Write a warm welcome line for entrance screens.',
            default => 'Write clear informational signage copy.',
        };

        if ($variantCount > 1) {
            $system = <<<SYS
You write premium digital signage copy for RMSignage.
{$modeGuide}
Tone: {$tone}. Length: {$length}. Hard limit per option: {$max} characters.
Return STRICT JSON only: {"texts":["option1","option2","option3"]}
Provide exactly {$variantCount} distinct alternatives. No markdown, quotes wrappers, or HTML inside strings.
SYS;
            $payload = $this->requestChat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ], responseFormat: ['type' => 'json_object'], temperature: 0.85);

            $content = (string) data_get($payload, 'choices.0.message.content', '');
            $decoded = json_decode($content, true);
            $texts = [];
            if (is_array($decoded) && is_array($decoded['texts'] ?? null)) {
                foreach ($decoded['texts'] as $t) {
                    if (is_string($t) && trim($t) !== '') {
                        $texts[] = mb_substr(trim($t), 0, $max);
                    }
                }
            }
            $texts = array_slice($texts, 0, $variantCount);
            if ($texts === []) {
                throw AiException::invalidOutput();
            }

            return new TextGenerationResult(
                text: $texts[0],
                inputTokens: isset($payload['usage']['prompt_tokens']) ? (int) $payload['usage']['prompt_tokens'] : null,
                outputTokens: isset($payload['usage']['completion_tokens']) ? (int) $payload['usage']['completion_tokens'] : null,
                model: $this->model(),
                texts: $texts,
            );
        }

        $system = <<<SYS
You write premium digital signage copy for RMSignage.
{$modeGuide}
Return ONLY the final copy text — no quotes, markdown, or HTML.
Tone: {$tone}. Length: {$length}. Hard limit: {$max} characters.
Prefer short lines readable from a distance.
SYS;

        return $this->chat($system, $prompt, $max, temperature: 0.8);
    }

    public function rewrite(string $text, array $options = []): TextGenerationResult
    {
        $action = (string) ($options['action'] ?? 'simplify');
        $max = (int) ($options['max_chars'] ?? config('ai.limits.text_max_chars', 400));
        $variantCount = max(1, min(3, (int) ($options['variants'] ?? 1)));

        $instruction = match ($action) {
            'shorten' => 'Make it shorter while keeping the meaning.',
            'expand' => 'Expand slightly with clearer signage language.',
            'make_professional' => 'Make the tone more professional.',
            'make_friendlier' => 'Make the tone friendlier and warmer.',
            'make_promotional' => 'Make it more promotional and persuasive.',
            'fix_grammar' => 'Fix grammar and spelling only.',
            default => 'Simplify the wording for digital signage.',
        };

        if ($variantCount > 1) {
            $system = <<<SYS
You rewrite digital signage text. {$instruction}
Hard limit per option: {$max} characters.
Return STRICT JSON only: {"texts":["option1","option2"]}
Provide exactly {$variantCount} distinct rewrites. No markdown or HTML.
SYS;
            $payload = $this->requestChat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $text],
            ], responseFormat: ['type' => 'json_object'], temperature: 0.75);

            $content = (string) data_get($payload, 'choices.0.message.content', '');
            $decoded = json_decode($content, true);
            $texts = [];
            if (is_array($decoded) && is_array($decoded['texts'] ?? null)) {
                foreach ($decoded['texts'] as $t) {
                    if (is_string($t) && trim($t) !== '') {
                        $texts[] = mb_substr(trim($t), 0, $max);
                    }
                }
            }
            $texts = array_slice($texts, 0, $variantCount);
            if ($texts === []) {
                throw AiException::invalidOutput();
            }

            return new TextGenerationResult(
                text: $texts[0],
                inputTokens: isset($payload['usage']['prompt_tokens']) ? (int) $payload['usage']['prompt_tokens'] : null,
                outputTokens: isset($payload['usage']['completion_tokens']) ? (int) $payload['usage']['completion_tokens'] : null,
                model: $this->model(),
                texts: $texts,
            );
        }

        $system = <<<SYS
You rewrite digital signage text. Return ONLY the rewritten text — no quotes, markdown, or HTML.
{$instruction}
Hard limit: {$max} characters.
SYS;

        return $this->chat($system, $text, $max, temperature: 0.7);
    }

    public function generateDesignPlan(CreativeBrief $brief, ?string $archetype = null): DesignPlanResult
    {
        $archetype ??= DesignArchetypes::selectFor($brief);
        $briefJson = json_encode($brief->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $archetypeLabel = DesignArchetypes::label($archetype);

        $system = <<<SYS
You write premium digital-signage COPY for RMSignage. Do NOT invent layout coordinates.
Archetype (geometry is applied server-side): {$archetype} ({$archetypeLabel}).
Follow the creative brief JSON for brand colours and mood:
{$briefJson}

Return STRICT JSON only (no x/y/width/height):
{"suggestedName":"string","background":{"type":"color","value":"#RRGGBB"},"elements":[
  {"type":"text","name":"Headline","text":"...","color":"#RRGGBB","fontWeight":"700"},
  {"type":"text","name":"Subheadline","text":"...","color":"#RRGGBB","fontWeight":"500"},
  {"type":"text","name":"CTA","text":"...","color":"#RRGGBB","fontWeight":"700"},
  {"type":"text","name":"Body","text":"...","color":"#RRGGBB","fontWeight":"400"}
]}

Copy rules:
- Headline: 2–6 memorable words, TV-readable. Prefer punchy event/sports lines over dumping the full prompt.
- Subheadline: one short supporting line.
- CTA: 2–4 words.
- Body: optional, ≤100 characters.
- Use brand colours from the brief. High contrast text.
SYS;

        $payload = $this->requestChat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => 'Produce signage copy for: '.$brief->prompt],
        ], responseFormat: [
            'type' => 'json_object',
        ], temperature: 0.75, useDesignModel: true);

        $content = (string) data_get($payload, 'choices.0.message.content', '');
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw AiException::invalidOutput();
        }

        /** @var list<array<string, mixed>> $elements */
        $elements = [];
        if (isset($decoded['elements']) && is_array($decoded['elements'])) {
            $elements = array_values(array_filter(
                $decoded['elements'],
                fn ($el) => is_array($el),
            ));
        }

        // Accept flat copy bags as synthetic text elements.
        foreach (['headline' => 'Headline', 'subheadline' => 'Subheadline', 'cta' => 'CTA', 'body' => 'Body'] as $key => $name) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
                $elements[] = [
                    'type' => 'text',
                    'name' => $name,
                    'text' => trim($decoded[$key]),
                ];
            }
        }

        if ($elements === []) {
            // Fall back to brief copy so geometry merge still has something useful.
            $elements = [
                ['type' => 'text', 'name' => 'Headline', 'text' => $brief->headline],
                ['type' => 'text', 'name' => 'Subheadline', 'text' => $brief->subheadline],
                ['type' => 'text', 'name' => 'CTA', 'text' => $brief->cta],
                ['type' => 'text', 'name' => 'Body', 'text' => $brief->body],
            ];
        }

        $background = is_array($decoded['background'] ?? null) ? $decoded['background'] : null;

        return new DesignPlanResult(
            elements: $elements,
            background: $background,
            suggestedName: isset($decoded['suggestedName']) ? (string) $decoded['suggestedName'] : null,
            inputTokens: isset($payload['usage']['prompt_tokens']) ? (int) $payload['usage']['prompt_tokens'] : null,
            outputTokens: isset($payload['usage']['completion_tokens']) ? (int) $payload['usage']['completion_tokens'] : null,
            model: $this->designModel(),
            archetype: $archetype,
        );
    }

    public function model(): string
    {
        return (string) config('ai.openai.text_model');
    }

    public function designModel(): string
    {
        return (string) config('ai.openai.design_model');
    }

    public function name(): string
    {
        return 'openai_compatible';
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

        return $map[$purpose] ?? 'information';
    }

    private function chat(string $system, string $user, int $maxChars, float $temperature = 0.7): TextGenerationResult
    {
        $payload = $this->requestChat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], temperature: $temperature);

        $text = trim((string) data_get($payload, 'choices.0.message.content', ''));
        if ($text === '') {
            throw AiException::invalidOutput();
        }

        $trimmed = mb_substr($text, 0, $maxChars);

        return new TextGenerationResult(
            text: $trimmed,
            inputTokens: isset($payload['usage']['prompt_tokens']) ? (int) $payload['usage']['prompt_tokens'] : null,
            outputTokens: isset($payload['usage']['completion_tokens']) ? (int) $payload['usage']['completion_tokens'] : null,
            model: $this->model(),
            texts: [$trimmed],
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $responseFormat
     * @return array<string, mixed>
     */
    private function requestChat(
        array $messages,
        ?array $responseFormat = null,
        float $temperature = 0.6,
        bool $useDesignModel = false,
    ): array {
        $key = (string) config('ai.openai.api_key');
        if ($key === '') {
            throw AiException::unavailable();
        }

        $body = [
            'model' => $useDesignModel ? $this->designModel() : $this->model(),
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        if ($responseFormat !== null) {
            $body['response_format'] = $responseFormat;
        }

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
                ->post('/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw AiException::timeout(previous: $e);
        } catch (Throwable $e) {
            throw new AiException('The AI provider could not be reached.', 'provider_error', true, 0, $e);
        }

        if ($response->status() === 429) {
            throw AiException::rateLimited();
        }

        if (in_array($response->status(), [400, 422], true)) {
            $msg = (string) data_get($response->json(), 'error.message', '');
            if (str_contains(strtolower($msg), 'safety') || str_contains(strtolower($msg), 'content policy')) {
                throw AiException::refused();
            }
        }

        if (! $response->successful()) {
            throw AiException::unavailable();
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }
}
