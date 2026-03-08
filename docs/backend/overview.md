# Laravel NotebookLLM Backend Overview

## Project Overview

Laravel NotebookLLM is a modern AI-powered notebook application backend built with Laravel 12. The application provides chat functionality with AI agents, document management, and multimedia processing capabilities. It's designed as a full-stack solution with containerized deployment using Docker.

## Technology Stack

### Core Framework
- **Laravel 12** - Modern PHP framework with latest features
- **PHP 8.2+** - Minimum PHP version requirement

### AI Integration
- **Laravel AI (v0.2.5)** - Official Laravel AI package for AI operations
- **Multiple AI Providers Supported**:
  - OpenAI (default)
  - Anthropic Claude
  - Google Gemini
  - Azure OpenAI
  - Groq
  - DeepSeek
  - Mistral
  - Ollama (local)
  - And many more

### Authentication & Security
- **Laravel Sanctum (v4.0)** - API authentication with token-based security
- **Custom Stream Authentication** - Special middleware for streaming endpoints

### Database & Storage
- **PostgreSQL with pgvector** - Primary database with vector extension for embeddings
- **SQLite** - Default configuration (development)
- **Redis** - Caching and session storage
- **MongoDB** - Document storage (configured in Docker)

### Document Processing
- **smalot/pdfparser (v2.0)** - PDF parsing and extraction

### Development Tools
- **Laravel Tinker** - Interactive REPL
- **Laravel Pail** - Real-time log monitoring
- **PHPUnit** - Testing framework
- **Laravel Pint** - Code style fixing

## Architecture

### MVC Pattern
The application follows Laravel's Model-View-Controller pattern with additional layers:

- **Controllers** - Handle HTTP requests and responses
- **Models** - Data models and business logic
- **Middleware** - Request processing and authentication
- **Services** - Business logic and external integrations
- **Jobs** - Background processing

### AI Agent System
- **KnowledgeAgent** - Main AI agent for chat functionality
- **Streaming Support** - Real-time response streaming
- **Multi-provider Support** - Flexible AI provider configuration

### API Design
- **RESTful API** - Standard REST endpoints
- **Server-Sent Events (SSE)** - For real-time streaming
- **Token-based Authentication** - Secure API access

## Key Features

1. **AI Chat System** - Real-time conversations with AI agents
2. **Document Management** - Upload, store, and process documents
3. **Image Generation** - AI-powered image creation
4. **Audio Transcription** - Convert audio to text
5. **Streaming Responses** - Real-time AI response streaming
6. **User Authentication** - Secure user management
7. **Multi-tenant Support** - Notebook-based organization

## Configuration

### Environment Variables
The application uses a comprehensive `.env` configuration supporting:
- Database connections (PostgreSQL, SQLite, MySQL)
- AI provider API keys
- Redis configuration
- File storage settings
- Mail configuration
- Cache settings

### AI Provider Configuration
All major AI providers are pre-configured in `config/ai.php`:
- OpenAI, Anthropic, Gemini, Azure, Groq, DeepSeek, Mistral
- Support for embeddings, reranking, audio transcription
- Flexible provider switching

## Development Setup

### Local Development
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Docker Development
```bash
docker-compose up -d
```

The Docker setup includes:
- Backend service (Laravel)
- Frontend service (React/Vite)
- PostgreSQL with pgvector
- Redis
- MongoDB
- Chroma (vector database)

## API Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/user` - Get current user

### Chat
- `POST /api/chat` - Send chat message
- `GET/POST /api/chat/stream` - Stream chat responses

### Documents
- `GET /api/documents` - List documents
- `POST /api/documents` - Upload document

### Media Processing
- `POST /api/images/generate` - Generate images
- `POST /api/audio/transcribe` - Transcribe audio

## Security Features

1. **API Token Authentication** - Sanctum-based security
2. **Request Validation** - Comprehensive input validation
3. **Rate Limiting** - Built-in Laravel rate limiting
4. **CORS Protection** - Cross-origin request handling
5. **SQL Injection Prevention** - Eloquent ORM protection

## Logging & Monitoring

- **Comprehensive Logging** - Detailed request/response logging
- **Error Tracking** - Full exception logging with stack traces
- **Performance Monitoring** - Request timing and memory usage
- **Real-time Logs** - Laravel Pail for development monitoring

## Deployment

### Production Considerations
- Environment-specific configurations
- Database migrations and seeding
- Asset compilation and optimization
- Caching strategies
- Security hardening

### Container Deployment
- Multi-stage Docker builds
- Service orchestration with docker-compose
- Volume management for persistent data
- Network configuration for service communication
