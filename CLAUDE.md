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
  - `Agent/` — the conversation turn: `ConversationAgent` (orchestration),
    `AgentPromptBuilder`, `LeadState` (required fields, next target), `LeadSummariser`.
  - `Knowledge/` — the WhatsApp agent's RAG: `KnowledgeRetriever` (interface) +
    `PostgresKnowledgeRetriever`, and `GuardrailComposer` for the standing instructions.
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

## The knowledge system (WhatsApp agent)

See `docs/WHATSAPP-AGENT-BRIEF.md` §5. Two kinds of knowledge, deliberately not the same mechanism:

- **`knowledge_passages` is retrieved.** Tag-first filtering on `problem_family`, then French
  full-text ranking. No embeddings and no `pgvector`: the corpus is ~45 short passages, so ranking
  is not the bottleneck, and it keeps homeowner messages away from a third-party embedding API.
  Swapping in embeddings later is one container binding in `KnowledgeServiceProvider`.
- **`agent_guardrails` is never retrieved.** Persona, tone and the never-claim list go into the
  system prompt on every turn. A compliance rule that is only sometimes recalled is worse than none.
  Do not cache this block — a stale guardrail is the exact failure mode to avoid.

Three Postgres details that are load-bearing:

- `unaccent()` is only STABLE, so it cannot be used in a generated column or index. The migration
  creates an IMMUTABLE `f_unaccent()` wrapper; the `search_vector` column depends on it.
- `plainto_tsquery` joins terms with AND, which never matches a conversational sentence. The
  retriever builds an OR query from `unnest(to_tsvector(...))` and **drops lexemes of 1–2 chars** —
  otherwise « à » unaccents to a bare `a` that matches every passage containing "difficile à chauffer".
- Eligibility requires a hit in title or keywords (`ts_filter(search_vector, '{a,b}')`). An
  incidental body mention is not a match. Titles are topical labels, never customer quotes: a quoted
  sentence puts its stop-words at the highest search weight.

Known limitation: French stemming collides « aider » with « aide », so "ça peut aider ?" can surface
the state-aid passage. Measure before reaching for embeddings; the guardrails contain the risk.

## The conversation agent

One inbound message in, one reply out — `ConversationAgent::handle()`. The model does exactly two
things: read what the homeowner just said, and write the next sentence. Everything that *decides*
anything is code: which field to chase (`LeadState::nextTarget()`), when a lead is complete, when a
human takes over, what the summary says.

- One LLM call per turn returns JSON with `extracted`, `taxonomy`, `reply`, `handoff_requested`,
  `opted_out`. Splitting extraction from composition would double the cost to re-send the same
  context twice.
- A reply over 240 chars or containing two questions is regenerated once, then replaced with a safe
  templated question. A wall of text is never sent to a homeowner.
- A `value_slug` the model invented is never written as a fact — it goes to `leads.raw_notes`, so
  nothing the homeowner said is lost.
- `/agent` is the test console: same retrieval, same prompt, same rules, no WhatsApp. It shows the
  working (retrieved passages, extracted fields, lead state) because testing a conversation without
  that is guesswork.
- With no API key the console says so and refuses to run. It never fakes a reply.

### Photos

`PromptProvider::complete()` takes an optional `ImageInput[]`; all three providers implement it
(Gemini `inline_data`, Anthropic image blocks, OpenAI `image_url` data URIs) and declare
`supportsImages()`. A provider that cannot read images gets none sent, and the inbound message is
flagged `ignored_by_provider` — the photo is never silently dropped.

A photo turn is the highest-risk moment in the conversation: a picture of black stains invites a
confident diagnosis. `AgentPromptBuilder::photoInstructions()` forces the shape — say what is
visible, give **at least two** possible causes, state that a photo cannot conclude, then ask one
question — and the seeded guardrails forbid asserting a cause, estimating gravity/cost/danger, or
claiming to recognise a brand or material. Image turns get a wider reply budget
(`reply.max_chars_with_image`) because that shape does not fit in 240 characters.

What the model saw is written to `leads.raw_notes` as `Photo : …` — it is evidence about the home,
not a transient. `ImageInput` carries an optional `url` so a stored upload today, and a WhatsApp
media URL later, both display in the thread.

Two bugs worth not reintroducing: reading `$conversation->lead` caches a null relation (use
`lead()->firstOrCreate()`), and the summary must be written *after* the status update or it always
describes the previous turn.

## The gateway seam

`POST /api/agent/turn` is how the WhatsApp gateway (zailer) talks to the agent — see
`docs/AGENT-API.md`. HMAC-signed both ways with `AGENT_API_SECRET`, timestamped against replay, and
**503 when no secret is configured**; there is no unauthenticated mode for an endpoint that creates
leads and spends model credits.

**Prefer the async path.** With a `callback_url` the turn is queued and the request returns 202 in
under a second; the reply is posted back signed when it is ready. This is not a preference — a real
Gemini turn measured 20s, and a synchronous call blew PHP's 30s limit outright. Sync (no callback)
exists for the console and quick tests only, and the caller owns the timeout.

Requires `php artisan queue:work`. Without a worker, queued turns never run and no callback is sent.

`ProcessAgentTurn` is locked per conversation (`WithoutOverlapping`), so two fast messages from the
same person are answered in order rather than interleaved, and `failed()` still posts a callback —
the gateway is never left waiting for one that is not coming.

Idempotency is `message.id` → `conversation_messages.external_id` (unique). A retry replays the
original reply with no second model charge. One subtlety: an inbound recorded with **no reply after
it** means the first attempt was interrupted, so it is genuinely retried rather than replayed as
silence.

Attribution: `creative_reference` is the reliable path; failing that a reference-shaped string
anywhere in the CTWA `referral` payload is matched. Unmatched means null plus the raw payload kept —
never a guess, because a wrong attribution credits the wrong branch of the tree.

Still to build: zailer's side (webhook receipt, media download, sending, the 24-hour window). The
agent is transport-agnostic and needs no changes for it.

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
