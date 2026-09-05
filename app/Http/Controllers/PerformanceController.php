<?php

namespace App\Http\Controllers;

use App\Models\Creative;
use App\Models\CreativeMetric;
use App\Services\CreativeFilters;
use App\Services\CreativePresenter;
use App\Services\HistoryLogger;
use App\Support\MetricsSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function __construct(
        private readonly CreativeFilters $filters,
        private readonly CreativePresenter $presenter,
        private readonly HistoryLogger $history,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters->fromRequest($request);

        $query = Creative::query()
            ->with(['product:id,name,code,color', 'status:id,name,slug,color', 'channels:id,name,code', 'campaigns:id,name'])
            ->withMetricTotals();

        $this->filters->apply($query, $filters);

        $creatives = $this->filters->applyRating($query->get(), $filters)
            ->filter(fn ($c) => $c->metrics_sum_spend > 0 || $c->metrics_sum_leads > 0)
            ->sortByDesc(fn ($c) => $c->metrics_sum_spend)
            ->values();

        $totals = MetricsSummary::fromRows(
            CreativeMetric::whereIn('creative_id', $creatives->pluck('id'))->get()
        );

        // Which persona segments are actually working, ranked by cost per qualified lead.
        $byProduct = $creatives->groupBy(fn ($c) => $c->product?->name ?? 'Sans produit')
            ->map(function ($group, $name) {
                $summary = $group->reduce(fn (?MetricsSummary $carry, $c) => $carry ? $carry->plus($c->summary()) : $c->summary());

                return ['label' => $name, 'creatives' => $group->count(), 'metrics' => $summary?->toArray()];
            })->sortByDesc(fn ($row) => $row['metrics']['spend'] ?? 0)->values();

        $byChannel = $creatives->flatMap(fn ($c) => $c->channels->map(fn ($ch) => ['channel' => $ch->name, 'creative' => $c]))
            ->groupBy('channel')
            ->map(function ($group, $name) {
                $summary = $group->reduce(fn (?MetricsSummary $carry, $row) => $carry ? $carry->plus($row['creative']->summary()) : $row['creative']->summary());

                return ['label' => $name, 'creatives' => $group->count(), 'metrics' => $summary?->toArray()];
            })->sortByDesc(fn ($row) => $row['metrics']['spend'] ?? 0)->values();

        return Inertia::render('Performance', [
            'creatives' => $creatives->map(fn ($c) => $this->presenter->card($c))->values(),
            'totals' => $totals->toArray(),
            'byProduct' => $byProduct,
            'byChannel' => $byChannel,
            'filters' => $filters,
            'options' => $this->filters->options(),
        ]);
    }

    public function store(Request $request, Creative $creative): RedirectResponse
    {
        $data = $this->validated($request);

        $creative->metrics()->create([...$data, 'created_by' => $request->user()->id]);

        $this->history->log($creative, 'metrics_added', 'Performances saisies du '.$data['period_start'].' au '.$data['period_end']);

        return back()->with('success', 'Performances enregistrées.');
    }

    public function update(Request $request, CreativeMetric $metric): RedirectResponse
    {
        $metric->update($this->validated($request));

        return back()->with('success', 'Performances mises à jour.');
    }

    public function destroy(CreativeMetric $metric): RedirectResponse
    {
        $metric->delete();

        return back()->with('success', 'Ligne de performance supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'channel_id' => ['nullable', 'exists:channels,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'spend' => ['required', 'numeric', 'min:0'],
            'impressions' => ['required', 'integer', 'min:0'],
            'reach' => ['required', 'integer', 'min:0'],
            'clicks' => ['required', 'integer', 'min:0'],
            'leads' => ['required', 'integer', 'min:0'],
            'qualified_leads' => ['required', 'integer', 'min:0'],
            'contacted' => ['required', 'integer', 'min:0'],
            'phone_qualified' => ['required', 'integer', 'min:0'],
            'appointments' => ['required', 'integer', 'min:0'],
            'confirmed' => ['required', 'integer', 'min:0'],
            'sales' => ['required', 'integer', 'min:0'],
            'revenue' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
