<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $start      = microtime(true);
        $notebookId = $request->query('notebook_id');

        try {
            $documents = $request->user()
                ->documents()
                ->when($notebookId, fn ($q) => $q->where('notebook_id', $notebookId))
                ->latest()
                ->get();

            Log::info('document.listed', [
                'operation'      => 'list',
                'status'         => 'success',
                'user_id'        => $request->user()?->id,
                'notebook_id'    => $notebookId ? (int) $notebookId : null,
                'document_count' => $documents->count(),
                'duration_ms'    => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $documents;

        } catch (\Throwable $e) {
            Log::error('document.list_failed', [
                'operation'   => 'list',
                'status'      => 'failed',
                'user_id'     => $request->user()?->id,
                'notebook_id' => $notebookId ? (int) $notebookId : null,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['error' => 'Failed to retrieve documents'], 500);
        }
    }

    public function store(Request $request)
    {
        $start = microtime(true);

        $validated = $request->validate([
            'file'        => ['required', 'file', 'max:10240', 'mimes:pdf,txt,docx,csv,xlsx'],
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
        ]);

        try {
            $file       = $request->file('file');
            $filename   = uniqid().'_'.$file->getClientOriginalName();
            $notebookId = (int) $validated['notebook_id'];

            $file->storeAs('documents', $filename);

            /** @var \App\Models\User $user */
            $user = $request->user();

            $document = Document::create([
                'user_id'     => $user->id,
                'title'       => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'notebook_id' => $notebookId,
                'filename'    => $filename,
                'mime_type'   => $file->getClientMimeType(),
                'status'      => 'uploaded',
            ]);

            ProcessUploadedDocument::dispatch($document);

            Log::info('document.uploaded', [
                'operation'     => 'upload',
                'status'        => 'uploaded',
                'user_id'       => $user->id,
                'notebook_id'   => $notebookId,
                'document_id'   => $document->id,
                'original_name' => $file->getClientOriginalName(),
                'filename'      => $filename,
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getClientMimeType(),
                'duration_ms'   => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json($document, 201);

        } catch (\Throwable $e) {
            Log::error('document.upload_failed', [
                'operation'   => 'upload',
                'status'      => 'failed',
                'user_id'     => $request->user()?->id,
                'notebook_id' => (int) ($validated['notebook_id'] ?? 0),
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['error' => 'Failed to upload document'], 500);
        }
    }

    public function storeUrl(Request $request)
    {
        $start = microtime(true);

        $validated = $request->validate([
            'url'         => ['required', 'url', 'max:2048'],
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
        ]);

        try {
            $url     = $validated['url'];
            $context = stream_context_create(['http' => [
                'timeout'    => 15,
                'user_agent' => 'Mozilla/5.0 (compatible; NotebookLLM/1.0)',
            ]]);
            $html = @file_get_contents($url, false, $context);

            if ($html === false) {
                return response()->json(['error' => 'Could not fetch the URL'], 422);
            }

            preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $titleMatches);
            $title = isset($titleMatches[1])
                ? html_entity_decode(trim(strip_tags($titleMatches[1])), ENT_QUOTES, 'UTF-8')
                : parse_url($url, PHP_URL_HOST) ?? $url;

            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text ?? '');

            if (empty($text)) {
                return response()->json(['error' => 'No text content found at URL'], 422);
            }

            $filename   = uniqid('url_').'.txt';
            Storage::disk('local')->put('documents/'.$filename, $text);

            /** @var \App\Models\User $user */
            $user       = $request->user();
            $notebookId = (int) $validated['notebook_id'];

            $document = Document::create([
                'user_id'     => $user->id,
                'title'       => mb_substr($title, 0, 255),
                'notebook_id' => $notebookId,
                'filename'    => $filename,
                'mime_type'   => 'text/plain',
                'source_url'  => $url,
                'source_type' => 'url',
                'status'      => 'uploaded',
            ]);

            ProcessUploadedDocument::dispatch($document);

            Log::info('document.url_imported', [
                'operation'   => 'url_import',
                'status'      => 'uploaded',
                'user_id'     => $user->id,
                'notebook_id' => $notebookId,
                'document_id' => $document->id,
                'source_url'  => $url,
                'text_length' => strlen($text),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json($document, 201);

        } catch (\Throwable $e) {
            Log::error('document.url_import_failed', [
                'operation'   => 'url_import',
                'status'      => 'failed',
                'user_id'     => $request->user()?->id,
                'notebook_id' => (int) ($validated['notebook_id'] ?? 0),
                'source_url'  => $validated['url'] ?? null,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['error' => 'Failed to import URL'], 500);
        }
    }

    public function destroy(Request $request, Document $document)
    {
        if ($document->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $documentId = $document->id;
        $notebookId = $document->notebook_id;

        Storage::disk('local')->delete('documents/'.$document->filename);
        $document->delete();

        Log::info('document.deleted', [
            'operation'   => 'delete',
            'status'      => 'success',
            'user_id'     => $request->user()->id,
            'notebook_id' => $notebookId,
            'document_id' => $documentId,
        ]);

        return response()->noContent();
    }
}
