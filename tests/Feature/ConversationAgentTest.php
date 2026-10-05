<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Lead;
use App\Services\Agent\ConversationAgent;
use App\Services\Ai\ImageInput;
use App\Services\Ai\PromptCompletion;
use App\Services\Ai\Providers\PromptProvider;
use App\Services\Ai\Providers\PromptProviderRegistry;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The turn pipeline with the model scripted, so everything the model does *not*
 * decide can be asserted: what gets written, when a lead is complete, when a
 * human is called in.
 */
class ConversationAgentTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->seed(KnowledgeSeeder::class);

        $this->conversation = Conversation::create([
            'channel' => 'test',
            'external_id' => 'test-1',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $replies
     */
    private function scriptModel(array $replies): \ArrayObject
    {
        $seen = new \ArrayObject;

        $provider = new class($replies, $seen) implements PromptProvider
        {
            public function __construct(private array $replies, private \ArrayObject $seen) {}

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
                $this->seen[] = ['system' => $system, 'user' => $user];

                $next = array_shift($this->replies) ?? ['reply' => 'D\'accord.'];

                return new PromptCompletion(json_encode($next, JSON_UNESCAPED_UNICODE));
            }
        };

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

        return $seen;
    }

    private function agent(): ConversationAgent
    {
        return app(ConversationAgent::class);
    }

    public function test_it_extracts_everything_from_one_rambling_message(): void
    {
        // The example from the brief: three facts and a problem, in one breath.
        $this->scriptModel([[
            'problem_family' => 'humidite',
            'extracted' => [
                'city' => 'Angers',
                'property_type' => 'maison',
                'problem_duration' => 'cet hiver',
            ],
            'taxonomy' => [['category' => 'specific-problem', 'value_slug' => 'condensation']],
            'reply' => 'Je comprends. Vous êtes propriétaire du logement ?',
        ]]);

        $turn = $this->agent()->handle(
            $this->conversation,
            'bonjour jai 63 ans ma maison est a Angers et jai beaucoup de moisissure dans la chambre depuis cet hiver',
        );

        $lead = $turn->lead;

        $this->assertSame('Angers', $lead->city);
        $this->assertSame('maison', $lead->details['property_type']);
        $this->assertSame('cet hiver', $lead->details['problem_duration']);
        $this->assertSame('humidite', $turn->conversation->problem_family);

        // And the problem is a real taxonomy link, not a string.
        $this->assertTrue($lead->parameterValues()->where('slug', 'condensation')->exists());
    }

    public function test_the_prompt_tells_the_model_what_is_already_known(): void
    {
        $seen = $this->scriptModel([
            ['extracted' => ['city' => 'Angers'], 'reply' => 'Merci. Quel est votre code postal ?'],
            ['extracted' => ['postal_code' => '49000'], 'reply' => 'Merci. Et votre nom ?'],
        ]);

        $this->agent()->handle($this->conversation, 'je suis à Angers');
        $this->agent()->handle($this->conversation, '49000');

        // Second turn: the city is in the "already known" block, and the agent
        // is told to go after the next missing field instead.
        $this->assertStringContainsString('CE QUE VOUS SAVEZ DÉJÀ', $seen[1]['user']);
        $this->assertStringContainsString('Angers', $seen[1]['user']);
        $this->assertStringContainsString('À OBTENIR MAINTENANT', $seen[1]['user']);
    }

    public function test_the_standing_guardrails_are_in_every_single_turn(): void
    {
        $seen = $this->scriptModel([
            ['reply' => 'Bonjour, je comprends.'],
            ['reply' => 'D\'accord, merci.'],
        ]);

        $this->agent()->handle($this->conversation, 'bonjour');
        $this->agent()->handle($this->conversation, 'et les aides ?');

        foreach ($seen as $index => $call) {
            $this->assertStringContainsString('À NE JAMAIS AFFIRMER', $call['system'], "Turn {$index} lost the guardrails");
            $this->assertStringContainsString('MaPrimeRénov', $call['system']);
        }
    }

    public function test_a_lead_becomes_qualified_only_when_the_required_fields_are_in(): void
    {
        $this->scriptModel([
            [
                'problem_family' => 'humidite',
                'taxonomy' => [['category' => 'specific-problem', 'value_slug' => 'condensation']],
                'reply' => 'Je comprends. Vous êtes propriétaire ?',
            ],
            ['extracted' => ['first_name' => 'Marie'], 'reply' => 'Merci Marie. Votre nom de famille ?'],
            ['extracted' => ['last_name' => 'Dubois'], 'reply' => 'Merci. Votre code postal ?'],
            ['extracted' => ['postal_code' => '49000'], 'reply' => 'Merci, un conseiller vous rappellera.'],
        ]);

        $this->agent()->handle($this->conversation, 'de la condensation partout');
        $this->assertSame('in_progress', $this->conversation->fresh()->lead->qualification_status);

        $this->agent()->handle($this->conversation, 'Marie');
        $this->agent()->handle($this->conversation, 'Dubois');
        $this->assertSame('in_progress', $this->conversation->fresh()->lead->qualification_status);

        $turn = $this->agent()->handle($this->conversation, '49000');

        $this->assertSame('qualified', $turn->lead->qualification_status);
        $this->assertNotNull($turn->lead->qualified_at);
        $this->assertSame([], $turn->lead->missing_required_fields);

        // The département is derived, never asked for.
        $this->assertSame('49', $turn->lead->department);

        // And the conversation now belongs to a human.
        $this->assertSame('awaiting_human', $turn->conversation->status);
    }

    public function test_the_summary_is_readable_without_the_transcript(): void
    {
        $this->scriptModel([[
            'problem_family' => 'humidite',
            'extracted' => [
                'first_name' => 'Marie', 'last_name' => 'Dubois', 'postal_code' => '49000',
                'city' => 'Angers', 'is_homeowner' => true, 'property_type' => 'maison',
                'problem_duration' => 'l\'hiver dernier', 'heating_system' => 'chaudière gaz',
            ],
            'taxonomy' => [['category' => 'specific-problem', 'value_slug' => 'condensation']],
            'reply' => 'Merci, un conseiller vous rappellera.',
        ]]);

        $summary = $this->agent()->handle($this->conversation, 'tout en un message')->lead->summary;

        $this->assertStringContainsString('Marie Dubois', $summary);
        $this->assertStringContainsString('49000 Angers', $summary);
        $this->assertStringContainsString('Propriétaire', $summary);
        $this->assertStringContainsString('Condensation', $summary);
        $this->assertStringContainsString('depuis l\'hiver dernier', $summary);
        $this->assertStringContainsString('Qualifié', $summary);
    }

    public function test_asking_for_a_human_hands_the_conversation_over(): void
    {
        $this->scriptModel([[
            'extracted' => ['first_name' => 'Jean', 'last_name' => 'Martin', 'postal_code' => '75011'],
            'taxonomy' => [['category' => 'specific-problem', 'value_slug' => 'condensation']],
            'handoff_requested' => true,
            'reply' => 'Bien sûr, un conseiller vous rappellera.',
        ]]);

        $turn = $this->agent()->handle($this->conversation, 'je voudrais parler à quelqu\'un');

        $this->assertSame('appointment_requested', $turn->lead->qualification_status);
        $this->assertSame('awaiting_human', $turn->conversation->status);
        $this->assertNotNull($turn->lead->handed_off_at);
    }

    public function test_opting_out_closes_the_conversation(): void
    {
        $this->scriptModel([[
            'opted_out' => true,
            'reply' => 'Très bien, je ne vous recontacterai pas. Bonne journée.',
        ]]);

        $turn = $this->agent()->handle($this->conversation, 'STOP ne me contactez plus');

        $this->assertSame('lost', $turn->lead->qualification_status);
        $this->assertSame('closed', $turn->conversation->status);
    }

    public function test_an_invented_taxonomy_value_is_kept_as_a_note_not_dropped(): void
    {
        $this->scriptModel([[
            'taxonomy' => [['category' => 'specific-problem', 'value_slug' => 'probleme-de-toiture-inventé']],
            'reply' => 'Je comprends.',
        ]]);

        $turn = $this->agent()->handle($this->conversation, 'ma toiture fuit');

        $this->assertCount(0, $turn->lead->parameters, 'A slug that does not exist must never become a fact.');
        $this->assertStringContainsString('probleme-de-toiture-inventé', $turn->lead->raw_notes);
    }

    public function test_an_over_long_reply_is_regenerated_then_replaced_rather_than_sent(): void
    {
        $wall = str_repeat('Cette explication est beaucoup trop longue pour WhatsApp. ', 10);

        // Both attempts come back unusable.
        $this->scriptModel([
            ['reply' => $wall],
            ['reply' => $wall],
        ]);

        $turn = $this->agent()->handle($this->conversation, 'pourquoi ça arrive ?');

        $this->assertLessThanOrEqual(config('agent.reply.max_chars'), mb_strlen($turn->reply));
        $this->assertNotSame($wall, $turn->reply);
    }

    public function test_two_questions_in_one_reply_are_rejected(): void
    {
        $this->scriptModel([
            ['reply' => 'Vous êtes propriétaire ? Et quel est votre code postal ?'],
            ['reply' => 'Vous êtes propriétaire du logement ?'],
        ]);

        $turn = $this->agent()->handle($this->conversation, 'bonjour');

        $this->assertSame(1, substr_count($turn->reply, '?'));
    }

    private function image(): ImageInput
    {
        // A 1x1 PNG is enough to prove the plumbing; quality is the model's job.
        return ImageInput::fromBinary(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            'image/png',
            'mur.png',
        );
    }

    public function test_a_photo_reaches_the_model_with_photo_specific_instructions(): void
    {
        $seen = $this->scriptModel([[
            'problem_family' => 'humidite',
            'image_observations' => 'Des taches sombres au bas d\'un mur clair.',
            'reply' => 'Je vois des taches sombres en bas du mur. Cela peut venir de plusieurs choses, '
                .'comme un excès d\'humidité ou une infiltration ; seule une visite permet de trancher. '
                .'Dans quelle pièce est-ce ?',
        ]]);

        $turn = $this->agent()->handle($this->conversation, '', null, [$this->image()]);

        // The prompt must carry the photo rules, not just the image bytes.
        $this->assertStringContainsString('A ENVOYÉ 1 PHOTO', $seen[0]['user']);
        $this->assertStringContainsString('DEUX causes possibles', $seen[0]['user']);
        $this->assertStringContainsString('ne permet pas de conclure', $seen[0]['user']);

        // What the model saw is kept with the lead — it is evidence about the home.
        $this->assertStringContainsString('taches sombres', $turn->lead->raw_notes);
        $this->assertSame(1, $turn->message->meta['images']);
    }

    public function test_the_inbound_photo_is_recorded_as_an_image_message(): void
    {
        $this->scriptModel([['reply' => 'Je vois la photo. Dans quelle pièce ?']]);

        $this->agent()->handle($this->conversation, 'regardez', null, [$this->image()]);

        $inbound = $this->conversation->messages()->where('direction', 'inbound')->firstOrFail();

        $this->assertSame('image', $inbound->type);
        $this->assertSame('image/png', $inbound->raw['images'][0]['mime']);
        $this->assertFalse($inbound->raw['ignored_by_provider']);
    }

    public function test_a_photo_turn_may_be_longer_than_a_text_turn(): void
    {
        $observation = 'Je vois des taches sombres au bas du mur. Cela peut avoir plusieurs causes, '
            .'par exemple un excès d\'humidité dans l\'air ou une infiltration. Une photo ne permet pas '
            .'de conclure, seul un professionnel sur place le peut. Dans quelle pièce est-ce ?';

        $this->assertGreaterThan(config('agent.reply.max_chars'), mb_strlen($observation));

        $this->scriptModel([['reply' => $observation]]);

        $turn = $this->agent()->handle($this->conversation, '', null, [$this->image()]);

        // It would have been cut as a text reply; with a photo it stands.
        $this->assertSame($observation, $turn->reply);
        $this->assertLessThanOrEqual(config('agent.reply.max_chars_with_image'), mb_strlen($turn->reply));
    }

    public function test_a_provider_that_cannot_read_images_says_so_rather_than_ignoring_them(): void
    {
        $registry = new class extends PromptProviderRegistry
        {
            public function get(string $key): PromptProvider
            {
                return new class implements PromptProvider
                {
                    public static array $received = [];

                    public function key(): string
                    {
                        return 'anthropic';
                    }

                    public function label(): string
                    {
                        return 'Fake sans vision';
                    }

                    public function isConfigured(): bool
                    {
                        return true;
                    }

                    public function supportsImages(): bool
                    {
                        return false;
                    }

                    public function complete(string $system, string $user, string $modelId, array $images = []): PromptCompletion
                    {
                        self::$received = $images;

                        return new PromptCompletion(json_encode(['reply' => 'Je ne peux pas voir les photos.']));
                    }
                };
            }
        };

        $this->app->instance(PromptProviderRegistry::class, $registry);

        $this->agent()->handle($this->conversation, 'voici une photo', null, [$this->image()]);

        $inbound = $this->conversation->messages()->where('direction', 'inbound')->firstOrFail();

        // The photo is recorded as received and explicitly marked as unusable by
        // this provider — never silently dropped.
        $this->assertSame('text', $inbound->type);
        $this->assertTrue($inbound->raw['ignored_by_provider']);
    }

    public function test_it_refuses_to_run_without_a_configured_model(): void
    {
        // The real registry, with no API keys set — the production state today.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('n\'est pas configuré');

        config()->set('ai.providers.anthropic.api_key', null);
        config()->set('ai.providers.gemini.api_key', null);
        config()->set('ai.providers.openai.api_key', null);

        $this->agent()->handle($this->conversation, 'bonjour');
    }

    public function test_the_knowledge_retrieved_is_recorded_on_the_reply(): void
    {
        $this->scriptModel([[
            'problem_family' => 'humidite',
            'reply' => 'Je comprends. Vous êtes propriétaire ?',
        ]]);

        $turn = $this->agent()->handle($this->conversation, 'j\'ai de la buée sur les vitres');

        $meta = $turn->message->meta;

        $this->assertArrayHasKey('retrieval', $meta);
        $this->assertNotEmpty($meta['retrieval']['passages'], 'The turn must record what grounded it.');
        $this->assertSame('anthropic', $meta['provider']);
    }

    public function test_the_lead_and_conversation_survive_a_model_failure(): void
    {
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
                        return new PromptCompletion('désolé je ne sais pas répondre en JSON');
                    }
                };
            }
        };

        $this->app->instance(PromptProviderRegistry::class, $registry);

        try {
            $this->agent()->handle($this->conversation, 'bonjour');
            $this->fail('A non-JSON reply must not be silently accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('JSON', $e->getMessage());
        }

        // The inbound message is still on the record: no invented reply, no
        // lost message.
        $this->assertSame(1, $this->conversation->messages()->count());
        $this->assertSame('inbound', $this->conversation->messages()->first()->direction);
        $this->assertInstanceOf(Lead::class, $this->conversation->fresh()->lead);
    }

    public function test_the_default_model_is_used_when_none_is_named(): void
    {
        $this->scriptModel([['reply' => 'Bonjour.']]);

        $turn = $this->agent()->handle($this->conversation, 'bonjour');

        $this->assertSame(AiModel::default()->model_id, $turn->message->meta['model']);
    }
}
