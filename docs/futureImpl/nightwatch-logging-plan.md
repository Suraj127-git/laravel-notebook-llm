# Laravel Nightwatch Logging Implementation Plan

This plan implements comprehensive Laravel Nightwatch logging throughout the backend to provide real-time monitoring, performance tracking, and error analysis for the NotebookLLM application.

## Current State Analysis

**Existing Setup**:
- Laravel Nightwatch package installed (v1.24)
- Basic Nightwatch configuration exists
- Default log channel set to 'nightwatch' in logging.php
- LogRequests middleware with basic logging implemented
- Individual Log:: calls in controllers and agents

**Gaps Identified**:
- No structured logging format for Nightwatch
- Missing performance metrics logging
- No AI-specific event tracking
- Limited error context and correlation
- No business intelligence logging

## Implementation Plan

### 1. Enhanced Logging Configuration

**Update config/logging.php**:
```php
'channels' => [
    'nightwatch' => [
        'driver' => 'nightwatch',
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
        'processors' => [
            \App\Logging\Processors\RequestIdProcessor::class,
            \App\Logging\Processors\UserContextProcessor::class,
            \App\Logging\Processors\PerformanceProcessor::class,
        ],
    ],
    
    'ai_events' => [
        'driver' => 'nightwatch',
        'level' => 'info',
        'replace_placeholders' => true,
        'name' => 'ai_events',
        'processors' => [
            \App\Logging\Processors\AIContextProcessor::class,
        ],
    ],
    
    'performance' => [
        'driver' => 'nightwatch', 
        'level' => 'info',
        'replace_placeholders' => true,
        'name' => 'performance',
        'processors' => [
            \App\Logging\Processors\PerformanceProcessor::class,
        ],
    ],
],
```

### 2. Custom Logging Processors

**Create RequestIdProcessor**:
```php
// app/Logging/Processors/RequestIdProcessor.php
class RequestIdProcessor
{
    public function __invoke(array $record): array
    {
        $record['context']['request_id'] ??= request()->attributes->get('request_id', uniqid('req_', true));
        return $record;
    }
}
```

**Create UserContextProcessor**:
```php
// app/Logging/Processors/UserContextProcessor.php
class UserContextProcessor
{
    public function __invoke(array $record): array
    {
        if (auth()->check()) {
            $record['context']['user_id'] = auth()->id();
            $record['context']['user_email'] = auth()->user()->email;
        }
        return $record;
    }
}
```

**Create PerformanceProcessor**:
```php
// app/Logging/Processors/PerformanceProcessor.php
class PerformanceProcessor
{
    public function __invoke(array $record): array
    {
        if (request()->has('_start_time')) {
            $duration = round((microtime(true) - request('_start_time')) * 1000, 2);
            $record['context']['duration_ms'] = $duration;
        }
        return $record;
    }
}
```

**Create AIContextProcessor**:
```php
// app/Logging/Processors/AIContextProcessor.php
class AIContextProcessor
{
    public function __invoke(array $record): array
    {
        $record['context']['ai_provider'] = config('ai.default');
        $record['context']['notebook_id'] = request()->input('notebook_id');
        return $record;
    }
}
```

### 3. Enhanced ChatController Logging

**Update ChatController with structured logging**:
```php
class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $startTime = microtime(true);
        request()->attributes->set('_start_time', $startTime);
        
        Log::channel('ai_events')->info('Chat request initiated', [
            'event_type' => 'chat_request',
            'notebook_id' => $request->input('notebook_id'),
            'message_length' => strlen($request->input('message')),
            'message_preview' => substr($request->input('message'), 0, 100),
            'ai_provider' => config('ai.default'),
        ]);

        try {
            $agent = new KnowledgeAgent($request->user());
            $response = $agent->prompt($request->string('message'));
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::channel('ai_events')->info('Chat request completed', [
                'event_type' => 'chat_response',
                'response_length' => strlen($response->content()),
                'duration_ms' => $duration,
                'ai_provider' => config('ai.default'),
                'success' => true,
            ]);

            return response()->json(['answer' => $response->content()]);

        } catch (\Exception $e) {
            Log::channel('ai_events')->error('Chat request failed', [
                'event_type' => 'chat_error',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'ai_provider' => config('ai.default'),
            ]);

            throw $e;
        }
    }
}
```

### 4. Enhanced KnowledgeAgent Logging

**Update KnowledgeAgent with detailed AI logging**:
```php
class KnowledgeAgent
{
    public function prompt(string $message)
    {
        Log::channel('ai_events')->debug('AI agent prompt initiated', [
            'event_type' => 'agent_prompt',
            'message_length' => strlen($message),
            'ai_provider' => config('ai.default'),
            'model' => $this->getModel(),
        ]);

        $startTime = microtime(true);
        
        // AI processing logic here
        $response = "Temporary response"; // Replace with actual AI call
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::channel('ai_events')->debug('AI agent prompt completed', [
            'event_type' => 'agent_response',
            'response_length' => strlen($response),
            'duration_ms' => $duration,
            'ai_provider' => config('ai.default'),
            'model' => $this->getModel(),
            'tokens_used' => $this->estimateTokens($message, $response),
        ]);

        return new class($response) {
            public function __construct(private string $message) {}
            public function content(): string { return $this->message; }
        };
    }

    public function stream(string $message): \Generator
    {
        Log::channel('ai_events')->info('AI stream initiated', [
            'event_type' => 'stream_start',
            'message_length' => strlen($message),
            'ai_provider' => config('ai.default'),
        ]);

        $startTime = microtime(true);
        $chunkCount = 0;
        
        // Streaming logic here
        foreach ($this->generateStream($message) as $chunk) {
            $chunkCount++;
            yield $chunk;
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::channel('ai_events')->info('AI stream completed', [
            'event_type' => 'stream_end',
            'duration_ms' => $duration,
            'chunks_sent' => $chunkCount,
            'ai_provider' => config('ai.default'),
        ]);
    }
}
```

### 5. Performance Monitoring Service

**Create PerformanceMonitoringService**:
```php
// app/Services/PerformanceMonitoringService.php
class PerformanceMonitoringService
{
    public static function logSlowQuery($query, $duration, $bindings = [])
    {
        if ($duration > 1000) { // Log queries over 1 second
            Log::channel('performance')->warning('Slow database query detected', [
                'event_type' => 'slow_query',
                'duration_ms' => $duration,
                'sql' => $query,
                'bindings' => $bindings,
                'connection' => config('database.default'),
            ]);
        }
    }

    public static function logMemoryUsage($context = 'general')
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        Log::channel('performance')->debug('Memory usage snapshot', [
            'event_type' => 'memory_usage',
            'context' => $context,
            'current_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($memoryPeak / 1024 / 1024, 2),
        ]);
    }

    public static function logCacheHitRate($key, $hit)
    {
        Log::channel('performance')->debug('Cache operation', [
            'event_type' => $hit ? 'cache_hit' : 'cache_miss',
            'cache_key' => $key,
            'cache_driver' => config('cache.default'),
        ]);
    }
}
```

### 6. Business Intelligence Logging

**Create BusinessEventLogger**:
```php
// app/Services/BusinessEventLogger.php
class BusinessEventLogger
{
    public static function logUserActivity($action, $context = [])
    {
        Log::channel('business')->info('User activity', [
            'event_type' => 'user_activity',
            'action' => $action,
            'context' => $context,
        ]);
    }

    public static function logDocumentOperation($operation, $documentId, $context = [])
    {
        Log::channel('business')->info('Document operation', [
            'event_type' => 'document_operation',
            'operation' => $operation,
            'document_id' => $documentId,
            'context' => $context,
        ]);
    }

    public static function logAIUsage($provider, $model, $tokens, $cost = null)
    {
        Log::channel('business')->info('AI usage', [
            'event_type' => 'ai_usage',
            'provider' => $provider,
            'model' => $model,
            'tokens_used' => $tokens,
            'estimated_cost' => $cost,
        ]);
    }
}
```

### 7. Enhanced Error Handling

**Update exception handler in bootstrap/app.php**:
```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (Request $request, Throwable $e) {
        Log::channel('nightwatch')->error('Unhandled exception', [
            'event_type' => 'unhandled_exception',
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_path' => $request->path(),
            'request_method' => $request->method(),
            'user_id' => auth()->id(),
        ]);

        return $request->expectsJson()
            ? response()->json([
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500)
            : response()->view('errors.500', [
                'message' => $e->getMessage(),
            ], 500);
    });
})
```

### 8. Environment Configuration

**Update .env.example with Nightwatch settings**:
```env
# Nightwatch Configuration
NIGHTWATCH_ENABLED=true
NIGHTWATCH_TOKEN=your_nightwatch_token_here
NIGHTWATCH_DEPLOY=local
NIGHTWATCH_SERVER=notebookllm-local
NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE=true
NIGHTWATCH_CAPTURE_REQUEST_PAYLOAD=false
NIGHTWATCH_REDACT_PAYLOAD_FIELDS=_token,password,password_confirmation
NIGHTWATCH_REDACT_HEADERS=Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN

# Nightwatch Sampling Rates
NIGHTWATCH_REQUEST_SAMPLE_RATE=1.0
NIGHTWATCH_COMMAND_SAMPLE_RATE=1.0
NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0
NIGHTWATCH_SCHEDULED_TASK_SAMPLE_RATE=1.0

# Nightwatch Filtering
NIGHTWATCH_IGNORE_CACHE_EVENTS=false
NIGHTWATCH_IGNORE_MAIL=false
NIGHTWATCH_IGNORE_NOTIFICATIONS=false
NIGHTWATCH_IGNORE_OUTGOING_REQUESTS=false
NIGHTWATCH_IGNORE_QUERIES=false
NIGHTWATCH_LOG_LEVEL=debug

# Nightwatch Ingest
NIGHTWATCH_INGEST_URI=127.0.0.1:2407
NIGHTWATCH_INGEST_TIMEOUT=0.5
NIGHTWATCH_INGEST_CONNECTION_TIMEOUT=0.5
NIGHTWATCH_INGEST_EVENT_BUFFER=500
```

### 9. Custom Artisan Commands for Nightwatch

**Create NightwatchStatus command**:
```php
// app/Console/Commands/NightwatchStatus.php
class NightwatchStatus extends Command
{
    protected $signature = 'nightwatch:status';
    protected $description = 'Check Nightwatch connection and status';

    public function handle()
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get(config('nightwatch.ingest.uri') . '/status');
            
            $this->info('Nightwatch connection: OK');
            $this->info('Response time: ' . $response->getHeader('X-Response-Time')[0] . 'ms');
            
        } catch (\Exception $e) {
            $this->error('Nightwatch connection failed: ' . $e->getMessage());
        }
    }
}
```

**Create NightwatchTest command**:
```php
// app/Console/Commands/NightwatchTest.php
class NightwatchTest extends Command
{
    protected $signature = 'nightwatch:test {--count=10}';
    protected $description = 'Send test events to Nightwatch';

    public function handle()
    {
        $count = $this->option('count');
        
        for ($i = 0; $i < $count; $i++) {
            Log::channel('nightwatch')->info('Test event ' . ($i + 1), [
                'test' => true,
                'timestamp' => now()->toISOString(),
                'random' => uniqid(),
            ]);
        }
        
        $this->info("Sent {$count} test events to Nightwatch");
    }
}
```

## Implementation Steps

### Step 1: Setup Configuration
1. Update logging.php configuration
2. Add environment variables to .env.example
3. Test Nightwatch connection

### Step 2: Create Processors
1. Create custom logging processors
2. Register processors in configuration
3. Test processor functionality

### Step 3: Enhance Controllers
1. Update ChatController with structured logging
2. Update other controllers with business events
3. Add performance monitoring

### Step 4: Create Services
1. Implement PerformanceMonitoringService
2. Create BusinessEventLogger
3. Add AI-specific logging

### Step 5: Error Handling
1. Update exception handler
2. Add comprehensive error context
3. Test error logging

### Step 6: Commands & Monitoring
1. Create Nightwatch artisan commands
2. Set up monitoring dashboard
3. Test end-to-end logging

## Expected Outcomes

**Immediate Benefits**:
- Real-time visibility into application performance
- Structured logging for better analysis
- AI operation tracking and optimization
- Error correlation and debugging

**Long-term Benefits**:
- Business intelligence from user activity
- Performance optimization insights
- Cost tracking for AI usage
- Proactive issue detection

**Monitoring Capabilities**:
- Request/response timing analysis
- AI provider performance comparison
- User behavior patterns
- System health monitoring

This implementation provides comprehensive Nightwatch logging that transforms the application's observability and enables data-driven optimization decisions.
