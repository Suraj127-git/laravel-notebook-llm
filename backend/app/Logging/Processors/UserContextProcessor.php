<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class UserContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        try {
            if (auth()->check()) {
                $user = auth()->user();
                $extra['user_id'] = $user->id;
                $extra['user_email'] = $user->email;
            }
        } catch (\Throwable) {
            // Auth may not be available during boot
        }

        return $record->with(extra: $extra);
    }
}
