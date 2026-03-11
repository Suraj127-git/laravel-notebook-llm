# Backend Directory Structure

```
backend/
├── app/
│   ├── Ai/
│   │   └── Agents/
│   │       └── KnowledgeAgent.php          ← Core RAG + Groq agent
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php           ← register, login, logout
│   │   │   ├── NotebookController.php       ← CRUD, user-scoped
│   │   │   ├── DocumentController.php       ← upload, list, delete
│   │   │   ├── ChatController.php           ← chat, stream, history, suggest-questions
│   │   │   ├── ContentGenerationController.php ← generate (study_guide|faq|timeline|briefing)
│   │   │   ├── AudioOverviewController.php  ← async audio summary generation
│   │   │   └── UserController.php           ← profile, password, delete, usage
│   │   └── Middleware/
│   │       ├── InitializeTrace.php          ← attaches request_id UUID to each request
│   │       ├── LogRequests.php              ← structured HTTP request/response logging
│   │       └── StreamAuth.php              ← token-via-query-param auth for EventSource
│   ├── Jobs/
│   │   ├── ProcessUploadedDocument.php      ← extract → chunk → embed → store
│   │   └── GenerateAudioOverview.php        ← notebook content → audio file
│   ├── Logging/
│   │   └── Processors/
│   │       ├── RequestIdProcessor.php       ← injects request_id into every log record
│   │       ├── UserContextProcessor.php     ← injects user_id / email
│   │       ├── AIContextProcessor.php       ← injects provider, model, notebook_id
│   │       └── EnvironmentProcessor.php     ← injects app env and version
│   ├── Models/
│   │   ├── User.php
│   │   ├── Notebook.php
│   │   ├── Document.php                     ← status: uploaded|processing|ready|failed
│   │   ├── DocumentChunk.php                ← content + 1024-dim vector column
│   │   ├── ChatMessage.php                  ← role, content, metadata(sources)
│   │   └── AiUsageLog.php                   ← provider, model, tokens, estimated_cost
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/
│       ├── EmbeddingService.php             ← Voyage AI embed + pgvector search
│       ├── ChunkingService.php              ← 2000-char chunks, 200-char overlap
│       └── BusinessEventLogger.php         ← structured AI event logging helpers
├── bootstrap/
│   ├── app.php                              ← middleware registration, exception handler
│   ├── providers.php
│   └── cache/
├── config/
│   ├── ai.php                               ← Groq + VoyageAI provider definitions
│   ├── logging.php                          ← nightwatch + ai_events channels + processors
│   ├── services.php                         ← external service credentials
│   ├── queue.php                            ← redis connection, job timeouts
│   └── ...standard Laravel configs
├── database/
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── NotebookFactory.php
│   │   ├── DocumentFactory.php
│   │   └── ChatMessageFactory.php
│   ├── migrations/
│   │   ├── ...create_users_table
│   │   ├── ...create_notebooks_table
│   │   ├── ...create_documents_table
│   │   ├── ...create_document_chunks_table  ← vector(1024) column
│   │   ├── ...create_chat_messages_table
│   │   └── ...create_ai_usage_logs_table
│   └── seeders/
│       └── DatabaseSeeder.php
├── routes/
│   ├── api.php                              ← all REST + SSE endpoints
│   ├── console.php
│   └── web.php
├── storage/
│   ├── app/documents/                       ← uploaded file storage
│   ├── app/audio/                           ← generated audio overviews
│   ├── framework/
│   └── logs/
├── tests/
│   ├── Feature/
│   │   ├── Auth/AuthControllerTest.php
│   │   ├── Notebook/NotebookControllerTest.php
│   │   ├── Document/DocumentControllerTest.php
│   │   └── Chat/ChatControllerTest.php
│   ├── Unit/
│   │   └── Services/ChunkingServiceTest.php
│   └── TestCase.php
├── .env.docker                              ← Docker environment (fill API keys here)
├── .env.example                             ← Local dev environment template
├── Dockerfile                               ← Multi-stage, PHP 8.2 + extensions
├── docker-entrypoint.sh                     ← migrate + storage:link on container start
├── composer.json
└── phpunit.xml
```

---

## Notable File Details

### `app/Ai/Agents/KnowledgeAgent.php`
Implements `Laravel\Ai\Contracts\Agent` with the `Promptable` trait. Orchestrates the full RAG loop:
- `chat()` — synchronous: retrieve context → prompt Groq → return answer + sources
- `chatStream()` — streaming: retrieve context → stream Groq → yield `TextDelta` events
- `suggestQuestions()` — send last answer → ask Groq for 3 JSON follow-ups
- `generateContent($type)` — type-specific prompt → Groq → markdown output
- `retrieveContext()` — private: embed query → pgvector search → build `<context>` block

### `app/Services/EmbeddingService.php`
- `embed(string $text): array` — calls Voyage AI `voyage-3`, returns float[1024]
- `searchSimilarChunks(string $query, $notebookId, int $limit): Collection` — embeds query, runs `whereVectorSimilarTo()` scoped to notebook documents

### `app/Services/ChunkingService.php`
- `chunk(string $text): array<string>` — splits on sentence boundaries where possible; 2000-char target, 200-char overlap to preserve context continuity across chunk edges

### `app/Jobs/ProcessUploadedDocument.php`
Dispatched immediately on document upload. Runs on the `redis` queue:
1. Sets document status to `processing`
2. Extracts raw text (PDF / DOCX / CSV / TXT)
3. Passes text to `ChunkingService`
4. Loops chunks: embed each via `EmbeddingService` → `DocumentChunk::create()`
5. Sets document status to `ready` (or `failed` on exception)

### `app/Http/Middleware/InitializeTrace.php`
Runs first in the middleware stack. Generates a UUID `request_id` and stores it in `request()->attributes`. All subsequent log processors pick it up, ensuring every log line from a single request shares the same `request_id` — essential for Nightwatch trace correlation.

### `config/ai.php`
Defines two active providers:
- `Lab::Groq` → `GROQ_API_KEY` → `llama-3.3-70b-versatile` for text generation
- `Lab::VoyageAI` → `VOYAGE_API_KEY` → `voyage-3` for embeddings

### `config/logging.php`
- `nightwatch` channel (default) with `RequestIdProcessor` + `UserContextProcessor` + `EnvironmentProcessor`
- `ai_events` channel with `AIContextProcessor` for AI operation tracing
- Both feed into the `notebookllm-nightwatch` Docker container running `php artisan nightwatch:agent`

### `routes/api.php`
Groups:
```
(guest)       → register, login
(auth:sanctum) → all protected endpoints
(stream.auth)  → /chat/stream (accepts token query param)
```
