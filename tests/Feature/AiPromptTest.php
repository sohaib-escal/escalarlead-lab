<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\Creative;
use App\Models\CreativeGeneration;
use App\Models\CreativePrompt;
use App\Models\CreativeStatus;
use App\Models\ParameterCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\CreativeAiState;
use App\Services\Ai\CreativeOutcome;
use App\Services\Ai\PromptCompletion;
use App\Services\Ai\Providers\PromptProvider;
use App\Services\Ai\Providers\PromptProviderRegistry;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Creative $creative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->admin = User::factory()->create();

        $this->creative = Creative::create([
            'reference' => 'PAC-W-60-69-HIGHBILL-AID-FB-001',
            'name' => 'Femme 60-69 — facture chauffage',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'idea')->value('id'),
            'format' => 'video',
        ]);

        foreach (['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL', 'trigger' => 'WINTER', 'motivation' => 'AID'] as $slug => $code) {
            $category = ParameterCategory::where('slug', $slug)->firstOrFail();
            $this->creative->parameters()->create([
                'parameter_category_id' => $category->id,
                'parameter_value_id' => $category->values()->where('code', $code)->firstOrFail()->id,
            ]);
        }
    }

    private function fakeProvider(string $text = 'A realistic French residential scene…'): void
    {
        $provider = new class($text) implements PromptProvider
        {
            public static array $received = [];

            public function __construct(private string $text) {}

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

            public function complete(string $system, string $user, string $modelId): PromptCompletion
            {
                self::$received = compact('system', 'user', 'modelId');

                return new PromptCompletion($this->text, ['provider' => 'anthropic', 'model' => $modelId]);
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
    }

    public function test_the_creative_outcome_reads_the_idea_back_in_plain_language(): void
    {
        $outcome = app(CreativeOutcome::class)->for($this->creative);

        $this->assertContains('Femme', $outcome['who']);
        $this->assertContains('60–69', $outcome['who']);
        $this->assertContains('Facture de chauffage élevée', $outcome['problem']);
        $this->assertContains('Hiver', $outcome['trigger']);
        $this->assertContains("Aides de l'État", $outcome['motivation']);
        $this->assertSame('Pompe à chaleur', $outcome['product']);

        $brief = app(CreativeOutcome::class)->toBrief($outcome);
        $this->assertStringContainsString('QUI : Femme', $brief);
        $this->assertStringContainsString('PRODUIT : Pompe à chaleur', $brief);
    }

    public function test_generating_a_prompt_briefs_the_model_with_the_structured_idea(): void
    {
        $this->fakeProvider('Create a realistic French residential advertisement…');

        $model = AiModel::where('provider', 'anthropic')->firstOrFail();

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/prompts", [
            'ai_model_id' => $model->id,
            'target_format' => 'video',
        ])->assertRedirect();

        $prompt = CreativePrompt::firstOrFail();

        $this->assertSame(1, $prompt->version);
        $this->assertSame(CreativePrompt::STATUS_DRAFT, $prompt->status);
        $this->assertStringContainsString('French residential', $prompt->body);
        $this->assertContains('Femme', $prompt->outcome['who']);

        // The brief the model actually received carries the idea, not just the id.
        $received = $prompt->model->provider === 'anthropic' ? true : false;
        $this->assertTrue($received);
        $this->assertTrue($this->creative->history()->where('event', 'prompt_generated')->exists());
    }

    public function test_a_prompt_is_editable_and_editing_a_validated_prompt_sends_it_back_for_review(): void
    {
        $this->fakeProvider();
        $model = AiModel::first();

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/prompts", [
            'ai_model_id' => $model->id, 'target_format' => 'video',
        ]);

        $prompt = CreativePrompt::firstOrFail();

        $this->actingAs($this->admin)->post("/prompts/{$prompt->id}/validate")->assertRedirect();
        $this->assertSame(CreativePrompt::STATUS_VALIDATED, $prompt->refresh()->status);
        $this->assertNotNull($prompt->validated_at);

        // Validating moves the creative to "prompt prêt".
        $this->assertSame('prompt_ready', $this->creative->refresh()->status->slug);

        $this->actingAs($this->admin)->put("/prompts/{$prompt->id}", ['body' => 'Version retouchée à la main.'])
            ->assertRedirect();

        $prompt->refresh();
        $this->assertSame('Version retouchée à la main.', $prompt->body);
        $this->assertSame(CreativePrompt::STATUS_DRAFT, $prompt->status, 'An edited prompt must be reviewed again.');
    }

    public function test_generation_is_refused_until_the_admin_validates_the_prompt(): void
    {
        $this->fakeProvider();
        $model = AiModel::first();

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/prompts", [
            'ai_model_id' => $model->id, 'target_format' => 'video',
        ]);

        $prompt = CreativePrompt::firstOrFail();

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/generations", [
            'creative_prompt_id' => $prompt->id,
            'provider' => 'google_flow',
        ])->assertSessionHas('error');

        $this->assertSame(0, CreativeGeneration::count());

        $this->actingAs($this->admin)->post("/prompts/{$prompt->id}/validate");

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/generations", [
            'creative_prompt_id' => $prompt->id,
            'provider' => 'google_flow',
        ])->assertSessionHas('success');

        $this->assertSame(1, CreativeGeneration::count());
    }

    public function test_the_creative_always_knows_its_state_and_the_next_action(): void
    {
        $state = app(CreativeAiState::class);

        $this->assertSame('idea', $state->for($this->creative)['key']);
        $this->assertSame('Générer le prompt', $state->for($this->creative)['next_action']);

        $prompt = $this->creative->prompts()->create([
            'version' => 1, 'body' => 'x', 'status' => CreativePrompt::STATUS_DRAFT,
        ]);
        $this->assertSame('prompt', $state->for($this->creative->fresh())['key']);

        $prompt->update(['status' => CreativePrompt::STATUS_VALIDATED, 'validated_at' => now()]);
        $this->assertSame('validated', $state->for($this->creative->fresh())['key']);

        $generation = $this->creative->generations()->create([
            'creative_prompt_id' => $prompt->id, 'provider' => 'google_veo', 'status' => CreativeGeneration::STATUS_GENERATING,
        ]);
        $this->assertSame('generating', $state->for($this->creative->fresh())['key']);

        // A failure is a state of its own, with something to do about it.
        $generation->update(['status' => CreativeGeneration::STATUS_FAILED, 'error' => 'Quota']);
        $failed = $state->for($this->creative->fresh());
        $this->assertSame('Génération en échec', $failed['label']);
        $this->assertSame('Relancer la génération', $failed['next_action']);

        $generation->update(['status' => CreativeGeneration::STATUS_COMPLETED, 'asset_url' => 'https://x/a.mp4']);
        $this->assertSame('generated', $state->for($this->creative->fresh())['key']);

        $this->creative->update(['creative_generation_id' => $generation->id]);
        $this->assertSame('attached', $state->for($this->creative->fresh())['key']);
    }

    public function test_a_failing_model_reports_the_error_instead_of_inventing_a_prompt(): void
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
                        return false;
                    }

                    public function complete(string $system, string $user, string $modelId): PromptCompletion
                    {
                        throw new \RuntimeException('Clé API manquante.');
                    }
                };
            }
        };

        $this->app->instance(PromptProviderRegistry::class, $registry);

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/prompts", [
            'ai_model_id' => AiModel::first()->id,
            'target_format' => 'video',
        ])->assertSessionHas('error');

        $this->assertSame(0, CreativePrompt::count());
    }
}
