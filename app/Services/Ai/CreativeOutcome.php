<?php

namespace App\Services\Ai;

use App\Models\Creative;
use Illuminate\Support\Collection;

/**
 * The human-readable summary of a creative idea: who we talk to, what problem we
 * name, what we want them to feel and do. The admin reads this before any prompt
 * is generated, and the AI is briefed from exactly the same structure.
 */
class CreativeOutcome
{
    /**
     * @return array<string, mixed>
     */
    public function for(Creative $creative): array
    {
        $creative->loadMissing(['product', 'channels', 'cta', 'landingPage', 'parameters.category', 'parameters.value']);

        $byCategory = $creative->parameters
            ->filter(fn ($p) => $p->category && $p->value)
            ->groupBy(fn ($p) => $p->category->slug)
            ->map(fn (Collection $rows) => $rows->map(fn ($p) => $p->value->label)->values()->all());

        $byGroup = $creative->parameters
            ->filter(fn ($p) => $p->category && $p->value)
            ->groupBy(fn ($p) => $p->category->group);

        $pick = fn (string $slug) => $byCategory->get($slug, []);

        // "Who" is every persona/property/financial trait except the problem block.
        $who = collect(['persona', 'property', 'financial', 'energy'])
            ->flatMap(fn ($group) => ($byGroup->get($group) ?? collect())
                ->reject(fn ($p) => in_array($p->category->slug, ['trigger', 'motivation', 'objection'], true))
                ->map(fn ($p) => $p->value->label))
            ->values()
            ->all();

        $problem = array_values(array_filter([
            ...$pick('problem'),
            ...$pick('specific-problem'),
            ...$pick('symptom'),
            ...$pick('heating-system'),
        ]));

        return [
            'who' => $who,
            'problem' => $problem,
            'trigger' => $pick('trigger'),
            'motivation' => $pick('motivation'),
            'objection' => $pick('objection'),
            'angle' => $pick('motivation') ?: $pick('awareness'),
            'product' => $creative->product?->name,
            'channel' => $creative->channels->pluck('name')->all(),
            'format' => config('creative.formats.'.$creative->format, $creative->format),
            'desired_response' => $creative->cta?->label,
            'landing_page' => $creative->landingPage?->name,
            'hook' => $creative->hook,
        ];
    }

    /**
     * Flatten the outcome into the text block that goes into the AI brief.
     */
    public function toBrief(array $outcome): string
    {
        $section = function (string $label, $value): ?string {
            $value = is_array($value) ? implode(', ', array_filter($value)) : $value;

            return filled($value) ? $label.' : '.$value : null;
        };

        return collect([
            $section('QUI', $outcome['who']),
            $section('PROBLÈME', $outcome['problem']),
            $section('DÉCLENCHEUR', $outcome['trigger']),
            $section('MOTIVATION', $outcome['motivation']),
            $section('OBJECTION', $outcome['objection']),
            $section('ANGLE', $outcome['angle']),
            $section('PRODUIT', $outcome['product']),
            $section('CANAL', $outcome['channel']),
            $section('FORMAT', $outcome['format']),
            $section('RÉPONSE ATTENDUE', $outcome['desired_response']),
            $section('HOOK EXISTANT', $outcome['hook']),
        ])->filter()->implode("\n");
    }
}
