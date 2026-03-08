<?php

namespace Tests\Feature\Notebook;

use App\Models\Notebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotebookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_own_notebooks(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Notebook::factory()->create(['user_id' => $alice->id, 'name' => 'Alice NB']);
        Notebook::factory()->create(['user_id' => $bob->id, 'name' => 'Bob NB']);

        $this->actingAs($alice)->getJson('/api/notebooks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Alice NB'])
            ->assertJsonMissing(['name' => 'Bob NB']);
    }

    public function test_store_creates_notebook(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/notebooks', ['name' => 'My NB', 'emoji' => '🧪'])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'My NB', 'emoji' => '🧪']);

        $this->assertDatabaseHas('notebooks', ['user_id' => $user->id, 'name' => 'My NB']);
    }

    public function test_store_fails_without_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/notebooks', [])->assertStatus(422);
    }

    public function test_update_modifies_own_notebook(): void
    {
        $user = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->patchJson("/api/notebooks/{$nb->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated']);
    }

    public function test_update_returns_403_for_other_users_notebook(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $bob->id]);

        $this->actingAs($alice)->patchJson("/api/notebooks/{$nb->id}", ['name' => 'Hacked'])->assertStatus(403);
    }

    public function test_destroy_deletes_own_notebook(): void
    {
        $user = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/notebooks/{$nb->id}")->assertNoContent();

        $this->assertDatabaseMissing('notebooks', ['id' => $nb->id]);
    }

    public function test_destroy_returns_403_for_other_users_notebook(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $bob->id]);

        $this->actingAs($alice)->deleteJson("/api/notebooks/{$nb->id}")->assertStatus(403);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/notebooks')->assertStatus(401);
    }
}
