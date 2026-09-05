<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\CtaOption;
use App\Models\LandingPage;
use App\Models\ParameterCategory;
use App\Models\Product;
use App\Models\PromptTemplate;
use App\Services\Generation\GenerationProviderRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared filter handling + the option lists every screen needs.
 */
class CreativeFilters
{
    /**
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $params = collect($request->input('params', []))
            ->map(fn ($values) => array_values(array_filter((array) $values)))
            ->filter(fn ($values) => $values !== [])
            ->all();

        return [
            'search' => $request->string('search')->toString() ?: null,
            'product' => $request->filled('product') ? (int) $request->input('product') : null,
            'channel' => $request->filled('channel') ? (int) $request->input('channel') : null,
            'campaign' => $request->filled('campaign') ? (int) $request->input('campaign') : null,
            'status' => $request->filled('status') ? (int) $request->input('status') : null,
            'rating' => $request->string('rating')->toString() ?: null,
            'landing_page' => $request->filled('landing_page') ? (int) $request->input('landing_page') : null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'params' => $params,
            'sort' => $request->string('sort')->toString() ?: 'updated_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->search($filters['search'] ?? null);

        if ($filters['product'] ?? null) {
            $query->where('product_id', $filters['product']);
        }

        if ($filters['status'] ?? null) {
            $query->where('creative_status_id', $filters['status']);
        }

        if ($filters['landing_page'] ?? null) {
            $query->where('landing_page_id', $filters['landing_page']);
        }

        if ($filters['channel'] ?? null) {
            $query->whereHas('channels', fn ($q) => $q->where('channels.id', $filters['channel']));
        }

        if ($filters['campaign'] ?? null) {
            $query->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $filters['campaign']));
        }

        if ($filters['date_from'] ?? null) {
            $query->whereDate('creatives.created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] ?? null) {
            $query->whereDate('creatives.created_at', '<=', $filters['date_to']);
        }

        foreach ($filters['params'] ?? [] as $valueIds) {
            $query->whereHas('parameters', fn ($q) => $q->whereIn('parameter_value_id', $valueIds));
        }

        return $query;
    }

    /**
     * Rating is derived from metrics, so it is filtered after the query.
     *
     * @param  Collection<int, Creative>  $creatives
     * @param  array<string, mixed>  $filters
     */
    public function applyRating($creatives, array $filters)
    {
        if (! ($filters['rating'] ?? null)) {
            return $creatives;
        }

        return $creatives->filter(
            fn ($creative) => ($creative->performance_override ?: $creative->summary()->rating()) === $filters['rating']
        )->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'products' => Product::active()->orderBy('position')->get(['id', 'name', 'code', 'color']),
            'channels' => Channel::active()->orderBy('position')->get(['id', 'name', 'code', 'default_utm_source', 'default_utm_medium']),
            'statuses' => CreativeStatus::where('is_active', true)->orderBy('position')
                ->get(['id', 'name', 'slug', 'color', 'counts_as_live', 'is_archived_state']),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'status', 'product_id']),
            'landing_pages' => LandingPage::where('is_active', true)->orderBy('name')->get(['id', 'name', 'url', 'product_id', 'version']),
            'ctas' => CtaOption::where('is_active', true)->orderBy('position')->get(['id', 'label']),
            'categories' => ParameterCategory::active()->ordered()->with('activeValues:id,parameter_category_id,label,code,product_id')->get()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'group' => $category->group,
                    'is_multi' => $category->is_multi,
                    'in_tree' => $category->in_tree,
                    'in_naming' => $category->in_naming,
                    'values' => $category->activeValues->map(fn ($v) => [
                        'id' => $v->id, 'label' => $v->label, 'code' => $v->code, 'product_id' => $v->product_id,
                    ])->values(),
                ])->values(),
            'ai_models' => AiModel::active()->orderByDesc('is_default')->orderBy('position')
                ->get(['id', 'name', 'provider', 'model_id', 'is_default']),
            'prompt_templates' => PromptTemplate::active()->orderByDesc('is_default')->orderBy('position')
                ->get(['id', 'name', 'target_format', 'is_default']),
            'generation_providers' => app(GenerationProviderRegistry::class)->status(),
            'formats' => collect(config('creative.formats'))->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'ratings' => collect(config('creative.ratings'))->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
        ];
    }
}
