<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Injects a trace_id (UUID v4) into every log record.
 *
 * - HTTP requests: generated once in InitializeTrace middleware, lives for the request lifecycle.
 * - Queue jobs: overridden via setTraceId() with the job's UUID so all job logs share one trace.
 * - Console commands: generated lazily on first log call.
 *
 * Also injects HTTP request metadata (method, path, IP) when in an HTTP context.
 */
class RequestIdProcessor implements ProcessorInterface
{
    private static ?string $traceId = null;

    private static ?string $httpMethod = null;

    private static ?string $httpPath = null;

    private static ?string $httpIp = null;

    /**
     * Generate and store a new UUID v4 trace ID. Called by InitializeTrace middleware.
     */
    public static function generate(): string
    {
        return self::$traceId = self::uuid4();
    }

    /**
     * Set a specific trace ID (used by queue jobs to reuse the job UUID).
     */
    public static function setTraceId(string $id): void
    {
        self::$traceId = $id;
    }

    /**
     * Set HTTP context so every log carries request metadata.
     */
    public static function setHttpContext(string $method, string $path, string $ip): void
    {
        self::$httpMethod = strtoupper($method);
        self::$httpPath   = $path;
        self::$httpIp     = $ip;
    }

    /**
     * Get current trace ID (for passing into job/event payloads).
     */
    public static function current(): ?string
    {
        return self::$traceId;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (self::$traceId === null) {
            self::generate();
        }

        $extra = array_merge($record->extra, [
            'trace_id' => self::$traceId,
        ]);

        if (self::$httpMethod !== null) {
            $extra['http_method'] = self::$httpMethod;
            $extra['http_path']   = self::$httpPath;
            $extra['http_ip']     = self::$httpIp;
        }

        return $record->with(extra: $extra);
    }

    private static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
