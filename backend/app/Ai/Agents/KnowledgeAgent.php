<?php

namespace App\Ai\Agents;

use App\Models\AiUsageLog;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Streaming\Events\TextDelta;

class KnowledgeAgent implements Agent
{
    use Promptable;

    private string $retrievedContext = '';

    /** @var array<int, array{title: string, document_id: int}> */
    private array $sources = [];

    public function __construct(
        public ?Authenticatable $user = null,
        private int|string|null $notebookId = null,
    ) {
        Log::info('KnowledgeAgent initialized', [
            'user_id' => $user?->id,
            'notebook_id' => $notebookId,
        ]);
    }

    /**
     * System prompt sent to Groq before every request.
     * Dynamically includes retrieved document chunks for RAG.
     */
    public function instructions(): string
    {
        $base = 'You are an AI assistant for a document notebook. '
            .'Answer questions using ONLY the provided document context below. '
            .'If the context does not contain enough information, say so clearly. '
            .'Be concise, accurate, and cite the source document name when referencing specific information.';

        if (empty($this->retrievedContext)) {
            return $base."\n\nNo document context is available. Ask the user to upload relevant documents first.";
        }

        return $base."\n\n<context>\n{$this->retrievedContext}\n</context>";
    }

    /**
     * Ask a question and receive a full answer with source citations.
     */
    public function chat(string $message): object
    {
        $startTime = microtime(true);
        $this->retrieveContext($message);

        Log::info('KnowledgeAgent chat: context retrieved', [
            'user_id' => $this->user?->id,
            'sources_count' => count($this->sources),
        ]);

        $response = $this->prompt(
            $message,
            provider: Lab::Groq,
            model: 'llama-3.3-70b-versatile',
        );

        $text = $response->text;
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('KnowledgeAgent chat: completed', [
            'user_id' => $this->user?->id,
            'response_length' => strlen($text),
            'duration_ms' => $duration,
        ]);

        $this->logUsage('chat', strlen($message), strlen($text));

        $sources = $this->sources;

        return new class($text, $sources) {
            /** @param array<int, array{title: string, document_id: int}> $sources */
            public function __construct(
                private readonly string $text,
                public readonly array $sources,
            ) {}

            public function content(): string
            {
                return $this->text;
            }
        };
    }

    /**
     * Stream an answer token-by-token, yielding chunk objects with text().
     *
     * @return \Generator<int, object>
     */
    public function chatStream(string $message): \Generator
    {
        $startTime = microtime(true);
        $this->retrieveContext($message);

        Log::info('KnowledgeAgent chatStream: starting', [
            'user_id' => $this->user?->id,
            'sources_count' => count($this->sources),
        ]);

        $stream = $this->stream(
            $message,
            provider: Lab::Groq,
            model: 'llama-3.3-70b-versatile',
        );

        $chunkCount = 0;
        $fullText = '';

        foreach ($stream as $event) {
            if (! $event instanceof TextDelta) {
                continue;
            }

            $chunkText = $event->delta;
            $fullText .= $chunkText;
            $chunkCount++;

            yield new class($chunkText) {
                public function __construct(private readonly string $text) {}

                public function text(): string
                {
                    return $this->text;
                }
            };
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('KnowledgeAgent chatStream: completed', [
            'user_id' => $this->user?->id,
            'chunks' => $chunkCount,
            'duration_ms' => $duration,
        ]);

        $this->logUsage('stream', strlen($message), strlen($fullText));
    }

    /**
     * Return sources from the most recent context retrieval.
     *
     * @return array<int, array{title: string, document_id: int}>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Generate 3 follow-up questions based on the last AI answer.
     *
     * @return string[]
     */
    public function suggestQuestions(string $lastAnswer): array
    {
        $prompt = "Based on this AI answer, generate exactly 3 short follow-up questions a curious user might ask next. "
            ."Return a JSON array of strings only, no other text. Answer:\n\n{$lastAnswer}";

        $response = $this->prompt(
            $prompt,
            provider: Lab::Groq,
            model: 'llama-3.3-70b-versatile',
        );

        try {
            $text = $response->text;
            // Extract JSON array from response
            preg_match('/\[.*?\]/s', $text, $matches);
            if (! empty($matches[0])) {
                $questions = json_decode($matches[0], true);
                if (is_array($questions)) {
                    return array_slice(array_values($questions), 0, 3);
                }
            }
        } catch (\Throwable) {}

        return [];
    }

    /**
     * Generate structured content (study_guide, faq, timeline, briefing) from notebook documents.
     */
    public function generateContent(string $type): string
    {
        $this->retrieveContext("Generate a {$type} from these documents");

        $systemPrompts = [
            'study_guide' => 'Create a comprehensive study guide with ## sections and bullet points covering key concepts, definitions, and important points from the documents.',
            'faq'         => 'Create a FAQ (Frequently Asked Questions) document with Q: and A: format covering the most important topics from the documents.',
            'timeline'    => 'Create a chronological timeline of events, dates, and developments mentioned in the documents. Use "**Year/Date:** Event" format.',
            'briefing'    => 'Create a concise executive briefing document summarizing the key findings, recommendations, and important points from the documents.',
        ];

        $prompt = ($systemPrompts[$type] ?? 'Summarize the key information from the documents.')
            .' Format your response in clean Markdown.';

        $response = $this->prompt(
            $prompt,
            provider: Lab::Groq,
            model: 'llama-3.3-70b-versatile',
        );

        return $response->text;
    }

    /**
     * Run vector similarity search and populate $retrievedContext and $sources.
     */
    private function retrieveContext(string $message): void
    {
        try {
            /** @var EmbeddingService $embeddingService */
            $embeddingService = app(EmbeddingService::class);
            $chunks = $embeddingService->searchSimilarChunks($message, $this->notebookId, limit: 5);

            $this->sources = [];
            $contextParts = [];

            foreach ($chunks as $chunk) {
                $contextParts[] = "Source: {$chunk->document_title}\n{$chunk->content}";
                $this->sources[] = [
                    'title' => $chunk->document_title,
                    'document_id' => (int) $chunk->document_id,
                ];
            }

            $this->retrievedContext = implode("\n\n---\n\n", $contextParts);
        } catch (\Throwable $e) {
            Log::warning('KnowledgeAgent: context retrieval failed, proceeding without context', [
                'error' => $e->getMessage(),
            ]);

            $this->retrievedContext = '';
            $this->sources = [];
        }
    }

    private function logUsage(string $operation, int $inputChars, int $outputChars): void
    {
        try {
            AiUsageLog::create([
                'user_id' => $this->user?->id,
                'provider' => 'groq',
                'model' => 'llama-3.3-70b-versatile',
                'operation' => $operation,
                'prompt_tokens' => (int) ceil($inputChars / 4),
                'completion_tokens' => (int) ceil($outputChars / 4),
                'estimated_cost' => null,
                'metadata' => ['notebook_id' => $this->notebookId],
            ]);
        } catch (\Throwable $e) {
            Log::warning('KnowledgeAgent: failed to log AI usage', ['error' => $e->getMessage()]);
        }
    }
}
