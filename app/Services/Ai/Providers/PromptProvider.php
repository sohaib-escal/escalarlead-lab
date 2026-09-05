<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\PromptCompletion;

interface PromptProvider
{
    public function key(): string;

    public function label(): string;

    /** False when no API key is configured — the UI says so instead of pretending. */
    public function isConfigured(): bool;

    public function complete(string $system, string $user, string $modelId): PromptCompletion;
}
