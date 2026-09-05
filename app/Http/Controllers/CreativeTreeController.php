<?php

namespace App\Http\Controllers;

use App\Models\Creative;
use App\Services\CreativeFilters;
use App\Services\CreativePresenter;
use App\Services\CreativeTree;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreativeTreeController extends Controller
{
    public function __construct(
        private readonly CreativeTree $tree,
        private readonly CreativeFilters $filters,
        private readonly CreativePresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $axes = array_values(array_filter((array) $request->input('axes', config('creative.default_tree_axes'))));

        if ($axes === []) {
            $axes = config('creative.default_tree_axes');
        }

        $filters = $this->filters->fromRequest($request);

        $result = $this->tree->build($axes, fn ($query) => $this->filters->apply($query, $filters));

        // Creatives sitting on the branch the user has selected in the tree.
        $selection = array_values(array_filter((array) $request->input('selection', [])));
        $branchCreatives = [];

        if ($selection !== []) {
            $branchCreatives = $this->creativesForBranch($selection);
        }

        return Inertia::render('CreativeTree', [
            'axes' => $axes,
            'availableAxes' => $this->tree->availableAxes(),
            'tree' => $result['tree'],
            'missingRoots' => $result['missingRoots'],
            'gaps' => $result['gaps'],
            'totals' => $result['totals'],
            'filters' => $filters,
            'options' => $this->filters->options(),
            'selection' => $selection,
            'branchCreatives' => $branchCreatives,
        ]);
    }

    /**
     * @param  array<int, string>  $selection  entries shaped `axis:value_id`
     * @return array<int, array<string, mixed>>
     */
    private function creativesForBranch(array $selection): array
    {
        $query = Creative::query()->with([
            'product:id,name,code,color',
            'status:id,name,slug,color',
            'channels:id,name,code',
            'campaigns:id,name',
            'parameters.category:id,name,slug,group',
            'parameters.value:id,label,code',
        ])->withMetricTotals();

        foreach ($selection as $step) {
            [$axis, $valueId] = array_pad(explode(':', $step, 2), 2, null);

            if ($valueId === null || $valueId === 'none') {
                continue;
            }

            match ($axis) {
                'product' => $query->where('product_id', (int) $valueId),
                'channel' => $query->whereHas('channels', fn ($q) => $q->where('channels.id', (int) $valueId)),
                default => $query->whereHas('parameters', fn ($q) => $q->where('parameter_value_id', (int) $valueId)),
            };
        }

        return $query->orderByDesc('updated_at')->get()
            ->map(fn ($creative) => $this->presenter->card($creative))
            ->values()
            ->all();
    }
}
