<?php

namespace App\Services\Generation;

use App\Services\Generation\Providers\GoogleFlowProvider;
use App\Services\Generation\Providers\GoogleVeoProvider;
use RuntimeException;

class GenerationProviderRegistry
{
    /** @var array<string, GenerationProvider> */
    private array $providers;

    public function __construct()
    {
        $this->providers = collect([
            new GoogleVeoProvider,
            new GoogleFlowProvider,
        ])->keyBy(fn (GenerationProvider $provider) => $provider->key())->all();
    }

    public function get(string $key): GenerationProvider
    {
        return $this->providers[$key]
            ?? throw new RuntimeException("Fournisseur de génération inconnu : {$key}.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function status(): array
    {
        return collect($this->providers)
            ->map(fn (GenerationProvider $provider) => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'configured' => $provider->isConfigured(),
                ...$provider->capabilities(),
            ])
            ->values()
            ->all();
    }
}
