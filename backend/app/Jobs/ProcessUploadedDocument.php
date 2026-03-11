<?php

namespace App\Jobs;

use App\Events\DocumentProcessingFailed;
use App\Events\DocumentStatusUpdated;
use App\Logging\Processors\RequestIdProcessor;
use App\Models\Document;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        // Pin trace_id to this job's UUID so all log lines share one filterable trace
        if ($this->job) {
            RequestIdProcessor::setTraceId($this->job->uuid());
        }

        $jobStart = microtime(true);

        $logContext = [
            'document_id'   => $this->document->id,
            'notebook_id'   => $this->document->notebook_id,
            'user_id'       => $this->document->user_id,
            'filename'      => $this->document->filename,
            'mime_type'     => $this->document->mime_type,
            'file_size'     => $this->document->file_size ?? null,
            'attempt'       => $this->attempts(),
        ];

        Log::info('ProcessUploadedDocument: starting', $logContext);

        $path = Storage::disk('local')->path('documents/'.$this->document->filename);

        try {
            $extractStart = microtime(true);

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
            Log::error('ProcessUploadedDocument: text extraction failed', array_merge($logContext, [
                'status'          => 'failed',
                'error'           => $e->getMessage(),
                'error_class'     => get_class($e),
                'duration_ms'     => round((microtime(true) - $jobStart) * 1000, 2),
            ]));

            $this->document->status = 'failed';
            $this->document->extraction_error = $e->getMessage();
            $this->document->save();
            broadcast(new DocumentProcessingFailed($this->document));

            return;
        }

        $this->document->content = $text;
        $this->document->status = 'processing';
        $this->document->save();
        broadcast(new DocumentStatusUpdated($this->document));

        Log::info('ProcessUploadedDocument: text extracted, chunking', array_merge($logContext, [
            'status'          => 'processing',
            'text_length'     => strlen($text),
            'extract_ms'      => round((microtime(true) - $extractStart) * 1000, 2),
        ]));

        // Split into overlapping chunks for better RAG retrieval quality
        $chunks = $chunkingService->chunk($text);

        Log::info('ProcessUploadedDocument: chunks created, embedding with Voyage AI', array_merge($logContext, [
            'status'      => 'embedding',
            'chunk_count' => count($chunks),
            'provider'    => 'voyage_ai',
            'model'       => 'voyage-3',
        ]));

        try {
            $embedStart = microtime(true);
            $embeddingService->embedChunks($this->document, $chunks);
        } catch (\Throwable $e) {
            Log::error('ProcessUploadedDocument: embedding failed', array_merge($logContext, [
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'chunk_count' => count($chunks),
                'provider'    => 'voyage_ai',
                'duration_ms' => round((microtime(true) - $jobStart) * 1000, 2),
            ]));

            $this->document->status = 'failed';
            $this->document->extraction_error = 'Embedding failed: '.$e->getMessage();
            $this->document->save();
            broadcast(new DocumentProcessingFailed($this->document));

            return;
        }

        $this->document->status = 'ready';
        $this->document->save();
        broadcast(new DocumentStatusUpdated($this->document));

        $totalMs = round((microtime(true) - $jobStart) * 1000, 2);

        Log::info('ProcessUploadedDocument: completed', array_merge($logContext, [
            'status'       => 'ready',
            'chunk_count'  => count($chunks),
            'text_length'  => strlen($text),
            'embed_ms'     => round((microtime(true) - $embedStart) * 1000, 2),
            'duration_ms'  => $totalMs,
        ]));
    }

    protected function extractPdf(string $path): string
    {
        // Primary: pdftotext (poppler-utils) — handles complex/malformed PDFs better
        $pdftotext = file_exists('/usr/bin/pdftotext') ? '/usr/bin/pdftotext' : trim(shell_exec('which pdftotext 2>/dev/null') ?? '');

        if ($pdftotext !== '') {
            $escaped = escapeshellarg($path);
            $text = shell_exec("{$pdftotext} -layout -enc UTF-8 {$escaped} - 2>/dev/null") ?? '';

            if (! empty(trim($text))) {
                return $text;
            }
        }

        // Fallback: smalot/pdfparser
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            if (! empty(trim($text))) {
                return $text;
            }

            // Try page-by-page
            $parts = [];
            foreach ($pdf->getPages() as $page) {
                try {
                    $parts[] = $page->getText();
                } catch (\Throwable) {}
            }

            $text = implode("\n", array_filter($parts));

            if (! empty(trim($text))) {
                return $text;
            }
        } catch (\Throwable) {}

        // OCR fallback: convert pages to images via pdftoppm, then run tesseract
        $tesseract = file_exists('/usr/bin/tesseract') ? '/usr/bin/tesseract' : trim(shell_exec('which tesseract 2>/dev/null') ?? '');
        $pdftoppm = file_exists('/usr/bin/pdftoppm') ? '/usr/bin/pdftoppm' : trim(shell_exec('which pdftoppm 2>/dev/null') ?? '');

        if ($tesseract !== '' && $pdftoppm !== '') {
            $tmpDir = sys_get_temp_dir().'/pdf_ocr_'.uniqid();
            mkdir($tmpDir, 0755, true);

            try {
                $escapedPath = escapeshellarg($path);
                $escapedTmp = escapeshellarg($tmpDir.'/page');
                shell_exec("pdftoppm -png -r 200 {$escapedPath} {$escapedTmp} 2>/dev/null");

                $images = glob($tmpDir.'/page-*.png');
                sort($images);
                $parts = [];

                foreach ($images as $image) {
                    $escapedImg = escapeshellarg($image);
                    $outBase = escapeshellarg($image.'.txt_out');
                    shell_exec("tesseract {$escapedImg} {$image}.txt_out -l eng quiet 2>/dev/null");
                    $ocrText = @file_get_contents($image.'.txt_out.txt');
                    if ($ocrText !== false && ! empty(trim($ocrText))) {
                        $parts[] = trim($ocrText);
                    }
                }

                $text = implode("\n\n", $parts);

                if (! empty(trim($text))) {
                    return $text;
                }
            } finally {
                array_map('unlink', glob($tmpDir.'/*'));
                @rmdir($tmpDir);
            }
        }

        throw new \RuntimeException('No extractable text found. The PDF appears to be image-based but OCR also returned no text. Try a clearer scan or a text-based PDF.');
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

