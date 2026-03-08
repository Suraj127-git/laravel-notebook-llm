# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel NotebookLLM application - a full-stack document Q&A system that allows users to upload documents, extract their content, generate embeddings, and chat with an AI agent about the content. It combines a Laravel 12 backend with a React + TypeScript + Vite frontend.

## Architecture

### Backend (Laravel 12)

**Core Components:**
- **Authentication**: Laravel Sanctum for API token authentication
- **Document Processing Pipeline**:
  1. Upload handled by `DocumentController`
  2. Async processing via `ProcessUploadedDocument` job
  3. PDF extraction using `smalot/pdfparser`
  4. Vector embeddings via `EmbeddingService` using Laravel AI
  5. Storage of 1536-dimension embeddings with pgvector
- **AI Integration**:
  - `KnowledgeAgent` (App\Ai\Agents) - Currently returns placeholder responses, intended for Laravel AI integration
  - Supports both standard and streaming chat responses
  - Vector similarity search for document retrieval
- **Queue System**: Database-backed queue for async document processing
- **Database**: SQLite (default), with vector extension support for embeddings

**Key Files:**
- Routes: `backend/routes/api.php` - All API endpoints with Sanctum auth
- Controllers: `backend/app/Http/Controllers/` - Auth, Document, Chat, Image, Audio
- Models: Document, ChatMessage, AiUsageLog, User (all in `backend/app/Models/`)
- Jobs: `ProcessUploadedDocument` - Extracts text and generates embeddings
- Services: `EmbeddingService` - Handles embedding generation and similarity search
- Middleware: `StreamAuth` - Custom auth for SSE streaming endpoints

**Document Workflow:**
1. File uploaded → stored in `storage/app/documents/`
2. Job dispatched → extracts content (PDF or text)
3. Embedding generated → stored as vector in database
4. Status: uploaded → processing → ready (or failed)

### Frontend (React + TypeScript + Vite)

**Stack:**
- React 19 with TypeScript
- React Router for routing
- Tailwind CSS v4 for styling
- Framer Motion for animations
- Axios for API calls
- EventSource for SSE streaming

**Structure:**
- Pages: `LoginPage`, `DashboardPage`
- Components: `ChatPanel`, `DocumentUpload`, `ProtectedRoute`
- API proxy configured in Vite to `http://backend:8000`

**Streaming Implementation:**
- `frontend/src/lib/streaming.ts` handles SSE connections
- Passes token via query param for EventSource compatibility
- ChatPanel uses streaming for real-time AI responses

## Development Commands

### Backend

**Setup:**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

**Run Development Server:**
```bash
cd backend
composer dev
```
This starts 4 concurrent processes: Laravel server, queue worker, log viewer (pail), and Vite dev server.

**Run Individual Services:**
```bash
php artisan serve                    # Start Laravel server
php artisan queue:listen             # Process queued jobs
php artisan pail                     # View logs
```

**Testing:**
```bash
cd backend
composer test                        # Runs PHPUnit tests
php artisan test                     # Alternative command
```

**Code Quality:**
```bash
cd backend
vendor/bin/pint                      # Laravel Pint code formatter
```

**Database:**
```bash
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed     # Fresh migration with seeding
php artisan db:seed                  # Run seeders
```

### Frontend

**Setup:**
```bash
cd frontend
npm install
```

**Development:**
```bash
cd frontend
npm run dev                          # Start Vite dev server
npm run build                        # Build for production
npm run preview                      # Preview production build
npm run lint                         # Run ESLint
```

## Important Patterns

### Authentication Flow
1. Frontend stores token in localStorage as `auth_token`
2. Standard API requests use Sanctum token in headers
3. Streaming endpoints (`/api/chat/stream`) accept token as query param since EventSource doesn't support custom headers
4. Custom `StreamAuth` middleware handles token extraction from query params

### Document Processing
- All document processing is asynchronous via Laravel queue
- Status field tracks progress: uploaded → processing → ready/failed
- Queue must be running (`php artisan queue:listen`) for documents to process
- Embeddings are 1536-dimensional vectors compatible with OpenAI embeddings

### Chat Streaming
- Backend: `ChatController::stream()` returns `StreamedResponse` with SSE format
- Sends JSON events: `{"delta": "text"}` for chunks, `{"done": true}` when complete
- Frontend: `createChatStream()` wraps EventSource API for React state updates

### Vector Search
- Uses pgvector extension (ensure `Schema::ensureVectorExtensionExists()` runs)
- `whereVectorSimilarTo()` query method with minimum similarity threshold
- Embeddings generated via Laravel AI's `Embeddings` facade

## Configuration Notes

- Queue connection: `database` (see `config/queue.php`)
- File storage: `local` disk, documents in `storage/app/documents/`
- Session driver: `database`
- Cache store: `database`
- Default database: SQLite (production may use PostgreSQL for pgvector)

## API Endpoints

**Public:**
- POST `/api/register` - User registration
- POST `/api/login` - User login

**Protected (Sanctum):**
- GET `/api/user` - Current user
- POST `/api/logout` - Logout
- GET `/api/documents` - List user documents
- POST `/api/documents` - Upload document (multipart/form-data: file, notebook_id)
- POST `/api/chat` - Non-streaming chat
- POST `/api/images/generate` - Generate images
- POST `/api/audio/transcribe` - Transcribe audio

**Protected (Custom StreamAuth):**
- GET/POST `/api/chat/stream` - SSE streaming chat (accepts token in query param)

## Dependencies

**Backend Key Packages:**
- `laravel/ai` - Laravel AI integration (embeddings, prompts)
- `laravel/sanctum` - API authentication
- `laravel/nightwatch` - Monitoring/debugging
- `smalot/pdfparser` - PDF text extraction

**Frontend Key Packages:**
- `react-router-dom` - Client-side routing
- `framer-motion` - Animations
- `axios` - HTTP client
- `@tailwindcss/vite` - Tailwind v4 integration
