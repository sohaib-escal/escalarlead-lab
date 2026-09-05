<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\LandingPage;
use App\Models\ParameterCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreativeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->buyer = User::factory()->create();
    }

    /**
     * @return array<int, int> parameter value ids keyed by nothing, in the order asked for
     */
    private function values(array $pairs): array
    {
        return collect($pairs)->map(function ($code, $categorySlug) {
            return ParameterCategory::where('slug', $categorySlug)->firstOrFail()
                ->values()->where('code', $code)->firstOrFail()->id;
        })->all();
    }

    private function parameterPayload(array $pairs): array
    {
        $payload = [];

        foreach ($pairs as $categorySlug => $code) {
            $category = ParameterCategory::where('slug', $categorySlug)->firstOrFail();
            $payload[$category->id] = [$category->values()->where('code', $code)->firstOrFail()->id];
        }

        return $payload;
    }

    public function test_a_media_buyer_creates_a_creative_and_gets_a_readable_id_and_tracking_url(): void
    {
        $landingPage = LandingPage::create([
            'name' => 'Diagnostic PAC',
            'url' => 'https://example.com/diagnostic',
            'version' => 'v1',
        ]);

        $response = $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Femme 60-69 — facture chauffage',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'ready')->value('id'),
            'landing_page_id' => $landingPage->id,
            'format' => 'static_image',
            'hook' => 'Votre chaudière vous coûte de plus en plus cher ?',
            'channels' => [Channel::where('code', 'FB')->value('id')],
            'parameters' => $this->parameterPayload([
                'gender' => 'W',
                'age' => '60-69',
                'specific-problem' => 'HIGHBILL',
                'motivation' => 'AID',
            ]),
            'utm' => ['auto_sync' => true],
        ]);

        $creative = Creative::firstOrFail();
        $response->assertRedirect('/creatives/'.$creative->id);

        $this->assertSame('PAC-W-60-69-HIGHBILL-AID-FB-001', $creative->reference);
        $this->assertCount(4, $creative->parameters);

        $creative->load('utm', 'landingPage');
        $this->assertSame(
            'https://example.com/diagnostic?utm_source=facebook&utm_medium=paid_social'
            .'&utm_campaign=pompe_a_chaleur_france&utm_content=pac-w-60-69-highbill-aid-fb-001',
            $creative->utm->finalUrl($creative->landingPage->url),
        );
    }

    public function test_references_do_not_collide(): void
    {
        $payload = [
            'name' => 'Test',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'idea')->value('id'),
            'format' => 'static_image',
            'channels' => [Channel::where('code', 'FB')->value('id')],
            'parameters' => $this->parameterPayload(['gender' => 'W', 'age' => '60-69']),
        ];

        $this->actingAs($this->buyer)->post('/creatives', $payload);
        $this->actingAs($this->buyer)->post('/creatives', $payload);

        $this->assertSame(
            ['PAC-W-60-69-FB-001', 'PAC-W-60-69-FB-002'],
            Creative::orderBy('id')->pluck('reference')->all(),
        );
    }

    public function test_duplicating_keeps_the_copy_and_the_targeting(): void
    {
        $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Femme 60-69 — facture chauffage',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'static_image',
            'hook' => 'Votre chaudière vous coûte cher ?',
            'primary_text' => 'Texte long.',
            'channels' => [Channel::where('code', 'FB')->value('id')],
            'parameters' => $this->parameterPayload(['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL']),
        ]);

        $original = Creative::firstOrFail();

        $this->actingAs($this->buyer)->post("/creatives/{$original->id}/duplicate");

        $copy = Creative::where('duplicated_from_id', $original->id)->firstOrFail();

        $this->assertNotSame($original->reference, $copy->reference);
        $this->assertSame($original->hook, $copy->hook);
        $this->assertSame($original->primary_text, $copy->primary_text);
        $this->assertSame('idea', $copy->status->slug);
        $this->assertEqualsCanonicalizing(
            $original->parameters->pluck('parameter_value_id')->all(),
            $copy->parameters->pluck('parameter_value_id')->all(),
        );
    }

    public function test_creatives_can_be_queried_by_persona_combination(): void
    {
        $product = Product::where('code', 'PAC')->value('id');
        $status = CreativeStatus::where('slug', 'live')->value('id');
        $facebook = Channel::where('code', 'FB')->value('id');

        $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Femme 60-69', 'product_id' => $product, 'creative_status_id' => $status,
            'format' => 'static_image', 'channels' => [$facebook],
            'parameters' => $this->parameterPayload(['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL']),
        ]);

        $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Homme 60-69', 'product_id' => $product, 'creative_status_id' => $status,
            'format' => 'static_image', 'channels' => [$facebook],
            'parameters' => $this->parameterPayload(['gender' => 'M', 'age' => '60-69', 'specific-problem' => 'HIGHBILL']),
        ]);

        $women = $this->values(['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL']);

        $matches = Creative::withAllParameterValues($women)->get();

        $this->assertCount(1, $matches);
        $this->assertSame('Femme 60-69', $matches->first()->name);
    }

    public function test_search_covers_hook_utm_and_audience(): void
    {
        $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Créa test', 'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'static_image', 'channels' => [Channel::where('code', 'FB')->value('id')],
            'hook' => 'Votre chaudière fioul coûte cher',
            'parameters' => $this->parameterPayload(['gender' => 'W']),
        ]);

        $this->assertCount(1, Creative::search('chaudière fioul')->get());
        $this->assertCount(1, Creative::search('facebook')->get());   // via the UTM source
        $this->assertCount(1, Creative::search('Femme')->get());      // via a persona value
        $this->assertCount(0, Creative::search('TikTok')->get());
    }

    public function test_performance_entry_updates_the_rating_and_history(): void
    {
        $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Créa perf', 'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'static_image', 'channels' => [Channel::where('code', 'FB')->value('id')],
        ]);

        $creative = Creative::firstOrFail();

        $this->actingAs($this->buyer)->post("/creatives/{$creative->id}/metrics", [
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07',
            'spend' => 420, 'impressions' => 96000, 'reach' => 60000, 'clicks' => 2100,
            'leads' => 84, 'qualified_leads' => 42, 'contacted' => 70, 'phone_qualified' => 46,
            'appointments' => 21, 'confirmed' => 12, 'sales' => 4, 'revenue' => 19600,
        ])->assertRedirect();

        $creative->refresh()->load('metrics');

        $this->assertSame('winner', $creative->rating());
        $this->assertSame(10.0, $creative->summary()->costPerQualified());

        // A manual override always wins over the computed rating.
        $this->actingAs($this->buyer)->put("/creatives/{$creative->id}/rating", ['performance_override' => 'average']);
        $this->assertSame('average', $creative->refresh()->rating());

        $this->assertTrue($creative->history()->where('event', 'metrics_added')->exists());
    }

    public function test_a_creative_can_belong_to_several_campaigns_without_duplication(): void
    {
        $campaignA = Campaign::create(['name' => 'Meta septembre', 'country' => 'France', 'status' => 'active']);
        $campaignB = Campaign::create(['name' => 'Meta octobre', 'country' => 'France', 'status' => 'draft']);

        $this->actingAs($this->buyer)->post('/creatives', [
            'name' => 'Créa partagée', 'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'live')->value('id'),
            'format' => 'static_image', 'channels' => [Channel::where('code', 'FB')->value('id')],
            'campaigns' => [$campaignA->id, $campaignB->id],
        ]);

        $this->assertSame(1, Creative::count());
        $this->assertSame(2, Creative::firstOrFail()->campaigns()->count());
    }
}
