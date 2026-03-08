<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGroq(string $reply = 'Test response'): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => $reply, 'role' => 'assistant'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
            'api.voyageai.com/*' => Http::response([
                'data' => [['embedding' => array_fill(0, 1024, 0.1)]],
            ]),
        ]);
    }

    public function test_history_returns_messages_for_notebook(): void
    {
        $user = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $user->id]);

        ChatMessage::factory()->create(['user_id' => $user->id, 'notebook_id' => $nb->id, 'role' => 'user']);
        ChatMessage::factory()->create(['user_id' => $user->id, 'notebook_id' => $nb->id, 'role' => 'assistant']);

        $this->actingAs($user)->getJson("/api/chat/history/{$nb->id}")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_history_is_scoped_to_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $alice->id]);

        // Bob's message in Alice's notebook (cross-user boundary in DB)
        ChatMessage::factory()->create(['user_id' => $bob->id, 'notebook_id' => $nb->id]);

        $this->actingAs($alice)->getJson("/api/chat/history/{$nb->id}")
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/chat/history/1')->assertStatus(401);
    }
}
