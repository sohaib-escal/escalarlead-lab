<?php

namespace App\Services\Agent;

use App\Models\Lead;

/**
 * The fifteen-second brief a salesperson reads instead of the conversation.
 *
 * Written in code rather than by the model: it must be consistent, it must
 * never invent, and it costs nothing.
 */
class LeadSummariser
{
    public function summarise(Lead $lead): string
    {
        $lead->loadMissing('parameters.value', 'parameters.category', 'conversation.creative', 'product');

        $lines = [];

        $identity = array_filter([
            $lead->fullName(),
            $lead->postal_code ? trim($lead->postal_code.' '.($lead->city ?? '')) : $lead->city,
            $lead->phone_e164,
        ]);

        $lines[] = implode(' · ', $identity) ?: 'Coordonnées incomplètes';

        $details = $lead->details ?? [];

        $property = array_filter([
            isset($details['is_homeowner'])
                ? ($details['is_homeowner'] ? 'propriétaire' : 'locataire')
                : null,
            $details['property_type'] ?? null,
            isset($details['property_age']) ? 'logement : '.$details['property_age'] : null,
        ]);

        if ($property) {
            $lines[] = ucfirst(implode(' · ', $property));
        }

        if ($problems = $this->parameterLabels($lead, ['problem', 'specific-problem', 'symptom', 'trigger'])) {
            $problem = 'Problème : '.implode(', ', $problems);

            if (filled($details['problem_duration'] ?? null)) {
                // The model often answers with the preposition already attached.
                $duration = preg_replace('/^(depuis|il y a)\s+/iu', '', trim($details['problem_duration']));
                $problem .= ' (depuis '.$duration.')';
            }

            $lines[] = $problem;
        }

        $energy = array_filter([
            $details['heating_system'] ?? null,
            $details['energy_source'] ?? null,
            isset($details['estimated_bill']) ? 'facture ~'.$details['estimated_bill'] : null,
        ]);

        if ($energy) {
            $lines[] = 'Énergie : '.implode(', ', $energy);
        }

        if (filled($details['availability'] ?? null)) {
            $lines[] = 'Disponibilité : '.$details['availability'];
        }

        $lines[] = $lead->qualification_status === 'qualified' || $lead->qualification_status === 'appointment_requested'
            ? 'Qualifié : '.$this->whyQualified($lead)
            : 'Incomplet : manque '.implode(', ', $lead->missing_required_fields ?? ['—']);

        if ($creative = $lead->conversation?->creative) {
            $lines[] = 'Source : '.$lead->conversation->channel.' · créa '.$creative->reference;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $categorySlugs
     * @return array<int, string>
     */
    private function parameterLabels(Lead $lead, array $categorySlugs): array
    {
        return $lead->parameters
            ->filter(fn ($p) => in_array($p->category?->slug, $categorySlugs, true))
            ->map(fn ($p) => $p->value?->label)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function whyQualified(Lead $lead): string
    {
        $reasons = array_filter([
            $lead->fullName() ? 'nom' : null,
            $lead->postal_code ? 'code postal' : null,
            $lead->phone_e164 ? 'téléphone' : null,
            $lead->parameters->isNotEmpty() ? 'problème identifié' : null,
        ]);

        return implode(', ', $reasons);
    }
}
