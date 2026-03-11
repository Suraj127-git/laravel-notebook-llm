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

        Log::info('notebook.listed', [
            'operation'      => 'list',
            'user_id'        => $request->user()->id,
            'notebook_count' => $notebooks->count(),
        ]);

        return response()->json($notebooks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'emoji'       => ['nullable', 'string', 'max:8'],
        ]);

        try {
            $notebook = Notebook::create([
                'user_id'     => $request->user()->id,
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'emoji'       => $validated['emoji'] ?? '📓',
            ]);

            Log::info('notebook.created', [
                'operation'   => 'create',
                'status'      => 'success',
                'user_id'     => $request->user()->id,
                'notebook_id' => $notebook->id,
                'name'        => $notebook->name,
            ]);

            return response()->json($notebook->loadCount('documents'), 201);

        } catch (\Throwable $e) {
            Log::error('notebook.create_failed', [
                'operation'   => 'create',
                'status'      => 'failed',
                'user_id'     => $request->user()->id,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function show(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorizeNotebook($request, $notebook);

        Log::debug('notebook.viewed', [
            'operation'   => 'show',
            'user_id'     => $request->user()->id,
            'notebook_id' => $notebook->id,
        ]);

        return response()->json($notebook->loadCount('documents'));
    }

    public function update(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorizeNotebook($request, $notebook);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'emoji'       => ['nullable', 'string', 'max:8'],
        ]);

        try {
            $notebook->update($validated);

            Log::info('notebook.updated', [
                'operation'      => 'update',
                'status'         => 'success',
                'user_id'        => $request->user()->id,
                'notebook_id'    => $notebook->id,
                'changed_fields' => array_keys($validated),
            ]);

            return response()->json($notebook);

        } catch (\Throwable $e) {
            Log::error('notebook.update_failed', [
                'operation'   => 'update',
                'status'      => 'failed',
                'user_id'     => $request->user()->id,
                'notebook_id' => $notebook->id,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function destroy(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorizeNotebook($request, $notebook);

        try {
            $notebookId = $notebook->id;
            $notebook->delete();

            Log::info('notebook.deleted', [
                'operation'   => 'delete',
                'status'      => 'success',
                'user_id'     => $request->user()->id,
                'notebook_id' => $notebookId,
            ]);

            return response()->json(['message' => 'Notebook deleted']);

        } catch (\Throwable $e) {
            Log::error('notebook.delete_failed', [
                'operation'   => 'delete',
                'status'      => 'failed',
                'user_id'     => $request->user()->id,
                'notebook_id' => $notebook->id,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    private function authorizeNotebook(Request $request, Notebook $notebook): void
    {
        abort_if($notebook->user_id !== $request->user()->id, 403, 'Forbidden');
    }
}
