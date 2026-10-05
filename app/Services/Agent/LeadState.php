<?php

namespace App\Services\Agent;

use App\Models\Lead;

/**
 * What we know, what we still need, and what to ask next.
 *
 * Deliberately deterministic: the model proposes a reply, but whether a lead is
 * complete — and therefore whether a human should be called in — is a business
 * rule, not a judgement call.
 */
class LeadState
{
    public function __construct(private readonly Lead $lead) {}

    /**
     * @return array<string, mixed>
     */
    public function known(): array
    {
        $details = $this->lead->details ?? [];

        return array_filter([
            'first_name' => $this->lead->first_name,
            'last_name' => $this->lead->last_name,
            'postal_code' => $this->lead->postal_code,
            'city' => $this->lead->city,
            'problem' => $this->problemLabel(),
            ...$details,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function problemLabel(): ?string
    {
        $this->lead->loadMissing('parameters.value', 'parameters.category');

        $problem = $this->lead->parameters
            ->first(fn ($p) => in_array($p->category?->slug, ['specific-problem', 'problem'], true));

        return $problem?->value?->label ?? $this->lead->conversation?->problem_family;
    }

    /**
     * @return array<int, string>
     */
    public function missingRequired(): array
    {
        $known = $this->known();

        return array_values(array_filter(
            config('agent.required_fields'),
            fn (string $field) => blank($known[$field] ?? null),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function missingEnrichment(): array
    {
        $known = $this->known();

        return array_values(array_filter(
            config('agent.enrichment_fields'),
            fn (string $field) => blank($known[$field] ?? null),
        ));
    }

    /**
     * The question the agent asked last turn, so it does not ask it again when
     * the homeowner answers something else.
     */
    public function lastQuestion(): ?string
    {
        $last = $this->lead->conversation?->messages()
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();

        return $last?->body;
    }

    public function isComplete(): bool
    {
        return $this->missingRequired() === [];
    }

    /**
     * The single thing the next question should be about.
     *
     * Order is the conversation design, not a technicality:
     *   1. the problem — nothing else matters until we understand it
     *   2. a little context about the home, which also builds trust
     *   3. identity, now that the person feels heard
     *   4. enrichment, until it stops being worth the friction
     */
    public function nextTarget(): ?string
    {
        $known = $this->known();

        if (blank($known['problem'] ?? null)) {
            return 'problem';
        }

        $context = $this->missingFrom(config('agent.context_fields'));
        $gathered = count(config('agent.context_fields')) - count($context);

        if ($context !== [] && $gathered < config('agent.context_before_identity')) {
            return $context[0];
        }

        if ($identity = $this->missingFrom(config('agent.identity_fields'))) {
            return $identity[0];
        }

        if ($context !== []) {
            return $context[0];
        }

        $enrichment = $this->missingEnrichment();
        $collected = count(config('agent.enrichment_fields')) - count($enrichment);

        if ($collected >= config('agent.max_enrichment_after_qualified')) {
            return null;
        }

        return $enrichment[0] ?? null;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    private function missingFrom(array $fields): array
    {
        $known = $this->known();

        return array_values(array_filter($fields, fn (string $field) => blank($known[$field] ?? null)));
    }
}
