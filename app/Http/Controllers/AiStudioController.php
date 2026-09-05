<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\CreativeGeneration;
use App\Models\CreativePrompt;
use App\Models\PromptTemplate;
use App\Services\Ai\Providers\PromptProviderRegistry;
use App\Services\Generation\GenerationProviderRegistry;
use Inertia\Inertia;
use Inertia\Response;

class AiStudioController extends Controller
{
    public function __invoke(PromptProviderRegistry $prompts, GenerationProviderRegistry $generation): Response
    {
        return Inertia::render('AiStudio', [
            'models' => AiModel::orderByDesc('is_default')->orderBy('position')->orderBy('name')
                ->withCount('prompts')->get(),
            'templates' => PromptTemplate::orderByDesc('is_default')->orderBy('position')->get(),
            'promptProviders' => $prompts->status(),
            'generationProviders' => $generation->status(),
            'recentPrompts' => CreativePrompt::with(['creative:id,reference,name', 'model:id,name'])
                ->latest('id')->take(8)->get()
                ->map(fn ($prompt) => [
                    'id' => $prompt->id,
                    'creative' => $prompt->creative ? [
                        'id' => $prompt->creative->id,
                        'reference' => $prompt->creative->reference,
                    ] : null,
                    'model' => $prompt->model?->name,
                    'status' => $prompt->status,
                    'version' => $prompt->version,
                    'created_at' => $prompt->created_at?->toDateTimeString(),
                ]),
            'recentGenerations' => CreativeGeneration::with(['creative:id,reference'])
                ->latest('id')->take(8)->get()
                ->map(fn ($generation) => [
                    'id' => $generation->id,
                    'creative' => $generation->creative ? [
                        'id' => $generation->creative->id,
                        'reference' => $generation->creative->reference,
                    ] : null,
                    'provider' => $generation->provider,
                    'status' => $generation->status,
                    'created_at' => $generation->created_at?->toDateTimeString(),
                ]),
        ]);
    }
}
