<?php

namespace App\Services;

use App\Logging\Processors\RequestIdProcessor;
use Illuminate\Support\Facades\Log;

/**
 * Structured business-event logger.
 *
 * Every method logs to BOTH channels:
 *  - "enriched" (default) → Nightwatch + single file, picks up all four processors
 *  - "ai_events" → daily rotating file for dedicated AI audit trail
 *
 * The trace_id from RequestIdProcessor ties these events to the originating
 * HTTP request or queue job so they are filterable together in Nightwatch.
 */
class BusinessEventLogger
{
    public static function logDocumentOperation(
        string $operation,
        int $documentId,
        int $userId,
        int $notebookId,
        string $status,
        array $extra = []
    ): void {
        $payload = array_merge([
            'event_type'  => 'document_operation',
            'operation'   => $operation,
            'document_id' => $documentId,
            'user_id'     => $userId,
            'notebook_id' => $notebookId,
            'status'      => $status,
            'trace_id'    => RequestIdProcessor::current(),
            'timestamp'   => now()->toIso8601String(),
        ], $extra);

        Log::info('document_operation', $payload);
        Log::channel('ai_events')->info('document_operation', $payload);
    }

    public static function logAIUsage(
        string $provider,
        string $model,
        string $operation,
        int $userId,
        int $tokensIn,
        int $tokensOut,
        float $costUsd = 0.0,
        array $extra = []
    ): void {
        $payload = array_merge([
            'event_type'   => 'ai_usage',
            'provider'     => $provider,
            'model'        => $model,
            'operation'    => $operation,
            'user_id'      => $userId,
            'tokens_in'    => $tokensIn,
            'tokens_out'   => $tokensOut,
            'total_tokens' => $tokensIn + $tokensOut,
            'cost_usd'     => $costUsd,
            'trace_id'     => RequestIdProcessor::current(),
            'timestamp'    => now()->toIso8601String(),
        ], $extra);

        Log::info('ai_usage', $payload);
        Log::channel('ai_events')->info('ai_usage', $payload);
    }

    public static function logChatSession(
        int $userId,
        int $notebookId,
        int $messageCount,
        bool $usedRag,
        array $sourceDocumentIds = [],
        float $durationMs = 0.0
    ): void {
        $payload = [
            'event_type'          => 'chat_session',
            'operation'           => 'chat_session',
            'user_id'             => $userId,
            'notebook_id'         => $notebookId,
            'message_count'       => $messageCount,
            'used_rag'            => $usedRag,
            'source_document_ids' => $sourceDocumentIds,
            'source_count'        => count($sourceDocumentIds),
            'duration_ms'         => $durationMs,
            'trace_id'            => RequestIdProcessor::current(),
            'timestamp'           => now()->toIso8601String(),
        ];

        Log::info('chat_session', $payload);
        Log::channel('ai_events')->info('chat_session', $payload);
    }

    public static function logEmbeddingBatch(
        int $userId,
        int $documentId,
        int $notebookId,
        int $chunkCount,
        string $provider,
        string $model,
        float $durationMs = 0.0,
        array $extra = []
    ): void {
        $payload = array_merge([
            'event_type'  => 'embedding_batch',
            'operation'   => 'embed',
            'user_id'     => $userId,
            'document_id' => $documentId,
            'notebook_id' => $notebookId,
            'chunk_count' => $chunkCount,
            'provider'    => $provider,
            'model'       => $model,
            'duration_ms' => $durationMs,
            'trace_id'    => RequestIdProcessor::current(),
            'timestamp'   => now()->toIso8601String(),
        ], $extra);

        Log::info('embedding_batch', $payload);
        Log::channel('ai_events')->info('embedding_batch', $payload);
    }

    public static function logError(
        string $eventType,
        string $message,
        array $context = []
    ): void {
        $payload = array_merge([
            'event_type' => $eventType,
            'error'      => $message,
            'trace_id'   => RequestIdProcessor::current(),
            'timestamp'  => now()->toIso8601String(),
        ], $context);

        Log::error($eventType, $payload);
        Log::channel('ai_events')->error($eventType, $payload);
    }
}
