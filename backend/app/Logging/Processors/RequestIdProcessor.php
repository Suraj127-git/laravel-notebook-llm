<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    private static ?string $requestId = null;

    public static function generate(): string
    {
        return self::$requestId = substr(bin2hex(random_bytes(8)), 0, 16);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (self::$requestId === null) {
            self::generate();
        }

        return $record->with(extra: array_merge($record->extra, [
            'request_id' => self::$requestId,
        ]));
    }
}
