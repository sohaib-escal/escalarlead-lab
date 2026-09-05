<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Creative;
use App\Models\CreativeGeneration;
use App\Models\CreativeHistory;
use App\Models\CreativeMetric;
use App\Models\CreativePrompt;
use App\Models\ParameterCategory;
use App\Services\CreativePresenter;
use App\Services\CreativeTree;
use App\Support\MetricsSummary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A quick look at the lab — not the center of the product. The tree is.
 */
class DashboardController extends Controller
{
    public function __invoke(CreativePresenter $presenter, CreativeTree $tree): Response
    {
        $creatives = Creative::query()
            ->with([
                'status:id,name,slug,color,counts_as_live',
                'product:id,name,code,color',
                'channels:id,name,code',
                'parameters.category:id,name,slug,group',
                'parameters.value:id,label,code',
            ])
            ->withMetricTotals()
            ->get();

        $rated = $creatives->map(fn ($creative) => [
            'creative' => $creative,
            'rating' => $creative->performance_override ?: $creative->summary()->rating(),
        ]);

        $treeResult = $tree->build(config('creative.default_tree_axes'));

        // "Waiting for me": the work that is already started and blocked on a decision.
        $waiting = [
            'prompts_to_review' => CreativePrompt::where('status', CreativePrompt::STATUS_DRAFT)
                ->with('creative:id,reference,name')->latest('id')->get()
                ->map(fn ($prompt) => [
                    'id' => $prompt->id,
                    'creative_id' => $prompt->creative_id,
                    'reference' => $prompt->creative?->reference,
                    'label' => 'Prompt v'.$prompt->version.' à relire',
                ])->values(),
            'generations_running' => CreativeGeneration::whereIn('status', [
                CreativeGeneration::STATUS_QUEUED, CreativeGeneration::STATUS_GENERATING,
            ])->with('creative:id,reference')->latest('id')->get()
                ->map(fn ($generation) => [
                    'id' => $generation->id,
                    'creative_id' => $generation->creative_id,
                    'reference' => $generation->creative?->reference,
                    'label' => 'Génération en cours',
                ])->values(),
            'handoffs' => CreativeGeneration::where('status', CreativeGeneration::STATUS_AWAITING_MANUAL)
                ->with('creative:id,reference')->latest('id')->get()
                ->map(fn ($generation) => [
                    'id' => $generation->id,
                    'creative_id' => $generation->creative_id,
                    'reference' => $generation->creative?->reference,
                    'label' => 'À générer dans Flow',
                ])->values(),
            'assets_to_promote' => CreativeGeneration::where('status', CreativeGeneration::STATUS_COMPLETED)
                ->whereDoesntHave('creative', fn ($query) => $query->whereColumn('creative_generation_id', 'creative_generations.id'))
                ->with('creative:id,reference')->latest('id')->get()
                ->map(fn ($generation) => [
                    'id' => $generation->id,
                    'creative_id' => $generation->creative_id,
                    'reference' => $generation->creative?->reference,
                    'label' => 'Asset généré à rattacher',
                ])->values(),
        ];

        return Inertia::render('Dashboard', [
            'cards' => [
                'winners' => $rated->where('rating', 'winner')->count(),
                'live_tests' => $creatives->filter(fn ($c) => $c->status?->counts_as_live)->count(),
                'opportunities' => count($treeResult['gaps']),
                'ready' => $creatives->filter(fn ($c) => $c->status?->slug === 'ready')->count(),
            ],
            'totals' => MetricsSummary::fromRows(CreativeMetric::all())->toArray(),
            'waiting' => $waiting,
            'waitingCount' => collect($waiting)->sum(fn ($bucket) => $bucket->count()),
            'testedWithoutData' => $rated->filter(
                fn ($row) => $row['creative']->status?->counts_as_live && $row['rating'] === 'no_data'
            )->count(),
            'opportunities' => collect($treeResult['gaps'])->take(6)->values(),
            // So a recommendation can link straight into a pre-filled creation flow.
            'categoriesBySlug' => ParameterCategory::active()->pluck('id', 'slug'),
            'recentWinners' => $rated->where('rating', 'winner')
                ->sortByDesc(fn ($row) => $row['creative']->updated_at)
                ->take(4)
                ->map(fn ($row) => $presenter->card($row['creative']))
                ->values(),
            'activeCampaigns' => Campaign::with(['product:id,name,code', 'channels:id,name,code', 'metrics'])
                ->withCount('creatives')
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn ($campaign) => $presenter->campaign($campaign)),
            'activity' => CreativeHistory::with(['creative:id,reference', 'user:id,name'])
                ->latest('id')
                ->take(10)
                ->get()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'event' => $entry->event,
                    'description' => $entry->description,
                    'author' => $entry->user?->name,
                    'creative' => $entry->creative ? [
                        'id' => $entry->creative->id,
                        'reference' => $entry->creative->reference,
                    ] : null,
                    'created_at' => $entry->created_at?->toDateTimeString(),
                ]),
        ]);
    }
}
