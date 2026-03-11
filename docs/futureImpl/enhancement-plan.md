# Enhancement Plan & Roadmap

Tracks what has been shipped and what remains on the roadmap.

---

## Shipped (v1.0)

### Backend
- [x] **Groq LLM integration** — `llama-3.3-70b-versatile` via `laravel/ai` `Lab::Groq`
- [x] **Voyage AI embeddings** — `voyage-3` (1024-dim) for high-quality retrieval
- [x] **RAG pipeline** — `ChunkingService` (2000/200 overlap) + pgvector cosine search
- [x] **Token-by-token streaming** — `KnowledgeAgent::chatStream()` via SSE
- [x] **Source citations** — every answer carries document chunk attribution
- [x] **Suggested follow-up questions** — AI generates 3 contextual next questions
- [x] **Content generation** — study_guide, faq, timeline, briefing from notebook docs
- [x] **Audio overview** — async audio summary generation job
- [x] **Chat history** — persistence with source metadata in `chat_messages`
- [x] **AI usage logging** — per-call token tracking in `ai_usage_logs`
- [x] **Notebooks CRUD** — full create/read/update/delete, user-scoped
- [x] **Multi-format document processing** — PDF, DOCX, CSV, TXT
- [x] **Laravel Reverb** — WebSocket server running in Docker
- [x] **Laravel Nightwatch** — structured logging with custom processors
- [x] **Comprehensive test suite** — Feature + Unit tests with factories
- [x] **PHPStan + Larastan** — static analysis at strictest level

### Frontend
- [x] **Redux Toolkit + RTK Query** — centralized server state with tag invalidation
- [x] **SSE streaming UI** — real-time token-by-token chat rendering
- [x] **Shadcn/ui primitives** — `Button`, `Input` with CVA variants
- [x] **Sonner toasts** — non-blocking notifications
- [x] **React 19 patterns** — `useActionState` (login), `useOptimistic` (notebook create)
- [x] **Chat export** — Markdown export of full conversation
- [x] **Recharts usage dashboard** — token/cost visualization
- [x] **Settings page** — profile, password change, account deletion
- [x] **Multi-stage Dockerfile** — dev / build / production (nginx) targets
- [x] **Document status polling** — 3s interval, auto-stops on terminal state

---

## Planned (v2.0)

### Real-time Collaboration
- [ ] **Multi-user notebooks** — share a notebook with collaborators via invite
- [ ] **Typing indicators** — Reverb presence channels showing who's typing
- [ ] **Live document processing status** — Reverb broadcast instead of polling
- [ ] **Concurrent chat sessions** — multiple users chatting in the same notebook

### Enhanced AI Capabilities
- [ ] **Conversation context** — maintain last N turns in the prompt for multi-turn coherence
- [ ] **Hybrid search** — combine keyword (full-text) + semantic (vector) search
- [ ] **Re-ranking** — Cohere Rerank as a post-retrieval quality filter
- [ ] **Model selection per notebook** — let users pick Claude/GPT-4 for specific notebooks
- [ ] **Image understanding** — upload images and ask questions about them

### Authentication & Teams
- [ ] **OAuth login** — Google and GitHub via Laravel Socialite
- [ ] **Workspace management** — team accounts with role-based access (owner/editor/viewer)
- [ ] **API rate limiting per plan** — tiered usage limits with Redis
- [ ] **Audit log** — record all destructive actions (delete notebook, delete document)

### Performance & Scalability
- [ ] **Embedding cache** — cache identical query embeddings in Redis (TTL: 1 hour)
- [ ] **Response cache** — cache identical question+notebook answers (TTL: 15 min)
- [ ] **Horizontal queue workers** — scale document processing workers independently
- [ ] **CDN for documents** — S3 + CloudFront for document storage

### Developer Experience
- [ ] **OpenAPI spec** — auto-generated from Laravel routes
- [ ] **Storybook** — component playground for UI primitives
- [ ] **E2E tests** — Playwright for critical user flows (login → upload → chat)
- [ ] **GitHub Actions CI** — lint + test + build on every PR

---

## Architecture Evolution

### Current (v1.0)
```
Single Postgres instance (data + vectors)
Single Redis instance (cache + queue + sessions)
All background work on one queue worker
```

### Target (v2.0)
```
Read replica for heavy vector search queries
Dedicated Redis Cluster for queue isolation
Auto-scaling queue workers (Kubernetes HPA or Fly.io machines)
S3-compatible storage for document files
```

---

## Performance Targets (v2.0)

| Metric | Current | Target |
|---|---|---|
| Chat first-token latency | ~300ms (Groq) | <200ms |
| Document processing (1MB PDF) | ~5-10s | <3s |
| API p99 (non-AI) | ~50ms | <30ms |
| Vector search (10k chunks) | ~20ms | <10ms |
| Frontend FCP | ~800ms | <600ms |
