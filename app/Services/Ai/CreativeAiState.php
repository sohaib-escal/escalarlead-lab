<?php

namespace App\Services\Ai;

use App\Models\Creative;
use App\Models\CreativeGeneration;
use App\Models\CreativePrompt;

/**
 * Where a creative stands in the generation loop, and the single next action.
 *
 * The admin should never have to work this out from a list of rows: one state,
 * one sentence, one button.
 */
class CreativeAiState
{
    public const STEPS = ['idea', 'prompt', 'validated', 'generating', 'generated', 'attached'];

    /**
     * @return array<string, mixed>
     */
    public function for(Creative $creative): array
    {
        $creative->loadMissing(['prompts', 'generations']);

        $latestPrompt = $creative->prompts->first();
        $validated = $creative->prompts->firstWhere('status', CreativePrompt::STATUS_VALIDATED);
        $generations = $creative->generations;

        $pending = $generations->first(fn ($generation) => $generation->isPending());
        $manual = $generations->firstWhere('status', CreativeGeneration::STATUS_AWAITING_MANUAL);
        $completed = $generations->firstWhere('status', CreativeGeneration::STATUS_COMPLETED);
        $failed = $generations->first(fn ($generation) => $generation->status === CreativeGeneration::STATUS_FAILED);

        // Most advanced state first: the loop only ever moves forward.
        return match (true) {
            (bool) $creative->creative_generation_id => $this->state(
                'attached', 'Asset rattaché', 'emerald', 5,
                'L\'asset est en place. Complétez la copy, puis passez la créa en Prêt.',
                'Compléter l\'exécution',
            ),
            (bool) $completed => $this->state(
                'generated', 'Asset généré', 'teal', 4,
                'La génération est terminée. Vérifiez le rendu puis utilisez-le comme asset de la créa.',
                'Utiliser comme asset',
            ),
            (bool) $pending => $this->state(
                'generating', 'Génération en cours', 'amber', 3,
                'Le service fabrique le visuel. Actualisez pour récupérer le résultat.',
                'Actualiser le statut',
            ),
            (bool) $manual => $this->state(
                'generating', 'À générer dans Flow', 'sky', 3,
                'Le prompt validé est prêt. Générez-le dans Flow, puis rattachez le fichier obtenu.',
                'Rattacher le résultat',
            ),
            $failed && ! $validated => $this->state(
                'prompt', 'Génération en échec', 'rose', 1,
                'La dernière génération a échoué. Corrigez le prompt puis revalidez-le.',
                'Corriger le prompt',
            ),
            (bool) $failed => $this->state(
                'validated', 'Génération en échec', 'rose', 2,
                'La dernière génération a échoué : '.$failed->error.' Vous pouvez relancer.',
                'Relancer la génération',
            ),
            (bool) $validated => $this->state(
                'validated', 'Prompt validé', 'blue', 2,
                'Le prompt est validé. Choisissez un service et lancez la génération.',
                'Lancer la génération',
            ),
            (bool) $latestPrompt => $this->state(
                'prompt', 'Prompt à relire', 'violet', 1,
                'Un prompt a été généré. Relisez-le, ajustez-le, puis validez-le.',
                'Relire et valider',
            ),
            default => $this->state(
                'idea', 'Idée', 'slate', 0,
                'L\'idée est posée. Générez un prompt de création à partir de cette cible.',
                'Générer le prompt',
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function state(string $key, string $label, string $color, int $step, string $help, string $nextAction): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'color' => $color,
            'step' => $step,
            'help' => $help,
            'next_action' => $nextAction,
        ];
    }
}
