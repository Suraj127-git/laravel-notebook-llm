<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
        Log::channel('ai_events')->info('document_operation', array_merge([
            'event_type' => 'document_operation',
            'operation' => $operation,
            'document_id' => $documentId,
            'user_id' => $userId,
            'notebook_id' => $notebookId,
            'status' => $status,
        ], $extra));
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
        Log::channel('ai_events')->info('ai_usage', array_merge([
            'event_type' => 'ai_usage',
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'user_id' => $userId,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd' => $costUsd,
        ], $extra));
    }

    public static function logChatSession(
        int $userId,
        int $notebookId,
        int $messageCount,
        bool $usedRag,
        array $sourceDocumentIds = []
    ): void {
        Log::channel('ai_events')->info('chat_session', [
            'event_type' => 'chat_session',
            'user_id' => $userId,
            'notebook_id' => $notebookId,
            'message_count' => $messageCount,
            'used_rag' => $usedRag,
            'source_document_ids' => $sourceDocumentIds,
        ]);
    }
}
