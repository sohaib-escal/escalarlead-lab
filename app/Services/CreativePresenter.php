<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Creative;
use App\Services\Ai\CreativeAiState;
use App\Services\Ai\CreativeOutcome;
use App\Support\MetricsSummary;

class CreativePresenter
{
    /**
     * Compact shape used by tables, cards and pickers.
     *
     * @return array<string, mixed>
     */
    public function card(Creative $creative): array
    {
        $summary = $creative->summary();

        return [
            'id' => $creative->id,
            'reference' => $creative->reference,
            'name' => $creative->name,
            'format' => $creative->format,
            'format_label' => config('creative.formats.'.$creative->format, $creative->format),
            'product' => $creative->product ? [
                'id' => $creative->product->id,
                'name' => $creative->product->name,
                'code' => $creative->product->code,
                'color' => $creative->product->color,
            ] : null,
            'status' => $creative->status ? [
                'id' => $creative->status->id,
                'name' => $creative->status->name,
                'slug' => $creative->status->slug,
                'color' => $creative->status->color,
            ] : null,
            'channels' => $creative->channels->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
            ])->values()->all(),
            'campaigns' => $creative->relationLoaded('campaigns')
                ? $creative->campaigns->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all()
                : [],
            'hook' => $creative->hook,
            'rating' => $creative->performance_override ?: $summary->rating(),
            'rating_is_manual' => (bool) $creative->performance_override,
            'has_performance' => $summary->spend > 0 || $summary->leads > 0,
            'metrics' => $summary->toArray(),
            'updated_at' => $creative->updated_at?->toDateString(),
            'persona' => $creative->relationLoaded('parameters')
                ? $this->personaChips($creative)
                : [],
        ];
    }

    /**
     * @return array<int, array{category:string, label:string}>
     */
    public function personaChips(Creative $creative): array
    {
        return $creative->parameters
            ->filter(fn ($p) => $p->relationLoaded('value') ? $p->value : true)
            ->map(fn ($p) => [
                'category' => $p->category?->name ?? '',
                'category_slug' => $p->category?->slug ?? '',
                'group' => $p->category?->group ?? 'persona',
                'label' => $p->value?->label ?? '',
                'code' => $p->value?->code,
                'value_id' => $p->parameter_value_id,
            ])
            ->filter(fn ($chip) => $chip['label'] !== '')
            ->sortBy(fn ($chip) => $chip['category'])
            ->values()
            ->all();
    }

    /**
     * Full creative record for the detail page.
     *
     * @return array<string, mixed>
     */
    public function detail(Creative $creative): array
    {
        $creative->loadMissing([
            'product', 'status', 'channels', 'campaigns.product', 'landingPage.type', 'cta',
            'parameters.category', 'parameters.value', 'utm', 'metrics.campaign', 'metrics.channel',
            'noteEntries.user', 'history.user', 'author', 'editor', 'duplicatedFrom',
            'prompts.model', 'prompts.template', 'generations.prompt', 'generation.prompt.model',
        ]);

        $summary = $creative->summary();
        $utm = $creative->utm;

        return [
            ...$this->card($creative),
            'description' => $creative->description,
            'primary_text' => $creative->primary_text,
            'headline' => $creative->headline,
            'ad_description' => $creative->ad_description,
            'concept' => $creative->concept,
            'notes' => $creative->notes,
            'version' => $creative->version,
            'asset_url' => $creative->asset_url,
            'asset_filename' => $creative->asset_filename,
            'asset_public_url' => $creative->asset_path ? asset('storage/'.$creative->asset_path) : null,
            'thumbnail_url' => $creative->thumbnail_url,
            'cta' => $creative->cta ? ['id' => $creative->cta->id, 'label' => $creative->cta->label] : null,
            'landing_page' => $creative->landingPage ? [
                'id' => $creative->landingPage->id,
                'name' => $creative->landingPage->name,
                'url' => $creative->landingPage->url,
                'version' => $creative->landingPage->version,
                'notes' => $creative->landingPage->notes,
                'type' => $creative->landingPage->type?->name,
            ] : null,
            'utm' => [
                'base_url' => $utm?->base_url,
                'utm_source' => $utm?->utm_source,
                'utm_medium' => $utm?->utm_medium,
                'utm_campaign' => $utm?->utm_campaign,
                'utm_content' => $utm?->utm_content,
                'utm_term' => $utm?->utm_term,
                'auto_sync' => $utm?->auto_sync ?? true,
                'final_url' => $utm?->finalUrl($creative->landingPage?->url),
            ],
            'metric_rows' => $creative->metrics->sortByDesc('period_start')->map(fn ($m) => [
                'id' => $m->id,
                'period_start' => $m->period_start?->toDateString(),
                'period_end' => $m->period_end?->toDateString(),
                'campaign' => $m->campaign?->name,
                'channel' => $m->channel?->name,
                'spend' => (float) $m->spend,
                'impressions' => $m->impressions,
                'reach' => $m->reach,
                'clicks' => $m->clicks,
                'leads' => $m->leads,
                'qualified_leads' => $m->qualified_leads,
                'contacted' => $m->contacted,
                'phone_qualified' => $m->phone_qualified,
                'appointments' => $m->appointments,
                'confirmed' => $m->confirmed,
                'sales' => $m->sales,
                'revenue' => (float) $m->revenue,
                'notes' => $m->notes,
            ])->values()->all(),
            'totals' => $summary->toArray(),
            'note_entries' => $creative->noteEntries->map(fn ($n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->user?->name,
                'created_at' => $n->created_at?->toDateTimeString(),
            ])->values()->all(),
            'history' => $creative->history->map(fn ($h) => [
                'id' => $h->id,
                'event' => $h->event,
                'description' => $h->description,
                'author' => $h->user?->name,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values()->all(),
            'created_by' => $creative->author?->name,
            'updated_by' => $creative->editor?->name,
            'created_at' => $creative->created_at?->toDateTimeString(),
            'duplicated_from' => $creative->duplicatedFrom ? [
                'id' => $creative->duplicatedFrom->id,
                'reference' => $creative->duplicatedFrom->reference,
            ] : null,
            'outcome' => app(CreativeOutcome::class)->for($creative),
            'ai_state' => app(CreativeAiState::class)->for($creative),
            'asset_provenance' => $this->provenance($creative),
            'prompts' => $creative->prompts->map(fn ($prompt) => [
                'id' => $prompt->id,
                'version' => $prompt->version,
                'body' => $prompt->body,
                'status' => $prompt->status,
                'target_format' => $prompt->target_format,
                'model' => $prompt->model?->name,
                'provider' => $prompt->model?->provider,
                'template' => $prompt->template?->name,
                'validated_at' => $prompt->validated_at?->toDateTimeString(),
                'created_at' => $prompt->created_at?->toDateTimeString(),
                'meta' => $prompt->meta,
            ])->values()->all(),
            'generations' => $creative->generations->map(fn ($generation) => [
                'id' => $generation->id,
                'provider' => $generation->provider,
                'model' => $generation->model,
                'format' => $generation->format,
                'status' => $generation->status,
                'external_id' => $generation->external_id,
                'asset_url' => $generation->asset_url,
                'local_url' => ($generation->meta['local_path'] ?? null)
                    ? asset('storage/'.$generation->meta['local_path'])
                    : null,
                'handoff_url' => $generation->meta['handoff_url'] ?? null,
                'thumbnail_url' => $generation->thumbnail_url,
                'error' => $generation->error,
                'prompt_version' => $generation->prompt?->version,
                'created_at' => $generation->created_at?->toDateTimeString(),
                'completed_at' => $generation->completed_at?->toDateTimeString(),
                'is_current' => $creative->creative_generation_id === $generation->id,
            ])->values()->all(),
            'asset_source' => $creative->asset_source,
            'parameter_selection' => $creative->parameters
                ->groupBy('parameter_category_id')
                ->map(fn ($rows) => $rows->pluck('parameter_value_id')->values()->all())
                ->all(),
        ];
    }

    /**
     * Where the current asset came from — never an orphaned file.
     *
     * @return array<string, mixed>|null
     */
    private function provenance(Creative $creative): ?array
    {
        $generation = $creative->generation;

        if (! $generation) {
            return $creative->asset_url || $creative->asset_path
                ? ['source' => $creative->asset_source ?? 'manual', 'label' => 'Ajouté à la main']
                : null;
        }

        $generation->loadMissing('prompt.model');

        return [
            'source' => $generation->provider,
            'label' => $generation->provider === 'google_veo' ? 'Google Veo (Gemini API)' : 'Google Flow',
            'generation_id' => $generation->id,
            'model' => $generation->model,
            'prompt_version' => $generation->prompt?->version,
            'prompt_model' => $generation->prompt?->model?->name,
            'generated_at' => $generation->completed_at?->toDateTimeString(),
            'external_id' => $generation->external_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaign(Campaign $campaign): array
    {
        $summary = MetricsSummary::fromRows($campaign->metrics);

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'code' => $campaign->code,
            'product' => $campaign->product ? [
                'id' => $campaign->product->id, 'name' => $campaign->product->name, 'code' => $campaign->product->code,
            ] : null,
            'country' => $campaign->country,
            'objective' => $campaign->objective,
            'start_date' => $campaign->start_date?->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'budget' => $campaign->budget !== null ? (float) $campaign->budget : null,
            'status' => $campaign->status,
            'notes' => $campaign->notes,
            'channels' => $campaign->channels->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
            ])->values()->all(),
            'creatives_count' => $campaign->creatives_count ?? $campaign->creatives->count(),
            'metrics' => $summary->toArray(),
        ];
    }
}
