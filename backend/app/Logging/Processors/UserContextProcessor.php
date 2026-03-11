<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Injects authenticated user context into every log record.
 * Fields appear as filterable metadata in Nightwatch (user_id, user_email, user_name).
 */
class UserContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        try {
            if (auth()->check()) {
                $user = auth()->user();
                $extra['user_id']    = $user->id;
                $extra['user_email'] = $user->email;

                if (isset($user->name)) {
                    $extra['user_name'] = $user->name;
                }
            }
        } catch (\Throwable) {
            // Auth may not be available during boot or in queue context
        }

        return $record->with(extra: $extra);
    }
}
