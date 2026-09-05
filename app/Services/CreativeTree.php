<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Creative;
use App\Models\ParameterCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the creative tree: creatives grouped along a configurable list of axes,
 * plus the untested branches at every level (the testing roadmap).
 *
 * An axis is either the pseudo-axis `product`, the pseudo-axis `channel`,
 * or the slug of any admin-managed parameter category flagged `in_tree`.
 */
class CreativeTree
{
    /**
     * Axes the user is allowed to build the tree from.
     *
     * @return array<int, array{key:string,label:string,group:string}>
     */
    public function availableAxes(): array
    {
        $axes = [
            ['key' => 'product', 'label' => 'Produit', 'group' => 'product'],
            ['key' => 'channel', 'label' => 'Canal', 'group' => 'channel'],
        ];

        foreach (ParameterCategory::active()->where('in_tree', true)->ordered()->get() as $category) {
            $axes[] = ['key' => $category->slug, 'label' => $category->name, 'group' => $category->group];
        }

        return $axes;
    }

    /**
     * @param  array<int, string>  $axes
     * @param  callable(Builder): void|null  $filter
     * @return array{tree: array<int, array<string, mixed>>, gaps: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function build(array $axes, ?callable $filter = null): array
    {
        $axes = $this->resolveAxes($axes);

        $query = Creative::query()
            ->with(['product:id,name,code,color', 'status:id,name,slug,color,counts_as_live', 'channels:id,name,code', 'parameters:id,creative_id,parameter_category_id,parameter_value_id'])
            ->withMetricTotals();

        if ($filter) {
            $filter($query);
        }

        $creatives = $query->get();

        $root = ['children' => []];
        $totals = ['creatives' => 0, 'live' => 0, 'winners' => 0];

        foreach ($creatives as $creative) {
            $summary = $creative->summary();
            $rating = $creative->performance_override ?: $summary->rating();
            $isLive = (bool) $creative->status?->counts_as_live;

            $totals['creatives']++;
            $totals['live'] += $isLive ? 1 : 0;
            $totals['winners'] += $rating === 'winner' ? 1 : 0;

            foreach ($this->pathsFor($creative, $axes) as $path) {
                $this->insert($root, $path, $creative, $isLive, $rating === 'winner');
            }
        }

        $tree = $this->finalise($root['children'], $axes, 0, []);

        // Values of the first axis that carry no creative at all — the column
        // explorer needs them to show untested entry points next to tested ones.
        $firstAxis = $axes[0] ?? null;
        $missingRoots = [];

        if ($firstAxis) {
            $present = collect($tree)->pluck('value_id')->all();

            $missingRoots = collect($firstAxis['values'])
                ->reject(fn ($value) => in_array($value['id'], $present, true))
                ->map(fn ($value) => [
                    'axis' => $firstAxis['key'],
                    'axis_label' => $firstAxis['label'],
                    'value_id' => $value['id'],
                    'label' => $value['label'],
                    'code' => $value['code'] ?? null,
                    'path' => [[
                        'axis' => $firstAxis['key'],
                        'value_id' => $value['id'],
                        'label' => $value['label'],
                        'code' => $value['code'] ?? null,
                    ]],
                ])
                ->values()
                ->all();
        }

        return [
            'tree' => $tree,
            'missingRoots' => $missingRoots,
            'gaps' => $this->collectGaps($tree),
            'totals' => $totals,
        ];
    }

    /**
     * Resolve axis keys into definitions with their full value list.
     *
     * @param  array<int, string>  $axes
     * @return array<int, array{key:string,label:string,values:array<int, array{id:int,label:string,code:?string}>}>
     */
    public function resolveAxes(array $axes): array
    {
        $resolved = [];

        foreach ($axes as $key) {
            if ($key === 'product') {
                $resolved[] = [
                    'key' => 'product',
                    'label' => 'Produit',
                    'values' => Product::active()->orderBy('position')->get()
                        ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'code' => $p->code])->all(),
                ];

                continue;
            }

            if ($key === 'channel') {
                $resolved[] = [
                    'key' => 'channel',
                    'label' => 'Canal',
                    'values' => Channel::active()->orderBy('position')->get()
                        ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name, 'code' => $c->code])->all(),
                ];

                continue;
            }

            $category = ParameterCategory::with('activeValues')->where('slug', $key)->first();

            if (! $category) {
                continue;
            }

            $resolved[] = [
                'key' => $category->slug,
                'label' => $category->name,
                'category_id' => $category->id,
                'values' => $category->activeValues->map(fn ($v) => [
                    'id' => $v->id, 'label' => $v->label, 'code' => $v->code, 'product_id' => $v->product_id,
                ])->all(),
            ];
        }

        return $resolved;
    }

    /**
     * Every branch a single creative belongs to (it can sit on several, e.g. 3 channels).
     *
     * @param  array<int, array<string, mixed>>  $axes
     * @return array<int, array<int, array{axis:string,id:int|string,label:string}>>
     */
    private function pathsFor(Creative $creative, array $axes): array
    {
        $paths = [[]];

        foreach ($axes as $axis) {
            $options = $this->creativeValuesForAxis($creative, $axis);

            $next = [];
            foreach ($paths as $path) {
                foreach ($options as $option) {
                    $next[] = [...$path, $option];
                }
            }

            $paths = $next;

            if ($paths === []) {
                return [];
            }
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $axis
     * @return array<int, array{axis:string,id:int|string,label:string}>
     */
    private function creativeValuesForAxis(Creative $creative, array $axis): array
    {
        if ($axis['key'] === 'product') {
            return $creative->product
                ? [['axis' => 'product', 'id' => $creative->product->id, 'label' => $creative->product->name, 'code' => $creative->product->code]]
                : [['axis' => 'product', 'id' => 'none', 'label' => 'Sans produit', 'code' => null]];
        }

        if ($axis['key'] === 'channel') {
            if ($creative->channels->isEmpty()) {
                return [['axis' => 'channel', 'id' => 'none', 'label' => 'Sans canal', 'code' => null]];
            }

            return $creative->channels->map(fn ($c) => [
                'axis' => 'channel', 'id' => $c->id, 'label' => $c->name, 'code' => $c->code,
            ])->all();
        }

        $labels = collect($axis['values'])->keyBy('id');

        $values = $creative->parameters
            ->where('parameter_category_id', $axis['category_id'] ?? 0)
            ->map(fn ($p) => $labels->get($p->parameter_value_id))
            ->filter()
            ->map(fn ($v) => ['axis' => $axis['key'], 'id' => $v['id'], 'label' => $v['label'], 'code' => $v['code'] ?? null])
            ->values()
            ->all();

        return $values ?: [['axis' => $axis['key'], 'id' => 'none', 'label' => 'Non renseigné', 'code' => null]];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array{axis:string,id:int|string,label:string}>  $path
     */
    private function insert(array &$node, array $path, Creative $creative, bool $isLive, bool $isWinner): void
    {
        $cursor = &$node;

        foreach ($path as $step) {
            $key = $step['axis'].':'.$step['id'];

            if (! isset($cursor['children'][$key])) {
                $cursor['children'][$key] = [
                    'key' => $key,
                    'axis' => $step['axis'],
                    'value_id' => $step['id'],
                    'label' => $step['label'],
                    'code' => $step['code'] ?? null,
                    'code' => $step['code'] ?? null,
                    'creatives' => 0,
                    'live' => 0,
                    'winners' => 0,
                    'spend' => 0.0,
                    'leads' => 0,
                    'qualified_leads' => 0,
                    'ids' => [],
                    'children' => [],
                ];
            }

            $child = &$cursor['children'][$key];

            if (! in_array($creative->id, $child['ids'], true)) {
                $child['ids'][] = $creative->id;
                $child['creatives']++;
                $child['live'] += $isLive ? 1 : 0;
                $child['winners'] += $isWinner ? 1 : 0;
                $child['spend'] += (float) ($creative->metrics_sum_spend ?? 0);
                $child['leads'] += (int) ($creative->metrics_sum_leads ?? 0);
                $child['qualified_leads'] += (int) ($creative->metrics_sum_qualified_leads ?? 0);
            }

            $cursor = &$child;
        }
    }

    /**
     * Sort branches, attach the missing (untested) values of the next axis.
     *
     * @param  array<string, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $axes
     * @param  array<int, array<string, mixed>>  $parentPath
     * @return array<int, array<string, mixed>>
     */
    private function finalise(array $children, array $axes, int $depth, array $parentPath): array
    {
        $nodes = [];

        foreach ($children as $child) {
            $path = [...$parentPath, [
                'axis' => $child['axis'],
                'value_id' => $child['value_id'],
                'label' => $child['label'],
            ]];

            $nextAxis = $axes[$depth + 1] ?? null;
            $childNodes = $this->finalise($child['children'], $axes, $depth + 1, $path);

            $missing = [];
            if ($nextAxis) {
                $present = collect($childNodes)->pluck('value_id')->all();

                // A value scoped to another product is not a gap, it is nonsense:
                // a heat pump creative is never about old windows.
                $branchProduct = collect($path)->firstWhere('axis', 'product');
                $branchProductId = $branchProduct['value_id'] ?? null;

                $missing = collect($nextAxis['values'])
                    ->reject(fn ($v) => in_array($v['id'], $present, true))
                    ->reject(fn ($v) => $branchProductId
                        && ($v['product_id'] ?? null)
                        && $v['product_id'] !== $branchProductId)
                    ->map(fn ($v) => [
                        'axis' => $nextAxis['key'],
                        'axis_label' => $nextAxis['label'],
                        'value_id' => $v['id'],
                        'label' => $v['label'],
                        'code' => $v['code'] ?? null,
                        'path' => [...$path, ['axis' => $nextAxis['key'], 'value_id' => $v['id'], 'label' => $v['label']]],
                    ])
                    ->values()
                    ->all();
            }

            $nodes[] = [
                'key' => $child['key'],
                'axis' => $child['axis'],
                'axis_label' => $axes[$depth]['label'] ?? $child['axis'],
                'value_id' => $child['value_id'],
                'label' => $child['label'],
                'code' => $child['code'] ?? null,
                'depth' => $depth,
                'is_leaf' => $nextAxis === null,
                'creatives' => $child['creatives'],
                'live' => $child['live'],
                'winners' => $child['winners'],
                'spend' => round($child['spend'], 2),
                'leads' => $child['leads'],
                'qualified_leads' => $child['qualified_leads'],
                'cost_per_qualified' => $child['qualified_leads'] > 0
                    ? round($child['spend'] / $child['qualified_leads'], 2)
                    : null,
                'path' => $path,
                'children' => $childNodes,
                'missing_children' => $missing,
            ];
        }

        usort($nodes, fn ($a, $b) => $b['creatives'] <=> $a['creatives'] ?: strcmp($a['label'], $b['label']));

        return $nodes;
    }

    /**
     * Every untested branch, at every level: the testing roadmap.
     *
     * Ranked so the gaps next to the branches we already invest in come first,
     * and shallower gaps (a whole angle never tested) outrank a single missing
     * channel on a small branch.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    private function collectGaps(array $tree): array
    {
        $gaps = [];

        $walk = function (array $nodes) use (&$walk, &$gaps) {
            foreach ($nodes as $node) {
                foreach ($node['missing_children'] as $missing) {
                    $gaps[] = [
                        'label' => collect($missing['path'])->pluck('label')->implode(' · '),
                        'axis_label' => $missing['axis_label'],
                        'depth' => $node['depth'] + 1,
                        'path' => $missing['path'],
                        'sibling_creatives' => $node['creatives'],
                        'sibling_winners' => $node['winners'],
                    ];
                }

                if ($node['children'] !== []) {
                    $walk($node['children']);
                }
            }
        };

        $walk($tree);

        usort($gaps, fn ($a, $b) => [$b['sibling_creatives'], $a['depth']] <=> [$a['sibling_creatives'], $b['depth']]);

        return array_slice($gaps, 0, 200);
    }
}
