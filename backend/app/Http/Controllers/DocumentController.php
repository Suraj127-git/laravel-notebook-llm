<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
}

