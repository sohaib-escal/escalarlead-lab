<?php

namespace App\Services\Agent;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Services\Ai\ImageInput;
use App\Services\Ai\Providers\PromptProviderRegistry;
use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Knowledge\RetrievalQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * One inbound message in, one reply out.
 *
 * The model does two things: read what the homeowner just said, and write the
 * next sentence. Everything that decides anything — which field to chase, when
 * a lead is complete, when a human takes over — is code.
 */
class ConversationAgent
{
    public function __construct(
        private readonly PromptProviderRegistry $providers,
        private readonly KnowledgeRetriever $retriever,
        private readonly AgentPromptBuilder $prompts,
    ) {}

    /**
     * @param  array<int, ImageInput>  $images
     */
    public function handle(
        Conversation $conversation,
        string $inbound,
        ?AiModel $model = null,
        array $images = [],
        ?string $externalId = null,
    ): AgentTurn {
        $model ??= AiModel::default() ?? throw new RuntimeException('Aucun modèle IA actif.');
        $provider = $this->providers->get($model->provider);

        if (! $provider->isConfigured()) {
            throw new RuntimeException(
                $provider->label().' n\'est pas configuré : ajoutez sa clé API dans .env.'
            );
        }

        // A provider that cannot read images must say so rather than quietly
        // dropping them and answering as if nothing was sent. Keep the record of
        // what actually arrived, separately from what we can send on.
        $received = array_slice($images, 0, config('agent.images.max_per_message'));
        $imagesIgnored = $received !== [] && ! $provider->supportsImages();
        $images = $imagesIgnored ? [] : $received;

        $lead = $this->leadFor($conversation);

        $inboundMessage = $conversation->messages()->create([
            'direction' => ConversationMessage::INBOUND,
            // The gateway's message id. Unique in the schema, so a retried
            // delivery cannot create a second turn.
            'external_id' => $externalId,
            'type' => $images !== [] ? 'image' : 'text',
            'body' => $inbound,
            'status' => 'received',
            'raw' => $received === [] ? null : [
                'images' => array_map(fn (ImageInput $image) => [
                    'mime' => $image->mime,
                    'filename' => $image->filename,
                    'bytes' => $image->bytes(),
                    'url' => $image->url,
                ], $received),
                'ignored_by_provider' => $imagesIgnored,
            ],
        ]);
        $conversation->update(['last_inbound_at' => now()]);

        // Knowledge is retrieved on what was just said, filtered by what the
        // conversation is already known to be about.
        // A photo with no caption has no words to search on, so fall back to
        // whatever the conversation is already about.
        $knowledge = $this->retriever->retrieve(new RetrievalQuery(
            text: $inbound,
            problemFamily: $conversation->problem_family,
        ));

        $state = new LeadState($lead);
        $system = $this->prompts->system();
        $user = $this->prompts->user(
            $conversation, $state, $knowledge, $inbound, $this->vocabulary(), count($images),
        );

        $payload = $this->ask($provider, $system, $user, $model->model_id, $images);

        return DB::transaction(function () use (
            $conversation, $lead, $inboundMessage, $payload, $knowledge, $model, $provider, $images
        ) {
            $this->applyExtraction($conversation, $lead, $payload, $inboundMessage);

            $reply = $this->safeReply($payload['reply'] ?? null, $lead, $this->replyLimit($images));

            $outbound = $conversation->messages()->create([
                'direction' => ConversationMessage::OUTBOUND,
                'body' => $reply,
                'status' => 'sent',
                'meta' => [
                    'model' => $model->model_id,
                    'provider' => $provider->key(),
                    'retrieval' => $knowledge->toLog(),
                    'extracted' => array_filter($payload['extracted'] ?? []),
                    'taxonomy' => $payload['taxonomy'] ?? [],
                    'images' => count($images),
                    'image_observations' => $payload['image_observations'] ?? null,
                ],
            ]);

            $conversation->update(['last_outbound_at' => now()]);

            $this->applyRules($conversation, $lead, $payload);

            return new AgentTurn(
                reply: $reply,
                lead: $lead->fresh(['parameters.value', 'parameters.category']),
                conversation: $conversation->fresh(),
                knowledge: $knowledge,
                extracted: array_filter($payload['extracted'] ?? []),
                message: $outbound,
            );
        });
    }

    /**
     * Ask once; if the reply breaks the shape rules, ask once more with a
     * blunter instruction. After that, fall back to a safe templated question
     * rather than sending a wall of text to a homeowner.
     *
     * @return array<string, mixed>
     */
    private function ask($provider, string $system, string $user, string $modelId, array $images = []): array
    {
        $limit = $this->replyLimit($images);

        $payload = $this->decode($provider->complete($system, $user, $modelId, $images)->text);

        if ($this->replyIsAcceptable($payload['reply'] ?? null, $limit)) {
            return $payload;
        }

        $retry = $user."\n\nRAPPEL : votre réponse précédente était trop longue ou contenait plusieurs questions. "
            ."Répondez en {$limit} caractères maximum, avec une seule question.";

        $second = $this->decode($provider->complete($system, $retry, $modelId, $images)->text);

        return $this->replyIsAcceptable($second['reply'] ?? null, $limit) ? $second : $payload;
    }

    /**
     * A photo earns a little more room: observation, possible causes, question.
     *
     * @param  array<int, ImageInput>  $images
     */
    private function replyLimit(array $images): int
    {
        return $images === []
            ? config('agent.reply.max_chars')
            : config('agent.reply.max_chars_with_image');
    }

    /**
     * Models wrap JSON in prose or code fences often enough that this has to be
     * defensive — but it must never invent a payload.
     *
     * @return array<string, mixed>
     */
    private function decode(string $raw): array
    {
        $text = trim($raw);
        $text = (string) preg_replace('/^```(?:json)?|```$/m', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false) {
            throw new RuntimeException('Le modèle n\'a pas renvoyé de JSON exploitable.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Le modèle a renvoyé un JSON invalide.');
        }

        return $decoded;
    }

    private function replyIsAcceptable(?string $reply, ?int $limit = null): bool
    {
        if (blank($reply)) {
            return false;
        }

        return mb_strlen($reply) <= ($limit ?? config('agent.reply.max_chars'))
            && substr_count($reply, '?') <= config('agent.reply.max_questions');
    }

    private function safeReply(?string $reply, Lead $lead, int $limit): string
    {
        if ($this->replyIsAcceptable($reply, $limit)) {
            return trim($reply);
        }

        if (filled($reply)) {
            // Keep the acknowledgement and the first question, drop the rest.
            $cut = Str::of($reply)->limit($limit, '')->trim();

            if ($this->replyIsAcceptable((string) $cut, $limit)) {
                return (string) $cut;
            }
        }

        $target = (new LeadState($lead))->nextTarget();

        return match ($target) {
            'first_name', 'last_name' => 'Je comprends. Pour transmettre votre demande, quel est votre nom ?',
            'postal_code' => 'D\'accord, merci. Quel est votre code postal ?',
            null => 'Merci pour ces informations. Un conseiller va vous rappeler.',
            default => 'Je comprends. Pouvez-vous m\'en dire un peu plus ?',
        };
    }

    /**
     * Write what the model read. Anything it could not map to a real taxonomy
     * value is kept verbatim rather than dropped.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyExtraction(
        Conversation $conversation,
        Lead $lead,
        array $payload,
        ConversationMessage $source,
    ): void {
        $extracted = array_filter($payload['extracted'] ?? [], fn ($v) => $v !== null && $v !== '');

        $direct = ['first_name', 'last_name', 'postal_code', 'city'];
        $updates = [];

        foreach ($direct as $field) {
            if (blank($lead->{$field}) && filled($extracted[$field] ?? null)) {
                $updates[$field] = is_string($extracted[$field]) ? trim($extracted[$field]) : $extracted[$field];
            }
        }

        // The département is the first two digits of the postal code — derived,
        // never asked for.
        if (isset($updates['postal_code']) && preg_match('/^\d{5}$/', $updates['postal_code'])) {
            $updates['department'] = substr($updates['postal_code'], 0, 2);
        }

        $details = $lead->details ?? [];
        foreach (array_diff_key($extracted, array_flip($direct)) as $key => $value) {
            if (! isset($details[$key])) {
                $details[$key] = $value;
            }
        }
        $updates['details'] = $details;

        $lead->update($updates);

        if (filled($payload['problem_family'] ?? null) && $payload['problem_family'] !== 'null'
            && blank($conversation->problem_family)) {
            $family = $payload['problem_family'];

            if (array_key_exists($family, config('knowledge.families'))) {
                $conversation->update(['problem_family' => $family]);
            }
        }

        if (filled($payload['image_observations'] ?? null)) {
            $lead->update([
                'raw_notes' => trim(($lead->raw_notes ?? '')."\nPhoto : ".trim($payload['image_observations'])),
            ]);
        }

        $this->applyTaxonomy($lead, $payload['taxonomy'] ?? [], $source);
    }

    /**
     * @param  array<int, array<string, string>>  $taxonomy
     */
    private function applyTaxonomy(Lead $lead, array $taxonomy, ConversationMessage $source): void
    {
        foreach ($taxonomy as $entry) {
            $slug = $entry['value_slug'] ?? null;
            $categorySlug = $entry['category'] ?? null;

            if (blank($slug)) {
                continue;
            }

            $value = ParameterValue::query()
                ->notArchived()
                ->where('slug', $slug)
                ->when($categorySlug, fn ($q) => $q->whereHas(
                    'category',
                    fn ($c) => $c->where('slug', $categorySlug),
                ))
                ->first();

            // A slug the model invented is not a fact. Keep the attempt in the
            // notes so nothing the homeowner said is lost.
            if (! $value) {
                $lead->update([
                    'raw_notes' => trim(($lead->raw_notes ?? '')."\n".($categorySlug ?? '?').' = '.$slug),
                ]);

                continue;
            }

            $lead->parameters()->updateOrCreate(
                ['parameter_value_id' => $value->id],
                [
                    'parameter_category_id' => $value->parameter_category_id,
                    'confidence' => 'stated',
                    'source_message_id' => $source->id,
                ],
            );

            if ($value->product_id && blank($lead->product_id)) {
                $lead->update(['product_id' => $value->product_id]);
            }
        }
    }

    /**
     * The business rules. None of this is the model's decision.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyRules(Conversation $conversation, Lead $lead, array $payload): void
    {
        $lead->refresh();
        $state = new LeadState($lead);

        $lead->update(['missing_required_fields' => $state->missingRequired()]);

        if ($payload['opted_out'] ?? false) {
            $lead->update(['qualification_status' => 'lost']);
            $conversation->update(['status' => 'closed']);

            return;
        }

        $status = match (true) {
            $state->isComplete() => 'qualified',
            default => 'in_progress',
        };

        if ($payload['handoff_requested'] ?? false) {
            $status = $state->isComplete() ? 'appointment_requested' : $status;
            $conversation->update(['status' => 'awaiting_human']);
        }

        $lead->update([
            'qualification_status' => $status,
            'qualified_at' => $status === 'qualified' && ! $lead->qualified_at ? now() : $lead->qualified_at,
            'handed_off_at' => in_array($status, ['qualified', 'appointment_requested'], true) && ! $lead->handed_off_at
                ? now()
                : $lead->handed_off_at,
        ]);

        // Summarise only once the status is written, or every summary describes
        // the state one turn behind.
        $lead->update(['summary' => app(LeadSummariser::class)->summarise($lead->refresh())]);

        if ($state->isComplete() && $conversation->status === 'active') {
            $conversation->update(['status' => 'awaiting_human']);
        }
    }

    /**
     * `firstOrCreate` on the relation *query*, never `$conversation->lead`:
     * reading the accessor caches a null relation, and everything that reads it
     * afterwards — including the next turn — would see no lead at all.
     */
    private function leadFor(Conversation $conversation): Lead
    {
        $lead = $conversation->lead()->firstOrCreate([], [
            'qualification_status' => 'new',
            'phone_e164' => $conversation->channel === 'whatsapp' ? $conversation->external_id : null,
        ]);

        $conversation->setRelation('lead', $lead);

        return $lead;
    }

    /**
     * The slugs the model is allowed to use. Anything else is rejected on the
     * way back in, so the vocabulary is given up front to reduce the attempts.
     *
     * @return array<int, string>
     */
    private function vocabulary(): array
    {
        try {
            return ParameterCategory::query()
                ->whereIn('slug', ['specific-problem', 'problem', 'property-type', 'homeowner', 'heating-system', 'trigger'])
                ->with('activeValues:id,parameter_category_id,slug')
                ->get()
                ->flatMap(fn (ParameterCategory $category) => $category->activeValues
                    ->map(fn ($value) => $category->slug.':'.$value->slug))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
