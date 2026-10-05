# Build brief — WhatsApp lead qualification agent (V1)

**How to use this file.** Open Claude Code in this repository and say:

> Read `docs/WHATSAPP-AGENT-BRIEF.md` and build it. Follow the decisions already made in it,
> use your own judgement on everything it marks as yours, and show me the plan before you write
> migrations.

Everything Claude Code needs is in this file. It must not ask the business to be re-explained.

---

## 0. One-paragraph summary

We advertise on WhatsApp Status. A French homeowner taps **« Envoyer un message »** and lands in a
WhatsApp conversation with an AI. The AI's job is to understand their renovation problem, ask a
small number of useful questions in plain French, capture a qualified lead, and hand it to the
commercial team with a summary a salesperson can read in fifteen seconds. Build the working V1
inside this existing Laravel application.

---

## 1. What already exists — do not rebuild any of it

This repository is **Creative Lab**, an internal creative-intelligence tool for the same business.
Read `README.md` and `CLAUDE.md` before writing code. Verified facts as of this brief:

| | |
|---|---|
| Stack | Laravel 13 · PHP 8.5 · PostgreSQL 18 · Inertia · React 19 · Tailwind v4 |
| Queue / cache / session | all on `database` — jobs table exists, a worker is all that's needed |
| LLM SDK | `anthropic-ai/sdk` already installed |
| Provider abstraction | `App\Services\Ai\Providers\PromptProviderRegistry` — Anthropic, Gemini, OpenAI already behind one interface |
| Admin-managed models | `ai_models` table + `/ai-studio` screen already let an admin add a model and mark a default |
| Postgres extensions available | `pg_trgm`, `unaccent`, and the `french` text-search configuration |
| Postgres extensions **not** available | **`pgvector` is not installed** — see §5 |
| Tests | PHPUnit against the `fr_renovation_creative_os_test` database, `./vendor/bin/pint` for style |

**Reuse, don't duplicate:**

- `PromptProviderRegistry` is the LLM abstraction. Do **not** write a second one.
- `ai_models` / `prompt_templates` are the admin-editable model and prompt storage. Reuse them;
  add a `purpose` discriminator (`creative_prompt` vs `whatsapp_agent`) rather than new tables.
- `HistoryLogger`, the `Ui.jsx` primitives, the archive-not-delete conventions, the French-UI /
  English-code rule: all apply here. `CLAUDE.md` is binding.

### 1.1 The taxonomy is already built — and it is your qualification vocabulary

The app already stores targeting relationally: `parameter_categories` → `parameter_values` →
`creative_parameters`. Today that is **24 categories and 102 values**, admin-editable, including
exactly the dimensions this agent needs to capture:

`Genre · Âge · Foyer · Statut de propriété · Type de bien · Âge du logement · Zone ·
Sensibilité au prix · Connaissance des aides · Système de chauffage · Situation électricité ·
Consommation · Problème principal · Problème spécifique · Symptôme · Déclencheur · Motivation ·
Objection`

**Decision, already made: the agent captures qualification data as `parameter_value` links, not as
free-text columns.** A lead's "problème spécifique = Condensation" must point at the same row the
creative pointed at. This is non-negotiable, because of §2.

Two consequences you must handle:

1. The current `specific-problem` values are `HIGHBILL, OLDBOILER, BREAKDOWN, COLDHOUSE, ELECBILL,
   ROOF, DRAFTS, CONDENS, NOISE, OLDWIN`. The WhatsApp campaigns advertise **humidité,
   moisissures, infiltration, mauvaise isolation**, which do not exist yet. Add them through the
   existing admin taxonomy (seeder + the `/admin` screen), not as new code. Consider whether
   `Isolation / Humidité` needs to exist as a fourth product alongside PAC, Solaire and Double
   vitrage — if it does, add it as a product row, and scope the new problem values to it with
   `parameter_values.product_id` the way the existing ones are.
2. Free text the homeowner gives you that maps to no taxonomy value must still be kept verbatim on
   the lead. Never discard what someone actually said.

---

## 2. The architectural insight this project exists for — read this twice

Creative Lab exists to answer *which audience × problem × angle combination earns money*. Today the
loop is broken at the bottom: a media buyer types `qualified_leads`, `appointments`, `confirmed` and
`sales` into a form by hand, from a spreadsheet, once a week.

**This WhatsApp agent closes that loop.**

```
Creative (PAC-W-60-69-HIGHBILL-AID-FB-001)
        ↓ its reference is also its utm_content
WhatsApp ad  →  conversation  →  qualified lead
        ↓
the lead knows which creative produced it
        ↓
creative_metrics.leads / qualified_leads / appointments / confirmed
stop being typed by hand
        ↓
the Creative Tree starts showing a REAL cost per qualified lead per branch
```

So:

- **Every conversation must carry its acquisition context** and resolve, wherever possible, to a
  `creatives.id`. The creative reference *is* the `utm_content` the app already generates — match on
  it. WhatsApp Click-to-WhatsApp ads deliver a `referral` object on the first inbound message
  (source id, headline, body, ctwa_clid, source_url). Parse it, store it raw, and resolve what you
  can. Where resolution fails, keep the raw payload and leave the link null — never guess.
- **Qualified leads must be able to feed `creative_metrics`.** Build the write path behind a flag or
  an explicit admin action rather than silently mutating the numbers a buyer typed; but build it.
  Adding a `source` column to `creative_metrics` (`manual` | `whatsapp`) is the cleanest way to keep
  the two from fighting.
- A lead whose creative is unknown is still a lead. Degrade, don't drop.

If you see a better way to attach a conversation to a creative, take it — but attribution is a
requirement, not a nice-to-have.

---

## 3. What to build / what not to build

**Build**

- WhatsApp webhook (receive) + send, behind a provider abstraction
- Conversation + message persistence, idempotent against retries and duplicates
- A conversation state machine with deterministic qualification rules
- One LLM turn that both extracts structured facts and writes the next French message
- A small, admin-editable knowledge base with filtered retrieval (§5)
- Lead records with status, attribution and a generated commercial summary
- Human handoff signalling
- An admin screen to read conversations, read leads and edit knowledge
- Tests, including a full scripted conversation

**Do not build**

- A CRM. Lead capture and qualification only.
- Automatic appointment booking, calendar integration, voice, multi-agent orchestration
- A vector database cluster, a separate embedding service, microservices
- Multi-language. French only in V1 — but do not hard-code French strings into PHP classes where a
  DB-backed template would do.
- A new analytics dashboard. Logs, a conversation list and counters are enough.
- Roles and permissions. `CLAUDE.md`: one role, everyone is admin.

---

## 4. The conversation — this is the product

### 4.1 Who you are talking to

French homeowners, many of them 50–75, often on a phone, often using voice-to-text, often not
comfortable with technology. They are curious about a problem in their house. They have not asked to
buy anything.

### 4.2 Hard rules for every outbound message

- **One question per message.** Never two.
- **Short.** Target under 25 words. Never a paragraph.
- Plain French. No jargon (`ITE`, `COP`, `VMC double flux`, `R=7`), no English, no marketing voice.
- At most one emoji, and usually none.
- Acknowledge before asking: « Je comprends. » / « D'accord, merci. » then the question.
- Never send two messages in a row unless the user sent two.
- Never re-ask for something already known or already derivable.
- Tolerate `oui`, `non`, `jsp`, `je sais pas`, typos, missing accents, and three facts crammed into
  one sentence.

### 4.3 The target feeling

> « Je parle à quelqu'un qui comprend mon problème. »

Not a form. Not a chatbot. Not a company pushing a sale.

### 4.4 The shape of a good opening

```
Homeowner: Bonjour, j'ai beaucoup de condensation chez moi et des taches noires
           commencent à apparaître.

Agent:     Bonjour, je comprends. La condensation et les taches noires peuvent avoir
           plusieurs causes. Je vais vous poser quelques petites questions pour mieux
           comprendre. Vous êtes propriétaire du logement ?
```

One acknowledgement, one honest framing, one question. That is the whole register.

### 4.5 Extraction must run on every message

```
Homeowner: Bonjour j'ai 63 ans ma maison est à Angers et j'ai beaucoup de moisissure
           dans la chambre depuis cet hiver
```

must yield, before any reply is composed:

```
age ≈ 63 · city = Angers · property_type = maison · problem = moisissures
room = chambre · since = cet hiver (≈ winter)
```

and the next question must be one of the things still **missing**, never one of those.

### 4.6 Never claim

Put this list in the database as editable guardrails, and inject it into the system prompt on every
turn. It is not RAG — it must always be present, never retrieved.

The agent must never:

- diagnose the house remotely with confidence — « votre problème vient d'une mauvaise ventilation »
  is forbidden; « cela peut avoir plusieurs causes » is correct
- promise savings, amounts, subsidies, eligibility for MaPrimeRénov' or any aid, or renovation prices
- claim to be, or to act for, a government service or a public agency
- make health claims about mould or indoor air
- invent an appointment, a technician, a name, or a delay
- deny being an AI if asked directly

If asked « Vous êtes une vraie personne ? » answer plainly and warmly, in one line, then continue.

### 4.7 Different problems, different conversations

Do not run one script. The agent picks the next question from the problem family. Minimum coverage:

| Family | Explore |
|---|---|
| Humidité / moisissures / condensation | where in the home · how long · condensation on windows · smell · visible damp on walls · ventilation present · house or flat · owner |
| Infiltration | where · after rain · roof / wall / window · how long · already repaired |
| Chauffage | current system · energy source · age of system · bill size · cold rooms · breakdowns |
| Facture élevée (gaz / élec / fioul) | which energy · rough monthly or annual amount · heating type · insulation suspected · house age |
| Fenêtres / courants d'air | age of windows · single or double glazing · how many · drafts · condensation on glass · noise |
| Isolation | where it feels cold · attic / walls / floor · house age · previous work |
| Solaire | house · roof orientation if known · electricity consumption · existing panels · motivation |

Let the LLM choose the next question within the family. Let **code** decide when the required fields
are complete and the lead can be handed over.

---

## 5. RAG — think before you reach for embeddings

The brief that produced this file asked for RAG. Here is the decision, with the reasoning, so you do
not default to "vector DB + embeddings" out of habit.

### 5.1 Split the knowledge four ways

| Kind | Where it lives | Why |
|---|---|---|
| Lead fields, conversation state, attribution | Postgres columns + `parameter_value` links | must be queryable, joined, counted, and must never depend on semantic recall |
| Qualification rules, required fields, routing, dedupe, status transitions | **deterministic PHP** | a business rule that sometimes works is not a business rule |
| Persona, tone, the never-claim list, the one-question rule | system prompt, assembled from DB-backed, admin-editable fragments | must be present on *every* turn — retrieval that misses is a compliance incident |
| Educational explanations, causes, FAQ, objection handling, coverage, process | **the retrieved knowledge base** | genuinely open-ended, genuinely long-tail, genuinely worth retrieving |

Only the fourth row is RAG. That is the whole point of this section.

### 5.2 Retrieval: use Postgres, not a vector database

`pgvector` is **not installed** on this machine's PostgreSQL 18. `pg_trgm`, `unaccent` and the
`french` text-search configuration *are*.

The knowledge base for V1 is roughly **60–150 short passages**. At that size, embedding search buys
very little and costs real money, latency, and an extra processor of personal data. Build this
instead:

1. **Tag-first filtering.** Every passage carries structured metadata — `problem_family`, `product`,
   `content_type` (`education` | `faq` | `objection` | `process` | `coverage` | `guardrail`),
   `audience`, `is_active`. The conversation state already knows the problem family. Filter on it.
2. **Rank inside the filtered set** with Postgres French full-text search
   (`to_tsvector('french', …)`) over the last user message, with `unaccent` so `facade` matches
   `façade`, and `pg_trgm` similarity as a fallback for heavy typos.
3. Return **at most 3 passages, hard-capped at ~700 tokens total.**

This is deterministic, debuggable, costs nothing per turn, adds no network hop, and keeps homeowner
messages away from a third-party embedding API — which matters for §8.

**Add embeddings later only if measurement shows retrieval is actually missing**, and when you do,
install `pgvector` and put the vectors in the same table as a second ranking signal. Design the
retrieval interface so that swap is a one-class change. Say so in a comment; do not build it now.

### 5.3 What a passage looks like

Short, standalone, French, written for a human-sounding agent to paraphrase — never to recite:

```yaml
slug: condensation-fenetres-causes
problem_family: humidite
content_type: education
title: Condensation sur les fenêtres
body: |
  De la buée ou des gouttes sur les vitres, surtout le matin en hiver, vient
  souvent d'un excès d'humidité dans l'air combiné à des surfaces froides.
  Les causes possibles sont multiples : ventilation insuffisante, vitrage
  ancien, ou simplement la vie quotidienne (cuisine, douche, séchage du linge).
  Seul un professionnel sur place peut déterminer la cause réelle.
never_claim: ne jamais affirmer une cause unique sans visite
```

**Instruct the model to use retrieved passages as grounding, not as script.** The reply stays short
even when the passage is long.

### 5.4 Seed the knowledge base with this content

Write these as seeder rows so an admin can edit them afterwards. Expand each into 2–4 short
passages; this is the skeleton of the domain, in the client's own words.

**Humidité / moisissures / condensation**
Symptoms: taches noires dans les angles, autour des fenêtres, derrière les meubles · buée sur les
vitres · odeur de renfermé · papier peint qui se décolle · murs froids au toucher.
Possible causes (always plural, never asserted): ventilation insuffisante, humidité ascensionnelle,
infiltration, pont thermique, vitrage ancien, habitudes du quotidien.
Useful questions: où exactement · depuis quand · pire en hiver · buée sur les vitres · y a-t-il une
VMC ou des grilles d'aération · maison ou appartement · propriétaire.
Never: affirmer la cause · parler des effets sur la santé · promettre une élimination définitive.

**Infiltration**
Symptoms: auréoles au plafond, trace qui s'agrandit après la pluie, mur humide par endroits.
Questions: après la pluie · toiture, façade ou fenêtre · depuis quand · déjà réparé.
Never: estimer un coût de réparation à distance.

**Mauvaise isolation / maison froide**
Symptoms: pièces difficiles à chauffer, sensation de froid près des murs ou du sol, chauffage qui
tourne en permanence, factures qui montent.
Questions: quelles pièces · combles, murs ou sol · âge du logement · travaux déjà faits.
Never: annoncer un gain de chauffage chiffré.

**Chauffage ancien / panne**
Context: chaudière fioul ou gaz de plus de 15 ans, radiateurs électriques anciens, pompe à chaleur
existante. Questions: quel système · quelle énergie · âge · pannes récentes · confort.
Never: promettre une aide, un montant, ou une éligibilité.

**Facture élevée (gaz / électricité / fioul)**
Questions: quelle énergie · montant approximatif par mois ou par an · type de chauffage · logement
bien isolé ou non · nombre de personnes.
Never: garantir une économie · citer un pourcentage de réduction.

**Fenêtres / courants d'air / bruit**
Symptoms: courant d'air près des fenêtres, buée entre les vitres, bruit de la rue, fenêtres qui
ferment mal. Questions: âge des fenêtres · simple ou double vitrage · combien · quelles pièces.

**Solaire**
Questions: maison individuelle · consommation d'électricité approximative · toiture · déjà équipé ·
ce qui motive le projet.
Never: promettre une production, un revenu de revente, ou une prime.

**Process / trust (content_type: `process`)**
- Comment ça se passe : quelques questions, puis un conseiller rappelle pour convenir d'un
  rendez-vous si c'est pertinent.
- Le passage d'un professionnel permet d'établir un vrai diagnostic ; à distance on ne peut
  qu'évoquer des pistes.
- Pas d'engagement à ce stade.

**Objections (content_type: `objection`)**
« C'est pour me vendre quelque chose ? » · « Combien ça coûte ? » · « Je veux juste des
renseignements » · « Vous êtes qui exactement ? » · « Je ne donne pas mes informations » ·
« J'ai déjà eu des appels » — write a short, honest, non-pushy answer for each, and in every case
offer the exit politely.

---

## 6. Data model

Design it yourself, but it must support everything below. Follow the repo's conventions: relational
targeting, archive over delete, timestamps, `created_by` where a human acted.

**`whatsapp_conversations`** — one per `wa_id` per campaign episode
`wa_id` (normalised E.164) · `provider` · `provider_conversation_id` · `status`
(`active` | `awaiting_human` | `closed`) · `locale` · `last_inbound_at` · `last_outbound_at` ·
`summary` (rolling, regenerated, not on every turn) · `consent_at` · attribution block (raw referral
JSON + resolved `creative_id`, `campaign_id`, `channel_id`) · timestamps.

**`whatsapp_messages`** — immutable log
`conversation_id` · `direction` · `provider_message_id` (**unique — this is your idempotency key**) ·
`type` (`text` | `audio` | `image` | `interactive` | `system`) · `body` · `raw` JSON ·
`status` (`received` | `queued` | `sent` | `delivered` | `read` | `failed`) · `sent_at` · timestamps.

**`leads`**
`conversation_id` · identity: `first_name`, `last_name`, `phone_e164`, `postal_code`, `city`,
`department` (derive from postal code — it is the first two digits; store both) ·
`qualification_status` (`new` | `in_progress` | `qualified` | `appointment_requested` |
`appointment_booked` | `not_qualified` | `lost`) · `product_id` · `missing_required_fields` (array) ·
`summary` · `qualified_at` · `handed_off_at` · attribution · free-text `raw_notes` · timestamps.

**`lead_parameters`** — the mirror of `creative_parameters`
`lead_id` · `parameter_category_id` · `parameter_value_id` · `confidence`
(`stated` | `inferred`) · `source_message_id`.
This table is what makes "which tree branch produces qualified leads" answerable. Do not skip it.

**`knowledge_passages`**
`slug` (unique) · `title` · `body` · `problem_family` · `product_id` (nullable) · `content_type` ·
`audience` · `never_claim` · `is_active` · `updated_by` · timestamps · a generated `tsvector` column
with a GIN index.

**`agent_guardrails`** (or reuse `prompt_templates` with a purpose) — the always-present system
prompt fragments: persona, tone rules, never-claim list, disclosure line.

Minimum required to call a lead *qualified*: **full name + phone + postal code**, plus at least one
identified problem. Everything else is enrichment. Make the required set a config constant, not a
scatter of `if` statements.

---

## 7. Transport, reliability and the turn

### 7.1 Provider abstraction

```php
interface WhatsAppProvider {
    public function key(): string;
    public function verifyWebhook(Request $request): bool;   // signature, not just a token
    public function parse(Request $request): array;           // → normalised InboundMessage[]
    public function sendText(string $waId, string $body): SentMessage;
    public function capabilities(): array;
}
```

Implement **one** provider for V1 — WhatsApp Cloud API (Meta) is the right default for
Click-to-WhatsApp ads; if you judge a BSP (360dialog, Twilio) materially simpler to operate, choose
it and justify the choice in a comment. Verify the `X-Hub-Signature-256` HMAC. Follow
`CLAUDE.md`: a provider that cannot do something says so in `capabilities()` — never fake it.

### 7.2 The webhook is not a script

Required behaviour, and the tests must prove it:

- **Respond 200 within ~2 seconds, always.** Persist the raw payload, dispatch a job, return.
  A slow LLM must never cause Meta to retry.
- **Idempotency** on `provider_message_id`. Meta retries; duplicates must be no-ops.
- **Ordering and concurrency:** serialise per conversation. Use a queue with a per-`wa_id` lock
  (`WithoutOverlapping` keyed on the conversation) so two fast messages cannot produce two
  interleaved replies.
- **Debounce:** if a user sends three fragments in ten seconds, wait briefly and answer once.
  A separate reply per fragment is the clearest sign of a bot.
- **Outbound failures:** retry with backoff; after exhaustion mark the message `failed`, log, and
  surface it in the admin — never silently drop.
- **24-hour window:** outside Meta's customer-service window, free-form sends are rejected. Detect
  it, do not pretend the message went out, and flag the conversation for human follow-up.
- **Rate limits:** respect 429 / `Retry-After`.
- Audio messages: V1 may reply asking for text — but detect the type and say something human
  (« Je n'arrive pas à écouter les messages vocaux, pouvez-vous m'écrire en quelques mots ? »).
  Do not stay silent.

### 7.3 The LLM turn

One call per inbound turn, returning structured output:

```json
{
  "extracted": { "<field>": "<value>", "...": "..." },
  "taxonomy": [ { "category": "specific-problem", "value_slug": "condensation",
                  "confidence": "stated" } ],
  "reply": "D'accord, merci. Et depuis combien de temps voyez-vous ces taches ?",
  "next_target_field": "problem_duration",
  "handoff_requested": false,
  "out_of_scope": false
}
```

Use the SDK's structured-output support so this parses deterministically. Then:

- **Code**, not the model, writes the fields, decides whether required fields are complete, sets
  status, and decides whether to hand off.
- If the model returns a `reply` longer than the limit, or containing more than one `?`, regenerate
  once with a terser instruction, then fall back to a safe templated question. Never ship a wall of
  text.
- Map `taxonomy[].value_slug` against real `parameter_values`; ignore anything that does not match,
  and keep the raw phrase in `raw_notes`.

**Context budget per turn — do not exceed without measuring:**
system prompt + guardrails (~600 tokens, cached) · rolling conversation summary (~150) · last 6–8
messages verbatim · ≤3 retrieved passages (~700) · the current lead state as compact JSON.
**Never send the full history or the whole knowledge base.** Regenerate the rolling summary every
~10 messages, not every turn.

### 7.4 Model choice

Default to **`claude-opus-5`** via the existing `PromptProviderRegistry` — quality of the French and
of the judgement is the whole product, and a bad first message costs a lead.

This is the one real cost lever in the system, so make it measurable and make it the owner's
decision, not a silent default: log tokens per turn and cost per qualified lead, and surface them.
`claude-sonnet-5` is the step down if the numbers justify it, and `claude-haiku-4-5` is defensible
for an extraction-only path if you later split extraction from composition. Put the choice in
`ai_models` so it is changed in the UI, not in code. Use prompt caching on the stable system prefix.

---

## 8. Privacy, compliance and trust

This processes personal data of French residents through a US platform. Build accordingly; this is
not optional polish.

- **Lawful basis and notice.** The first agent message must identify the business by name, say in
  one short line what the data is used for, and link to a privacy notice. Record `consent_at` and
  what version of the notice was shown.
- **Disclosure.** Do not claim to be human. If asked, say plainly that it is an automated assistant
  and a human will follow up.
- **Data minimisation.** Collect only what qualifies a lead. Never ask for date of birth, income,
  household composition beyond what the taxonomy needs, health information, or anything about
  vulnerability. If a homeowner volunteers something sensitive — illness, bereavement, money
  trouble — acknowledge kindly, **do not store it in structured fields, and do not repeat it back**.
- **Retention.** Configurable; default to deleting raw message bodies after 12 months while keeping
  the lead and its summary. Write the pruning command.
- **Right of access / erasure.** An artisan command that exports or deletes everything for a given
  `wa_id`. It is an afternoon of work and it is the difference between compliant and not.
- **Processor exposure.** Record, in the README, exactly which third parties see message content:
  Meta (transport) and the chosen model provider. Prefer a model provider configured for zero
  retention. This is another reason §5.2 keeps embeddings out of V1 — one fewer processor.
- **Secrets.** Webhook secret, app secret and model keys in `.env` only. Never log message bodies at
  `info` level; never log the full payload with phone numbers in plain text.
- **Opt-out.** `STOP` / « arrêtez » / « ne me contactez plus » must close the conversation, mark the
  lead `lost`, suppress further outbound, and confirm once, politely.

---

## 9. Handoff and the commercial summary

A salesperson must understand the lead in **fifteen seconds, without reading the conversation.**

Generate and store a summary in this shape — French, factual, no sales language:

```
Marie D. · 49000 Angers (49) · 06 XX XX XX XX
Propriétaire · maison · construite vers 1975

Problème : moisissures dans la chambre et condensation sur les fenêtres,
depuis l'hiver dernier. Pas de VMC. A déjà repeint, le problème est revenu.

Chauffage : gaz, chaudière d'environ 18 ans. Facture ~180 €/mois.
Intérêt : demande un diagnostic. Disponible en semaine après 17 h.

Qualifiée : nom, téléphone et code postal obtenus, problème identifié,
propriétaire occupant.

Source : WhatsApp · campagne humidite_octobre · créa PAC-W-60-69-CONDENS-COMFORT-WA-003
```

Trigger handoff when **either** the required fields are complete **or** the homeowner asks for a
human — « je voudrais parler à quelqu'un », « vous pouvez m'appeler ? ». Set
`qualification_status`, stamp `handed_off_at`, and make it visible in the admin list. Tell the
homeowner plainly what happens next and roughly when; never invent a specific time or a named person.

Also hand off — and say so honestly — when the homeowner is clearly out of scope (tenant with no
decision power, outside coverage, a question the agent should not answer). Mark `not_qualified`
with a reason. A clean "no" is worth more than a padded "maybe".

---

## 10. Admin surface

Reuse the existing Inertia/React patterns and `Ui.jsx`. Three screens, no more:

1. **Conversations** — list with status, last message, attribution; detail shows the transcript, the
   extracted lead state side by side, and which knowledge passages were retrieved per turn.
   That last part is your debugging tool; do not skip it.
2. **Leads** — list filtered by status, with the summary and a "marquer comme traité" action.
3. **Knowledge** — CRUD on passages: title, body, family, type, active toggle, last updated. Keep it
   as plain as the existing `/admin` taxonomy screens.

Empty states follow the house rule: say what the area is, why it matters, and the next action.

---

## 11. Observability

Enough to run it, no more: messages in/out per day, replies generated, median turn latency, tokens
and cost per conversation, retrieval hits per turn, LLM and provider errors, handoffs, conversations
that reached qualified, and the drop-off point for those that did not. Counters and a log, not a
dashboard product.

---

## 12. Tests — required before you call it done

Use the existing PHPUnit setup against PostgreSQL, with the LLM and the WhatsApp provider faked the
way `AiPromptTest` and `GenerationProviderTest` already fake theirs.

1. **Webhook reliability:** duplicate `provider_message_id` is a no-op · invalid signature is
   rejected · the endpoint returns 200 before the model is called · two rapid messages produce one
   reply, in order.
2. **Extraction:** the Angers example in §4.5 fills the right fields and the next question is not one
   already answered.
3. **Qualification:** required-fields logic, status transitions, a lead that stays incomplete is
   still persisted.
4. **Taxonomy mapping:** « j'ai de la buée sur les vitres » maps to the `condensation` value and
   links a `lead_parameters` row; an unmappable phrase is kept in `raw_notes` and drops nothing.
5. **Guardrails:** given a message asking « j'aurai droit à MaPrimeRénov ? », the composed system
   prompt contains the never-claim block, and a canned non-committal answer path exists.
6. **Retrieval:** a humidity message retrieves humidity passages and **not** the solar ones; the cap
   of 3 / ~700 tokens holds.
7. **Attribution:** a first message carrying a CTWA referral resolves to the right creative; a
   missing referral still creates a lead.
8. **Opt-out:** « STOP » closes the conversation and suppresses outbound.
9. **One scripted end-to-end conversation** — eight to twelve turns, from ad click to handoff —
   asserting the final lead state and the generated summary.

Then: `php artisan test`, `./vendor/bin/pint`, `npm run build`.

---

## 13. Acceptance — V1 is done when this works

```
WhatsApp Status ad  →  « Envoyer un message »
  →  agent greets, identifies the problem family
  →  asks one short question at a time, never repeating what it knows
  →  obtains NOM + TÉLÉPHONE + CODE POSTAL
  →  enriches with property, heating, duration, severity, timing
  →  lead saved, attributed to a creative, status qualified
  →  a fifteen-second summary exists for the commercial team
  →  handoff flagged
```

with the conversation reading, to a 65-year-old homeowner in Angers, like a patient human being.

---

## 14. Decisions that are yours

Make them, record them in a short `docs/WHATSAPP-AGENT.md`, and do not come back to ask:

- WhatsApp provider (Cloud API vs a BSP) and why
- Whether `Isolation / Humidité` becomes a fourth product
- The exact lead schema and status machine
- Debounce window, summary cadence, retention default
- Whether extraction and composition are one LLM call or two
- Whether `creative_metrics` is written automatically or on an admin action

## 15. Decisions that are not yours

- Do not add roles or permissions
- Do not store the persona as JSON blobs or free text where the taxonomy applies
- Do not install a vector database in V1
- Do not fake a provider capability or a delivery
- Do not let the agent diagnose, promise aid, or imply it is a public service
- Do not delete anything history points at — archive, per `CLAUDE.md`

---

**Start by reading `CLAUDE.md`, `README.md`, `app/Services/Ai/`, `app/Services/Generation/` and
`database/seeders/TaxonomySeeder.php`. Then show the plan — schema and the turn pipeline — before
writing migrations.**
