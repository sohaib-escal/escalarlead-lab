<?php

namespace App\Http\Controllers;

use App\Models\Creative;
use App\Models\CreativeGeneration;
use App\Models\CreativePrompt;
use App\Models\CreativeStatus;
use App\Services\Generation\GenerationProviderRegistry;
use App\Services\HistoryLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CreativeGenerationController extends Controller
{
    public function __construct(
        private readonly GenerationProviderRegistry $registry,
        private readonly HistoryLogger $history,
    ) {}

    /**
     * Start a generation from a *validated* prompt. Never from a draft.
     */
    public function store(Request $request, Creative $creative): RedirectResponse
    {
        $data = $request->validate([
            'creative_prompt_id' => ['required', 'exists:creative_prompts,id'],
            'provider' => ['required', 'string'],
        ]);

        $prompt = CreativePrompt::findOrFail($data['creative_prompt_id']);

        if (! $prompt->isValidated()) {
            return back()->with('error', 'Validez le prompt avant de lancer une génération.');
        }

        $provider = $this->registry->get($data['provider']);

        if (! $provider->isConfigured()) {
            return back()->with('error', $provider->label().' n\'est pas configuré.');
        }

        $generation = $creative->generations()->create([
            'creative_prompt_id' => $prompt->id,
            'provider' => $provider->key(),
            'format' => $prompt->target_format,
            'status' => CreativeGeneration::STATUS_QUEUED,
            'created_by' => $request->user()->id,
        ]);

        try {
            $provider->submit($generation, $prompt->body);
        } catch (Throwable $e) {
            $generation->update([
                'status' => CreativeGeneration::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Génération impossible : '.$e->getMessage());
        }

        $generation->refresh();
        $this->syncCreativeStatus($creative->fresh());

        $this->history->log($creative, 'generation_started', 'Génération lancée sur '.$provider->label());

        return back()->with('success', $generation->status === CreativeGeneration::STATUS_AWAITING_MANUAL
            ? 'Prompt prêt pour '.$provider->label().' — générez puis rattachez le résultat.'
            : 'Génération lancée sur '.$provider->label().'.');
    }

    /** Poll the provider for an in-flight generation. */
    public function refresh(CreativeGeneration $generation): RedirectResponse
    {
        $provider = $this->registry->get($generation->provider);

        if (! $provider->capabilities()['polling']) {
            return back()->with('error', $provider->label().' ne permet pas de suivre la génération automatiquement.');
        }

        try {
            $provider->refresh($generation);
        } catch (Throwable $e) {
            $generation->update(['status' => CreativeGeneration::STATUS_FAILED, 'error' => $e->getMessage()]);
            $this->syncCreativeStatus($generation->creative);

            return back()->with('error', 'Impossible de récupérer le statut : '.$e->getMessage());
        }

        $generation = $generation->fresh();
        $this->syncCreativeStatus($generation->creative);

        if ($generation->status === CreativeGeneration::STATUS_FAILED) {
            $this->history->log($generation->creative, 'generation_failed', 'Génération #'.$generation->id.' en échec');

            return back()->with('error', 'La génération a échoué : '.$generation->error);
        }

        if ($generation->status === CreativeGeneration::STATUS_COMPLETED) {
            return back()->with('success', 'Génération terminée — vous pouvez rattacher l\'asset à la créa.');
        }

        return back()->with('success', 'Génération toujours en cours.');
    }

    /**
     * Record the asset produced outside the app (Flow handoff, or any other tool).
     */
    public function attach(Request $request, CreativeGeneration $generation): RedirectResponse
    {
        $data = $request->validate([
            'asset_url' => ['required', 'url', 'max:500'],
            'asset_reference' => ['nullable', 'string', 'max:255'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
        ]);

        $generation->update([
            ...$data,
            'status' => CreativeGeneration::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->history->log($generation->creative, 'generation_attached', 'Asset généré rattaché ('.$generation->provider.')');

        return back()->with('success', 'Asset rattaché à la génération.');
    }

    /**
     * Promote a completed generation to be the creative's asset.
     */
    public function use(CreativeGeneration $generation): RedirectResponse
    {
        if ($generation->status !== CreativeGeneration::STATUS_COMPLETED) {
            return back()->with('error', 'Cette génération n\'est pas terminée.');
        }

        $creative = $generation->creative;

        $creative->update([
            'asset_url' => $generation->asset_url,
            'asset_path' => $generation->meta['local_path'] ?? $creative->asset_path,
            'asset_mime' => $generation->asset_mime,
            'asset_source' => $generation->provider,
            'thumbnail_url' => $generation->thumbnail_url ?: $creative->thumbnail_url,
            'creative_generation_id' => $generation->id,
            'format' => $generation->format === 'video' ? 'video' : $creative->format,
        ]);

        $created = CreativeStatus::where('slug', 'created')->first();
        if ($created && in_array($creative->status?->slug, ['idea', 'brief', 'prompt_ready', 'generating'], true)) {
            $creative->update(['creative_status_id' => $created->id]);
        }

        $this->history->log($creative, 'asset_uploaded', 'Asset de la génération #'.$generation->id.' utilisé pour la créa');

        return back()->with('success', 'Asset rattaché à la créa.');
    }

    public function destroy(CreativeGeneration $generation): RedirectResponse
    {
        $creative = $generation->creative;

        // Never leave the creative pointing at an asset that no longer exists.
        if ($creative->creative_generation_id === $generation->id) {
            $creative->update([
                'creative_generation_id' => null,
                'asset_url' => null,
                'asset_path' => null,
                'asset_source' => null,
            ]);
        }

        $generation->delete();
        $this->syncCreativeStatus($creative->fresh());

        return back()->with('success', 'Génération supprimée.');
    }

    /**
     * Keep the creative's lifecycle honest about what is actually happening:
     * generating while something runs, prompt ready when nothing does.
     * States the admin drove past (created, ready, live…) are left alone.
     */
    private function syncCreativeStatus(Creative $creative): void
    {
        $slug = $creative->status?->slug;

        if (! in_array($slug, ['idea', 'brief', 'prompt_ready', 'generating'], true)) {
            return;
        }

        $pending = $creative->generations()
            ->whereIn('status', [CreativeGeneration::STATUS_QUEUED, CreativeGeneration::STATUS_GENERATING])
            ->exists();

        $target = $pending
            ? 'generating'
            : ($creative->prompts()->where('status', 'validated')->exists() ? 'prompt_ready' : $slug);

        if ($target !== $slug && $status = CreativeStatus::where('slug', $target)->first()) {
            $creative->update(['creative_status_id' => $status->id]);
        }
    }
}
