<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Creative;
use App\Models\CreativeGeneration;
use App\Models\CreativePrompt;
use App\Models\CreativeStatus;
use App\Models\LandingPage;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nothing that carries history may disappear because of a single click.
 */
class SafeActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->admin = User::factory()->create();
    }

    private function creative(array $attributes = []): Creative
    {
        return Creative::create([
            'reference' => 'REF-'.Creative::count().'-'.uniqid(),
            'name' => 'Créa',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'video',
            ...$attributes,
        ]);
    }

    public function test_a_creative_with_history_is_archived_not_deleted(): void
    {
        $creative = $this->creative();
        $creative->metrics()->create([
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'spend' => 100, 'leads' => 10,
        ]);

        $this->actingAs($this->admin)->delete("/creatives/{$creative->id}")->assertRedirect('/creatives');

        $this->assertDatabaseHas('creatives', ['id' => $creative->id]);
        $this->assertTrue($creative->refresh()->status->is_archived_state);
        $this->assertTrue($creative->history()->where('event', 'archived')->exists());
    }

    public function test_an_empty_creative_can_be_deleted_outright_when_asked(): void
    {
        $creative = $this->creative();

        $this->actingAs($this->admin)->delete("/creatives/{$creative->id}", ['force' => true]);

        $this->assertDatabaseMissing('creatives', ['id' => $creative->id]);
    }

    public function test_a_creative_carrying_a_generation_survives_a_force_delete(): void
    {
        $creative = $this->creative();
        $creative->generations()->create(['provider' => 'google_flow', 'status' => 'completed']);

        $this->actingAs($this->admin)->delete("/creatives/{$creative->id}", ['force' => true]);

        $this->assertDatabaseHas('creatives', ['id' => $creative->id]);
        $this->assertTrue($creative->refresh()->status->is_archived_state);
    }

    public function test_a_campaign_with_creatives_is_archived(): void
    {
        $campaign = Campaign::create(['name' => 'Meta septembre', 'country' => 'France', 'status' => 'active']);
        $campaign->creatives()->attach($this->creative()->id);

        $this->actingAs($this->admin)->delete("/campaigns/{$campaign->id}");

        $this->assertSame('archived', $campaign->refresh()->status);
    }

    public function test_a_landing_page_in_use_is_deactivated(): void
    {
        $page = LandingPage::create(['name' => 'LP', 'url' => 'https://example.com', 'version' => 'v1']);
        $this->creative(['landing_page_id' => $page->id]);

        $this->actingAs($this->admin)->delete("/landing-pages/{$page->id}");

        $this->assertDatabaseHas('landing_pages', ['id' => $page->id, 'is_active' => false]);
    }

    public function test_taxonomy_in_use_is_deactivated_and_users_are_never_deleted(): void
    {
        $product = Product::where('code', 'PAC')->firstOrFail();
        $this->creative();

        $this->actingAs($this->admin)->delete("/admin/products/{$product->id}");
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);

        $other = User::factory()->create();
        $this->actingAs($this->admin)->delete("/admin/users/{$other->id}");
        $this->assertDatabaseHas('users', ['id' => $other->id, 'is_active' => false]);
    }

    public function test_duplicate_taxonomy_values_are_rejected_with_a_readable_error(): void
    {
        $category = ParameterCategory::where('slug', 'gender')->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/parameter-values', [
            'parameter_category_id' => $category->id,
            'label' => 'Femme',
            'code' => 'W2',
            'position' => 9,
        ])->assertSessionHasErrors('label');

        $this->actingAs($this->admin)->post('/admin/parameter-values', [
            'parameter_category_id' => $category->id,
            'label' => 'Autre',
            'code' => 'W',
            'position' => 9,
        ])->assertSessionHasErrors('code');

        $this->assertSame(2, $category->values()->count());
    }

    public function test_archived_values_disappear_from_creation_but_stay_on_existing_creatives(): void
    {
        $value = ParameterValue::whereHas('category', fn ($q) => $q->where('slug', 'gender'))->first();

        $creative = $this->creative();
        $creative->parameters()->create([
            'parameter_category_id' => $value->parameter_category_id,
            'parameter_value_id' => $value->id,
        ]);

        $this->actingAs($this->admin)->delete("/admin/parameter-values/{$value->id}");
        $this->assertTrue($value->refresh()->is_archived);

        $categories = collect($this->actingAs($this->admin)->get('/creatives/new')
            ->viewData('page')['props']['options']['categories']);

        $offered = collect($categories->firstWhere('slug', 'gender')['values'])->pluck('id');

        $this->assertNotContains($value->id, $offered, 'An archived value must not be offered on new creatives.');
        $this->assertTrue($creative->parameterValues()->where('parameter_values.id', $value->id)->exists());
    }

    public function test_a_prompt_that_produced_a_generation_cannot_be_deleted(): void
    {
        $creative = $this->creative();
        $prompt = $creative->prompts()->create([
            'version' => 1, 'body' => 'x', 'status' => CreativePrompt::STATUS_VALIDATED, 'validated_at' => now(),
        ]);
        $creative->generations()->create([
            'creative_prompt_id' => $prompt->id, 'provider' => 'google_flow', 'status' => 'completed',
        ]);

        $this->actingAs($this->admin)->delete("/prompts/{$prompt->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('creative_prompts', ['id' => $prompt->id]);
    }

    public function test_deleting_the_current_generation_clears_the_asset_it_provided(): void
    {
        $creative = $this->creative();
        $generation = $creative->generations()->create([
            'provider' => 'google_flow', 'status' => 'completed', 'asset_url' => 'https://example.com/a.mp4',
        ]);
        $creative->update([
            'creative_generation_id' => $generation->id,
            'asset_url' => 'https://example.com/a.mp4',
            'asset_source' => 'google_flow',
        ]);

        $this->actingAs($this->admin)->delete("/generations/{$generation->id}");

        $creative->refresh();
        $this->assertNull($creative->creative_generation_id);
        $this->assertNull($creative->asset_url, 'The creative must not point at an asset that is gone.');
    }

    public function test_a_failed_generation_does_not_leave_the_creative_stuck_in_generating(): void
    {
        config()->set('integrations.generation.google_veo.api_key', 'test-key');
        Http::fake([
            '*:predictLongRunning' => Http::response(['name' => 'operations/x']),
            '*operations/x' => Http::response(['done' => true, 'error' => ['message' => 'boom']]),
        ]);

        $creative = $this->creative(['creative_status_id' => CreativeStatus::where('slug', 'idea')->value('id')]);
        $prompt = $creative->prompts()->create([
            'version' => 1, 'body' => 'x', 'status' => CreativePrompt::STATUS_VALIDATED, 'validated_at' => now(),
        ]);

        $this->actingAs($this->admin)->post("/creatives/{$creative->id}/generations", [
            'creative_prompt_id' => $prompt->id, 'provider' => 'google_veo',
        ]);
        $this->assertSame('generating', $creative->refresh()->status->slug);

        $generation = CreativeGeneration::firstOrFail();
        $this->actingAs($this->admin)->post("/generations/{$generation->id}/refresh")->assertSessionHas('error');

        $this->assertSame(CreativeGeneration::STATUS_FAILED, $generation->refresh()->status);
        $this->assertSame(
            'prompt_ready',
            $creative->refresh()->status->slug,
            'A failed generation must return the creative to a state the admin can act on.',
        );
    }

    public function test_a_variation_records_exactly_what_changed(): void
    {
        $creative = $this->creative();
        $gender = ParameterCategory::where('slug', 'gender')->firstOrFail();
        $creative->parameters()->create([
            'parameter_category_id' => $gender->id,
            'parameter_value_id' => $gender->values()->where('code', 'W')->value('id'),
        ]);
        $creative->channels()->sync([Channel::where('code', 'FB')->value('id')]);

        $this->actingAs($this->admin)->post("/creatives/{$creative->id}/duplicate", [
            'variations' => [[
                'parameter_category_id' => $gender->id,
                'parameter_value_id' => $gender->values()->where('code', 'M')->value('id'),
            ]],
        ])->assertSessionHas('success');

        $variation = Creative::where('duplicated_from_id', $creative->id)->firstOrFail();
        $entry = $variation->history()->where('event', 'created')->firstOrFail();

        $this->assertStringContainsString('Genre : Femme → Homme', $entry->description);
        $this->assertStringContainsString('tout le reste inchangé', $entry->description);
        $this->assertSame([['category' => 'Genre', 'from' => 'Femme', 'to' => 'Homme']], $entry->meta['changes']);
    }
}
