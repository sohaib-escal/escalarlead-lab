<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\Channel;
use App\Models\CreativeStatus;
use App\Models\CtaOption;
use App\Models\LandingPageType;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Models\Product;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One generic CRUD surface for every admin-managed list, so adding a new
 * parameter or channel never requires a developer.
 */
class AdminController extends Controller
{
    private const RESOURCES = [
        'products' => Product::class,
        'channels' => Channel::class,
        'parameter-categories' => ParameterCategory::class,
        'parameter-values' => ParameterValue::class,
        'creative-statuses' => CreativeStatus::class,
        'cta-options' => CtaOption::class,
        'landing-page-types' => LandingPageType::class,
        'users' => User::class,
        'ai-models' => AiModel::class,
        'prompt-templates' => PromptTemplate::class,
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Index', [
            'products' => Product::orderBy('position')->withCount('creatives')->get(),
            'channels' => Channel::orderBy('position')->withCount('creatives')->get(),
            'categories' => ParameterCategory::ordered()->with('values')->withCount('values')->get()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'group' => $category->group,
                    'description' => $category->description,
                    'is_multi' => $category->is_multi,
                    'in_tree' => $category->in_tree,
                    'in_naming' => $category->in_naming,
                    'is_active' => $category->is_active,
                    'position' => $category->position,
                    'values' => $category->values->map(fn ($v) => [
                        'id' => $v->id,
                        'label' => $v->label,
                        'code' => $v->code,
                        'position' => $v->position,
                        'is_archived' => $v->is_archived,
                        'product_id' => $v->product_id,
                    ])->values(),
                ]),
            'statuses' => CreativeStatus::orderBy('position')->withCount('creatives')->get(),
            'ctas' => CtaOption::orderBy('position')->get(),
            'landingPageTypes' => LandingPageType::orderBy('position')->withCount('landingPages')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'is_active']),
            'groups' => ['persona', 'property', 'financial', 'energy', 'problem'],
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $model = $this->modelFor($resource);
        $data = $this->validated($request, $resource);

        $record = $model::create($this->withSlug($resource, $data));
        $this->enforceSingleDefault($resource, $record);

        return back()->with('success', 'Élément ajouté.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $model = $this->modelFor($resource);
        $record = $model::findOrFail($id);
        $data = $this->validated($request, $resource, $record);

        if ($resource === 'users' && blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $record->update($this->withSlug($resource, $data, $record));
        $this->enforceSingleDefault($resource, $record);

        return back()->with('success', 'Élément mis à jour.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        $model = $this->modelFor($resource);
        $record = $model::findOrFail($id);

        // Anything that history points at is archived or deactivated, never deleted.
        if ($message = $this->deactivateIfInUse($record)) {
            return back()->with('success', $message);
        }

        $record->delete();

        return back()->with('success', 'Élément supprimé.');
    }

    /**
     * Returns the message to show when the record must be kept for history.
     */
    private function deactivateIfInUse(Model $record): ?string
    {
        return match (true) {
            $record instanceof ParameterValue && $record->assignments()->exists() => tap(
                'Valeur archivée : elle est utilisée par des créas existantes.',
                fn () => $record->update(['is_archived' => true]),
            ),
            $record instanceof ParameterCategory && $record->values()->whereHas('assignments')->exists() => tap(
                'Catégorie désactivée : ses valeurs sont utilisées par des créas.',
                fn () => $record->update(['is_active' => false]),
            ),
            $record instanceof CreativeStatus && $record->creatives()->exists() => tap(
                'Statut désactivé : des créas le portent.',
                fn () => $record->update(['is_active' => false]),
            ),
            $record instanceof Product && ($record->creatives()->exists() || $record->campaigns()->exists()) => tap(
                'Produit désactivé : des créas ou des campagnes y sont rattachées.',
                fn () => $record->update(['is_active' => false]),
            ),
            $record instanceof Channel && $record->creatives()->exists() => tap(
                'Canal désactivé : des créas y sont diffusées.',
                fn () => $record->update(['is_active' => false]),
            ),
            $record instanceof LandingPageType && $record->landingPages()->exists() => tap(
                'Type désactivé : des landing pages l\'utilisent.',
                fn () => $record->update(['is_active' => false]),
            ),
            $record instanceof AiModel && $record->prompts()->exists() => tap(
                'Modèle désactivé : il a généré des prompts existants.',
                fn () => $record->update(['is_active' => false]),
            ),
            $record instanceof User => tap(
                'Utilisateur désactivé : son nom reste sur les créas qu\'il a créées.',
                fn () => $record->update(['is_active' => false]),
            ),
            default => null,
        };
    }

    /**
     * `is_default` is a single-winner flag on AI models and prompt templates.
     */
    private function enforceSingleDefault(string $resource, Model $record): void
    {
        if (! in_array($resource, ['ai-models', 'prompt-templates'], true) || ! $record->is_default) {
            return;
        }

        $record->newQuery()->whereKeyNot($record->getKey())->update(['is_default' => false]);
    }

    /**
     * @return class-string<Model>
     */
    private function modelFor(string $resource): string
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return self::RESOURCES[$resource];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $resource, ?Model $record = null): array
    {
        return match ($resource) {
            'products' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:16', Rule::unique('products', 'code')->ignore($record)],
                'color' => ['nullable', 'string', 'max:32'],
                'description' => ['nullable', 'string'],
                'position' => ['nullable', 'integer'],
                'is_active' => ['boolean'],
            ]),
            'channels' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:16', Rule::unique('channels', 'code')->ignore($record)],
                'default_utm_source' => ['nullable', 'string', 'max:120'],
                'default_utm_medium' => ['nullable', 'string', 'max:120'],
                'position' => ['nullable', 'integer'],
                'is_active' => ['boolean'],
            ]),
            'parameter-categories' => $request->validate([
                'name' => [
                    'required', 'string', 'max:120',
                    function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                        $exists = ParameterCategory::query()
                            ->where('slug', Str::slug((string) $value))
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail('Une catégorie porte déjà ce nom.');
                        }
                    },
                ],
                'group' => ['required', 'string', 'max:40'],
                'description' => ['nullable', 'string'],
                'is_multi' => ['boolean'],
                'in_tree' => ['boolean'],
                'in_naming' => ['boolean'],
                'position' => ['nullable', 'integer'],
                'is_active' => ['boolean'],
            ]),
            'parameter-values' => $request->validate([
                'parameter_category_id' => ['required', 'exists:parameter_categories,id'],
                'label' => [
                    'required', 'string', 'max:150',
                    // Two values with the same label inside one category would collide on slug.
                    function (string $attribute, mixed $value, \Closure $fail) use ($request, $record) {
                        $exists = ParameterValue::query()
                            ->where('parameter_category_id', $request->integer('parameter_category_id'))
                            ->where('slug', Str::slug((string) $value))
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail('Cette valeur existe déjà dans cette catégorie.');
                        }
                    },
                ],
                'code' => [
                    'required', 'string', 'max:24',
                    Rule::unique('parameter_values', 'code')
                        ->where('parameter_category_id', $request->integer('parameter_category_id'))
                        ->ignore($record),
                ],
                'product_id' => ['nullable', 'exists:products,id'],
                'position' => ['nullable', 'integer'],
                'is_archived' => ['boolean'],
            ]),
            'creative-statuses' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'color' => ['nullable', 'string', 'max:32'],
                'counts_as_live' => ['boolean'],
                'is_archived_state' => ['boolean'],
                'position' => ['nullable', 'integer'],
                'is_active' => ['boolean'],
            ]),
            'cta-options' => $request->validate([
                'label' => ['required', 'string', 'max:120'],
                'position' => ['nullable', 'integer'],
                'is_active' => ['boolean'],
            ]),
            'landing-page-types' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'position' => ['nullable', 'integer'],
                'is_active' => ['boolean'],
            ]),
            'users' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($record)],
                'password' => [$record ? 'nullable' : 'required', 'string', 'min:8'],
                'is_active' => ['boolean'],
            ]),
            'ai-models' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'provider' => ['required', Rule::in(array_keys(config('ai.providers')))],
                'model_id' => ['required', 'string', 'max:120'],
                'notes' => ['nullable', 'string'],
                'is_default' => ['boolean'],
                'is_active' => ['boolean'],
                'position' => ['nullable', 'integer'],
            ]),
            'prompt-templates' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'target_format' => ['required', 'in:video,image,any'],
                'description' => ['nullable', 'string'],
                'system_prompt' => ['required', 'string'],
                'user_template' => ['required', 'string'],
                'is_default' => ['boolean'],
                'is_active' => ['boolean'],
                'position' => ['nullable', 'integer'],
            ]),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSlug(string $resource, array $data, ?Model $record = null): array
    {
        $source = $data['name'] ?? $data['label'] ?? null;

        if (! $source) {
            return $data;
        }

        return match ($resource) {
            'products', 'channels', 'parameter-categories', 'creative-statuses', 'cta-options', 'landing-page-types', 'prompt-templates' => [
                ...$data,
                'slug' => $record?->slug ?: Str::slug($source),
            ],
            'parameter-values' => [
                ...$data,
                'slug' => $record?->slug ?: Str::slug($source),
            ],
            default => $data,
        };
    }
}
