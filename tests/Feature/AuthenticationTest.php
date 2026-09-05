<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_reachable_and_guests_are_redirected(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'password', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_there_is_a_single_role_and_it_can_reach_everything(): void
    {
        $user = User::factory()->create();

        // No role column, no role middleware: one internal team, full access.
        $this->assertFalse(Schema::hasColumn('users', 'role'));

        foreach (['/creatives', '/campaigns', '/performance', '/landing-pages', '/ai-studio', '/admin'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_the_landing_page_is_the_tree(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/creative-tree');
    }
}
