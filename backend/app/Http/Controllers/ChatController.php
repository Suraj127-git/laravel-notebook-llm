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
            'message'     => ['required', 'string', 'max:4000'],
        ]);

        $notebookId = (int) $validated['notebook_id'];
        $message    = $validated['message'];
        $user       = $request->user();
        $start      = microtime(true);

        Log::info('chat.request', [
            'operation'      => 'chat',
            'user_id'        => $user?->id,
            'notebook_id'    => $notebookId,
            'message_length' => strlen($message),
        ]);

        try {
            $agent    = new KnowledgeAgent($user, $notebookId);
            $response = $agent->chat($message);

            $this->persistMessages($user?->id, $notebookId, $message, $response->content(), $response->sources);

            Log::info('chat.completed', [
                'operation'       => 'chat',
                'user_id'         => $user?->id,
                'notebook_id'     => $notebookId,
                'source_count'    => count($response->sources),
                'response_length' => strlen($response->content()),
                'duration_ms'     => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'answer'  => $response->content(),
                'sources' => $response->sources,
            ]);

        } catch (\Throwable $e) {
            Log::error('chat.failed', [
                'operation'   => 'chat',
                'user_id'     => $user?->id,
                'notebook_id' => $notebookId,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
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
            $token       = $request->input('token');
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if ($accessToken) {
                auth()->setUser($accessToken->tokenable);
                Log::info('chat.stream_auth', [
                    'operation' => 'stream_auth',
                    'user_id'   => auth()->id(),
                    'status'    => 'success',
                ]);
            } else {
                Log::warning('chat.stream_auth_failed', [
                    'operation' => 'stream_auth',
                    'status'    => 'invalid_token',
                    'ip'        => $request->ip(),
                ]);
            }
        }

        $validated = $request->validate([
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
            'message'     => ['required', 'string', 'max:4000'],
        ]);

        $notebookId = (int) $validated['notebook_id'];
        $message    = $validated['message'];
        $user       = auth()->user();
        $start      = microtime(true);

        Log::info('chat.stream_request', [
            'operation'      => 'stream',
            'user_id'        => $user?->id,
            'notebook_id'    => $notebookId,
            'message_length' => strlen($message),
        ]);

        try {
            $agent = new KnowledgeAgent($user, $notebookId);

            return response()->stream(function () use ($agent, $message, $user, $notebookId, $start): void {
                try {
                    $stream     = $agent->chatStream($message);
                    $chunkCount = 0;
                    $fullText   = '';

                    foreach ($stream as $event) {
                        $chunk     = $event->text();
                        $fullText .= $chunk;
                        $chunkCount++;

                        echo 'data: '.json_encode(['delta' => $chunk], JSON_THROW_ON_ERROR)."\n\n";
                        @ob_flush();
                        @flush();
                    }

                    $sources = $agent->getSources();
                    echo 'data: '.json_encode(['sources' => $sources], JSON_THROW_ON_ERROR)."\n\n";
                    @ob_flush();
                    @flush();

                    echo 'data: '.json_encode(['done' => true], JSON_THROW_ON_ERROR)."\n\n";
                    @ob_flush();
                    @flush();

                    $this->persistMessages($user?->id, $notebookId, $message, $fullText, $sources);

                    Log::info('chat.stream_completed', [
                        'operation'       => 'stream',
                        'user_id'         => $user?->id,
                        'notebook_id'     => $notebookId,
                        'chunk_count'     => $chunkCount,
                        'source_count'    => count($sources),
                        'response_length' => strlen($fullText),
                        'duration_ms'     => round((microtime(true) - $start) * 1000, 2),
                    ]);

                } catch (\Throwable $e) {
                    Log::error('chat.stream_failed', [
                        'operation'   => 'stream',
                        'user_id'     => $user?->id,
                        'notebook_id' => $notebookId,
                        'error'       => $e->getMessage(),
                        'error_class' => get_class($e),
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    $userMsg = str_contains($e->getMessage(), '503')
                        ? 'AI service is temporarily unavailable. Please try again in a moment.'
                        : 'Stream processing failed. Please try again.';
                    echo 'data: '.json_encode(['error' => $userMsg], JSON_THROW_ON_ERROR)."\n\n";
                    @ob_flush();
                    @flush();
                }
            }, 200, [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
            ]);

        } catch (\Throwable $e) {
            Log::error('chat.stream_setup_failed', [
                'operation'   => 'stream',
                'user_id'     => $user?->id,
                'notebook_id' => $notebookId,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
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

        $start = microtime(true);

        try {
            $agent     = new KnowledgeAgent($request->user(), (int) $validated['notebook_id']);
            $questions = $agent->suggestQuestions($validated['last_answer']);

            Log::info('chat.suggest_questions_completed', [
                'operation'      => 'suggest_questions',
                'user_id'        => $request->user()?->id,
                'notebook_id'    => (int) $validated['notebook_id'],
                'question_count' => count($questions),
                'duration_ms'    => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['questions' => $questions]);
        } catch (\Throwable $e) {
            Log::warning('chat.suggest_questions_failed', [
                'operation'   => 'suggest_questions',
                'user_id'     => $request->user()?->id,
                'notebook_id' => (int) $validated['notebook_id'],
                'error'       => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

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
                'user_id'     => $userId,
                'notebook_id' => $notebookId,
                'role'        => 'user',
                'content'     => $userMessage,
                'metadata'    => null,
            ]);

            ChatMessage::create([
                'user_id'     => $userId,
                'notebook_id' => $notebookId,
                'role'        => 'assistant',
                'content'     => $assistantMessage,
                'metadata'    => ['sources' => $sources],
            ]);
        } catch (\Throwable $e) {
            Log::warning('chat.persist_failed', [
                'operation'   => 'persist_messages',
                'user_id'     => $userId,
                'notebook_id' => $notebookId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
