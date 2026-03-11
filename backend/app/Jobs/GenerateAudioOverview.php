<?php

namespace App\Jobs;

use App\Events\AudioOverviewReady;
use App\Models\AudioOverview;
use App\Models\Notebook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Logging\Processors\RequestIdProcessor;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Enums\Lab;

class GenerateAudioOverview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public AudioOverview $overview,
        public Notebook $notebook,
    ) {}

    public function handle(): void
    {
        // Pin trace_id to this job's UUID so all log lines share one filterable trace
        if ($this->job) {
            RequestIdProcessor::setTraceId($this->job->uuid());
        }

        $jobStart  = microtime(true);
        $userId    = $this->notebook->user_id ?? null;
        $logCtx    = [
            'notebook_id' => $this->notebook->id,
            'overview_id' => $this->overview->id,
            'user_id'     => $userId,
            'provider'    => 'groq',
            'model'       => 'llama-3.3-70b-versatile',
            'attempt'     => $this->attempts(),
        ];

        $this->overview->status = 'generating';
        $this->overview->save();

        Log::info('audio_overview.starting', array_merge($logCtx, [
            'operation' => 'generate_audio_overview',
            'status'    => 'generating',
        ]));

        try {
            // Collect document content from chunks
            $chunks = \App\Models\DocumentChunk::query()
                ->whereHas('document', fn ($q) => $q->where('notebook_id', $this->notebook->id)->where('status', 'ready'))
                ->orderBy('document_id')
                ->orderBy('chunk_index')
                ->get(['content', 'document_id']);

            if ($chunks->isEmpty()) {
                throw new \RuntimeException('No document content available. Make sure at least one source has finished processing.');
            }

            // Limit total context to ~8000 chars
            $contextParts = [];
            $total        = 0;
            foreach ($chunks as $chunk) {
                if ($total >= 8000) {
                    break;
                }
                $contextParts[] = $chunk->content;
                $total += strlen($chunk->content);
            }
            $context = implode("\n\n", $contextParts);

            // Generate podcast script via Groq LLM
            $scriptPrompt = "You are writing a conversational podcast transcript between two AI hosts named Alex and Sam. "
                ."They are discussing and explaining the following documents in an engaging, educational way. "
                ."Keep the conversation natural, insightful, and under 700 words total. "
                ."Format each line strictly as 'Alex: ...' or 'Sam: ...'\n\n"
                ."Documents:\n{$context}";

            $agent = new AnonymousAgent(
                instructions: 'You are a helpful podcast script writer.',
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($scriptPrompt, provider: Lab::Groq, model: 'llama-3.3-70b-versatile');
            $script   = $response->text;

            // Estimate reading duration (~150 wpm)
            $wordCount       = str_word_count($script);
            $durationSeconds = (int) ceil(($wordCount / 150) * 60);

            $this->overview->script           = $script;
            $this->overview->status           = 'ready';
            $this->overview->duration_seconds = $durationSeconds;
            $this->overview->save();

            broadcast(new AudioOverviewReady($this->overview));

            Log::info('audio_overview.completed', array_merge($logCtx, [
                'operation'        => 'generate_audio_overview',
                'status'           => 'ready',
                'script_length'    => strlen($script),
                'word_count'       => $wordCount,
                'duration_seconds' => $durationSeconds,
                'context_length'   => strlen($context),
                'chunk_count'      => $chunks->count(),
                'duration_ms'      => round((microtime(true) - $jobStart) * 1000, 2),
            ]));

        } catch (\Throwable $e) {
            Log::error('audio_overview.failed', array_merge($logCtx, [
                'operation'   => 'generate_audio_overview',
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $jobStart) * 1000, 2),
            ]));

            $this->overview->status = 'failed';
            $this->overview->error  = $e->getMessage();
            $this->overview->save();
        }
    }
}
