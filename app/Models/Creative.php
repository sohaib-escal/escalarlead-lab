<?php

namespace App\Models;

use App\Support\MetricsSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Creative extends Model
{
    protected $fillable = [
        'reference', 'name', 'description', 'product_id', 'creative_status_id',
        'landing_page_id', 'cta_option_id', 'format',
        'asset_url', 'asset_path', 'asset_filename', 'asset_mime',
        'thumbnail_url', 'thumbnail_path',
        'asset_source', 'creative_generation_id',
        'hook', 'primary_text', 'headline', 'ad_description', 'concept',
        'performance_override', 'version', 'notes',
        'created_by', 'updated_by', 'duplicated_from_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CreativeStatus::class, 'creative_status_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function cta(): BelongsTo
    {
        return $this->belongsTo(CtaOption::class, 'cta_option_id');
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(CreativeParameter::class);
    }

    public function parameterValues(): BelongsToMany
    {
        return $this->belongsToMany(ParameterValue::class, 'creative_parameters');
    }

    public function utm(): HasOne
    {
        return $this->hasOne(UtmConfiguration::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(CreativeMetric::class);
    }

    /**
     * Named `noteEntries` because `notes` is also a text column on the creative.
     */
    public function noteEntries(): HasMany
    {
        return $this->hasMany(CreativeNote::class)->latest();
    }

    public function history(): HasMany
    {
        return $this->hasMany(CreativeHistory::class)->latest();
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(CreativePrompt::class)->orderByDesc('version');
    }

    /** The prompt the admin validated and that generation runs from. */
    public function validatedPrompt(): HasOne
    {
        return $this->hasOne(CreativePrompt::class)
            ->where('status', CreativePrompt::STATUS_VALIDATED)
            ->latestOfMany('version');
    }

    public function latestPrompt(): HasOne
    {
        return $this->hasOne(CreativePrompt::class)->latestOfMany('version');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(CreativeGeneration::class)->latest('id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(CreativeGeneration::class, 'creative_generation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(Creative::class, 'duplicated_from_id');
    }

    public function summary(): MetricsSummary
    {
        if ($this->relationLoaded('metrics')) {
            return MetricsSummary::fromRows($this->metrics);
        }

        if ($this->getAttribute('metrics_sum_spend') !== null) {
            return MetricsSummary::fromAggregates($this);
        }

        return MetricsSummary::fromRows($this->metrics);
    }

    /**
     * Eager-load metric totals as aggregates so lists and trees stay on one query.
     */
    public function scopeWithMetricTotals(Builder $query): Builder
    {
        foreach (MetricsSummary::columns() as $column) {
            $query->withSum('metrics as metrics_sum_'.$column, $column);
        }

        return $query;
    }

    /**
     * Manual override wins over the computed rating.
     */
    public function rating(): string
    {
        return $this->performance_override ?: $this->summary()->rating();
    }

    /**
     * Filter creatives that carry every one of the given parameter value ids.
     *
     * @param  array<int, int|string>  $valueIds
     */
    public function scopeWithAllParameterValues(Builder $query, array $valueIds): Builder
    {
        foreach (array_filter($valueIds) as $valueId) {
            $query->whereHas('parameters', fn ($q) => $q->where('parameter_value_id', $valueId));
        }

        return $query;
    }

    /**
     * Filter creatives that carry at least one of the values of each given group.
     *
     * @param  array<int, array<int, int|string>>  $groups
     */
    public function scopeMatchingParameterGroups(Builder $query, array $groups): Builder
    {
        foreach ($groups as $valueIds) {
            $valueIds = array_values(array_filter($valueIds));

            if ($valueIds === []) {
                continue;
            }

            $query->whereHas('parameters', fn ($q) => $q->whereIn('parameter_value_id', $valueIds));
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('reference', 'ilike', $like)
                ->orWhere('name', 'ilike', $like)
                ->orWhere('hook', 'ilike', $like)
                ->orWhere('primary_text', 'ilike', $like)
                ->orWhere('headline', 'ilike', $like)
                ->orWhere('concept', 'ilike', $like)
                ->orWhere('notes', 'ilike', $like)
                ->orWhereHas('campaigns', fn ($c) => $c->where('name', 'ilike', $like))
                ->orWhereHas('landingPage', fn ($l) => $l->where('name', 'ilike', $like)->orWhere('url', 'ilike', $like))
                ->orWhereHas('utm', fn ($u) => $u->where('utm_campaign', 'ilike', $like)
                    ->orWhere('utm_content', 'ilike', $like)
                    ->orWhere('utm_source', 'ilike', $like)
                    ->orWhere('utm_term', 'ilike', $like))
                ->orWhereHas('parameterValues', fn ($p) => $p->where('label', 'ilike', $like));
        });
    }
}
