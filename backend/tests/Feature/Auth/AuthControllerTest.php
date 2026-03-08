<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Register ───────────────────────────────────────────────────────────

    public function test_register_creates_user_and_returns_token(): void
    {
        $res = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_register_fails_with_missing_fields(): void
    {
        $this->postJson('/api/register', [])->assertStatus(422);
    }

    // ─── Login ───────────────────────────────────────────────────────────────

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'bob@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'bob@example.com',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => bcrypt('secret123')]);

        $this->postJson('/api/login', [
            'email' => 'bob@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(422);
    }

    public function test_login_fails_for_nonexistent_user(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'anything',
        ])->assertStatus(422);
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/logout')->assertNoContent();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    // ─── /user ───────────────────────────────────────────────────────────────

    public function test_get_user_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/user')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_get_user_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }
}
