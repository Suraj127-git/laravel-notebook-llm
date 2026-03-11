# Backend Code Explanation

Deep-dives into the most important backend components.

---

## KnowledgeAgent

`app/Ai/Agents/KnowledgeAgent.php`

The heart of the application. Implements `Laravel\Ai\Contracts\Agent` and uses the `Promptable` trait which gives it `$this->prompt()` and `$this->stream()` methods bound to the Laravel AI package.

### Constructor

```php
public function __construct(
    public ?Authenticatable $user = null,
    private int|string|null $notebookId = null,
)
```

The `notebookId` is critical — it scopes all vector searches to only the documents belonging to this notebook, preventing cross-notebook data leakage.

### `instructions(): string`

This is the system prompt. It's dynamic: when `$retrievedContext` is empty (no documents uploaded yet) it tells the user to upload documents. When context is available it injects the RAG chunks inside `<context>` XML tags — a pattern that works particularly well with instruction-following models like Llama 3.3.

```php
return $base . "\n\n<context>\n{$this->retrievedContext}\n</context>";
```

### `chat()` — Synchronous path

```
retrieveContext(message)
  → Lab::Groq prompt (llama-3.3-70b-versatile)
  → logUsage() → AiUsageLog::create()
  → return anonymous class { content(), sources }
```

The anonymous class is returned instead of a named DTO to keep things lightweight. Callers destructure `$response->content()` and `$response->sources`.

### `chatStream()` — Streaming path

```php
$stream = $this->stream($message, provider: Lab::Groq, model: 'llama-3.3-70b-versatile');

foreach ($stream as $event) {
    if (!$event instanceof TextDelta) continue;
    yield new class($event->delta) { public function text(): string {...} };
}
```

The Laravel AI streaming API returns a mix of event types (`TextDelta`, `InputTokens`, `OutputTokens`, etc.). We filter to `TextDelta` only and yield a lightweight anonymous object. The controller drives this generator and flushes each chunk to the HTTP response immediately.

### `retrieveContext()` — Private

This is where RAG happens. Importantly, it catches all `\Throwable` — if the embedding service is unavailable or pgvector throws, we fall back to an empty context and let the LLM respond without document context rather than surfacing a 500 error to the user.

```php
$chunks = $embeddingService->searchSimilarChunks($message, $this->notebookId, limit: 5);

foreach ($chunks as $chunk) {
    $contextParts[]  = "Source: {$chunk->document_title}\n{$chunk->content}";
    $this->sources[] = ['title' => $chunk->document_title, 'document_id' => (int) $chunk->document_id];
}

$this->retrievedContext = implode("\n\n---\n\n", $contextParts);
```

Sources are stored on the instance so `getSources()` can be called after streaming completes (the controller needs them to emit the `{"sources": [...]}` SSE event at the end of the stream).

---

## ChatController

`app/Http/Controllers/ChatController.php`

### `stream()` — SSE endpoint

The streaming response uses Laravel's `response()->stream()` which accepts a closure. Inside the closure:

1. Call `$agent->chatStream($message)` — starts RAG retrieval then opens the Groq stream
2. Iterate the generator, writing each chunk as an SSE line:
   ```php
   echo 'data: ' . json_encode(['delta' => $chunk], JSON_THROW_ON_ERROR) . "\n\n";
   @ob_flush(); @flush();
   ```
3. After the generator exhausts, emit sources and the `done` sentinel
4. Persist both messages to `chat_messages` (with sources in `metadata`)

The `@ob_flush()` / `@flush()` pair bypasses output buffering at both PHP and web server levels — essential for true streaming. The `X-Accel-Buffering: no` header disables nginx proxy buffering.

### Token auth for EventSource

The browser `EventSource` API does not support custom headers. Rather than forcing users through a WebSocket upgrade just for auth, we accept the Sanctum token as a query parameter and validate it inline:

```php
if ($request->has('token') && !auth()->check()) {
    $accessToken = PersonalAccessToken::findToken($request->input('token'));
    if ($accessToken) {
        auth()->setUser($accessToken->tokenable);
    }
}
```

This happens before validation so `$request->user()` works normally for the rest of the method.

### `history()` — Chat history

```php
ChatMessage::query()
    ->where('user_id', $request->user()->id)
    ->where('notebook_id', $notebookId)
    ->latest()
    ->take(50)
    ->get()
    ->reverse()
    ->values();
```

Fetches the 50 most recent messages and reverses them to chronological order. The `->values()` re-indexes the collection so the frontend receives a clean JSON array.

---

## EmbeddingService

`app/Services/EmbeddingService.php`

### Embedding generation

Uses `Laravel\Ai\Facades\Embeddings` with `Lab::VoyageAI` and model `voyage-3`. The 1024-dim output balances quality and storage cost — each chunk vector takes 4KB as a `float4[]`.

### `searchSimilarChunks()`

The pgvector query is scoped to documents that belong to the given notebook:

```sql
SELECT document_chunks.*
FROM document_chunks
JOIN documents ON documents.id = document_chunks.document_id
WHERE documents.notebook_id = ?
  AND documents.status = 'ready'
ORDER BY embedding <=> '[query_vector]'   -- cosine distance operator
LIMIT 5
```

The `<=>` operator is pgvector's cosine distance. Results are the 5 most semantically similar chunks across all ready documents in the notebook.

---

## ChunkingService

`app/Services/ChunkingService.php`

Splits document text into overlapping segments. Key parameters:

- **Chunk size**: 2000 characters — fits comfortably within Groq's context window while providing enough context per chunk
- **Overlap**: 200 characters — prevents answers from being split at chunk boundaries

The service attempts to split on sentence/paragraph boundaries before falling back to hard character cuts.

---

## ProcessUploadedDocument Job

`app/Jobs/ProcessUploadedDocument.php`

Runs on the `redis` queue. The job is idempotent — it resets chunk data on retry, so a failed document can be reprocessed without leaving orphaned chunks.

Document format support:
- **PDF** — `smalot/pdfparser` extracts raw text from each page
- **DOCX** — `phpoffice/phpword` reads paragraphs
- **CSV** — `league/csv` joins all cells as text
- **TXT** — direct file read

Status flow: `uploaded → processing → ready` (or `failed` if an exception escapes the try/catch).

---

## Middleware Pipeline

### `InitializeTrace`

Runs very early in the stack. Generates `uniqid('req_', true)` and stores it via `$request->attributes->set('request_id', ...)`. Every subsequent log processor reads this value and adds it to the log record context. In Nightwatch, you can filter all logs from a single HTTP request by `request_id`.

### `LogRequests`

Logs at `info` level on request start (method, path, user_id) and on response completion (status code, duration_ms). Provides an audit trail without needing to instrument individual controllers.

### `StreamAuth`

Applied only to `/api/chat/stream`. Checks standard Sanctum auth first (for non-browser clients), then falls through to the query-param token approach for browser `EventSource` clients.

---

## Logging Architecture

```
Controller/Agent/Job
        │
        ▼
  Log::channel('nightwatch')->info(...)
  Log::channel('ai_events')->info(...)
        │
        ▼
  Monolog Processors (run on every record)
  ├─ RequestIdProcessor   → adds request_id
  ├─ UserContextProcessor → adds user_id, user_email
  ├─ AIContextProcessor   → adds ai_provider, model (ai_events channel only)
  └─ EnvironmentProcessor → adds app_env, app_version
        │
        ▼
  Nightwatch driver → UDP → nightwatch:agent container → Nightwatch cloud
```

All log calls use structured context arrays rather than interpolated strings — this allows Nightwatch to index and filter on individual fields (e.g., show all logs where `notebook_id = 42 AND duration_ms > 1000`).

---

## Error Handling Philosophy

1. **User-visible errors** — Controllers return JSON with a human-readable `error` field and an appropriate HTTP status. Raw exception messages never reach the client in production.

2. **AI fallbacks** — If RAG retrieval fails, `retrieveContext()` swallows the error and continues with empty context. The user gets a response (the LLM will say it has no context) rather than a 500.

3. **Stream errors** — If the Groq stream fails mid-response, the controller catches the exception inside the streaming closure and emits a `{"error": "..."}` SSE event so the frontend can display it gracefully rather than just hanging.

4. **Job retries** — `ProcessUploadedDocument` is configured with `$tries = 3`. Failed documents get their status set to `failed` after exhausting retries, and the user sees a clear status indicator in the UI.
