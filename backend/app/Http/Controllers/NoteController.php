<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request, int $notebookId): JsonResponse
    {
        $notes = $request->user()
            ->notes()
            ->where('notebook_id', $notebookId)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notebook_id'     => ['required', 'integer', 'exists:notebooks,id'],
            'content'         => ['required', 'string'],
            'title'           => ['nullable', 'string', 'max:255'],
            'chat_message_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
        ]);

        $note = $request->user()->notes()->create($validated);

        return response()->json($note, 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'pinned'  => ['sometimes', 'boolean'],
        ]);

        $note->update($validated);

        return response()->json($note);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->delete();

        return response()->json(['deleted' => true]);
    }
}
