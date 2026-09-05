<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreativeRequest;
use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Services\CreativeFilters;
use App\Services\CreativeNaming;
use App\Services\CreativePresenter;
use App\Services\HistoryLogger;
use App\Services\UtmBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CreativeController extends Controller
{
    public function __construct(
        private readonly CreativeFilters $filters,
        private readonly CreativePresenter $presenter,
        private readonly CreativeNaming $naming,
        private readonly UtmBuilder $utm,
        private readonly HistoryLogger $history,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters->fromRequest($request);

        $query = Creative::query()
            ->with([
                'product:id,name,code,color',
                'status:id,name,slug,color',
                'channels:id,name,code',
                'campaigns:id,name',
                'parameters.category:id,name,slug,group',
                'parameters.value:id,label,code',
            ])
            ->withMetricTotals();

        $this->filters->apply($query, $filters);

        $sort = in_array($filters['sort'], ['updated_at', 'created_at', 'reference', 'name'], true)
            ? $filters['sort']
            : 'updated_at';

        $creatives = $this->filters->applyRating(
            $query->orderBy($sort, $filters['direction'])->get(),
            $filters,
        );

        return Inertia::render('Creatives/Index', [
            'creatives' => $creatives->map(fn ($c) => $this->presenter->card($c))->values(),
            'filters' => $filters,
            'options' => $this->filters->options(),
        ]);
    }

    public function create(Request $request): Response
    {
        // The tree screen deep-links here with a pre-selected branch.
        $preset = [
            'product_id' => $request->integer('product_id') ?: null,
            'channels' => array_filter(array_map('intval', (array) $request->input('channels', []))),
            'parameters' => collect($request->input('parameters', []))
                ->mapWithKeys(fn ($values, $categoryId) => [(int) $categoryId => array_values(array_filter(array_map('intval', (array) $values)))])
                ->all(),
        ];

        return Inertia::render('Creatives/New', [
            'creative' => null,
            'preset' => $preset,
            'options' => $this->filters->options(),
            'suggestedReference' => $this->naming->suggest(
                $preset['product_id'],
                collect($preset['parameters'])->flatten()->all(),
                $preset['channels'],
            ),
        ]);
    }

    public function store(CreativeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $creative = DB::transaction(function () use ($request, $data) {
            $parameterValueIds = collect($data['parameters'] ?? [])->flatten()->filter()->map(fn ($v) => (int) $v)->all();

            $reference = filled($data['reference'] ?? null)
                ? $this->naming->ensureUnique($data['reference'])
                : $this->naming->ensureUnique($this->naming->suggest(
                    $data['product_id'] ?? null,
                    $parameterValueIds,
                    array_map('intval', $data['channels'] ?? []),
                ));

            $creative = Creative::create([
                ...collect($data)->except(['reference', 'channels', 'campaigns', 'parameters', 'utm', 'asset'])->all(),
                'reference' => $reference,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->syncRelations($creative, $data, $request);
            $this->history->log($creative, 'created', 'Créa créée');

            return $creative;
        });

        return redirect('/creatives/'.$creative->id)->with('success', 'Créa '.$creative->reference.' créée.');
    }

    public function show(Creative $creative): Response
    {
        return Inertia::render('Creatives/Show', [
            'creative' => $this->presenter->detail($creative),
            'options' => $this->filters->options(),
        ]);
    }

    public function edit(Creative $creative): Response
    {
        return Inertia::render('Creatives/Form', [
            'creative' => $this->presenter->detail($creative),
            'preset' => null,
            'options' => $this->filters->options(),
            'suggestedReference' => $creative->reference,
        ]);
    }

    public function update(CreativeRequest $request, Creative $creative): RedirectResponse
    {
        $data = $request->validated();
        $previousStatus = $creative->creative_status_id;

        DB::transaction(function () use ($request, $creative, $data, $previousStatus) {
            $reference = filled($data['reference'] ?? null) && $data['reference'] !== $creative->reference
                ? $this->naming->ensureUnique($data['reference'], $creative->id)
                : $creative->reference;

            $creative->update([
                ...collect($data)->except(['reference', 'channels', 'campaigns', 'parameters', 'utm', 'asset'])->all(),
                'reference' => $reference,
                'version' => $creative->version + 1,
                'updated_by' => $request->user()->id,
            ]);

            $this->syncRelations($creative, $data, $request);

            if ($previousStatus !== $creative->creative_status_id) {
                $status = CreativeStatus::find($creative->creative_status_id);
                $this->history->log($creative, 'status_changed', 'Statut passé à '.$status?->name);
            } else {
                $this->history->log($creative, 'updated', 'Créa mise à jour (v'.$creative->version.')');
            }
        });

        return redirect('/creatives/'.$creative->id)->with('success', 'Créa mise à jour.');
    }

    /**
     * Archiving is the default: a creative carries metrics, prompts, generations
     * and history that must not disappear silently. Only an empty record can
     * actually be deleted, and only when the admin asks for it explicitly.
     */
    public function destroy(Request $request, Creative $creative): RedirectResponse
    {
        $reference = $creative->reference;

        if ($request->boolean('force') && $this->isDisposable($creative)) {
            $creative->delete();

            return redirect('/creatives')->with('success', 'Créa '.$reference.' supprimée.');
        }

        $archived = CreativeStatus::where('is_archived_state', true)->first();

        if (! $archived) {
            return back()->with('error', 'Aucun statut « archivé » n\'est configuré.');
        }

        $creative->update(['creative_status_id' => $archived->id, 'updated_by' => $request->user()->id]);
        $this->history->log($creative, 'archived', 'Créa archivée');

        return redirect('/creatives')->with('success', 'Créa '.$reference.' archivée — rien n\'a été perdu.');
    }

    /**
     * A creative nothing points at: no numbers, no prompt, no generation, no copy.
     */
    private function isDisposable(Creative $creative): bool
    {
        return ! $creative->metrics()->exists()
            && ! $creative->prompts()->exists()
            && ! $creative->generations()->exists()
            && ! $creative->campaigns()->exists();
    }

    /**
     * Duplicate a creative keeping the copy and structure — the systematic
     * iteration workflow (same angle, different persona).
     */
    public function duplicate(Request $request, Creative $creative): RedirectResponse
    {
        // A variation swaps one (or a few) targeting values and keeps everything else.
        $data = $request->validate([
            'variations' => ['array'],
            'variations.*.parameter_category_id' => ['required', 'exists:parameter_categories,id'],
            'variations.*.parameter_value_id' => ['required', 'exists:parameter_values,id'],
        ]);

        $variations = collect($data['variations'] ?? [])
            ->keyBy(fn ($variation) => (int) $variation['parameter_category_id'])
            ->map(fn ($variation) => (int) $variation['parameter_value_id']);

        $creative->loadMissing(['parameters.category', 'parameters.value', 'channels', 'campaigns', 'utm']);

        // Spell out exactly what the variation changes — one line per dimension.
        $changes = $variations->map(function (int $valueId, int $categoryId) use ($creative) {
            $category = ParameterCategory::find($categoryId);
            $to = ParameterValue::find($valueId);
            $from = $creative->parameters
                ->where('parameter_category_id', $categoryId)
                ->map(fn ($parameter) => $parameter->value?->label)
                ->filter()
                ->implode(', ');

            return [
                'category' => $category?->name ?? '',
                'from' => $from ?: '—',
                'to' => $to?->label ?? '',
            ];
        })->values()->all();

        // The targeting the copy will carry, so its reference reflects the variation.
        $targetValueIds = $creative->parameters
            ->reject(fn ($parameter) => $variations->has($parameter->parameter_category_id))
            ->pluck('parameter_value_id')
            ->merge($variations->values())
            ->unique()
            ->values()
            ->all();

        $copy = DB::transaction(function () use ($request, $creative, $variations, $targetValueIds, $changes) {
            $ideaStatus = CreativeStatus::where('slug', 'idea')->first() ?? $creative->status;

            $copy = $creative->replicate([
                'reference', 'created_by', 'updated_by', 'performance_override', 'version', 'duplicated_from_id',
            ]);
            $copy->reference = $this->naming->ensureUnique($this->naming->suggest(
                $creative->product_id,
                $targetValueIds,
                $creative->channels->pluck('id')->all(),
            ));
            $copy->name = Str::limit($creative->name, 150, '').($variations->isEmpty() ? ' (copie)' : ' (variation)');
            $copy->creative_status_id = $ideaStatus->id;
            $copy->version = 1;
            $copy->duplicated_from_id = $creative->id;
            $copy->created_by = $request->user()->id;
            $copy->updated_by = $request->user()->id;
            $copy->save();

            $replaced = [];

            foreach ($creative->parameters as $parameter) {
                $categoryId = $parameter->parameter_category_id;

                if ($variations->has($categoryId)) {
                    // Swap every value of that category once, drop the rest.
                    if (in_array($categoryId, $replaced, true)) {
                        continue;
                    }

                    $replaced[] = $categoryId;

                    $copy->parameters()->create([
                        'parameter_category_id' => $categoryId,
                        'parameter_value_id' => $variations->get($categoryId),
                    ]);

                    continue;
                }

                $copy->parameters()->create([
                    'parameter_category_id' => $categoryId,
                    'parameter_value_id' => $parameter->parameter_value_id,
                ]);
            }

            // A variation on a dimension the original did not carry is simply added.
            foreach ($variations as $categoryId => $valueId) {
                if (! in_array($categoryId, $replaced, true)) {
                    $copy->parameters()->create([
                        'parameter_category_id' => $categoryId,
                        'parameter_value_id' => $valueId,
                    ]);
                }
            }

            $copy->channels()->sync($creative->channels->pluck('id'));
            $copy->campaigns()->sync($creative->campaigns->pluck('id'));

            $this->utm->syncCreative($copy->fresh(['channels', 'campaigns', 'landingPage', 'product']));
            $summary = collect($changes)
                ->map(fn ($change) => $change['category'].' : '.$change['from'].' → '.$change['to'])
                ->implode(' · ');

            $this->history->log(
                $copy,
                'created',
                $variations->isEmpty()
                    ? 'Dupliquée depuis '.$creative->reference
                    : 'Variation de '.$creative->reference.' — '.$summary.' (tout le reste inchangé)',
                ['source' => $creative->reference, 'changes' => $changes],
            );

            return $copy;
        });

        $message = $changes === []
            ? 'Créa dupliquée à l\'identique — ajustez ce que vous voulez tester.'
            : 'Variation créée — '.collect($changes)
                ->map(fn ($change) => $change['category'].' : '.$change['from'].' → '.$change['to'])
                ->implode(', ').'. Tout le reste est inchangé.';

        return redirect('/creatives/'.$copy->id)->with('success', $message);
    }

    public function updateStatus(Request $request, Creative $creative): RedirectResponse
    {
        $data = $request->validate([
            'creative_status_id' => ['required', 'exists:creative_statuses,id'],
        ]);

        $creative->update([
            'creative_status_id' => $data['creative_status_id'],
            'updated_by' => $request->user()->id,
        ]);

        $status = CreativeStatus::find($data['creative_status_id']);
        $this->history->log($creative, 'status_changed', 'Statut passé à '.$status?->name);

        return back()->with('success', 'Statut mis à jour : '.$status?->name);
    }

    public function updateRating(Request $request, Creative $creative): RedirectResponse
    {
        $data = $request->validate([
            'performance_override' => ['nullable', 'in:winner,promising,average,poor'],
        ]);

        $creative->update(['performance_override' => $data['performance_override'] ?? null]);

        $this->history->log(
            $creative,
            'performance',
            $data['performance_override']
                ? 'Performance forcée : '.Str::upper($data['performance_override'])
                : 'Performance repassée en automatique',
        );

        return back()->with('success', 'Indicateur de performance mis à jour.');
    }

    public function storeNote(Request $request, Creative $creative): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $creative->noteEntries()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Note ajoutée.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Creative $creative, array $data, Request $request): void
    {
        $creative->channels()->sync(array_map('intval', $data['channels'] ?? []));
        $creative->campaigns()->sync(array_map('intval', $data['campaigns'] ?? []));

        $creative->parameters()->delete();

        foreach ($data['parameters'] ?? [] as $categoryId => $valueIds) {
            foreach (array_filter((array) $valueIds) as $valueId) {
                $creative->parameters()->create([
                    'parameter_category_id' => (int) $categoryId,
                    'parameter_value_id' => (int) $valueId,
                ]);
            }
        }

        if ($request->hasFile('asset')) {
            $file = $request->file('asset');
            $path = $file->store('creatives', 'public');

            $creative->update([
                'asset_path' => $path,
                'asset_filename' => $file->getClientOriginalName(),
                'asset_mime' => $file->getMimeType(),
            ]);

            $this->history->log($creative, 'asset_uploaded', 'Asset téléversé : '.$file->getClientOriginalName());
        }

        $creative->refresh()->loadMissing(['channels', 'campaigns', 'landingPage', 'product']);

        $utmInput = collect($data['utm'] ?? [])->all();

        if ($utmInput !== []) {
            $creative->utm()->updateOrCreate([], [
                'base_url' => $utmInput['base_url'] ?? null,
                'utm_source' => $utmInput['utm_source'] ?? null,
                'utm_medium' => $utmInput['utm_medium'] ?? null,
                'utm_campaign' => $utmInput['utm_campaign'] ?? null,
                'utm_content' => $utmInput['utm_content'] ?? null,
                'utm_term' => $utmInput['utm_term'] ?? null,
                'auto_sync' => (bool) ($utmInput['auto_sync'] ?? true),
            ]);
        }

        $this->utm->syncCreative($creative->fresh(['channels', 'campaigns', 'landingPage', 'product', 'utm']));
    }
}
