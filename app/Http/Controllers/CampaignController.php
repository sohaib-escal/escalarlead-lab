<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CreativeFilters;
use App\Services\CreativePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CreativePresenter $presenter,
        private readonly CreativeFilters $filters,
    ) {}

    public function index(Request $request): Response
    {
        $campaigns = Campaign::query()
            ->with(['product:id,name,code,color', 'channels:id,name,code', 'metrics'])
            ->withCount('creatives')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('product'), fn ($q) => $q->where('product_id', $request->integer('product')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%'.$request->string('search').'%'))
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('name')
            ->get()
            ->map(fn ($campaign) => $this->presenter->campaign($campaign));

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'product' => $request->filled('product') ? $request->integer('product') : null,
                'search' => $request->string('search')->toString() ?: null,
            ],
            'options' => $this->filters->options(),
            'statuses' => Campaign::STATUSES,
        ]);
    }

    public function show(Campaign $campaign): Response
    {
        $campaign->loadMissing(['product', 'channels', 'metrics']);

        $creatives = $campaign->creatives()
            ->with(['product:id,name,code,color', 'status:id,name,slug,color', 'channels:id,name,code',
                'parameters.category:id,name,slug,group', 'parameters.value:id,label,code'])
            ->withMetricTotals()
            ->get()
            ->map(fn ($creative) => $this->presenter->card($creative));

        return Inertia::render('Campaigns/Show', [
            'campaign' => $this->presenter->campaign($campaign),
            'creatives' => $creatives,
            'options' => $this->filters->options(),
            'statuses' => Campaign::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $campaign = Campaign::create([
            ...collect($data)->except('channels')->all(),
            'created_by' => $request->user()->id,
        ]);

        $campaign->channels()->sync($data['channels'] ?? []);

        return redirect('/campaigns/'.$campaign->id)->with('success', 'Campagne créée.');
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $this->validated($request);

        $campaign->update(collect($data)->except('channels')->all());
        $campaign->channels()->sync($data['channels'] ?? []);

        return back()->with('success', 'Campagne mise à jour.');
    }

    /**
     * A campaign that carries creatives or numbers is archived, never deleted:
     * the performance rows reference it.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        if ($campaign->creatives()->exists() || $campaign->metrics()->exists()) {
            $campaign->update(['status' => 'archived']);

            return redirect('/campaigns')->with('success', 'Campagne archivée (elle porte des créas ou des performances).');
        }

        $campaign->delete();

        return redirect('/campaigns')->with('success', 'Campagne supprimée.');
    }

    public function syncCreatives(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'creatives' => ['array'],
            'creatives.*' => ['exists:creatives,id'],
        ]);

        $campaign->creatives()->sync($data['creatives'] ?? []);

        return back()->with('success', 'Créas de la campagne mises à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:64'],
            'product_id' => ['nullable', 'exists:products,id'],
            'country' => ['required', 'string', 'max:64'],
            'objective' => ['nullable', 'string', 'max:180'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:'.implode(',', Campaign::STATUSES)],
            'notes' => ['nullable', 'string'],
            'channels' => ['array'],
            'channels.*' => ['exists:channels,id'],
        ]);
    }
}
