# NotebookLLM

> **Chat with your documents.** Upload PDFs, ask questions, get AI-powered answers with source citations — all in real time.

A production-ready, full-stack RAG (Retrieval-Augmented Generation) application built with **Laravel 12** and **React 19**. Drop in documents, fire up a notebook, and have a streamed conversation backed by vector search and Groq's `llama-3.3-70b-versatile`.

---

## What it does

| Capability | Detail |
|---|---|
| **Document RAG** | Upload PDFs/DOCX/CSV → auto-chunked, embedded with Voyage AI, stored in pgvector |
| **Streaming Chat** | Token-by-token SSE streaming via Groq `llama-3.3-70b-versatile` |
| **Source Citations** | Every answer includes the document chunks it was drawn from |
| **Follow-up Questions** | AI suggests 3 contextual next questions after each answer |
| **Content Generation** | One-click Study Guide, FAQ, Timeline, or Briefing from your notebook |
| **Audio Overview** | Generate an audio summary of any notebook |
| **Chat History** | Full conversation persistence per notebook |
| **Usage Tracking** | Per-user token + cost tracking across all AI calls |

---

## Architecture at a Glance

```
Browser (React 19 + TypeScript)
  │
  ├── Redux Toolkit + RTK Query  ──→  REST endpoints (Sanctum auth)
  └── EventSource  ──────────────→  SSE stream  (/api/chat/stream)
                                          │
                              Laravel 12 backend
                                ┌──────────────────┐
                                │  KnowledgeAgent  │
                                │  ┌────────────┐  │
                                │  │  RAG Fetch │  │  Voyage AI embeddings
                                │  │  (pgvector)│◄─┼── DocumentChunk table
                                │  └─────┬──────┘  │
                                │        │          │
                                │  ┌─────▼──────┐  │
                                │  │ Groq LLM   │  │  llama-3.3-70b-versatile
                                │  │ (streaming)│  │
                                │  └────────────┘  │
                                └──────────────────┘
                                          │
                              PostgreSQL + pgvector
                              Redis (cache / queue)
```

---

## Tech Stack

### Backend
| Layer | Choice | Why |
|---|---|---|
| Framework | Laravel 12 / PHP 8.2 | Modern PHP, excellent queue/job system |
| LLM | Groq `llama-3.3-70b-versatile` | Sub-second TTFT, cost-effective at scale |
| Embeddings | Voyage AI `voyage-3` (1024-dim) | State-of-the-art retrieval quality |
| Vector DB | PostgreSQL + pgvector | Single-DB simplicity, production-proven |
| Chunking | 2000-char chunks, 200-char overlap | Balanced context window usage |
| Auth | Laravel Sanctum | Token-based, streaming-compatible |
| Queue | Redis-backed Laravel Queue | Async document processing |
| WebSockets | Laravel Reverb | Real-time events (audio, processing status) |
| Monitoring | Laravel Nightwatch | Structured request/AI event tracing |
| Testing | PHPUnit + Feature/Unit split | Full controller + service coverage |

### Frontend
| Layer | Choice | Why |
|---|---|---|
| Framework | React 19 + TypeScript | `useActionState`, React 19 concurrent features |
| State | Redux Toolkit + RTK Query | Normalized cache, optimistic updates |
| Streaming | Native `EventSource` | Standard SSE, no library overhead |
| Styling | Tailwind CSS v4 + Framer Motion | Utility-first, fluid animations |
| UI Primitives | Shadcn/ui (Radix + CVA) | Accessible, headless components |
| Charts | Recharts | Usage stats visualization |
| Toasts | Sonner | Clean, non-blocking notifications |
| Build | Vite 7 | <500ms HMR |

---

## Quick Start (Docker)

```bash
# 1. Clone
git clone <repo-url> && cd laravel-notebookllm

# 2. Configure environment
cp backend/.env.docker.example backend/.env.docker
# Fill in: GROQ_API_KEY, VOYAGE_API_KEY, NIGHTWATCH_TOKEN

# 3. Start everything
docker compose up -d

# 4. Run migrations
docker compose run --rm backend php artisan migrate

# 5. Open the app
# Frontend:  http://localhost:5173
# API:       http://localhost:8000
# WebSocket: ws://localhost:8080
```

All 8 services start with a single command:

| Container | Role |
|---|---|
| `notebookllm-postgres` | pgvector database |
| `notebookllm-redis` | Cache, sessions, queue broker |
| `notebookllm-backend` | Laravel HTTP server |
| `notebookllm-queue` | Async job worker (document processing) |
| `notebookllm-scheduler` | Laravel cron scheduler |
| `notebookllm-reverb` | WebSocket server (port 8080) |
| `notebookllm-nightwatch` | Log ingest agent → Nightwatch cloud |
| `notebookllm-frontend` | Vite dev server (port 5173) |

---

## Local Development (without Docker)

### Backend
```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
composer dev          # starts server + queue + log watcher concurrently
```

### Frontend
```bash
cd frontend
npm install
npm run dev
```

---

## API Reference

### Public
```
POST /api/register          Register a new user
POST /api/login             Authenticate, receive Sanctum token
```

### Authenticated (Bearer token)
```
GET  /api/user                          Current user profile
PUT  /api/user/profile                  Update display name / email
PUT  /api/user/password                 Change password
DELETE /api/user                        Delete account

GET  /api/notebooks                     List notebooks
POST /api/notebooks                     Create notebook
PUT  /api/notebooks/{id}                Rename notebook
DELETE /api/notebooks/{id}              Delete notebook

GET  /api/documents                     List documents (scoped to notebook)
POST /api/documents                     Upload document (multipart)
DELETE /api/documents/{id}              Delete document

POST /api/chat                          Synchronous chat (returns full answer)
GET  /api/chat/history/{notebookId}     Fetch last 50 messages
POST /api/chat/suggest-questions        Get 3 AI-suggested follow-ups
POST /api/content/generate              Generate study guide / FAQ / timeline / briefing

POST /api/audio/overview                Generate audio overview for notebook
GET  /api/user/usage                    Token + cost usage stats

POST /api/logout                        Revoke current token
```

### Streaming (token via query param for EventSource)
```
GET  /api/chat/stream?token=...&notebook_id=...&message=...
```

SSE event format:
```
data: {"delta": "Hello"}      ← text chunk
data: {"sources": [...]}      ← source citations
data: {"done": true}          ← stream complete
data: {"error": "..."}        ← error (if any)
```

---

## Document Processing Pipeline

```
Upload (multipart) → DocumentController
  │
  └→ ProcessUploadedDocument job (Redis queue)
       │
       ├── PDF/DOCX/CSV extraction
       ├── ChunkingService  →  2000-char chunks, 200-char overlap
       ├── EmbeddingService →  Voyage AI voyage-3 (1024-dim vectors)
       └── DocumentChunk::upsert()  →  pgvector storage
            Status: uploaded → processing → ready | failed
```

---

## RAG Flow

```
User message
  │
  ├── EmbeddingService::embed(message)   → Voyage AI
  ├── EmbeddingService::searchSimilarChunks()  → pgvector cosine similarity
  │    └── Top-5 chunks from notebook's documents
  │
  └── KnowledgeAgent::instructions()    → injects chunks as <context>
       └── Lab::Groq prompt (llama-3.3-70b-versatile)
            └── Response + source attribution
```

---

## Testing

```bash
# All tests (Docker)
docker compose run --rm backend php artisan test

# Specific suite
docker compose run --rm backend php artisan test --filter ChatControllerTest

# Static analysis
docker compose run --rm backend vendor/bin/phpstan analyse

# Code style
docker compose run --rm backend vendor/bin/pint
```

Test suites:
- `tests/Feature/Auth/AuthControllerTest`
- `tests/Feature/Notebook/NotebookControllerTest`
- `tests/Feature/Document/DocumentControllerTest`
- `tests/Feature/Chat/ChatControllerTest`
- `tests/Unit/Services/ChunkingServiceTest`

---

## Project Structure

```
laravel-notebookllm/
├── docker-compose.yml
├── backend/
│   ├── app/
│   │   ├── Ai/Agents/KnowledgeAgent.php      ← RAG + Groq + streaming
│   │   ├── Http/Controllers/                 ← Auth, Chat, Document, Audio, Content
│   │   ├── Http/Middleware/                  ← StreamAuth, LogRequests, InitializeTrace
│   │   ├── Jobs/                             ← ProcessUploadedDocument, GenerateAudioOverview
│   │   ├── Logging/Processors/               ← RequestId, User, AI, Environment context
│   │   ├── Models/                           ← User, Notebook, Document, DocumentChunk, ...
│   │   └── Services/                         ← EmbeddingService, ChunkingService, BusinessEventLogger
│   ├── config/
│   │   ├── ai.php                            ← Groq + VoyageAI provider config
│   │   ├── logging.php                       ← Nightwatch + ai_events channels
│   │   └── services.php
│   ├── database/
│   │   ├── migrations/                       ← notebooks, documents, chunks (vector), messages
│   │   └── factories/                        ← User, Notebook, Document, ChatMessage
│   └── tests/
│       ├── Feature/                          ← Controller integration tests
│       └── Unit/                             ← Service unit tests
└── frontend/
    └── src/
        ├── store/                            ← Redux + RTK Query slices
        ├── pages/                            ← Login, Dashboard, Settings, Usage
        ├── components/                       ← ChatPanel, NotebookSidebar, DocumentUpload, ...
        ├── components/ui/                    ← Button, Input (CVA-based primitives)
        └── lib/                              ← export.ts, utils.ts, streaming.ts
```

---

## Environment Variables

```env
# Required AI keys
GROQ_API_KEY=gsk_...
VOYAGE_API_KEY=pa-...

# Database (Docker defaults)
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=notebookllm
DB_USERNAME=postgres
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis

# Nightwatch monitoring
NIGHTWATCH_TOKEN=...

# Laravel Reverb (WebSocket)
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
```

---

## Key Engineering Decisions

**Why pgvector over a dedicated vector DB?**
Keeps the infrastructure simple (one less service), and pgvector with 1024-dim vectors handles millions of chunks without issue. Operational simplicity wins at this scale.

**Why SSE over WebSockets for chat streaming?**
SSE is unidirectional (server → client), which is exactly what chat streaming needs. It works over HTTP/1.1, through proxies, and requires no handshake. WebSockets (Reverb) are reserved for bidirectional events like processing status updates.

**Why Groq over OpenAI?**
~10x lower latency for streaming tokens. For a chat interface where users watch text appear in real time, time-to-first-token is the single most important metric.

**Why Voyage AI for embeddings?**
`voyage-3` consistently outperforms `text-embedding-ada-002` on retrieval benchmarks at a lower cost per token. The 1024-dim output is compact enough for efficient cosine similarity at scale.

---

## License

MIT
