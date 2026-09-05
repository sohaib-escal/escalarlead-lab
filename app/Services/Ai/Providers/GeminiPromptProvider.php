<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\PromptCompletion;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiPromptProvider implements PromptProvider
{
    public function key(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return config('ai.providers.gemini.label');
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.providers.gemini.api_key'));
    }

    public function complete(string $system, string $user, string $modelId): PromptCompletion
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Clé API Gemini manquante (GEMINI_API_KEY).');
        }

        $response = Http::timeout(config('ai.timeout'))
            ->withHeaders(['x-goog-api-key' => config('ai.providers.gemini.api_key')])
            ->post(rtrim(config('ai.providers.gemini.base_url'), '/')."/models/{$modelId}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => ['maxOutputTokens' => config('ai.max_output_tokens')],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini: '.$response->json('error.message', $response->status()));
        }

        $text = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter()
            ->implode('');

        if (trim($text) === '') {
            throw new RuntimeException('Le modèle n\'a renvoyé aucun texte.');
        }

        return new PromptCompletion(trim($text), [
            'provider' => $this->key(),
            'model' => $modelId,
            'input_tokens' => $response->json('usageMetadata.promptTokenCount'),
            'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
        ]);
    }
}
