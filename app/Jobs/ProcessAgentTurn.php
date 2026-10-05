<?php

namespace App\Jobs;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Services\Agent\ConversationAgent;
use App\Services\Ai\ImageInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Run one turn away from the request.
 *
 * A model call can take thirty seconds; a webhook cannot. So the gateway hands
 * the message over and gets an immediate acknowledgement, and the reply comes
 * back on a callback once the agent has actually thought about it.
 */
class ProcessAgentTurn implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<int, array{base64: string, mime: string, filename: ?string, url: ?string}>  $images
     */
    public function __construct(
        public readonly int $conversationId,
        public readonly string $text,
        public readonly array $images,
        public readonly ?string $externalId,
        public readonly ?string $callbackUrl,
        public readonly ?string $modelId = null,
    ) {}

    /**
     * Two messages from the same person must never be answered at once, or the
     * replies interleave and the second one answers a question the first had
     * not yet asked.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->conversationId))->expireAfter(180)];
    }

    public function handle(ConversationAgent $agent): void
    {
        $conversation = Conversation::find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $turn = $agent->handle(
            $conversation,
            $this->text,
            $this->modelId ? AiModel::where('model_id', $this->modelId)->first() : null,
            array_map(
                fn (array $image) => new ImageInput($image['base64'], $image['mime'], $image['filename'], $image['url']),
                $this->images,
            ),
            $this->externalId,
        );

        $this->deliver([
            'conversation_id' => $conversation->id,
            'message_id' => $this->externalId,
            'reply' => $turn->reply,
            'conversation_status' => $turn->conversation->status,
            'closed' => $turn->conversation->status === 'closed',
            'handoff' => $turn->conversation->status === 'awaiting_human',
            'lead' => [
                'id' => $turn->lead->id,
                'status' => $turn->lead->qualification_status,
                'qualified' => in_array($turn->lead->qualification_status, ['qualified', 'appointment_requested'], true),
                'full_name' => $turn->lead->fullName(),
                'postal_code' => $turn->lead->postal_code,
                'summary' => $turn->lead->summary,
            ],
        ]);
    }

    /**
     * The agent could not answer. Tell the gateway so it can decide — never
     * leave it waiting for a callback that is not coming.
     */
    public function failed(?Throwable $e): void
    {
        $this->deliver([
            'conversation_id' => $this->conversationId,
            'message_id' => $this->externalId,
            'reply' => null,
            'error' => 'agent_failed',
            'message' => $e?->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(array $payload): void
    {
        if (blank($this->callbackUrl)) {
            return;
        }

        $body = json_encode($payload);
        $timestamp = time();

        try {
            $response = Http::timeout(config('agent.api.callback_timeout'))
                ->withHeaders([
                    'X-Agent-Timestamp' => (string) $timestamp,
                    'X-Agent-Signature' => 'sha256='.hash_hmac(
                        'sha256',
                        $timestamp.'.'.$body,
                        (string) config('agent.api.secret'),
                    ),
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($this->callbackUrl);

            if ($response->failed()) {
                Log::warning('Agent callback rejected', [
                    'conversation' => $this->conversationId,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Agent callback failed', [
                'conversation' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
