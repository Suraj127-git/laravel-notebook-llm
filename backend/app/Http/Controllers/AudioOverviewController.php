<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAudioOverview;
use App\Models\AudioOverview;
use App\Models\Notebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudioOverviewController extends Controller
{
    public function show(Request $request, int $notebookId): JsonResponse
    {
        $notebook = Notebook::findOrFail($notebookId);
        abort_unless($notebook->user_id === $request->user()->id, 403);

        $overview = AudioOverview::where('notebook_id', $notebookId)->first();

        if (! $overview) {
            return response()->json(null, 404);
        }

        return response()->json($overview);
    }

    public function generate(Request $request, int $notebookId): JsonResponse
    {
        $notebook = Notebook::findOrFail($notebookId);
        abort_unless($notebook->user_id === $request->user()->id, 403);

        $overview = AudioOverview::updateOrCreate(
            ['notebook_id' => $notebookId],
            [
                'user_id' => $request->user()->id,
                'status'  => 'pending',
                'error'   => null,
            ]
        );

        GenerateAudioOverview::dispatch($overview, $notebook);

        return response()->json($overview, 202);
    }

    /**
     * Stream the audio file (uses stream.auth middleware for token-in-query-param auth).
     */
    public function stream(Request $request, int $notebookId): StreamedResponse|JsonResponse
    {
        // Token auth handled by stream.auth middleware — user is set via auth()
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $notebook = Notebook::findOrFail($notebookId);
        abort_unless($notebook->user_id === $user->id, 403);

        $overview = AudioOverview::where('notebook_id', $notebookId)
            ->where('status', 'ready')
            ->first();

        if (! $overview || ! $overview->storage_path) {
            return response()->json(['error' => 'Audio not available'], 404);
        }

        $fullPath = Storage::disk('local')->path($overview->storage_path);

        if (! file_exists($fullPath)) {
            return response()->json(['error' => 'Audio file not found'], 404);
        }

        $fileSize = filesize($fullPath);

        return response()->stream(function () use ($fullPath): void {
            $handle = fopen($fullPath, 'rb');
            if ($handle) {
                while (! feof($handle)) {
                    echo fread($handle, 8192);
                    @ob_flush();
                    @flush();
                }
                fclose($handle);
            }
        }, 200, [
            'Content-Type'        => 'audio/mpeg',
            'Content-Length'      => $fileSize,
            'Cache-Control'       => 'no-cache',
            'Accept-Ranges'       => 'bytes',
            'Content-Disposition' => 'inline',
        ]);
    }
}
