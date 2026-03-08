<?php

namespace App\Console\Commands;

use App\Logging\Processors\RequestIdProcessor;
use App\Services\BusinessEventLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NightwatchTest extends Command
{
    protected $signature = 'nightwatch:test';

    protected $description = 'Emit test log events through all Nightwatch channels';

    public function handle(): int
    {
        RequestIdProcessor::generate();

        $this->info('Emitting test events to nightwatch channel…');
        Log::channel('nightwatch')->info('Nightwatch test event', [
            'source' => 'artisan nightwatch:test',
            'timestamp' => now()->toISOString(),
        ]);
        $this->line('  <fg=green>✓</> nightwatch channel');

        $this->info('Emitting test events to ai_events channel…');
        BusinessEventLogger::logDocumentOperation(
            operation: 'test',
            documentId: 0,
            userId: 0,
            notebookId: 0,
            status: 'test',
            extra: ['note' => 'artisan nightwatch:test']
        );
        $this->line('  <fg=green>✓</> ai_events / document_operation');

        BusinessEventLogger::logAIUsage(
            provider: 'groq',
            model: 'llama-3.3-70b-versatile',
            operation: 'test',
            userId: 0,
            tokensIn: 100,
            tokensOut: 50,
            costUsd: 0.0001,
            extra: ['note' => 'artisan nightwatch:test']
        );
        $this->line('  <fg=green>✓</> ai_events / ai_usage');

        BusinessEventLogger::logChatSession(
            userId: 0,
            notebookId: 0,
            messageCount: 1,
            usedRag: true,
            sourceDocumentIds: [1, 2]
        );
        $this->line('  <fg=green>✓</> ai_events / chat_session');

        $this->newLine();
        $this->info('All test events emitted. Check storage/logs/ai_events-*.log');

        return self::SUCCESS;
    }
}
