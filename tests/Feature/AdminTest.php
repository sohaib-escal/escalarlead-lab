<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Creative;
use App\Models\CreativeStatus;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->admin = User::factory()->create();
    }

    public function test_an_admin_adds_a_parameter_category_and_a_value_without_a_developer(): void
    {
        $this->actingAs($this->admin)->post('/admin/parameter-categories', [
            'name' => 'Type de chaudière',
            'group' => 'energy',
            'is_multi' => false,
            'in_tree' => true,
            'in_naming' => false,
            'position' => 99,
            'is_active' => true,
        ])->assertRedirect();

        $category = ParameterCategory::where('slug', 'type-de-chaudiere')->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/parameter-values', [
            'parameter_category_id' => $category->id,
            'label' => 'Chaudière à condensation',
            'code' => 'COND',
            'position' => 0,
            'is_archived' => false,
        ])->assertRedirect();

        $this->assertSame(1, $category->values()->count());

        // The new category is immediately usable as a tree axis.
        $this->actingAs($this->admin)->get('/creative-tree?axes[]=type-de-chaudiere')->assertOk();
    }

    public function test_a_parameter_value_in_use_is_archived_rather_than_deleted(): void
    {
        $value = ParameterValue::first();

        $creative = Creative::create([
            'reference' => 'REF-001',
            'name' => 'Créa',
            'creative_status_id' => CreativeStatus::first()->id,
            'format' => 'static_image',
        ]);
        $creative->parameters()->create([
            'parameter_category_id' => $value->parameter_category_id,
            'parameter_value_id' => $value->id,
        ]);

        $this->actingAs($this->admin)->delete("/admin/parameter-values/{$value->id}")->assertRedirect();

        $this->assertTrue($value->refresh()->is_archived);
        $this->assertDatabaseHas('parameter_values', ['id' => $value->id]);
    }

    public function test_an_admin_adds_a_channel_with_its_utm_defaults(): void
    {
        $this->actingAs($this->admin)->post('/admin/channels', [
            'name' => 'Snapchat',
            'code' => 'SNAP',
            'default_utm_source' => 'snapchat',
            'default_utm_medium' => 'paid_social',
            'position' => 9,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('channels', ['slug' => 'snapchat', 'default_utm_source' => 'snapchat']);
        $this->assertSame(8, Channel::count());
    }

    public function test_an_admin_manages_users(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Nouveau buyer',
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'is_active' => true,
        ])->assertRedirect();

        $user = User::where('email', 'buyer@example.com')->firstOrFail();

        // Updating without a password keeps the existing one.
        $hash = $user->password;
        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
            'name' => 'Buyer renommé',
            'email' => 'buyer@example.com',
            'password' => '',
            'is_active' => true,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Buyer renommé', $user->name);
        $this->assertSame($hash, $user->password);
    }

    public function test_an_unknown_admin_resource_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/whatever', ['name' => 'x'])->assertNotFound();
    }
}
