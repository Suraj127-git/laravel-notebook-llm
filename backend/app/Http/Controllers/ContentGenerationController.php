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

        try {
            $agent = new KnowledgeAgent($request->user(), $notebookId);
            $content = $agent->generateContent($validated['type']);

            return response()->json(['content' => $content]);
        } catch (\Throwable $e) {
            Log::error('Content generation failed', [
                'type' => $validated['type'],
                'notebook_id' => $notebookId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Content generation failed'], 500);
        }
    }
}
