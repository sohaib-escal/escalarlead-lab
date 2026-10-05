<?php

namespace Tests\Feature;

use App\Jobs\ProcessAgentTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\Product;
use App\Services\Agent\ConversationAgent;
use App\Services\Ai\PromptCompletion;
use App\Services\Ai\Providers\PromptProvider;
use App\Services\Ai\Providers\PromptProviderRegistry;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The seam the WhatsApp gateway calls. Three things must hold: nobody else can
 * call it, a retry never costs a second reply, and a failure is distinguishable
 * from a silence.
 */
class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->seed(KnowledgeSeeder::class);

        config()->set('agent.api.secret', 'test-secret');

        $this->scriptModel([
            'problem_family' => 'humidite',
            'taxonomy' => [['category' => 'specific-problem', 'value_slug' => 'condensation']],
            'reply' => 'Je comprends pour ces taches. Dans quelle pièce est-ce ?',
        ]);
    }

    /**
     * @param  array<string, mixed>  $reply
     */
    private function scriptModel(array $reply): void
    {
        $provider = new class($reply) implements PromptProvider
        {
            public static int $calls = 0;

            public function __construct(private array $reply) {}

            public function key(): string
            {
                return 'anthropic';
            }

            public function label(): string
            {
                return 'Fake';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function supportsImages(): bool
            {
                return true;
            }

            public function complete(string $system, string $user, string $modelId, array $images = []): PromptCompletion
            {
                self::$calls++;

                return new PromptCompletion(json_encode($this->reply, JSON_UNESCAPED_UNICODE));
            }
        };

        $provider::$calls = 0;

        $registry = new class($provider) extends PromptProviderRegistry
        {
            public function __construct(private PromptProvider $provider)
            {
                parent::__construct();
            }

            public function get(string $key): PromptProvider
            {
                return $this->provider;
            }
        };

        $this->app->instance(PromptProviderRegistry::class, $registry);
        $this->provider = $provider;
    }

    private object $provider;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signed(array $payload, ?string $secret = null, ?int $timestamp = null): TestResponse
    {
        $body = json_encode($payload);
        $timestamp ??= time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? 'test-secret');

        return $this->call(
            'POST',
            '/api/agent/turn',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_AGENT_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_AGENT_SIGNATURE' => $signature,
            ],
            $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $text = 'des taches noires sur le mur', ?string $id = 'wamid.A1'): array
    {
        return [
            'channel' => 'whatsapp',
            'contact' => ['wa_id' => '33612345678', 'name' => 'Marie'],
            'message' => ['id' => $id, 'text' => $text],
        ];
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        $this->postJson('/api/agent/turn', $this->message())->assertStatus(401);

        $this->assertSame(0, Conversation::count());
    }

    public function test_a_wrong_secret_is_rejected(): void
    {
        $this->signed($this->message(), secret: 'not-the-secret')->assertStatus(401);
    }

    public function test_an_old_signature_is_rejected_so_a_captured_request_cannot_be_replayed(): void
    {
        $this->signed($this->message(), timestamp: time() - 3600)->assertStatus(401);
    }

    public function test_the_route_refuses_outright_when_no_secret_is_configured(): void
    {
        config()->set('agent.api.secret', null);

        $this->signed($this->message())->assertStatus(503);
    }

    public function test_a_signed_message_gets_a_reply_and_creates_the_conversation(): void
    {
        $response = $this->signed($this->message())->assertOk();

        $response->assertJsonPath('duplicate', false)
            ->assertJsonPath('conversation_status', 'active')
            ->assertJsonPath('lead.status', 'in_progress');

        $this->assertNotEmpty($response->json('reply'));

        // The phone is normalised once, so the same person is never two leads.
        $this->assertSame('+33612345678', Conversation::first()->external_id);
        $this->assertSame(1, $this->provider::$calls);
    }

    public function test_a_retried_delivery_replays_the_same_reply_without_calling_the_model_again(): void
    {
        $first = $this->signed($this->message())->assertOk();

        $second = $this->signed($this->message())->assertOk();

        $this->assertSame($first->json('reply'), $second->json('reply'));
        $this->assertTrue($second->json('duplicate'));

        // The whole point: one model charge, one lead, one reply.
        $this->assertSame(1, $this->provider::$calls);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, ConversationMessage::count());
    }

    public function test_an_interrupted_turn_is_genuinely_retried_not_replayed_as_silence(): void
    {
        // A first attempt that crashed after recording the message but before
        // answering: the inbound exists, no reply followed it.
        $conversation = Conversation::create([
            'channel' => 'whatsapp', 'external_id' => '+33612345678', 'status' => 'active',
        ]);
        $conversation->messages()->create([
            'direction' => ConversationMessage::INBOUND,
            'external_id' => 'wamid.A1',
            'body' => 'des taches noires sur le mur',
            'status' => 'received',
        ]);

        $response = $this->signed($this->message())->assertOk();

        // Replaying silence would leave the homeowner waiting forever.
        $this->assertFalse($response->json('duplicate'));
        $this->assertNotEmpty($response->json('reply'));
        $this->assertSame(1, $this->provider::$calls);

        // And the orphan is gone, so the transcript has no gap.
        $this->assertSame(1, $conversation->messages()->where('direction', 'inbound')->count());
    }

    public function test_a_message_with_no_text_and_no_image_is_refused(): void
    {
        $payload = $this->message();
        $payload['message']['text'] = '';

        $this->signed($payload)->assertStatus(422)->assertJsonPath('error', 'empty_message');
    }

    public function test_a_model_failure_is_reported_as_a_failure_not_as_silence(): void
    {
        $this->scriptModel(['reply' => null]);

        $registry = new class extends PromptProviderRegistry
        {
            public function get(string $key): PromptProvider
            {
                return new class implements PromptProvider
                {
                    public function key(): string
                    {
                        return 'anthropic';
                    }

                    public function label(): string
                    {
                        return 'Fake';
                    }

                    public function isConfigured(): bool
                    {
                        return true;
                    }

                    public function supportsImages(): bool
                    {
                        return true;
                    }

                    public function complete(string $system, string $user, string $modelId, array $images = []): PromptCompletion
                    {
                        return new PromptCompletion('pas du json');
                    }
                };
            }
        };
        $this->app->instance(PromptProviderRegistry::class, $registry);

        $this->signed($this->message())
            ->assertStatus(502)
            ->assertJsonPath('error', 'agent_failed');
    }

    public function test_an_explicit_creative_reference_attributes_the_conversation(): void
    {
        $creative = Creative::create([
            'reference' => 'PAC-W-60-69-HIGHBILL-AID-WA-001',
            'name' => 'Créa test',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'video',
        ]);

        $payload = $this->message();
        $payload['creative_reference'] = 'pac-w-60-69-highbill-aid-wa-001';

        $this->signed($payload)->assertOk()->assertJsonPath('attribution.creative_id', $creative->id);

        $this->assertSame('explicit', Conversation::first()->referral['matched_on']);
    }

    public function test_a_reference_hidden_in_the_click_to_whatsapp_referral_is_found(): void
    {
        $creative = Creative::create([
            'reference' => 'SOLAR-M-50-59-ELECBILL-INDEP-WA-007',
            'name' => 'Créa solaire',
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'video',
        ]);

        $payload = $this->message();
        $payload['referral'] = [
            'source_type' => 'ad',
            'source_id' => '120210000000',
            'headline' => 'Votre facture d\'électricité',
            'source_url' => 'https://fb.me/x?utm_content=solar-m-50-59-elecbill-indep-wa-007',
        ];

        $this->signed($payload)->assertOk()->assertJsonPath('attribution.creative_id', $creative->id);
        $this->assertSame('referral', Conversation::first()->referral['matched_on']);
    }

    public function test_an_unmatched_referral_still_creates_the_lead_and_keeps_the_payload(): void
    {
        $payload = $this->message();
        $payload['referral'] = ['source_id' => '999', 'headline' => 'une annonce inconnue'];

        $this->signed($payload)->assertOk()->assertJsonPath('attribution.creative_id', null);

        $conversation = Conversation::first();

        $this->assertNull($conversation->creative_id);
        $this->assertSame('999', $conversation->referral['source_id']);
        $this->assertNotNull($conversation->lead);
    }

    public function test_an_image_sent_as_base64_reaches_the_agent(): void
    {
        $payload = $this->message('regardez');
        $payload['message']['images'] = [[
            'base64' => base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')),
            'mime' => 'image/png',
            'filename' => 'mur.png',
        ]];

        $this->signed($payload)->assertOk();

        $inbound = ConversationMessage::where('direction', 'inbound')->firstOrFail();

        $this->assertSame('image', $inbound->type);
        $this->assertSame('image/png', $inbound->raw['images'][0]['mime']);
    }

    public function test_an_image_given_only_as_a_url_is_downloaded(): void
    {
        Http::fake([
            'cdn.example/*' => Http::response(
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);

        $payload = $this->message('regardez');
        $payload['message']['images'] = [['url' => 'https://cdn.example/media/1.png', 'mime' => 'image/png']];

        $this->signed($payload)->assertOk();

        $inbound = ConversationMessage::where('direction', 'inbound')->firstOrFail();

        $this->assertSame('image', $inbound->type);
        $this->assertSame('https://cdn.example/media/1.png', $inbound->raw['images'][0]['url']);
    }

    public function test_an_unreachable_image_is_reported_rather_than_answered_around(): void
    {
        Http::fake(['cdn.example/*' => Http::response('', 404)]);

        $payload = $this->message('regardez');
        $payload['message']['images'] = [['url' => 'https://cdn.example/media/missing.png', 'mime' => 'image/png']];

        $this->signed($payload)->assertStatus(422)->assertJsonPath('error', 'image_unreadable');
    }

    public function test_a_callback_url_makes_the_turn_asynchronous(): void
    {
        Queue::fake();

        $payload = $this->message();
        $payload['callback_url'] = 'https://whatsapp.zailer.ma/agent/reply';

        $this->signed($payload)
            ->assertStatus(202)
            ->assertJsonPath('accepted', true);

        // Nothing waits on the model inside the request.
        $this->assertSame(0, $this->provider::$calls);

        Queue::assertPushed(
            ProcessAgentTurn::class,
            fn ($job) => $job->callbackUrl === 'https://whatsapp.zailer.ma/agent/reply'
                && $job->externalId === 'wamid.A1',
        );
    }

    public function test_the_queued_turn_posts_a_signed_reply_back_to_the_gateway(): void
    {
        Http::fake(['whatsapp.zailer.ma/*' => Http::response(['ok' => true])]);

        $conversation = Conversation::create([
            'channel' => 'whatsapp', 'external_id' => '+33612345678', 'status' => 'active',
        ]);

        (new ProcessAgentTurn(
            $conversation->id,
            'des taches noires sur le mur',
            [],
            'wamid.A1',
            'https://whatsapp.zailer.ma/agent/reply',
        ))->handle(app(ConversationAgent::class));

        Http::assertSent(function ($request) {
            $body = $request->body();
            $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Agent-Timestamp')[0].'.'.$body, 'test-secret');

            $payload = json_decode($body, true);

            // The gateway can verify us the same way we verify it.
            return $request->url() === 'https://whatsapp.zailer.ma/agent/reply'
                && $request->header('X-Agent-Signature')[0] === $expected
                && filled($payload['reply'])
                && $payload['message_id'] === 'wamid.A1';
        });
    }

    public function test_a_failed_turn_tells_the_gateway_rather_than_going_quiet(): void
    {
        Http::fake(['whatsapp.zailer.ma/*' => Http::response(['ok' => true])]);

        $conversation = Conversation::create([
            'channel' => 'whatsapp', 'external_id' => '+33612345678', 'status' => 'active',
        ]);

        (new ProcessAgentTurn(
            $conversation->id, 'bonjour', [], 'wamid.A9', 'https://whatsapp.zailer.ma/agent/reply',
        ))->failed(new \RuntimeException('le modèle est tombé'));

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $payload['error'] === 'agent_failed'
                && $payload['reply'] === null
                && str_contains($payload['message'], 'tombé');
        });
    }

    public function test_the_gateway_is_told_when_to_stop_sending(): void
    {
        $this->scriptModel(['opted_out' => true, 'reply' => 'Très bien, bonne journée.']);

        $this->signed($this->message('STOP'))
            ->assertOk()
            ->assertJsonPath('closed', true)
            ->assertJsonPath('lead.status', 'lost');
    }
}
