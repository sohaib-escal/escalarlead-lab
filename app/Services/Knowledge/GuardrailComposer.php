<?php

namespace App\Services\Knowledge;

use App\Models\AgentGuardrail;

/**
 * Assembles the agent's standing instructions.
 *
 * This block is present on every single turn and is never retrieved. It sits
 * at the head of the system prompt so it also forms the stable, cacheable
 * prefix — the compliance rules and the cheapest tokens are the same bytes.
 */
class GuardrailComposer
{
    public function block(): string
    {
        $guardrails = AgentGuardrail::active()->orderBy('position')->orderBy('id')->get();

        if ($guardrails->isEmpty()) {
            return '';
        }

        $sections = $guardrails->groupBy('kind')->map(function ($group, $kind) {
            $heading = match ($kind) {
                'persona' => 'QUI VOUS ÊTES',
                'tone' => 'COMMENT VOUS PARLEZ',
                'rule' => 'RÈGLES',
                'never_claim' => 'À NE JAMAIS AFFIRMER',
                'disclosure' => 'TRANSPARENCE',
                default => mb_strtoupper($kind),
            };

            $lines = $group->map(fn (AgentGuardrail $rule) => '- '.trim($rule->body))->implode("\n");

            return $heading."\n".$lines;
        });

        // Order the sections, not just the rules inside them. `array_search`
        // returns 0 for the first entry, so the false check has to be explicit.
        $order = ['persona', 'tone', 'rule', 'never_claim', 'disclosure'];

        return $sections
            ->sortBy(function ($section, $kind) use ($order) {
                $index = array_search($kind, $order, true);

                return $index === false ? 99 : $index;
            })
            ->implode("\n\n");
    }

    /**
     * The never-claim list on its own, for assertions and for the admin.
     *
     * @return array<int, string>
     */
    public function neverClaims(): array
    {
        return AgentGuardrail::active()->where('kind', 'never_claim')
            ->orderBy('position')->pluck('body')->all();
    }
}
