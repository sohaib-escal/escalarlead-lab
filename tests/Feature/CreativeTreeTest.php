<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\ParameterCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\CreativeTree;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreativeTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
    }

    private function makeCreative(array $pairs, string $statusSlug = 'live', string $channelCode = 'FB'): Creative
    {
        $creative = Creative::create([
            'reference' => 'REF-'.Creative::count().'-'.$statusSlug,
            'name' => 'Créa '.Creative::count(),
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', $statusSlug)->value('id'),
            'format' => 'static_image',
        ]);

        foreach ($pairs as $categorySlug => $code) {
            $category = ParameterCategory::where('slug', $categorySlug)->firstOrFail();
            $creative->parameters()->create([
                'parameter_category_id' => $category->id,
                'parameter_value_id' => $category->values()->where('code', $code)->firstOrFail()->id,
            ]);
        }

        $creative->channels()->sync([Channel::where('code', $channelCode)->value('id')]);

        return $creative;
    }

    public function test_the_tree_counts_creatives_live_and_winners_per_branch(): void
    {
        $this->makeCreative(['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL']);
        $this->makeCreative(['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL'], 'ready');
        $winner = $this->makeCreative(['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL']);
        $winner->update(['performance_override' => 'winner']);

        $result = app(CreativeTree::class)->build(['product', 'specific-problem', 'gender', 'age']);

        $this->assertSame(['creatives' => 3, 'live' => 2, 'winners' => 1], $result['totals']);

        $product = $result['tree'][0];
        $this->assertSame('Pompe à chaleur', $product['label']);
        $this->assertSame(3, $product['creatives']);

        $leaf = $product['children'][0]['children'][0]['children'][0];
        $this->assertSame('60–69', $leaf['label']);
        $this->assertSame(3, $leaf['creatives']);
        $this->assertSame(2, $leaf['live']);
        $this->assertSame(1, $leaf['winners']);
        $this->assertTrue($leaf['is_leaf']);
    }

    public function test_untested_combinations_are_reported_as_gaps(): void
    {
        // Tested: PAC / high heating bill / women / government aid.
        $this->makeCreative([
            'gender' => 'W', 'specific-problem' => 'HIGHBILL', 'motivation' => 'AID',
        ]);

        $result = app(CreativeTree::class)->build(['product', 'specific-problem', 'gender', 'motivation']);

        $branch = $result['tree'][0]['children'][0]['children'][0];
        $tested = collect($branch['children'])->pluck('label');
        $missing = collect($branch['missing_children'])->pluck('label');

        $this->assertContains('Aides de l\'État', $tested);
        $this->assertContains('Confort', $missing, 'The comfort angle has not been tested and must show up as a gap.');

        $gapLabels = collect($result['gaps'])->pluck('label');
        $this->assertTrue($gapLabels->contains(fn ($label) => str_contains($label, 'Confort')));
    }

    public function test_the_tree_screen_renders_and_can_drill_into_a_branch(): void
    {
        $creative = $this->makeCreative(['gender' => 'W', 'age' => '60-69']);
        $genderValue = $creative->parameters->first()->parameter_value_id;

        $this->actingAs(User::factory()->create())
            ->get('/creative-tree?axes[]=product&axes[]=gender&selection[]=gender:'.$genderValue)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CreativeTree')
                ->has('tree')
                ->has('branchCreatives', 1)
            );
    }

    public function test_untested_branches_respect_the_product_a_value_belongs_to(): void
    {
        // A PAC creative is never about old windows: scoped values must not be proposed.
        $this->makeCreative(['specific-problem' => 'HIGHBILL']);

        $result = app(CreativeTree::class)->build(['product', 'specific-problem']);

        $pac = collect($result['tree'])->firstWhere('label', 'Pompe à chaleur');
        $proposed = collect($pac['missing_children'])->pluck('label');

        $this->assertContains('Chaudière ancienne', $proposed, 'Another heating problem is a real gap.');
        $this->assertNotContains('Fenêtres anciennes', $proposed);
        $this->assertNotContains('Toiture adaptée au solaire', $proposed);
    }

    public function test_axes_are_data_driven_and_follow_the_in_tree_flag(): void
    {
        $axes = collect(app(CreativeTree::class)->availableAxes())->pluck('key');

        $this->assertContains('product', $axes);
        $this->assertContains('specific-problem', $axes);
        $this->assertNotContains('objection', $axes, 'Objection is not flagged as a tree axis by default.');

        ParameterCategory::where('slug', 'objection')->update(['in_tree' => true]);

        $this->assertContains('objection', collect(app(CreativeTree::class)->availableAxes())->pluck('key'));
    }
}
