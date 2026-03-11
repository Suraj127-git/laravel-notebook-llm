<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds environment, hostname, memory usage, PHP version, and context type to every log record.
 * These fields make Nightwatch filtering by environment and infrastructure easy.
 */
class EnvironmentProcessor implements ProcessorInterface
{
    private static string $hostname = '';

    private static string $phpVersion = '';

    public function __invoke(LogRecord $record): LogRecord
    {
        if (self::$hostname === '') {
            self::$hostname = (string) gethostname() ?: 'unknown';
        }

        if (self::$phpVersion === '') {
            self::$phpVersion = PHP_VERSION;
        }

        $contextType = $this->resolveContextType();

        return $record->with(extra: array_merge($record->extra, [
            'app_env'     => app()->environment(),
            'app_name'    => config('app.name', 'laravel-notebookllm'),
            'hostname'    => self::$hostname,
            'php_version' => self::$phpVersion,
            'context'     => $contextType,
            'memory_mb'   => round(memory_get_usage(true) / 1_048_576, 2),
        ]));
    }

    private function resolveContextType(): string
    {
        if (app()->runningInConsole()) {
            // Distinguish queue jobs from artisan commands
            if (isset($_SERVER['argv']) && in_array('queue:work', $_SERVER['argv'], true)) {
                return 'queue_worker';
            }

            return 'console';
        }

        return 'http';
    }
}
