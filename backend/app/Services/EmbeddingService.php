<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class EmbeddingService
{
    // Voyage AI voyage-3 outputs 1024-dimensional vectors
    private const VOYAGE_MODEL = 'voyage-3';

    private const BATCH_SIZE = 10;

    /**
     * Embed a full document and store on the document record.
     * Prefer embedChunks() for better RAG retrieval quality.
     */
    public function embedDocument(Document $document): void
    {
        if (! $document->content) {
            return;
        }

        $response = Embeddings::for([$document->content])
            ->generate(Lab::VoyageAI, self::VOYAGE_MODEL);

        $embedding = $response->embeddings[0] ?? null;

        if ($embedding) {
            $document->embedding = $embedding;
            $document->save();
        }
    }

    /**
     * Embed text chunks and persist each as a DocumentChunk.
     * Processes in batches to respect API rate limits.
     *
     * @param  string[]  $chunks
     */
    public function embedChunks(Document $document, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        $start        = microtime(true);
        $totalBatches = (int) ceil(count($chunks) / self::BATCH_SIZE);

        // Remove any existing chunks before re-embedding
        DocumentChunk::where('document_id', $document->id)->delete();

        $batches    = array_chunk($chunks, self::BATCH_SIZE);
        $chunkIndex = 0;

        foreach ($batches as $batchNumber => $batch) {
            Log::debug('embedding.batch', [
                'operation'     => 'embed_batch',
                'document_id'   => $document->id,
                'notebook_id'   => $document->notebook_id,
                'user_id'       => $document->user_id,
                'provider'      => 'voyage_ai',
                'model'         => self::VOYAGE_MODEL,
                'batch'         => $batchNumber + 1,
                'total_batches' => $totalBatches,
                'batch_size'    => count($batch),
            ]);

            $response = Embeddings::for($batch)
                ->generate(Lab::VoyageAI, self::VOYAGE_MODEL);

            foreach ($response->embeddings as $i => $embedding) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'user_id'     => $document->user_id,
                    'notebook_id' => $document->notebook_id,
                    'chunk_index' => $chunkIndex++,
                    'content'     => $batch[$i],
                    'embedding'   => $embedding,
                    'token_count' => (int) ceil(strlen($batch[$i]) / 4),
                ]);
            }
        }

        Log::info('embedding.complete', [
            'operation'     => 'embed_chunks',
            'status'        => 'success',
            'document_id'   => $document->id,
            'notebook_id'   => $document->notebook_id,
            'user_id'       => $document->user_id,
            'provider'      => 'voyage_ai',
            'model'         => self::VOYAGE_MODEL,
            'chunk_count'   => $chunkIndex,
            'total_batches' => $totalBatches,
            'duration_ms'   => round((microtime(true) - $start) * 1000, 2),
        ]);
    }

    /**
     * Find the most semantically similar chunks to a query using Voyage AI embeddings.
     * Joins document title for source citations.
     */
    public function searchSimilarChunks(string $query, int|string|null $notebookId, int $limit = 5): Collection
    {
        $queryEmbedding = Embeddings::for([$query])
            ->generate(Lab::VoyageAI, self::VOYAGE_MODEL)
            ->embeddings[0];

        $q = DocumentChunk::query()
            ->select([
                'document_chunks.*',
                'documents.title as document_title',
            ])
            ->join('documents', 'documents.id', '=', 'document_chunks.document_id')
            ->orderByVectorDistance('document_chunks.embedding', $queryEmbedding)
            ->limit($limit);

        if ($notebookId) {
            $q->where('document_chunks.notebook_id', $notebookId);
        }

        return $q->get();
    }

    /**
     * Legacy document-level similarity search.
     */
    public function searchSimilar(string $query, int $limit = 5): Collection
    {
        $queryEmbedding = Embeddings::for([$query])
            ->generate(Lab::VoyageAI, self::VOYAGE_MODEL)
            ->embeddings[0];

        return Document::query()
            ->orderByVectorDistance('embedding', $queryEmbedding)
            ->limit($limit)
            ->get();
    }
}
