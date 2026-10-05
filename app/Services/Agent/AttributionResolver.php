<?php

namespace App\Services\Agent;

use App\Models\Creative;

/**
 * Which ad produced this conversation.
 *
 * Click-to-WhatsApp ads deliver a `referral` object on the first inbound
 * message. It carries no UTM parameters, so the reliable path is the gateway
 * telling us the creative reference outright; failing that we look for one in
 * the referral text. What cannot be resolved is left null and the raw payload
 * is kept — a guessed attribution is worse than none, because it silently
 * credits the wrong branch of the tree.
 */
class AttributionResolver
{
    /** Creative references look like PAC-W-60-69-HIGHBILL-AID-FB-001. */
    private const REFERENCE = '/\b[A-Z]{2,6}(?:-[A-Z0-9+]{1,12}){2,8}-\d{3}\b/';

    /**
     * @param  array<string, mixed>|null  $referral
     * @return array{creative_id: ?int, campaign_id: ?int, matched_on: ?string}
     */
    public function resolve(?string $explicitReference, ?array $referral): array
    {
        if (filled($explicitReference) && $creative = $this->byReference($explicitReference)) {
            return $this->from($creative, 'explicit');
        }

        foreach ($this->candidates($referral ?? []) as $candidate) {
            if ($creative = $this->byReference($candidate)) {
                return $this->from($creative, 'referral');
            }
        }

        return ['creative_id' => null, 'campaign_id' => null, 'matched_on' => null];
    }

    private function byReference(string $reference): ?Creative
    {
        return Creative::with('campaigns:id')
            ->whereRaw('upper(reference) = ?', [mb_strtoupper(trim($reference))])
            ->first();
    }

    /**
     * Every reference-shaped string anywhere in the referral payload.
     *
     * @param  array<string, mixed>  $referral
     * @return array<int, string>
     */
    private function candidates(array $referral): array
    {
        $haystack = [];

        array_walk_recursive($referral, function ($value) use (&$haystack) {
            if (is_string($value)) {
                $haystack[] = $value;
            }
        });

        $found = [];

        foreach ($haystack as $text) {
            if (preg_match_all(self::REFERENCE, mb_strtoupper(urldecode($text)), $matches)) {
                $found = [...$found, ...$matches[0]];
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return array{creative_id: ?int, campaign_id: ?int, matched_on: ?string}
     */
    private function from(Creative $creative, string $matchedOn): array
    {
        return [
            'creative_id' => $creative->id,
            'campaign_id' => $creative->campaigns->first()?->id,
            'matched_on' => $matchedOn,
        ];
    }
}
