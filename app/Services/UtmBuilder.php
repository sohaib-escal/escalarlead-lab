<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Creative;
use Illuminate\Support\Str;

/**
 * Suggests consistent UTM values from the creative + campaign data.
 * The media buyer can always override any of them.
 */
class UtmBuilder
{
    /**
     * @return array<string, string|null>
     */
    public function defaults(Creative $creative, ?Campaign $campaign = null): array
    {
        $creative->loadMissing(['channels', 'campaigns', 'landingPage', 'product']);

        $channel = $creative->channels->sortBy('position')->first();
        $campaign ??= $creative->campaigns->first();

        return [
            'base_url' => $creative->landingPage?->url,
            'utm_source' => $channel?->default_utm_source ?? ($channel ? Str::slug($channel->name, '_') : null),
            'utm_medium' => $channel?->default_utm_medium ?? 'paid_social',
            'utm_campaign' => $campaign
                ? ($campaign->code ?: Str::slug($campaign->name, '_'))
                : ($creative->product ? Str::slug($creative->product->name, '_').'_france' : null),
            'utm_content' => Str::lower($creative->reference),
            'utm_term' => null,
        ];
    }

    /**
     * Refresh only the values the buyer left empty, keeping manual overrides.
     */
    public function syncCreative(Creative $creative): void
    {
        $defaults = $this->defaults($creative);
        $utm = $creative->utm;

        if (! $utm) {
            $creative->utm()->create($defaults);

            return;
        }

        if (! $utm->auto_sync) {
            return;
        }

        $utm->fill(array_filter([
            'base_url' => $utm->base_url ?: $defaults['base_url'],
            'utm_source' => $utm->utm_source ?: $defaults['utm_source'],
            'utm_medium' => $utm->utm_medium ?: $defaults['utm_medium'],
            'utm_campaign' => $utm->utm_campaign ?: $defaults['utm_campaign'],
            'utm_content' => $defaults['utm_content'],
        ], fn ($v) => $v !== null))->save();
    }
}
