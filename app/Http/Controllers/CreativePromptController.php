<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Creative;
use App\Models\CreativePrompt;
use App\Models\CreativeStatus;
use App\Models\PromptTemplate;
use App\Services\Ai\PromptGenerator;
use App\Services\HistoryLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CreativePromptController extends Controller
{
    public function __construct(
        private readonly PromptGenerator $generator,
        private readonly HistoryLogger $history,
    ) {}

    public function store(Request $request, Creative $creative): RedirectResponse
    {
        $data = $request->validate([
            'ai_model_id' => ['required', 'exists:ai_models,id'],
            'prompt_template_id' => ['nullable', 'exists:prompt_templates,id'],
            'target_format' => ['required', 'in:video,image'],
        ]);

        $model = AiModel::findOrFail($data['ai_model_id']);
        $template = isset($data['prompt_template_id']) ? PromptTemplate::find($data['prompt_template_id']) : null;

        try {
            $prompt = $this->generator->generate($creative, $model, $template, $data['target_format']);
        } catch (Throwable $e) {
            return back()->with('error', 'Génération du prompt impossible : '.$e->getMessage());
        }

        $this->history->log($creative, 'prompt_generated', 'Prompt v'.$prompt->version.' généré ('.$model->name.')');

        return back()->with('success', 'Prompt généré — relisez-le avant de le valider.');
    }

    public function update(Request $request, CreativePrompt $prompt): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string']]);

        // Editing a validated prompt sends it back for review.
        $prompt->update([
            'body' => $data['body'],
            'status' => CreativePrompt::STATUS_DRAFT,
            'validated_at' => null,
        ]);

        return back()->with('success', 'Prompt mis à jour.');
    }

    public function validatePrompt(CreativePrompt $prompt): RedirectResponse
    {
        $prompt->update([
            'status' => CreativePrompt::STATUS_VALIDATED,
            'validated_at' => now(),
        ]);

        $creative = $prompt->creative;
        $this->history->log($creative, 'prompt_validated', 'Prompt v'.$prompt->version.' validé');

        // Move the creative forward unless the admin already pushed it further.
        $promptReady = CreativeStatus::where('slug', 'prompt_ready')->first();
        if ($promptReady && in_array($creative->status?->slug, ['idea', 'brief'], true)) {
            $creative->update(['creative_status_id' => $promptReady->id]);
        }

        return back()->with('success', 'Prompt validé — vous pouvez lancer la génération.');
    }

    public function destroy(CreativePrompt $prompt): RedirectResponse
    {
        if ($prompt->generations()->exists()) {
            return back()->with('error', 'Ce prompt a servi à générer un asset : il est conservé pour la traçabilité.');
        }

        $prompt->delete();

        return back()->with('success', 'Prompt supprimé.');
    }
}
