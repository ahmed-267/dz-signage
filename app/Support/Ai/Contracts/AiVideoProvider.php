<?php

namespace App\Support\Ai\Contracts;

/**
 * Reserved for a future Phase. Phase 14 does not implement video generation.
 */
interface AiVideoProvider
{
    public function name(): string;

    public function model(): string;

    public function isEnabled(): bool;
}
