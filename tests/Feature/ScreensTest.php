<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Creative;
use App\Models\LandingPage;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen listed in the spec renders against the seeded demo data.
 */
class ScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->admin = User::factory()->create();
    }

    public function test_the_dashboard_stays_a_quick_overview(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('cards.winners')
                ->has('cards.live_tests')
                ->has('cards.opportunities')
                ->has('cards.ready')
                ->has('opportunities')
                ->has('recentWinners')
                ->has('activity')
            );
    }

    public function test_the_tree_is_the_landing_screen(): void
    {
        $this->actingAs($this->admin)->get('/creative-tree')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CreativeTree')
                ->has('tree')
                ->has('missingRoots')
                ->has('gaps')
            );
    }

    public function test_every_spec_screen_renders(): void
    {
        $creative = Creative::firstOrFail();
        $campaign = Campaign::firstOrFail();

        $screens = [
            '/creative-tree' => 'CreativeTree',
            '/creatives' => 'Creatives/Index',
            '/creatives/new' => 'Creatives/New',
            "/creatives/{$creative->id}" => 'Creatives/Show',
            '/campaigns' => 'Campaigns/Index',
            "/campaigns/{$campaign->id}" => 'Campaigns/Show',
            '/landing-pages' => 'LandingPages/Index',
            '/performance' => 'Performance',
            '/admin' => 'Admin/Index',
            '/ai-studio' => 'AiStudio',
        ];

        foreach ($screens as $url => $component) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_filters_combine_and_narrow_the_creative_list(): void
    {
        $unfiltered = $this->actingAs($this->admin)->get('/creatives');
        $total = count($unfiltered->viewData('page')['props']['creatives']);

        $pacId = Product::where('code', 'PAC')->value('id');
        $filtered = $this->actingAs($this->admin)->get('/creatives?product='.$pacId.'&rating=winner');

        $creatives = $filtered->viewData('page')['props']['creatives'];

        $this->assertLessThan($total, count($creatives));
        $this->assertNotEmpty($creatives);

        foreach ($creatives as $creative) {
            $this->assertSame('PAC', $creative['product']['code']);
            $this->assertSame('winner', $creative['rating']);
        }
    }

    public function test_the_seeded_demo_data_is_coherent(): void
    {
        $this->assertGreaterThanOrEqual(10, Creative::count());
        $this->assertGreaterThan(0, Campaign::where('status', 'active')->count());
        $this->assertGreaterThan(0, LandingPage::count());

        // Every creative carries a queryable persona and a tracking URL.
        Creative::with(['parameters', 'utm', 'landingPage'])->get()->each(function (Creative $creative) {
            $this->assertNotEmpty($creative->parameters, $creative->reference.' has no persona');
            $this->assertNotNull($creative->utm, $creative->reference.' has no UTM row');
            $this->assertStringContainsString('utm_source=', (string) $creative->utm->finalUrl($creative->landingPage?->url));
        });
    }

    public function test_a_landing_page_is_shared_by_several_creatives(): void
    {
        $shared = LandingPage::withCount('creatives')->orderByDesc('creatives_count')->first();

        $this->assertGreaterThan(1, $shared->creatives_count);
    }
}
