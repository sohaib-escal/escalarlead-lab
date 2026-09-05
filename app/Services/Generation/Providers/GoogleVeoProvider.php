<?php

namespace App\Services\Generation\Providers;

use App\Models\CreativeGeneration;
use App\Services\Generation\GenerationProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Veo through the Gemini API — the officially supported way to generate video
 * with Google's models programmatically.
 *
 * Flow (flow.google) is Google's consumer app built on the same models; it has
 * no public API, so this provider is the real implementation and
 * {@see GoogleFlowProvider} handles the manual route.
 *
 * Generation is a long-running operation: submit returns an operation name that
 * we poll until `done`. Google keeps the resulting file for ~2 days, so we copy
 * it locally on completion instead of only keeping the reference.
 */
class GoogleVeoProvider implements GenerationProvider
{
    public function key(): string
    {
        return 'google_veo';
    }

    public function label(): string
    {
        return config('integrations.generation.google_veo.label');
    }

    public function isConfigured(): bool
    {
        return filled(config('integrations.generation.google_veo.api_key'));
    }

    public function capabilities(): array
    {
        return [
            'api_generation' => true,
            'polling' => true,
            'asset_retrieval' => true,
            'note' => 'API officielle (Gemini API). Les fichiers générés sont conservés 2 jours côté Google : l\'app en garde une copie locale.',
            'docs' => 'https://ai.google.dev/gemini-api/docs/video',
        ];
    }

    public function submit(CreativeGeneration $generation, string $prompt): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Clé API Gemini manquante (GEMINI_API_KEY).');
        }

        $model = $generation->model ?: config('integrations.generation.google_veo.model');

        $response = $this->http()->post($this->url("models/{$model}:predictLongRunning"), [
            'instances' => [['prompt' => $prompt]],
            'parameters' => array_filter([
                'aspectRatio' => $generation->meta['aspect_ratio'] ?? null,
            ]),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Veo: '.$response->json('error.message', 'HTTP '.$response->status()));
        }

        $operation = $response->json('name');

        if (! $operation) {
            throw new RuntimeException('Veo n\'a pas renvoyé d\'opération.');
        }

        $generation->update([
            'model' => $model,
            'status' => CreativeGeneration::STATUS_GENERATING,
            'external_id' => $operation,
            'meta' => [...($generation->meta ?? []), 'submitted_at' => now()->toIso8601String()],
        ]);
    }

    public function refresh(CreativeGeneration $generation): void
    {
        if (! $generation->external_id) {
            return;
        }

        $response = $this->http()->get($this->url($generation->external_id));

        if ($response->failed()) {
            $generation->update([
                'status' => CreativeGeneration::STATUS_FAILED,
                'error' => 'Veo: '.$response->json('error.message', 'HTTP '.$response->status()),
            ]);

            return;
        }

        if (! $response->json('done')) {
            $generation->update(['status' => CreativeGeneration::STATUS_GENERATING]);

            return;
        }

        if ($error = $response->json('error.message')) {
            $generation->update(['status' => CreativeGeneration::STATUS_FAILED, 'error' => 'Veo: '.$error]);

            return;
        }

        $uri = $this->extractVideoUri($response->json() ?? []);

        if (! $uri) {
            $generation->update([
                'status' => CreativeGeneration::STATUS_FAILED,
                'error' => 'Opération terminée mais aucun fichier vidéo trouvé dans la réponse.',
            ]);

            return;
        }

        $generation->update([
            'status' => CreativeGeneration::STATUS_COMPLETED,
            'asset_url' => $uri,
            'asset_reference' => $uri,
            'asset_mime' => 'video/mp4',
            'completed_at' => now(),
            'meta' => [...($generation->meta ?? []), 'operation' => $response->json('name')],
        ]);

        $this->keepLocalCopy($generation, $uri);
    }

    /**
     * Google removes generated files after ~2 days, so keep our own copy.
     */
    private function keepLocalCopy(CreativeGeneration $generation, string $uri): void
    {
        try {
            $file = $this->http()->get($uri);

            if ($file->failed()) {
                return;
            }

            $path = 'generations/'.$generation->id.'.mp4';
            Storage::disk('public')->put($path, $file->body());

            $generation->update([
                'meta' => [...($generation->meta ?? []), 'local_path' => $path],
            ]);
        } catch (\Throwable $e) {
            // The provider URL is still recorded; a missing local copy is not a failed generation.
            $generation->update([
                'meta' => [...($generation->meta ?? []), 'local_copy_error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractVideoUri(array $payload): ?string
    {
        $candidates = [
            'response.generateVideoResponse.generatedSamples.0.video.uri',
            'response.generatedSamples.0.video.uri',
            'response.predictions.0.video.uri',
            'response.videos.0.uri',
        ];

        foreach ($candidates as $path) {
            if ($uri = data_get($payload, $path)) {
                return $uri;
            }
        }

        // Fall back to the first `uri` anywhere in the payload.
        $found = null;
        array_walk_recursive($payload, function ($value, $key) use (&$found) {
            if ($found === null && in_array($key, ['uri', 'url'], true) && is_string($value) && str_starts_with($value, 'http')) {
                $found = $value;
            }
        });

        return $found;
    }

    private function http()
    {
        return Http::timeout(config('integrations.generation.google_veo.timeout'))
            ->withHeaders(['x-goog-api-key' => config('integrations.generation.google_veo.api_key')]);
    }

    private function url(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return rtrim(config('integrations.generation.google_veo.base_url'), '/').'/'.ltrim($path, '/');
    }
}
