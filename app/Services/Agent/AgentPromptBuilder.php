<?php

namespace App\Services\Agent;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Knowledge\GuardrailComposer;
use App\Services\Knowledge\RetrievalResult;

/**
 * Assembles the prompt for one turn.
 *
 * Order matters for cost as well as behaviour: the guardrails and the contract
 * are identical on every turn and sit first, so they form the stable prefix a
 * provider can cache. Everything that changes — state, knowledge, transcript —
 * comes after.
 */
class AgentPromptBuilder
{
    public function __construct(private readonly GuardrailComposer $guardrails) {}

    public function system(): string
    {
        return implode("\n\n", array_filter([
            $this->guardrails->block(),
            $this->task(),
            $this->contract(),
        ]));
    }

    private function task(): string
    {
        return <<<'TXT'
        VOTRE TÂCHE À CHAQUE MESSAGE
        1. Relever dans le message tout ce que la personne vient de vous apprendre.
        2. Écrire UNE réponse courte en français : une phrase qui accuse réception, puis UNE seule question.
        3. Ne poser la question que sur l'information demandée ci-dessous (« À OBTENIR MAINTENANT »).
        Si rien n'est demandé, ne posez plus de question : remerciez et annoncez qu'un conseiller va rappeler.

        LA PREMIÈRE PHRASE
        Elle doit montrer que vous avez lu ce que la personne vient d'écrire : reprenez son problème
        avec ses mots à elle (« des taches noires », « votre chaudière », « votre facture »).
        « Je comprends. » tout seul ne suffit pas quand la personne vient de décrire quelque chose de précis.

        NE REPOSEZ JAMAIS LA MÊME QUESTION
        Si votre question précédente est rappelée ci-dessous et que la personne n'y a pas répondu,
        n'insistez pas : passez à l'information demandée maintenant.
        TXT;
    }

    /**
     * One call does both jobs — extraction and composition — because they share
     * all of their context. Splitting them would double the cost of a turn to
     * re-send the same conversation twice.
     */
    private function contract(): string
    {
        return <<<'TXT'
        FORMAT DE RÉPONSE
        Répondez uniquement par un objet JSON valide, sans texte autour, sans balises de code :

        {
          "problem_family": "humidite|infiltration|isolation|chauffage|facture|fenetres|solaire|general|null",
          "extracted": {
            "first_name": null, "last_name": null, "postal_code": null, "city": null,
            "is_homeowner": null, "property_type": null, "property_age": null,
            "heating_system": null, "energy_source": null, "estimated_bill": null,
            "problem_duration": null, "previous_work": null, "availability": null
          },
          "taxonomy": [{"category": "specific-problem", "value_slug": "condensation"}],
          "reply": "votre réponse en français",
          "image_observations": null,
          "handoff_requested": false,
          "opted_out": false
        }

        Règles de remplissage :
        - "extracted" : uniquement ce que la personne a dit, explicitement ou clairement. Jamais de supposition. Laissez null sinon.
        - "property_type" : "maison" ou "appartement". "is_homeowner" : true, false ou null.
        - "taxonomy" : laissez une liste vide si vous n'êtes pas sûr. N'inventez jamais un value_slug.
        - "handoff_requested" : true si la personne demande à parler à quelqu'un ou à être rappelée.
        - "opted_out" : true si la personne demande à ne plus être contactée.
        - "image_observations" : si une photo est jointe, décrivez en une phrase factuelle ce qui est visible. Sinon null.
        - "reply" : 240 caractères maximum (420 si une photo est jointe), une seule question, jamais deux.
        TXT;
    }

    /**
     * The homeowner sent a photo. This is the highest-risk moment in the whole
     * conversation: a picture of black stains invites a confident diagnosis,
     * and a confident diagnosis is exactly what must never happen.
     */
    public function photoInstructions(int $count): string
    {
        $plural = $count > 1 ? 's' : '';

        return <<<TXT
        LA PERSONNE A ENVOYÉ {$count} PHOTO{$plural}
        Votre réponse doit, dans cet ordre et en restant courte :
        1. Dire ce que vous voyez, factuellement et sobrement (« je vois des taches sombres au bas du mur »).
        2. Donner DEUX causes possibles au minimum, toujours au pluriel, jamais une seule, jamais affirmées.
        3. Rappeler en quelques mots qu'une photo ne permet pas de conclure, seul un professionnel sur place le peut.
        4. Poser UNE question, celle demandée ci-dessous.

        Interdits absolus sur une photo :
        - affirmer la cause (« c'est de la condensation », « c'est une infiltration »)
        - parler de santé, de toxicité ou de danger
        - estimer un coût, une surface, une gravité ou une urgence
        - prétendre reconnaître un matériau, une marque ou un modèle d'équipement
        Si la photo est floue, sombre ou illisible, dites-le simplement et demandez-en une autre.
        TXT;
    }

    /**
     * @param  array<int, string>  $taxonomyVocabulary
     */
    public function user(
        Conversation $conversation,
        LeadState $state,
        RetrievalResult $knowledge,
        string $inbound,
        array $taxonomyVocabulary,
        int $imageCount = 0,
    ): string {
        $sections = [];

        if ($imageCount > 0) {
            $sections[] = $this->photoInstructions($imageCount);
        }

        if ($conversation->summary) {
            $sections[] = "RÉSUMÉ DE LA CONVERSATION JUSQU'ICI\n".$conversation->summary;
        }

        $known = $state->known();
        $sections[] = $known === []
            ? "CE QUE VOUS SAVEZ DÉJÀ\nRien encore."
            : "CE QUE VOUS SAVEZ DÉJÀ (ne le redemandez jamais)\n".$this->asLines($known);

        if ($previous = $state->lastQuestion()) {
            $sections[] = "VOTRE MESSAGE PRÉCÉDENT (ne le répétez pas)\n".$previous;
        }

        $target = $state->nextTarget();
        $sections[] = $target
            ? 'À OBTENIR MAINTENANT : '.$this->label($target)
            : "À OBTENIR MAINTENANT : rien. Vous avez l'essentiel — concluez.";

        if ($taxonomyVocabulary !== []) {
            $sections[] = "VALUE_SLUGS AUTORISÉS (aucun autre n'est accepté)\n".implode(', ', $taxonomyVocabulary);
        }

        if (! $knowledge->isEmpty()) {
            $sections[] = $knowledge->toPromptBlock();
        }

        $transcript = $this->transcript($conversation);
        if ($transcript !== '') {
            $sections[] = "DERNIERS ÉCHANGES\n".$transcript;
        }

        $sections[] = $imageCount > 0 && blank($inbound)
            ? 'NOUVEAU MESSAGE DU PROPRIÉTAIRE : une photo, sans texte.'
            : "NOUVEAU MESSAGE DU PROPRIÉTAIRE\n".$inbound;

        return implode("\n\n", $sections);
    }

    private function transcript(Conversation $conversation): string
    {
        return $conversation->recentMessages(config('agent.history_turns'))
            ->map(fn (ConversationMessage $message) => ($message->isInbound() ? 'Propriétaire' : 'Vous').' : '.$message->body)
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function asLines(array $values): string
    {
        return collect($values)
            ->map(fn ($value, $key) => '- '.$this->label($key).' : '.(is_bool($value) ? ($value ? 'oui' : 'non') : $value))
            ->implode("\n");
    }

    private function label(string $field): string
    {
        return [
            'first_name' => 'prénom',
            'last_name' => 'nom de famille',
            'postal_code' => 'code postal',
            'city' => 'ville',
            'problem' => 'de quoi il s\'agit exactement — posez une question sur le problème lui-même '
                .'(où, depuis quand, ce que la personne observe), pas sur son identité',
            'is_homeowner' => 'propriétaire ou locataire',
            'property_type' => 'maison ou appartement',
            'property_age' => 'âge du logement',
            'heating_system' => 'système de chauffage',
            'energy_source' => 'énergie utilisée',
            'estimated_bill' => 'montant approximatif des factures',
            'problem_duration' => 'depuis quand dure le problème',
            'previous_work' => 'travaux déjà réalisés',
            'availability' => 'disponibilité pour être rappelé',
        ][$field] ?? $field;
    }
}
