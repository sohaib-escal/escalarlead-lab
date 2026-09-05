<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\PromptCompletion;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiPromptProvider implements PromptProvider
{
    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return config('ai.providers.openai.label');
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.providers.openai.api_key'));
    }

    public function complete(string $system, string $user, string $modelId): PromptCompletion
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Clé API OpenAI manquante (OPENAI_API_KEY).');
        }

        $response = Http::timeout(config('ai.timeout'))
            ->withToken(config('ai.providers.openai.api_key'))
            ->post(rtrim(config('ai.providers.openai.base_url'), '/').'/chat/completions', [
                'model' => $modelId,
                'max_completion_tokens' => config('ai.max_output_tokens'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI: '.$response->json('error.message', $response->status()));
        }

        $text = (string) $response->json('choices.0.message.content');

        if (trim($text) === '') {
            throw new RuntimeException('Le modèle n\'a renvoyé aucun texte.');
        }

        return new PromptCompletion(trim($text), [
            'provider' => $this->key(),
            'model' => $modelId,
            'input_tokens' => $response->json('usage.prompt_tokens'),
            'output_tokens' => $response->json('usage.completion_tokens'),
        ]);
    }
}
