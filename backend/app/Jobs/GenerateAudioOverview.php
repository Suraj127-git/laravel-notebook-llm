<?php

namespace App\Jobs;

use App\Events\AudioOverviewReady;
use App\Models\AudioOverview;
use App\Models\Notebook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Facades\Ai;
use OpenAI\Client as OpenAIClient;

class GenerateAudioOverview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public AudioOverview $overview,
        public Notebook $notebook,
    ) {}

    public function handle(): void
    {
        $this->overview->status = 'generating';
        $this->overview->save();

        Log::info('GenerateAudioOverview: starting', [
            'notebook_id' => $this->notebook->id,
        ]);

        try {
            // Collect document content from chunks
            $chunks = \App\Models\DocumentChunk::query()
                ->whereHas('document', fn ($q) => $q->where('notebook_id', $this->notebook->id)->where('status', 'ready'))
                ->orderBy('document_id')
                ->orderBy('chunk_index')
                ->get(['content', 'document_id']);

            if ($chunks->isEmpty()) {
                throw new \RuntimeException('No document content available for audio overview generation.');
            }

            // Limit total context to ~8000 chars
            $contextParts = [];
            $total = 0;
            foreach ($chunks as $chunk) {
                if ($total >= 8000) {
                    break;
                }
                $contextParts[] = $chunk->content;
                $total += strlen($chunk->content);
            }
            $context = implode("\n\n", $contextParts);

            // Generate podcast script via Groq
            $scriptPrompt = "You are writing a conversational podcast transcript between two AI hosts named Alex and Sam. "
                ."They are discussing and explaining the following documents in an engaging, educational way. "
                ."Keep the conversation natural, insightful, and under 700 words total. "
                ."Format each line strictly as 'Alex: ...' or 'Sam: ...'\n\n"
                ."Documents:\n{$context}";

            $response = Ai::prompt($scriptPrompt, provider: Lab::Groq, model: 'llama-3.3-70b-versatile');
            $script = $response->text;

            $this->overview->script = $script;
            $this->overview->save();

            Log::info('GenerateAudioOverview: script generated', [
                'notebook_id' => $this->notebook->id,
                'script_length' => strlen($script),
            ]);

            // Generate TTS audio via Groq's OpenAI-compatible TTS endpoint
            $openai = \OpenAI::factory()
                ->withBaseUri('api.groq.com/openai/v1')
                ->withApiKey(config('services.groq.api_key', env('GROQ_API_KEY', '')))
                ->make();

            // Parse script into speaker turns
            $turns = $this->parseScript($script);

            $audioChunks = [];
            foreach ($turns as [$speaker, $text]) {
                if (empty(trim($text))) {
                    continue;
                }
                $voice = ($speaker === 'Alex') ? 'alloy' : 'echo';

                try {
                    $audio = $openai->audio()->speech([
                        'model' => 'playai-tts',
                        'input' => $text,
                        'voice' => $voice,
                    ]);
                    $audioChunks[] = $audio->getBody()->getContents();
                } catch (\Throwable $e) {
                    Log::warning('GenerateAudioOverview: TTS turn failed, skipping', [
                        'speaker' => $speaker,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            if (empty($audioChunks)) {
                throw new \RuntimeException('TTS generation produced no audio output.');
            }

            // Concatenate and store
            $audioData = implode('', $audioChunks);
            $storagePath = "audio_overviews/{$this->overview->id}.mp3";
            Storage::disk('local')->put($storagePath, $audioData);

            // Estimate duration (rough: ~150 words per minute, ~5 chars per word)
            $wordCount = str_word_count($script);
            $durationSeconds = (int) ceil(($wordCount / 150) * 60);

            $this->overview->status = 'ready';
            $this->overview->storage_path = $storagePath;
            $this->overview->duration_seconds = $durationSeconds;
            $this->overview->save();

            broadcast(new AudioOverviewReady($this->overview));

            Log::info('GenerateAudioOverview: completed', [
                'notebook_id'      => $this->notebook->id,
                'duration_seconds' => $durationSeconds,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateAudioOverview: failed', [
                'notebook_id' => $this->notebook->id,
                'error'       => $e->getMessage(),
            ]);

            $this->overview->status = 'failed';
            $this->overview->error = $e->getMessage();
            $this->overview->save();
        }
    }

    /**
     * Parse "Alex: text\nSam: text\n..." into [[speaker, text], ...] tuples.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function parseScript(string $script): array
    {
        $lines = explode("\n", $script);
        $turns = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(Alex|Sam):\s*(.+)$/i', $line, $m)) {
                $turns[] = [ucfirst(strtolower($m[1])), trim($m[2])];
            }
        }

        return $turns;
    }
}
