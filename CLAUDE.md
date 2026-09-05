# Creative Tree — working notes

Internal creative-intelligence tool for a French home-renovation media-buying team.
Read `README.md` first for the product intent. This file records the conventions to follow.

## Stack & layout

Laravel 13 + PostgreSQL + Inertia + React 19 + Tailwind v4.

- `app/Models` — Eloquent models. `Creative` is the atomic unit.
- `app/Services` — the domain logic worth naming:
  - `Ai/` — `CreativeOutcome` (the idea in plain language), `PromptGenerator`, and one
    `PromptProvider` per LLM vendor behind `PromptProviderRegistry`.
  - `Generation/` — `GenerationProvider` + `GoogleVeoProvider` (real) and `GoogleFlowProvider`
    (manual handoff), behind `GenerationProviderRegistry`.
  - `Performance/` — `PerformanceProvider`; manual is implemented, Meta is a declared placeholder.
  - `CreativeTree` builds the tree and the untested branches (the roadmap).
  - `CreativeNaming` generates/uniquifies the human-readable creative ID.
  - `UtmBuilder` suggests UTM values and keeps them in sync.
  - `CreativeFilters` holds the shared filter parsing + the option lists every screen needs.
  - `CreativePresenter` shapes creatives/campaigns for the frontend.
  - `HistoryLogger` writes the creative timeline.
- `app/Support/MetricsSummary` — every derived KPI and the automatic performance rating.
- `resources/js/Pages` — one file per screen, matching the Inertia component name.
- `resources/js/Components/Ui.jsx` — the shared primitives (Badge, Card, Table, Field, …).

## Conventions

- **Targeting stays relational.** Never store the persona as JSON. Add values through
  `parameter_categories` / `parameter_values` and link them via `creative_parameters`.
- **No hard-coded taxonomy in the UI.** Products, channels, statuses, CTAs, landing-page types and
  every parameter category/value are admin-managed. If a screen needs a list, get it from
  `CreativeFilters::options()`.
- **`in_tree` / `in_naming`** on a parameter category decide whether it can be a tree axis and
  whether it feeds the creative ID. Prefer flipping a flag over writing code.
- **Rating is cost-per-qualified-lead based**, thresholds in `config/creative.php`. A manual
  `performance_override` always wins.
- French UI copy; English code, comments and identifiers.
- **One role.** Every authenticated user is an admin. There is no `role` column and no role
  middleware — do not reintroduce them.
- **Archive, never delete, anything history points at.** Creatives, campaigns, landing pages,
  taxonomy values, products, channels, users: the controllers downgrade a delete to an
  archive/deactivate when the record is referenced. Only an empty creative can be force-deleted.
- **Values are scoped.** `parameter_values.product_id` keeps a value out of products where it makes
  no sense (a PAC creative is never about old windows). The tree, the wizard and the execution form
  all respect it; archived values disappear from creation but stay on existing creatives.
- **Tested ≠ measured.** A creative with no metrics rates `no_data`, the tree shows 🧪, and the UI
  says so. Never imply a verdict from the fact that something was launched.
- **Never fake an integration.** If a provider cannot do something, its `capabilities()` says so and
  the UI shows it. A generation only reaches `completed` when a real asset exists.

## The product loop

Tree → untested branch → idea wizard (`Creatives/New`) → creative page → AI prompt → validate →
generation (Veo or Flow handoff) → asset → campaign → performance → winner → next branch.

Layer 1 (the idea: targeting) and layer 2 (the execution: copy, asset, funnel) are deliberately
separate. `/creatives/new` only captures layer 1.

`App\Services\Ai\CreativeAiState` resolves the single state a creative is in
(`idea → prompt → validated → generating → generated → attached`, plus failure) and the one next
action. The creative page renders it as a stepper — the admin never has to infer state from rows.
A failed generation returns the creative to a state it can act on; it is never left in `generating`.

The tree is keyboard-driven: ↑/↓ between siblings, →/Enter to descend, ← to go back, C to create on
the current branch.

## Google Flow, factually

Flow (flow.google) has **no public generation API** (verified September 2026). `GoogleVeoProvider`
implements the official Gemini API route (`predictLongRunning`, operation polling, file download,
~2-day retention so we keep a local copy). `GoogleFlowProvider` is a deliberate manual handoff and
must stay that way until Google ships an API — do not wire it to unofficial third-party wrappers.

## Gotchas

- The pivot table for creatives × channels is `channel_creative` (alphabetical Laravel convention).
- `Creative` has both a `notes` **text column** and a `noteEntries` **relation** — the relation is
  deliberately not called `notes`, or `loadMissing('notes.user')` blows up on the string column.
- `Creative::scopeSearch` uses `ilike`, so tests run against PostgreSQL
  (`fr_renovation_creative_os_test`), not sqlite.
- Inertia's `useForm` **deep-clones data on every `setData`**, so arrays/objects get a new identity
  each keystroke. Effects must depend on serialised keys (`data.channels.join(',')`,
  `JSON.stringify(data.parameters)`), never on the objects themselves — otherwise you get an
  infinite render loop that silently freezes the page. See `Pages/Creatives/Form.jsx`.
- `Creative` has a `notes` column *and* `noteEntries`; it also has `prompts`, `validatedPrompt`,
  `generations`. Generation may only run from a prompt whose status is `validated`.
- List queries use `Creative::withMetricTotals()` (aggregate sums) so `summary()` costs no extra
  queries; `MetricsSummary::fromAggregates()` reads those.

## Commands

```bash
php artisan test
./vendor/bin/pint
npm run build
php artisan migrate:fresh --seed
```
