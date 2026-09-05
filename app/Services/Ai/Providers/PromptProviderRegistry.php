<?php

namespace App\Services\Ai\Providers;

use RuntimeException;

class PromptProviderRegistry
{
    /** @var array<string, PromptProvider> */
    private array $providers;

    public function __construct()
    {
        $this->providers = collect([
            new AnthropicPromptProvider,
            new GeminiPromptProvider,
            new OpenAiPromptProvider,
        ])->keyBy(fn (PromptProvider $provider) => $provider->key())->all();
    }

    public function get(string $key): PromptProvider
    {
        return $this->providers[$key]
            ?? throw new RuntimeException("Fournisseur IA inconnu : {$key}.");
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * @return array<int, array{key:string,label:string,configured:bool}>
     */
    public function status(): array
    {
        return collect($this->providers)
            ->map(fn (PromptProvider $provider) => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'configured' => $provider->isConfigured(),
            ])
            ->values()
            ->all();
    }
}
