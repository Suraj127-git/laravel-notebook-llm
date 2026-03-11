<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Promotes AI/domain-specific context keys from the log record's context array into
 * the extra array, making them first-class filterable fields in Nightwatch.
 *
 * Any key listed in $promotedKeys that appears in the log context will be hoisted to
 * extra so Nightwatch can index and filter by it.
 */
class AIContextProcessor implements ProcessorInterface
{
    /**
     * Keys to promote from context → extra for Nightwatch indexing.
     */
    private const PROMOTED_KEYS = [
        // Event classification
        'event_type',
        'operation',
        'operation_type',

        // AI provider details
        'provider',
        'model',

        // Token / cost metrics
        'tokens_in',
        'tokens_out',
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',

        // Domain identifiers — critical for Nightwatch filtering
        'notebook_id',
        'document_id',
        'chunk_count',
        'source_count',

        // Performance
        'duration_ms',
        'response_length',

        // Status / outcome
        'status',
        'used_rag',
        'error_code',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        foreach (self::PROMOTED_KEYS as $key) {
            if (array_key_exists($key, $record->context)) {
                $extra[$key] = $record->context[$key];
            }
        }

        return $record->with(extra: $extra);
    }
}
