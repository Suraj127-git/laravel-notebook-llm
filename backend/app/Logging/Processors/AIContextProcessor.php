<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class AIContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;

        // Promote AI-specific context keys to extra for structured querying
        $aiKeys = ['event_type', 'provider', 'model', 'tokens_in', 'tokens_out', 'cost_usd', 'notebook_id', 'document_id'];

        foreach ($aiKeys as $key) {
            if (isset($context[$key])) {
                $extra[$key] = $context[$key];
            }
        }

        return $record->with(extra: $extra);
    }
}
