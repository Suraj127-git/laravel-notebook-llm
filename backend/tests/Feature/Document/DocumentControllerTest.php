<?php

namespace Tests\Feature\Document;

use App\Jobs\ProcessUploadedDocument;
use App\Models\Document;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_documents_filtered_by_notebook(): void
    {
        $user = User::factory()->create();
        $nb1 = Notebook::factory()->create(['user_id' => $user->id]);
        $nb2 = Notebook::factory()->create(['user_id' => $user->id]);

        Document::factory()->create(['user_id' => $user->id, 'notebook_id' => $nb1->id, 'title' => 'Doc A']);
        Document::factory()->create(['user_id' => $user->id, 'notebook_id' => $nb2->id, 'title' => 'Doc B']);

        $this->actingAs($user)->getJson("/api/documents?notebook_id={$nb1->id}")
            ->assertOk()
            ->assertJsonFragment(['title' => 'Doc A'])
            ->assertJsonMissing(['title' => 'Doc B']);
    }

    public function test_store_uploads_document_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $this->actingAs($user)->postJson('/api/documents', [
            'file' => $file,
            'notebook_id' => $nb->id,
        ])->assertStatus(201)
            ->assertJsonFragment(['title' => 'test', 'status' => 'uploaded']);

        Queue::assertPushed(ProcessUploadedDocument::class);
    }

    public function test_store_rejects_file_too_large(): void
    {
        $user = User::factory()->create();
        $nb = Notebook::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('big.txt', 20000); // > 10MB

        $this->actingAs($user)->postJson('/api/documents', [
            'file' => $file,
            'notebook_id' => $nb->id,
        ])->assertStatus(422);
    }

    public function test_store_requires_valid_notebook_id(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.txt', 10, 'text/plain');

        $this->actingAs($user)->postJson('/api/documents', [
            'file' => $file,
            'notebook_id' => 9999999,
        ])->assertStatus(422);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/documents')->assertStatus(401);
    }
}
