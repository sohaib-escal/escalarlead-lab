<?php

namespace Tests\Feature;

use App\Models\Creative;
use App\Models\CreativeGeneration;
use App\Models\CreativePrompt;
use App\Models\CreativeStatus;
use App\Models\User;
use App\Services\Generation\GenerationProviderRegistry;
use App\Services\Generation\Providers\GoogleFlowProvider;
use App\Services\Generation\Providers\GoogleVeoProvider;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerationProviderTest extends TestCase
{
    use RefreshDatabase;

    private Creative $creative;

    private CreativePrompt $prompt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->admin = User::factory()->create();

        $this->creative = Creative::create([
            'reference' => 'PAC-TEST-001',
            'name' => 'Créa test',
            'creative_status_id' => CreativeStatus::where('slug', 'idea')->value('id'),
            'format' => 'video',
        ]);

        $this->prompt = $this->creative->prompts()->create([
            'version' => 1,
            'body' => 'A realistic French residential scene.',
            'status' => CreativePrompt::STATUS_VALIDATED,
            'target_format' => 'video',
            'validated_at' => now(),
        ]);
    }

    public function test_google_flow_declares_that_it_has_no_public_generation_api(): void
    {
        $capabilities = (new GoogleFlowProvider)->capabilities();

        $this->assertFalse($capabilities['api_generation']);
        $this->assertFalse($capabilities['polling']);
        $this->assertFalse($capabilities['asset_retrieval']);
        $this->assertStringContainsString('aucune API publique', $capabilities['note']);
    }

    public function test_flow_hands_the_prompt_over_instead_of_pretending_to_generate(): void
    {
        Http::fake(); // Nothing must be called.

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/generations", [
            'creative_prompt_id' => $this->prompt->id,
            'provider' => 'google_flow',
        ])->assertSessionHas('success');

        $generation = CreativeGeneration::firstOrFail();

        $this->assertSame(CreativeGeneration::STATUS_AWAITING_MANUAL, $generation->status);
        $this->assertNull($generation->asset_url, 'No asset may exist before the admin brings one back.');
        $this->assertSame($this->prompt->body, $generation->meta['prompt']);
        $this->assertNotNull($generation->meta['handoff_url']);

        Http::assertNothingSent();
    }

    public function test_an_externally_generated_asset_can_be_attached_and_promoted(): void
    {
        $generation = $this->creative->generations()->create([
            'creative_prompt_id' => $this->prompt->id,
            'provider' => 'google_flow',
            'format' => 'video',
            'status' => CreativeGeneration::STATUS_AWAITING_MANUAL,
        ]);

        $this->actingAs($this->admin)->post("/generations/{$generation->id}/attach", [
            'asset_url' => 'https://drive.google.com/file/d/abc/view',
        ])->assertRedirect();

        $generation->refresh();
        $this->assertSame(CreativeGeneration::STATUS_COMPLETED, $generation->status);

        $this->actingAs($this->admin)->post("/generations/{$generation->id}/use")->assertRedirect();

        $this->creative->refresh();
        $this->assertSame('https://drive.google.com/file/d/abc/view', $this->creative->asset_url);
        $this->assertSame('google_flow', $this->creative->asset_source);
        $this->assertSame($generation->id, $this->creative->creative_generation_id);
        $this->assertSame('created', $this->creative->status->slug);
    }

    public function test_veo_submits_a_long_running_operation_and_polls_it(): void
    {
        config()->set('integrations.generation.google_veo.api_key', 'test-key');
        Storage::fake('public');

        Http::fake([
            '*:predictLongRunning' => Http::response(['name' => 'models/veo-3.1/operations/abc123']),
            '*operations/abc123' => Http::sequence()
                ->push(['name' => 'models/veo-3.1/operations/abc123', 'done' => false])
                ->push([
                    'name' => 'models/veo-3.1/operations/abc123',
                    'done' => true,
                    'response' => [
                        'generateVideoResponse' => [
                            'generatedSamples' => [['video' => ['uri' => 'https://generativelanguage.googleapis.com/v1beta/files/xyz:download']]],
                        ],
                    ],
                ]),
            '*files/xyz:download' => Http::response('fake-mp4-bytes'),
        ]);

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/generations", [
            'creative_prompt_id' => $this->prompt->id,
            'provider' => 'google_veo',
        ])->assertSessionHas('success');

        $generation = CreativeGeneration::firstOrFail();
        $this->assertSame(CreativeGeneration::STATUS_GENERATING, $generation->status);
        $this->assertSame('models/veo-3.1/operations/abc123', $generation->external_id);
        $this->assertSame('generating', $this->creative->refresh()->status->slug);

        // Still running.
        $this->actingAs($this->admin)->post("/generations/{$generation->id}/refresh");
        $this->assertSame(CreativeGeneration::STATUS_GENERATING, $generation->refresh()->status);

        // Finished.
        $this->actingAs($this->admin)->post("/generations/{$generation->id}/refresh");
        $generation->refresh();

        $this->assertSame(CreativeGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertStringContainsString('files/xyz', $generation->asset_url);
        $this->assertNotNull($generation->completed_at);

        // Google drops generated files after ~2 days, so we keep our own copy.
        Storage::disk('public')->assertExists($generation->meta['local_path']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key'));
    }

    public function test_veo_reports_a_failed_operation_rather_than_a_silent_success(): void
    {
        config()->set('integrations.generation.google_veo.api_key', 'test-key');

        Http::fake([
            '*:predictLongRunning' => Http::response(['name' => 'operations/boom']),
            '*operations/boom' => Http::response([
                'done' => true,
                'error' => ['message' => 'Quota exceeded'],
            ]),
        ]);

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/generations", [
            'creative_prompt_id' => $this->prompt->id,
            'provider' => 'google_veo',
        ]);

        $generation = CreativeGeneration::firstOrFail();
        $this->actingAs($this->admin)->post("/generations/{$generation->id}/refresh");

        $generation->refresh();
        $this->assertSame(CreativeGeneration::STATUS_FAILED, $generation->status);
        $this->assertStringContainsString('Quota exceeded', $generation->error);
        $this->assertNull($generation->asset_url);
    }

    public function test_an_unconfigured_provider_refuses_instead_of_failing_silently(): void
    {
        config()->set('integrations.generation.google_veo.api_key', null);

        $this->actingAs($this->admin)->post("/creatives/{$this->creative->id}/generations", [
            'creative_prompt_id' => $this->prompt->id,
            'provider' => 'google_veo',
        ])->assertSessionHas('error');

        $this->assertSame(0, CreativeGeneration::count());
    }

    public function test_the_registry_reports_what_each_provider_can_actually_do(): void
    {
        $status = collect(app(GenerationProviderRegistry::class)->status())->keyBy('key');

        $this->assertTrue($status['google_veo']['api_generation']);
        $this->assertFalse($status['google_flow']['api_generation']);
        $this->assertInstanceOf(GoogleVeoProvider::class, app(GenerationProviderRegistry::class)->get('google_veo'));
    }
}
