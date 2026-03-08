<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class ProcessUploadedDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public Document $document) {}

    public function handle(EmbeddingService $embeddingService, ChunkingService $chunkingService): void
    {
        $this->document->refresh();

        Log::info('ProcessUploadedDocument: starting', [
            'document_id' => $this->document->id,
            'mime_type' => $this->document->mime_type,
        ]);

        $path = storage_path('app/documents/'.$this->document->filename);

        try {
            $text = match (true) {
                str_contains($this->document->mime_type, 'pdf') => $this->extractPdf($path),
                str_contains($this->document->mime_type, 'wordprocessingml') ||
                str_contains($this->document->mime_type, 'msword') ||
                str_ends_with($this->document->filename, '.docx') => $this->extractDocx($path),
                str_contains($this->document->mime_type, 'csv') ||
                str_ends_with($this->document->filename, '.csv') => $this->extractCsv($path),
                default => file_get_contents($path),
            };
        } catch (\Throwable $e) {
            Log::error('ProcessUploadedDocument: text extraction failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);

            $this->document->status = 'failed';
            $this->document->extraction_error = $e->getMessage();
            $this->document->save();

            return;
        }

        $this->document->content = $text;
        $this->document->status = 'processing';
        $this->document->save();

        Log::info('ProcessUploadedDocument: text extracted, chunking', [
            'document_id' => $this->document->id,
            'text_length' => strlen($text),
        ]);

        // Split into overlapping chunks for better RAG retrieval quality
        $chunks = $chunkingService->chunk($text);

        Log::info('ProcessUploadedDocument: chunks created, embedding with Voyage AI', [
            'document_id' => $this->document->id,
            'chunk_count' => count($chunks),
        ]);

        try {
            $embeddingService->embedChunks($this->document, $chunks);
        } catch (\Throwable $e) {
            Log::error('ProcessUploadedDocument: embedding failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);

            $this->document->status = 'failed';
            $this->document->extraction_error = 'Embedding failed: '.$e->getMessage();
            $this->document->save();

            return;
        }

        $this->document->status = 'ready';
        $this->document->save();

        Log::info('ProcessUploadedDocument: completed', [
            'document_id' => $this->document->id,
            'chunk_count' => count($chunks),
        ]);
    }

    protected function extractPdf(string $path): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);

        return $pdf->getText();
    }

    protected function extractDocx(string $path): string
    {
        $phpWord = WordIOFactory::load($path, 'Word2007');
        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $lines[] = $element->getText();
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $lines[] = $child->getText();
                        }
                    }
                }
            }
        }

        return implode("\n", array_filter($lines));
    }

    protected function extractCsv(string $path): string
    {
        $reader = \League\Csv\Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();

        $lines = [implode(', ', $reader->getHeader())];
        foreach ($records as $record) {
            $lines[] = implode(', ', $record);
        }

        return implode("\n", $lines);
    }
}

