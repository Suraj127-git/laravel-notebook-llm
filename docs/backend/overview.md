# Backend Overview

## Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Framework | Laravel | 12 |
| Language | PHP | 8.2+ |
| LLM | Groq `llama-3.3-70b-versatile` | via `laravel/ai` |
| Embeddings | Voyage AI `voyage-3` (1024-dim) | via `laravel/ai` |
| Database | PostgreSQL + pgvector | 16 / 0.7.4 |
| Cache / Queue | Redis | 7 |
| WebSockets | Laravel Reverb | 1.x |
| Auth | Laravel Sanctum | 4.x |
| Monitoring | Laravel Nightwatch | 1.24 |
| Testing | PHPUnit | via Laravel |
| Static Analysis | PHPStan + Larastan | |
| Code Style | Laravel Pint | |

---

## Application Layers

```
HTTP Request
     │
     ▼
Middleware Pipeline
  ├─ InitializeTrace      → attach request_id to every log line
  ├─ LogRequests          → structured request/response logging
  └─ StreamAuth           → token-via-query-param for EventSource endpoints
     │
     ▼
Controllers (thin — validation + orchestration only)
  ├─ AuthController        → register, login, logout
  ├─ NotebookController    → CRUD, user_id scoped
  ├─ DocumentController    → upload → dispatch job
  ├─ ChatController        → chat, stream, history, suggest-questions
  ├─ ContentGenerationController → study_guide, faq, timeline, briefing
  ├─ AudioOverviewController     → notebook audio summary
  └─ UserController        → profile, password, delete, usage stats
     │
     ▼
Services / Agents
  ├─ KnowledgeAgent        → RAG retrieval + Groq LLM + streaming
  ├─ EmbeddingService      → Voyage AI embed + pgvector similarity search
  ├─ ChunkingService        → text → 2000-char chunks (200-char overlap)
  └─ BusinessEventLogger   → structured AI event logging → ai_events channel
     │
     ▼
Jobs (Redis-backed queue)
  ├─ ProcessUploadedDocument  → extract text → chunk → embed → store
  └─ GenerateAudioOverview    → notebook content → audio synthesis
     │
     ▼
Data Layer
  ├─ PostgreSQL (Eloquent ORM)
  │   ├─ users, notebooks
  │   ├─ documents (status: uploaded|processing|ready|failed)
  │   ├─ document_chunks (content + 1024-dim vector)
  │   ├─ chat_messages (role, content, metadata→sources)
  │   └─ ai_usage_logs (provider, model, tokens, cost)
  └─ pgvector extension → cosine similarity search on chunk embeddings
```

---

## AI Integration

### RAG Pipeline

1. **Ingest** — `ProcessUploadedDocument` job runs after every upload:
   - Extracts text (PDF via `smalot/pdfparser`, DOCX via `phpoffice/phpword`, CSV via `league/csv`)
   - `ChunkingService` splits content into overlapping 2000-char segments
   - `EmbeddingService` calls Voyage AI `voyage-3` for each chunk
   - Chunks stored in `document_chunks` with 1024-dim vector column

2. **Retrieve** — `KnowledgeAgent::retrieveContext()`:
   - Embeds the user's question with Voyage AI
   - Runs `whereVectorSimilarTo()` cosine search (pgvector) scoped to the notebook
   - Returns top-5 chunks; assembles `<context>` block for the system prompt

3. **Generate** — `KnowledgeAgent::chat()` / `chatStream()`:
   - Sends assembled system prompt + user message to Groq via `Lab::Groq`
   - Model: `llama-3.3-70b-versatile`
   - Returns answer text + source citations (document title + id)

### Streaming

`KnowledgeAgent::chatStream()` yields `TextDelta` events from the Laravel AI streaming API. `ChatController::stream()` iterates the generator and writes SSE-formatted lines to the HTTP response:

```
data: {"delta": "Hello"}        ← text chunk (one per token/word)
data: {"sources": [...]}        ← after stream ends, inject citations
data: {"done": true}            ← sentinel
data: {"error": "..."}          ← only on failure
```

EventSource clients receive tokens as they arrive — typical first-token latency with Groq is under 300ms.

### Content Generation

`KnowledgeAgent::generateContent(type)` re-uses the same RAG retrieval pipeline but swaps in a format-specific system prompt:

| Type | Output |
|---|---|
| `study_guide` | Sectioned markdown with key concepts and definitions |
| `faq` | Q&A pairs covering main topics |
| `timeline` | Chronological events in `**Date:** Event` format |
| `briefing` | Executive summary with findings and recommendations |

### Suggested Questions

`KnowledgeAgent::suggestQuestions(lastAnswer)` sends the AI's last response back to Groq and asks for a JSON array of 3 follow-up questions. The UI displays these as clickable chips below each assistant message.

---

## Authentication

**Standard endpoints** use `auth:sanctum` middleware — token passed as `Authorization: Bearer <token>` header.

**Streaming endpoint** (`/api/chat/stream`) accepts the token as a query parameter (`?token=...`) because the browser's `EventSource` API does not support custom headers. `StreamAuth` middleware handles this case by looking up the token via `PersonalAccessToken::findToken()` and calling `auth()->setUser()`.

---

## Queue System

Document processing and audio generation run as background jobs on a Redis-backed queue. The `queue` container runs:

```bash
php artisan queue:work redis --tries=3 --timeout=300 --sleep=3 --max-jobs=500 --max-time=3600
```

`--tries=3` means a document that fails (e.g., corrupted PDF) is retried twice before being marked `failed`.

---

## Observability

Three structured log channels feed into Laravel Nightwatch:

| Channel | Purpose | Key fields |
|---|---|---|
| `nightwatch` | All application logs (default) | request_id, user_id, environment |
| `ai_events` | AI operation events | provider, model, duration_ms, chunk_count |
| `stack` | Combines nightwatch + stderr for dev | — |

Custom Monolog processors attach context to every log record:
- `RequestIdProcessor` — unique request UUID (from `InitializeTrace` middleware)
- `UserContextProcessor` — authenticated user id/email
- `AIContextProcessor` — provider, model, notebook_id for AI logs
- `EnvironmentProcessor` — app environment, version

All AI calls log: initiation → RAG retrieval → LLM complete, each with `duration_ms`. This makes it trivial to identify slow retrievals or LLM latency spikes in the Nightwatch dashboard.

---

## Testing Strategy

Tests live in `tests/Feature/` (controller-level, hits real SQLite database) and `tests/Unit/` (service-level, no HTTP).

```
tests/
├── Feature/
│   ├── Auth/AuthControllerTest.php
│   ├── Notebook/NotebookControllerTest.php
│   ├── Document/DocumentControllerTest.php
│   └── Chat/ChatControllerTest.php
└── Unit/
    └── Services/ChunkingServiceTest.php
```

Factories available: `UserFactory`, `NotebookFactory`, `DocumentFactory`, `ChatMessageFactory`.

Feature tests use Laravel's `RefreshDatabase` trait and test against an in-memory SQLite database — no mocking of the ORM layer.

---

## Key Dependencies

```json
{
  "laravel/framework": "^12.0",
  "laravel/ai": "^0.2",
  "laravel/sanctum": "^4.0",
  "laravel/nightwatch": "^1.24",
  "laravel/reverb": "^1.0",
  "smalot/pdfparser": "^2.0",
  "phpoffice/phpword": "^1.3",
  "league/csv": "^9.0",
  "phpstan/phpstan": "^2.0",
  "larastan/larastan": "^3.0"
}
```
