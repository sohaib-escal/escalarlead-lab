<?php

namespace App\Services\Ai\Providers;

use Anthropic\Client;
use App\Services\Ai\PromptCompletion;
use RuntimeException;

class AnthropicPromptProvider implements PromptProvider
{
    public function key(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return config('ai.providers.anthropic.label');
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.providers.anthropic.api_key'));
    }

    public function complete(string $system, string $user, string $modelId): PromptCompletion
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Clé API Anthropic manquante (ANTHROPIC_API_KEY).');
        }

        $client = new Client(apiKey: config('ai.providers.anthropic.api_key'));

        $message = $client->messages->create(
            model: $modelId,
            maxTokens: config('ai.max_output_tokens'),
            system: [['type' => 'text', 'text' => $system]],
            messages: [['role' => 'user', 'content' => $user]],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Le modèle n\'a renvoyé aucun texte.');
        }

        return new PromptCompletion(trim($text), [
            'provider' => $this->key(),
            'model' => $modelId,
            'input_tokens' => $message->usage->inputTokens ?? null,
            'output_tokens' => $message->usage->outputTokens ?? null,
        ]);
    }
}
