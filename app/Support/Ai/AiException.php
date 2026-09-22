<?php

namespace App\Support\Ai;

use RuntimeException;

class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codeKey = 'provider_error',
        public readonly bool $userSafe = true,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function unavailable(string $detail = 'AI generation is currently unavailable.'): self
    {
        return new self($detail, 'unavailable');
    }

    public static function refused(string $detail = 'This request could not be generated. Try adjusting your prompt.'): self
    {
        return new self($detail, 'refused');
    }

    public static function timeout(string $detail = 'The AI provider timed out. Please try again.', ?\Throwable $previous = null): self
    {
        return new self($detail, 'timeout', true, 0, $previous);
    }

    public static function rateLimited(string $detail = 'Too many AI requests. Please wait a moment and try again.'): self
    {
        return new self($detail, 'rate_limited');
    }

    public static function invalidOutput(string $detail = 'The AI response could not be used. Please try again.'): self
    {
        return new self($detail, 'invalid_output');
    }

    public static function storage(string $detail = 'Generated content could not be stored.', ?\Throwable $previous = null): self
    {
        return new self($detail, 'storage_failed', true, 0, $previous);
    }
}
