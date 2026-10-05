<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAgentTurn;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Services\Agent\AttributionResolver;
use App\Services\Agent\ConversationAgent;
use App\Services\Agent\LeadState;
use App\Services\Ai\ImageInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The seam the WhatsApp gateway calls.
 *
 * One message in, one reply out. The gateway owns instances, media download,
 * the 24-hour window and sending; this owns understanding, qualification and
 * what to say next. Neither needs to know how the other works.
 */
class AgentTurnController extends Controller
{
    public function __construct(
        private readonly ConversationAgent $agent,
        private readonly AttributionResolver $attribution,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:32'],
            'contact.wa_id' => ['required', 'string', 'max:64'],
            'contact.name' => ['nullable', 'string', 'max:120'],

            'message.id' => ['nullable', 'string', 'max:191'],
            'message.text' => ['nullable', 'string', 'max:4000'],
            'message.images' => ['array', 'max:'.config('agent.images.max_per_message')],
            'message.images.*.base64' => ['nullable', 'string'],
            'message.images.*.url' => ['nullable', 'url', 'max:1000'],
            'message.images.*.mime' => ['nullable', Rule::in(ImageInput::SUPPORTED_MIMES)],
            'message.images.*.filename' => ['nullable', 'string', 'max:191'],

            'referral' => ['nullable', 'array'],
            'creative_reference' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],

            // Where to post the reply. Given one, the turn runs on the queue and
            // this returns immediately — which is the only shape that survives a
            // webhook deadline when a model call can take half a minute.
            'callback_url' => ['nullable', 'url', 'max:500'],
        ]);

        $text = (string) ($data['message']['text'] ?? '');
        $externalId = $data['message']['id'] ?? null;

        if (blank($text) && empty($data['message']['images'] ?? [])) {
            return response()->json([
                'error' => 'empty_message',
                'message' => 'A message needs text, an image, or both.',
            ], 422);
        }

        // Meta retries. A retry must never produce a second reply, a second
        // model charge, or a second lead.
        if ($externalId && $seen = ConversationMessage::where('external_id', $externalId)->first()) {
            if ($replay = $this->replay($seen, $externalId)) {
                return response()->json($replay);
            }

            // The first attempt was interrupted before it answered — a crash, a
            // timeout, a restart. Replaying silence would leave the homeowner
            // waiting forever, so drop the orphan and genuinely retry.
            $seen->delete();
        }

        $conversation = $this->conversationFor($data);

        try {
            $images = $this->images($data['message']['images'] ?? []);
        } catch (Throwable $e) {
            return response()->json(['error' => 'image_unreadable', 'message' => $e->getMessage()], 422);
        }

        $callback = $data['callback_url'] ?? config('agent.api.callback_url');

        if (filled($callback)) {
            ProcessAgentTurn::dispatch(
                $conversation->id,
                $text,
                array_map(fn (ImageInput $image) => [
                    'base64' => $image->base64,
                    'mime' => $image->mime,
                    'filename' => $image->filename,
                    'url' => $image->url,
                ], $images),
                $externalId,
                $callback,
                $data['model'] ?? null,
            );

            return response()->json([
                'accepted' => true,
                'conversation_id' => $conversation->id,
                'message_id' => $externalId,
                'duplicate' => false,
            ], 202);
        }

        // No callback: answer inline. Fine for the console and for testing,
        // but the caller owns the timeout.
        try {
            $turn = $this->agent->handle(
                $conversation,
                $text,
                filled($data['model'] ?? null) ? AiModel::where('model_id', $data['model'])->first() : null,
                $images,
                $externalId,
            );
        } catch (Throwable $e) {
            report($e);

            // The gateway must be able to tell "say nothing" from "say this".
            return response()->json([
                'error' => 'agent_failed',
                'message' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ], 502);
        }

        return response()->json($this->payload($turn->conversation, $turn->lead, $turn->reply, $externalId, false));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function conversationFor(array $data): Conversation
    {
        $channel = $data['channel'] ?? 'whatsapp';
        $waId = $this->normalisePhone($data['contact']['wa_id']);

        $conversation = Conversation::firstOrCreate(
            ['channel' => $channel, 'external_id' => $waId],
            ['status' => 'active'],
        );

        // Attribution is resolved once, on the first message that carries it.
        if (blank($conversation->creative_id) && (filled($data['referral'] ?? null) || filled($data['creative_reference'] ?? null))) {
            $resolved = $this->attribution->resolve($data['creative_reference'] ?? null, $data['referral'] ?? null);

            $conversation->update([
                'creative_id' => $resolved['creative_id'],
                'campaign_id' => $resolved['campaign_id'],
                // Keep the raw payload whether or not anything matched.
                'referral' => [
                    ...($data['referral'] ?? []),
                    'creative_reference' => $data['creative_reference'] ?? null,
                    'matched_on' => $resolved['matched_on'],
                ],
            ]);
        }

        return $conversation;
    }

    /**
     * WhatsApp gives the number without a plus; store it one way so a lead is
     * never duplicated by formatting.
     */
    private function normalisePhone(string $waId): string
    {
        $digits = preg_replace('/\D+/', '', $waId);

        return '+'.ltrim((string) $digits, '+');
    }

    /**
     * Images arrive as base64 from a gateway that already downloaded them, or
     * as a URL we fetch ourselves.
     *
     * @param  array<int, array<string, string|null>>  $images
     * @return array<int, ImageInput>
     */
    private function images(array $images): array
    {
        $max = config('agent.images.max_bytes');

        return collect($images)->map(function (array $image) use ($max) {
            if (filled($image['base64'] ?? null)) {
                $bytes = base64_decode($image['base64'], true);

                if ($bytes === false) {
                    throw new \RuntimeException('Image base64 could not be decoded.');
                }

                if (strlen($bytes) > $max) {
                    throw new \RuntimeException('Image exceeds the maximum size.');
                }

                return ImageInput::fromBinary(
                    $bytes,
                    $image['mime'] ?? 'image/jpeg',
                    $image['filename'] ?? null,
                    $image['url'] ?? null,
                );
            }

            $response = Http::timeout(config('agent.api.fetch_timeout'))->get($image['url']);

            if ($response->failed()) {
                throw new \RuntimeException('Image could not be downloaded.');
            }

            if (strlen($response->body()) > $max) {
                throw new \RuntimeException('Image exceeds the maximum size.');
            }

            return ImageInput::fromBinary(
                $response->body(),
                $image['mime'] ?? $response->header('Content-Type') ?: 'image/jpeg',
                $image['filename'] ?? null,
                $image['url'],
            );
        })->all();
    }

    /**
     * A duplicate delivery returns exactly what we said the first time — or
     * null when the first attempt never got as far as saying anything.
     *
     * @return array<string, mixed>|null
     */
    private function replay(ConversationMessage $seen, string $externalId): ?array
    {
        $conversation = $seen->conversation;

        $reply = $conversation->messages()
            ->where('direction', ConversationMessage::OUTBOUND)
            ->where('id', '>', $seen->id)
            ->orderBy('id')
            ->first();

        if (! $reply) {
            return null;
        }

        return $this->payload($conversation, $conversation->lead, $reply->body, $externalId, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Conversation $conversation, ?Lead $lead, ?string $reply, ?string $externalId, bool $duplicate): array
    {
        $state = $lead ? new LeadState($lead) : null;

        return [
            'conversation_id' => $conversation->id,
            'message_id' => $externalId,
            'duplicate' => $duplicate,
            'reply' => $reply,
            'conversation_status' => $conversation->status,
            // The gateway stops sending when this is true.
            'closed' => $conversation->status === 'closed',
            'handoff' => $conversation->status === 'awaiting_human',
            'lead' => $lead ? [
                'id' => $lead->id,
                'status' => $lead->qualification_status,
                'qualified' => in_array($lead->qualification_status, ['qualified', 'appointment_requested'], true),
                'missing' => $state?->missingRequired() ?? [],
                'full_name' => $lead->fullName(),
                'postal_code' => $lead->postal_code,
                'summary' => $lead->summary,
            ] : null,
            'attribution' => [
                'creative_id' => $conversation->creative_id,
                'campaign_id' => $conversation->campaign_id,
            ],
        ];
    }
}
