<?php

namespace App\Services\Generation\Providers;

use App\Models\CreativeGeneration;
use App\Services\Generation\GenerationProvider;

/**
 * Google Flow (flow.google).
 *
 * Flow is Google's consumer AI filmmaking app built on the Veo models. As of
 * September 2026 Google publishes **no public Flow API**: there is no documented
 * endpoint to start a Flow generation, no way to poll one, and no way to pull the
 * resulting asset out of a Flow project programmatically. The officially supported
 * programmatic route to the same models is the Gemini API — see
 * {@see GoogleVeoProvider}, which is fully implemented.
 *
 * Third-party services resell unofficial Flow access by driving a logged-in
 * session; we deliberately do not use those.
 *
 * So this provider does the only honest thing: it hands the validated prompt over
 * for the admin to run in Flow, and records the asset they bring back. It never
 * claims a generation happened. When Google ships an official Flow API, only
 * `submit()`/`refresh()` here need to change.
 */
class GoogleFlowProvider implements GenerationProvider
{
    public function key(): string
    {
        return 'google_flow';
    }

    public function label(): string
    {
        return config('integrations.generation.google_flow.label');
    }

    public function isConfigured(): bool
    {
        // Nothing to configure: the handoff is manual by necessity.
        return true;
    }

    public function capabilities(): array
    {
        return [
            'api_generation' => false,
            'polling' => false,
            'asset_retrieval' => false,
            'note' => 'Google Flow n\'expose aucune API publique de génération. L\'app prépare le prompt validé, vous générez dans Flow, puis vous rattachez le résultat. Pour une génération automatique, utilisez Google Veo (Gemini API).',
            'docs' => config('integrations.generation.google_flow.url'),
        ];
    }

    public function submit(CreativeGeneration $generation, string $prompt): void
    {
        $generation->update([
            'status' => CreativeGeneration::STATUS_AWAITING_MANUAL,
            'meta' => [
                ...($generation->meta ?? []),
                'handoff_url' => config('integrations.generation.google_flow.url'),
                'handed_off_at' => now()->toIso8601String(),
                'prompt' => $prompt,
            ],
        ]);
    }

    public function refresh(CreativeGeneration $generation): void
    {
        // No API to poll. The generation leaves `awaiting_manual` only when the
        // admin attaches the asset they produced in Flow.
    }
}
