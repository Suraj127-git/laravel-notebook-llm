<?php

namespace App\Http\Controllers;

use App\Ai\Agents\KnowledgeAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContentGenerationController extends Controller
{
    public function generate(Request $request, int $notebookId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:study_guide,faq,timeline,briefing'],
        ]);

        $start = microtime(true);
        $type  = $validated['type'];

        try {
            $agent   = new KnowledgeAgent($request->user(), $notebookId);
            $content = $agent->generateContent($type);

            Log::info('content.generated', [
                'operation'      => 'generate_content',
                'status'         => 'success',
                'user_id'        => $request->user()?->id,
                'notebook_id'    => $notebookId,
                'content_type'   => $type,
                'content_length' => strlen($content),
                'duration_ms'    => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['content' => $content]);
        } catch (\Throwable $e) {
            Log::error('content.generate_failed', [
                'operation'    => 'generate_content',
                'status'       => 'failed',
                'user_id'      => $request->user()?->id,
                'notebook_id'  => $notebookId,
                'content_type' => $type,
                'error'        => $e->getMessage(),
                'error_class'  => get_class($e),
                'duration_ms'  => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['error' => 'Content generation failed'], 500);
        }
    }
}
