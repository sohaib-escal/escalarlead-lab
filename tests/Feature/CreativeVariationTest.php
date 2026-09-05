<?php

namespace Tests\Feature;

use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\ParameterCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "What if we changed one thing?" — the experimentation engine.
 */
class CreativeVariationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Creative $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->admin = User::factory()->create();

        $this->original = Creative::create([
            'reference' => 'PAC-W-60-69-HIGHBILL-AID-FB-001',
            'name' => 'Femme 60-69 — facture chauffage — aides',
            'product_id' => Product::where('code', 'PAC')->value('id'),
            'creative_status_id' => CreativeStatus::where('slug', 'winner')->value('id'),
            'format' => 'static_image',
            'hook' => 'Votre chaudière vous coûte de plus en plus cher ?',
            'primary_text' => 'Texte long conservé tel quel.',
        ]);

        foreach (['gender' => 'W', 'age' => '60-69', 'specific-problem' => 'HIGHBILL', 'motivation' => 'AID'] as $slug => $code) {
            $category = ParameterCategory::where('slug', $slug)->firstOrFail();
            $this->original->parameters()->create([
                'parameter_category_id' => $category->id,
                'parameter_value_id' => $category->values()->where('code', $code)->firstOrFail()->id,
            ]);
        }
    }

    private function valueId(string $categorySlug, string $code): int
    {
        return ParameterCategory::where('slug', $categorySlug)->firstOrFail()
            ->values()->where('code', $code)->firstOrFail()->id;
    }

    public function test_changing_one_variable_keeps_everything_else(): void
    {
        $genderCategory = ParameterCategory::where('slug', 'gender')->firstOrFail();

        $this->actingAs($this->admin)->post("/creatives/{$this->original->id}/duplicate", [
            'variations' => [[
                'parameter_category_id' => $genderCategory->id,
                'parameter_value_id' => $this->valueId('gender', 'M'),
            ]],
        ])->assertRedirect();

        $variation = Creative::where('duplicated_from_id', $this->original->id)->firstOrFail();

        // The copy and the rest of the targeting survive.
        $this->assertSame($this->original->hook, $variation->hook);
        $this->assertSame($this->original->primary_text, $variation->primary_text);

        $labels = $variation->parameterValues()->pluck('code')->sort()->values()->all();
        $this->assertSame(['60-69', 'AID', 'HIGHBILL', 'M'], $labels);

        // Only the swapped dimension changed, and the reference says so.
        $this->assertStringContainsString('-M-', $variation->reference);
        $this->assertStringNotContainsString('-W-', $variation->reference);

        // A variation starts as an idea again, not as a winner.
        $this->assertSame('idea', $variation->status->slug);
        $this->assertTrue($variation->history()->where('description', 'like', 'Variation de%')->exists());
    }

    public function test_a_variation_can_add_a_dimension_the_original_did_not_carry(): void
    {
        $triggerCategory = ParameterCategory::where('slug', 'trigger')->firstOrFail();

        $this->actingAs($this->admin)->post("/creatives/{$this->original->id}/duplicate", [
            'variations' => [[
                'parameter_category_id' => $triggerCategory->id,
                'parameter_value_id' => $this->valueId('trigger', 'WINTER'),
            ]],
        ]);

        $variation = Creative::where('duplicated_from_id', $this->original->id)->firstOrFail();

        $this->assertCount(5, $variation->parameters);
        $this->assertTrue($variation->parameterValues()->where('code', 'WINTER')->exists());
    }

    public function test_a_plain_duplicate_still_copies_everything(): void
    {
        $this->actingAs($this->admin)->post("/creatives/{$this->original->id}/duplicate");

        $copy = Creative::where('duplicated_from_id', $this->original->id)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            $this->original->parameterValues()->pluck('parameter_values.id')->all(),
            $copy->parameterValues()->pluck('parameter_values.id')->all(),
        );
        $this->assertStringContainsString('(copie)', $copy->name);
    }
}
