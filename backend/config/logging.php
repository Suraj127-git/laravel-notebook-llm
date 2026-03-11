<?php

use App\Logging\Processors\AIContextProcessor;
use App\Logging\Processors\EnvironmentProcessor;
use App\Logging\Processors\RequestIdProcessor;
use App\Logging\Processors\UserContextProcessor;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | "enriched" is a stack that fans out to nightwatch + single, but runs
    | all four context processors on every record before dispatching.
    | This means every log entry — whether from a controller, job, or service —
    | carries: trace_id, user_id, user_email, user_name, app_env, hostname,
    | memory_mb, context_type, http_method, http_path, notebook_id, document_id, etc.
    |
    */

    'default' => env('LOG_CHANNEL', 'enriched'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [

        /*
        | -----------------------------------------------------------------------
        | Primary channel — all application logs go here.
        | Processors run once on the stack and apply to every handler inside it.
        | -----------------------------------------------------------------------
        */
        'enriched' => [
            'driver'   => 'stack',
            'channels' => ['nightwatch', 'single'],
            // true = nightwatch timeout/failure never breaks a real user request
            'ignore_exceptions' => true,
            'processors' => [
                EnvironmentProcessor::class,   // app_env, hostname, memory_mb, context
                RequestIdProcessor::class,     // trace_id, http_method, http_path, http_ip
                UserContextProcessor::class,   // user_id, user_email, user_name
                AIContextProcessor::class,     // promote notebook_id, document_id, provider, model, tokens, cost, duration_ms …
            ],
        ],

        /*
        | -----------------------------------------------------------------------
        | AI / business-event structured log (daily rotation, 30-day retention).
        | Duplicate the four processors so this channel is self-contained when
        | used directly via Log::channel('ai_events').
        | -----------------------------------------------------------------------
        */
        'ai_events' => [
            'driver'   => 'daily',
            'path'     => storage_path('logs/ai_events.log'),
            'level'    => 'info',
            'days'     => 30,
            'replace_placeholders' => true,
            'processors' => [
                EnvironmentProcessor::class,
                RequestIdProcessor::class,
                UserContextProcessor::class,
                AIContextProcessor::class,
            ],
        ],

        // -----------------------------------------------------------------------
        // Standard channels (kept for reference / direct use)
        // -----------------------------------------------------------------------

        'stack' => [
            'driver'   => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver'  => 'single',
            'path'    => storage_path('logs/laravel.log'),
            'level'   => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver'  => 'daily',
            'path'    => storage_path('logs/laravel.log'),
            'level'   => env('LOG_LEVEL', 'debug'),
            'days'    => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver'   => 'slack',
            'url'      => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji'    => env('LOG_SLACK_EMOJI', ':boom:'),
            'level'    => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver'       => 'monolog',
            'level'        => env('LOG_LEVEL', 'debug'),
            'handler'      => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host'             => env('PAPERTRAIL_URL'),
                'port'             => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver'       => 'monolog',
            'level'        => env('LOG_LEVEL', 'debug'),
            'handler'      => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stderr'],
            'formatter'    => env('LOG_STDERR_FORMATTER'),
            'processors'   => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver'  => 'syslog',
            'level'   => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        // Legacy stacks — kept for backward compat, prefer "enriched"
        'app_stack' => [
            'driver'   => 'stack',
            'channels' => ['single', 'stderr'],
            'ignore_exceptions' => false,
            'processors' => [
                RequestIdProcessor::class,
                UserContextProcessor::class,
            ],
        ],

        'main' => [
            'driver'   => 'stack',
            'channels' => ['app_stack', 'nightwatch'],
            'ignore_exceptions' => true,
        ],

    ],

];
