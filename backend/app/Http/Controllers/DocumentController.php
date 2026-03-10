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
        Log::info('Document index request', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $notebookId = $request->query('notebook_id');

        $documents = $request->user()
            ->documents()
            ->when($notebookId, fn ($q) => $q->where('notebook_id', $notebookId))
            ->latest()
            ->get();
            
            Log::info('Document index completed', [
                'user_id' => $request->user()?->id,
                'document_count' => $documents->count()
            ]);

            return $documents;

        } catch (\Exception $e) {
            Log::error('Document index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'error' => 'Failed to retrieve documents'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('Document upload request', [
            'user_id' => $request->user()?->id,
            'notebook_id' => $request->input('notebook_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,txt,docx,csv,xlsx'],
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
        ]);

        Log::info('Document upload validated', [
            'user_id' => $request->user()?->id,
            'notebook_id' => $validated['notebook_id']
        ]);

        try {
            $file = $request->file('file');
            $filename = uniqid().'_'.$file->getClientOriginalName();
            
            Log::info('File processing started', [
                'user_id' => $request->user()?->id,
                'original_name' => $file->getClientOriginalName(),
                'generated_filename' => $filename,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType()
            ]);

            $file->storeAs('documents', $filename);

            Log::info('File stored successfully', [
                'user_id' => $request->user()?->id,
                'filename' => $filename
            ]);

            /** @var \App\Models\User $user */
            $user = $request->user();

            $document = Document::create([
                'user_id' => $user->id,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'notebook_id' => (int) $request->input('notebook_id'),
                'filename' => $filename,
                'mime_type' => $file->getClientMimeType(),
                'status' => 'uploaded',
            ]);

            Log::info('Document created in database', [
                'user_id' => $user->id,
                'document_id' => $document->id,
                'title' => $document->title,
                'notebook_id' => $document->notebook_id
            ]);

            ProcessUploadedDocument::dispatch($document);

            Log::info('Document processing job dispatched', [
                'user_id' => $user->id,
                'document_id' => $document->id
            ]);

            return response()->json($document, 201);

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'notebook_id' => $request->input('notebook_id')
            ]);

            return response()->json([
                'error' => 'Failed to upload document'
            ], 500);
        }
    }

    public function storeUrl(Request $request)
    {
        $validated = $request->validate([
            'url'         => ['required', 'url', 'max:2048'],
            'notebook_id' => ['required', 'integer', 'exists:notebooks,id'],
        ]);

        try {
            $url = $validated['url'];

            // Fetch page content
            $context = stream_context_create(['http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (compatible; NotebookLLM/1.0)',
            ]]);
            $html = @file_get_contents($url, false, $context);

            if ($html === false) {
                return response()->json(['error' => 'Could not fetch the URL'], 422);
            }

            // Extract title
            preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $titleMatches);
            $title = isset($titleMatches[1])
                ? html_entity_decode(trim(strip_tags($titleMatches[1])), ENT_QUOTES, 'UTF-8')
                : parse_url($url, PHP_URL_HOST) ?? $url;

            // Strip HTML to plain text
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text ?? '');

            if (empty($text)) {
                return response()->json(['error' => 'No text content found at URL'], 422);
            }

            // Store text as a temp file
            $filename = uniqid('url_').'.txt';
            Storage::disk('local')->put('documents/'.$filename, $text);

            /** @var \App\Models\User $user */
            $user = $request->user();

            $document = Document::create([
                'user_id'     => $user->id,
                'title'       => mb_substr($title, 0, 255),
                'notebook_id' => (int) $validated['notebook_id'],
                'filename'    => $filename,
                'mime_type'   => 'text/plain',
                'source_url'  => $url,
                'source_type' => 'url',
                'status'      => 'uploaded',
            ]);

            ProcessUploadedDocument::dispatch($document);

            return response()->json($document, 201);
        } catch (\Throwable $e) {
            Log::error('URL document import failed', [
                'url'   => $validated['url'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to import URL'], 500);
        }
    }

    public function destroy(Request $request, Document $document)
    {
        if ($document->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        Storage::disk('local')->delete('documents/' . $document->filename);
        $document->delete();

        return response()->noContent();
    }
}

