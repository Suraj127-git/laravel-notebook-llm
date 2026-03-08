<?php

namespace App\Http\Controllers;

use App\Models\Notebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotebookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notebooks = Notebook::where('user_id', $request->user()->id)
            ->withCount('documents')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($notebooks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'emoji' => ['nullable', 'string', 'max:8'],
        ]);

        $notebook = Notebook::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'emoji' => $validated['emoji'] ?? '📓',
        ]);

        Log::info('Notebook created', ['notebook_id' => $notebook->id, 'user_id' => $request->user()->id]);

        return response()->json($notebook->loadCount('documents'), 201);
    }

    public function show(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorizeNotebook($request, $notebook);

        return response()->json($notebook->loadCount('documents'));
    }

    public function update(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorizeNotebook($request, $notebook);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'emoji' => ['nullable', 'string', 'max:8'],
        ]);

        $notebook->update($validated);

        return response()->json($notebook);
    }

    public function destroy(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorizeNotebook($request, $notebook);

        $notebook->delete();

        Log::info('Notebook deleted', ['notebook_id' => $notebook->id, 'user_id' => $request->user()->id]);

        return response()->json(['message' => 'Notebook deleted']);
    }

    private function authorizeNotebook(Request $request, Notebook $notebook): void
    {
        abort_if($notebook->user_id !== $request->user()->id, 403, 'Forbidden');
    }
}
