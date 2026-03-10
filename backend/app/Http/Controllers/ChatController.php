<?php

namespace App\Http\Controllers;

use App\Ai\Agents\KnowledgeAgent;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    /**
     * Non-streaming chat: RAG retrieval → Groq → persist messages → return answer + sources.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $notebookId = (int) $validated['notebook_id'];
        $message = $validated['message'];
        $user = $request->user();

        Log::info('Chat request received', [
            'user_id' => $user?->id,
            'notebook_id' => $notebookId,
            'message_length' => strlen($message),
        ]);

        try {
            $agent = new KnowledgeAgent($user, $notebookId);
            $response = $agent->chat($message);

            // Persist both user and assistant messages
            $this->persistMessages($user?->id, $notebookId, $message, $response->content(), $response->sources);

            return response()->json([
                'answer' => $response->content(),
                'sources' => $response->sources,
            ]);

        } catch (\Throwable $e) {
            Log::error('Chat request failed', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
                'notebook_id' => $notebookId,
            ]);

            return response()->json(['error' => 'An error occurred while processing your request'], 500);
        }
    }

    /**
     * SSE streaming chat: RAG retrieval → Groq stream → emit deltas → emit sources → done.
     */
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        // Token authentication via query param for EventSource compatibility
        if ($request->has('token') && ! auth()->check()) {
            $token = $request->input('token');
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if ($accessToken) {
                auth()->setUser($accessToken->tokenable);
                Log::info('Stream: token auth successful', ['user_id' => auth()->id()]);
            } else {
                Log::warning('Stream: invalid token provided');
            }
        }

        $validated = $request->validate([
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $notebookId = (int) $validated['notebook_id'];
        $message = $validated['message'];
        $user = auth()->user();

        Log::info('Chat stream request received', [
            'user_id' => $user?->id,
            'notebook_id' => $notebookId,
            'message_length' => strlen($message),
        ]);

        try {
            $agent = new KnowledgeAgent($user, $notebookId);

            return response()->stream(function () use ($agent, $message, $user, $notebookId): void {
                try {
                    $stream = $agent->chatStream($message);
                    $chunkCount = 0;
                    $fullText = '';

                    foreach ($stream as $event) {
                        $chunk = $event->text();
                        $fullText .= $chunk;
                        $chunkCount++;

                        echo 'data: '.json_encode(['delta' => $chunk], JSON_THROW_ON_ERROR)."\n\n";
                        @ob_flush();
                        @flush();
                    }

                    // Send sources before signalling done
                    $sources = $agent->getSources();
                    echo 'data: '.json_encode(['sources' => $sources], JSON_THROW_ON_ERROR)."\n\n";
                    @ob_flush();
                    @flush();

                    echo 'data: '.json_encode(['done' => true], JSON_THROW_ON_ERROR)."\n\n";
                    @ob_flush();
                    @flush();

                    // Persist messages after stream completes
                    $this->persistMessages($user?->id, $notebookId, $message, $fullText, $sources);

                    Log::info('Stream completed', [
                        'chunks' => $chunkCount,
                        'user_id' => $user?->id,
                    ]);

                } catch (\Throwable $e) {
                    Log::error('Stream processing failed', [
                        'error' => $e->getMessage(),
                        'user_id' => $user?->id,
                    ]);

                    echo 'data: '.json_encode(['error' => 'Stream processing failed'], JSON_THROW_ON_ERROR)."\n\n";
                    @ob_flush();
                    @flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
            ]);

        } catch (\Throwable $e) {
            Log::error('Chat stream setup failed', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            return response()->json(['error' => 'Failed to initialize chat stream'], 500);
        }
    }

    /**
     * Generate 3 suggested follow-up questions based on the last AI answer.
     */
    public function suggestQuestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
            'last_answer' => ['required', 'string', 'max:8000'],
        ]);

        try {
            $agent = new KnowledgeAgent($request->user(), (int) $validated['notebook_id']);
            $questions = $agent->suggestQuestions($validated['last_answer']);

            return response()->json(['questions' => $questions]);
        } catch (\Throwable $e) {
            Log::warning('suggestQuestions failed', ['error' => $e->getMessage()]);

            return response()->json(['questions' => []]);
        }
    }

    /**
     * Return chat history for a notebook (latest 50 messages, oldest first).
     */
    public function history(Request $request, int $notebookId): JsonResponse
    {
        $messages = ChatMessage::query()
            ->where('user_id', $request->user()->id)
            ->where('notebook_id', $notebookId)
            ->latest()
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json($messages);
    }

    /**
     * Persist user + assistant messages with source metadata.
     *
     * @param  array<int, array{title: string, document_id: int}>  $sources
     */
    private function persistMessages(?int $userId, int $notebookId, string $userMessage, string $assistantMessage, array $sources): void
    {
        try {
            ChatMessage::create([
                'user_id' => $userId,
                'notebook_id' => $notebookId,
                'role' => 'user',
                'content' => $userMessage,
                'metadata' => null,
            ]);

            ChatMessage::create([
                'user_id' => $userId,
                'notebook_id' => $notebookId,
                'role' => 'assistant',
                'content' => $assistantMessage,
                'metadata' => ['sources' => $sources],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist chat messages', ['error' => $e->getMessage()]);
        }
    }
}

