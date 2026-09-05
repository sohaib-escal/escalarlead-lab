<?php

namespace App\Services\Generation;

use App\Models\CreativeGeneration;

interface GenerationProvider
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /**
     * What this provider can honestly do today.
     *
     * @return array{api_generation:bool, polling:bool, asset_retrieval:bool, note:string, docs:?string}
     */
    public function capabilities(): array;

    /**
     * Start a generation. Providers without a public API must not pretend:
     * they put the generation in `awaiting_manual` and explain the handoff.
     */
    public function submit(CreativeGeneration $generation, string $prompt): void;

    /** Ask the provider where an in-flight generation stands. */
    public function refresh(CreativeGeneration $generation): void;
}
