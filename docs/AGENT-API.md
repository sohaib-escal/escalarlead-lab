# Agent API — integrating the WhatsApp gateway

The gateway (zailer) owns WhatsApp: instances, the webhook, media download, the 24-hour window and
sending. This owns the conversation: understanding, qualification, knowledge, and what to say next.
Neither needs to know how the other works.

```
Meta ad (WhatsApp Status)  →  Click-to-WhatsApp
      ↓
zailer  — receives the webhook, downloads media, returns 200 to Meta fast
      ↓   POST /api/agent/turn        (signed)
the agent — understands, qualifies, decides the reply
      ↓   POST <callback_url>         (signed the same way)
zailer  — sends the reply on WhatsApp
```

---

## Authentication

Every request in both directions is signed with one shared secret, `AGENT_API_SECRET`.

```
X-Agent-Timestamp: 1759699200
X-Agent-Signature: sha256=<hmac_sha256("{timestamp}.{raw body}", secret)>
```

- The timestamp must be within **300 seconds**, so a captured request is useless tomorrow.
- Signatures are compared in constant time.
- **With no secret configured the route returns 503 and does nothing** — there is no unauthenticated
  mode for an endpoint that creates leads and spends model credits.

PHP:

```php
$body      = json_encode($payload);
$timestamp = time();
$signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, config('services.agent.secret'));
```

The callback the agent sends you is signed identically — **verify it the same way**, or anyone who
learns the URL can put words in the agent's mouth.

---

## `POST /api/agent/turn`

### Request

```jsonc
{
  "channel": "whatsapp",
  "contact": { "wa_id": "33612345678", "name": "Marie" },

  "message": {
    "id": "wamid.HBgLMzM2...",          // the provider's message id — the idempotency key
    "text": "jai des taches noires dans la chambre",
    "images": [
      { "base64": "...", "mime": "image/jpeg", "filename": "photo.jpg" }
      // or, if you would rather we fetch it:
      // { "url": "https://.../media.jpg", "mime": "image/jpeg" }
    ]
  },

  "referral": { /* the Click-to-WhatsApp referral object, verbatim */ },
  "creative_reference": "PAC-W-60-69-HIGHBILL-AID-WA-001",

  "callback_url": "https://whatsapp.zailer.ma/agent/reply",
  "model": "gemini-3.5-flash"           // optional override
}
```

Only `contact.wa_id` and one of `message.text` / `message.images` are required.

### Response — asynchronous (recommended)

With a `callback_url`, the turn is queued and you get an immediate **202**:

```json
{ "accepted": true, "conversation_id": 42, "message_id": "wamid.HBgLMzM2...", "duplicate": false }
```

**Use this path for WhatsApp.** A model call can take thirty seconds; a webhook cannot. Answer Meta
immediately, hand the message here, and send the reply when the callback arrives.

### Response — synchronous

Omit `callback_url` and the reply comes back in the same response. Convenient for testing; you own
the timeout, and 30s+ is possible.

```json
{
  "conversation_id": 42,
  "message_id": "wamid.HBgLMzM2...",
  "duplicate": false,
  "reply": "Je comprends pour ces taches noires. Dans quelle pièce apparaissent-elles ?",
  "conversation_status": "active",
  "closed": false,
  "handoff": false,
  "lead": {
    "id": 17,
    "status": "in_progress",
    "qualified": false,
    "missing": ["first_name", "last_name", "postal_code"],
    "full_name": null,
    "postal_code": null,
    "summary": null
  },
  "attribution": { "creative_id": 3, "campaign_id": 1 }
}
```

### The callback

```jsonc
{
  "conversation_id": 42,
  "message_id": "wamid.HBgLMzM2...",
  "reply": "Je comprends pour ces taches noires. Dans quelle pièce apparaissent-elles ?",
  "conversation_status": "awaiting_human",
  "closed": false,
  "handoff": true,
  "lead": { "status": "qualified", "qualified": true, "full_name": "Marie Dubois",
            "postal_code": "49000", "summary": "Marie Dubois · 49000 Angers\n…" }
}
```

On failure you get the same shape with `"reply": null` and `"error": "agent_failed"`. **You are
always told** — the gateway is never left waiting for a callback that is not coming.

---

## The three fields that drive your behaviour

| Field | What you do |
|---|---|
| `reply` | Send it. If `null`, send nothing. |
| `closed` | The person opted out. **Stop sending.** Suppress future outbound to this number. |
| `handoff` | The lead is qualified or asked for a human. Route it to the commercial team. |

---

## Idempotency

Pass `message.id` and retries are free: a repeated delivery returns the original reply with
`"duplicate": true`, with **no second model charge, no second lead, no second message**.

One subtlety worth knowing: if a first attempt was interrupted *before* it answered — a crash, a
restart, a timeout — the retry is treated as a genuine retry rather than replayed as silence.
Replaying nothing would leave the homeowner waiting forever.

---

## Attribution

The point of all this is to know which ad produced which lead.

- **Best:** send `creative_reference` — the creative's identifier from Creative Lab,
  e.g. `PAC-W-60-69-HIGHBILL-AID-WA-001`. Set it when you build the ad so you have it at webhook time.
- **Fallback:** send the `referral` object verbatim. A reference-shaped string anywhere in it
  (including inside `source_url`) is matched automatically.
- **Neither:** the lead is still created and the raw payload kept. Attribution stays null — a guessed
  attribution is worse than none, because it credits the wrong branch of the tree.

Attribution is resolved once, on the first message that carries it.

---

## Images

Send `base64` when you have already downloaded the media (you hold the instance credentials, so this
is usually simplest), or `url` and the agent fetches it.

- Up to **3 images** per message, **5 MB** each — `config/agent.images`.
- `image/jpeg`, `image/png`, `image/webp`, `image/heic`, `image/heif`.
- An unreadable or unreachable image returns **422** rather than being quietly ignored.
- If the configured model cannot read images, the photo is recorded as received and flagged
  `ignored_by_provider` — never silently dropped.

What the model saw is written to the lead's notes, so the salesperson sees it without opening the file.

---

## Running it

```bash
php artisan serve --port=8321     # or your real web server
php artisan queue:work            # REQUIRED for the async path
```

The queue is `database`. **Without a worker, queued turns never run and no callback is ever sent.**

Per-conversation locking (`WithoutOverlapping`) means two fast messages from the same person are
answered in order, never interleaved.

### Environment

```dotenv
AGENT_API_SECRET=        # shared with zailer — required, no default
AGENT_CALLBACK_URL=      # optional default callback if the caller omits one
GEMINI_API_KEY=          # or ANTHROPIC_API_KEY / OPENAI_API_KEY
```

---

## Try it

```bash
SECRET=$(grep '^AGENT_API_SECRET=' .env | cut -d= -f2-)
TS=$(date +%s)
BODY='{"contact":{"wa_id":"33612345678"},"message":{"id":"test-1","text":"jai des taches noires"}}'
SIG="sha256=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')"

curl -s -X POST http://127.0.0.1:8321/api/agent/turn \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-Agent-Timestamp: $TS" -H "X-Agent-Signature: $SIG" -d "$BODY" | jq
```

Or skip HTTP entirely while tuning the conversation: `php artisan agent:chat`, and
`/agent` in the browser for the console with the working shown.
