# Laravel NotebookLLM Documentation

## Project Overview

Laravel NotebookLLM is a modern, full-stack AI-powered notebook application that combines the power of Laravel's backend with React's frontend. The application provides real-time chat capabilities with AI agents, document management, and multimedia processing in a containerized environment.

## Architecture

### Backend (Laravel 12)
- **Framework**: Laravel 12 with PHP 8.2+
- **AI Integration**: Laravel AI package with 13+ AI providers
- **Authentication**: Laravel Sanctum for API security
- **Database**: PostgreSQL with pgvector, Redis, MongoDB
- **Real-time**: Server-Sent Events for streaming responses

### Frontend (React 19)
- **Framework**: React 19 with TypeScript
- **Build Tool**: Vite for fast development and building
- **Styling**: TailwindCSS 4.2.1 with Framer Motion animations
- **Routing**: React Router DOM 7.0.2
- **HTTP Client**: Axios for API communication

### Infrastructure
- **Containerization**: Docker and Docker Compose
- **Services**: PostgreSQL, Redis, MongoDB, Chroma (vector database)
- **Development**: Hot reload, concurrent development servers
- **Production**: Optimized builds with caching strategies

## Documentation Structure

```
docs/
├── README.md                 # This file - Project overview
├── backend/                  # Backend documentation
│   ├── overview.md          # Backend architecture and tech stack
│   ├── structure.md         # Directory structure and file organization
│   └── code-explanation.md  # Detailed code explanations
└── frontend/                 # Frontend documentation
    ├── overview.md          # Frontend architecture and tech stack
    ├── structure.md         # Directory structure and file organization
    └── code-explanation.md  # Detailed code explanations
```

## Key Features

### AI-Powered Chat System
- **Real-time Streaming**: Server-Sent Events for instant AI responses
- **Multiple AI Providers**: OpenAI, Anthropic, Gemini, Groq, and more
- **Context Management**: Notebook-based conversation organization
- **Token Authentication**: Secure API access with Laravel Sanctum

### Document Management
- **File Upload**: Drag-and-drop document upload interface
- **Multiple Formats**: PDF, DOC, DOCX, TXT support
- **Processing Pipeline**: Automated document processing and indexing
- **Storage Integration**: Flexible storage backends

### User Experience
- **Modern UI**: Clean, responsive interface with TailwindCSS
- **Smooth Animations**: Framer Motion for fluid interactions
- **Mobile-First**: Responsive design for all devices
- **Type Safety**: Full TypeScript implementation

### Developer Experience
- **Hot Reload**: Instant development feedback
- **Type Safety**: TypeScript throughout the stack
- **Containerized**: Docker for consistent development environment
- **Comprehensive Logging**: Detailed request/response logging

## Getting Started

### Prerequisites
- Docker and Docker Compose
- Node.js 18+ (for local frontend development)
- PHP 8.2+ (for local backend development)

### Quick Start with Docker
```bash
# Clone the repository
git clone <repository-url>
cd laravel-notebookllm

# Start all services
docker-compose up -d

# Run database migrations
docker-compose exec backend php artisan migrate

# Access the application
# Frontend: http://localhost:5173
# Backend API: http://localhost:8000
```

### Local Development Setup

#### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

#### Frontend
```bash
cd frontend
npm install
npm run dev
```

## API Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/user` - Current user information

### Chat
- `POST /api/chat` - Send chat message
- `GET/POST /api/chat/stream` - Stream chat responses (SSE)

### Documents
- `GET /api/documents` - List user documents
- `POST /api/documents` - Upload new document

### Media Processing
- `POST /api/images/generate` - Generate AI images
- `POST /api/audio/transcribe` - Transcribe audio files

## Configuration

### Environment Variables

#### Backend (.env)
```env
# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=notebookllm
DB_USERNAME=postgres
DB_PASSWORD=secret

# AI Providers
OPENAI_API_KEY=your_openai_key
ANTHROPIC_API_KEY=your_anthropic_key
GEMINI_API_KEY=your_gemini_key

# Cache & Queue
REDIS_HOST=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### Frontend (.env)
```env
VITE_API_BASE_URL=http://localhost:8000/api
```

### AI Provider Configuration

The application supports 13+ AI providers configured in `config/ai.php`:

- **OpenAI** (default for text)
- **Anthropic** (Claude)
- **Google Gemini** (default for images)
- **Azure OpenAI**
- **Groq**
- **DeepSeek**
- **Mistral**
- **Ollama** (local)
- And more...

## Development Workflow

### Concurrent Development
```bash
# Start all services concurrently
composer run dev
```

This command runs:
- Laravel development server
- Queue worker
- Log monitoring (Laravel Pail)
- Frontend Vite dev server

### Testing
```bash
# Backend tests
cd backend
php artisan test

# Frontend tests (when implemented)
cd frontend
npm run test
```

### Code Quality
```bash
# Backend linting
cd backend
composer run lint

# Frontend linting
cd frontend
npm run lint
```

## Deployment

### Production Build
```bash
# Backend
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
npm run build
```

### Docker Production
```bash
# Build and deploy
docker-compose -f docker-compose.prod.yml up -d
```

## Security Features

### Authentication & Authorization
- **API Tokens**: Laravel Sanctum for secure API access
- **Token Management**: Secure token generation and revocation
- **Route Protection**: Middleware-based route protection
- **CORS**: Proper cross-origin resource sharing

### Data Protection
- **Input Validation**: Comprehensive request validation
- **SQL Injection Prevention**: Eloquent ORM protection
- **XSS Prevention**: Input sanitization and output escaping
- **CSRF Protection**: Built-in Laravel CSRF protection

## Monitoring & Logging

### Application Logging
- **Request Logging**: Detailed request/response logging
- **Error Tracking**: Comprehensive exception logging
- **Performance Monitoring**: Request timing and memory usage
- **Real-time Logs**: Laravel Pail for development monitoring

### Stream Monitoring
- **Connection Tracking**: Stream connection monitoring
- **Error Handling**: Stream error management
- **Performance Metrics**: Stream performance tracking

## Contributing

### Code Standards
- **PHP**: Follow PSR-12 coding standards
- **TypeScript**: Strict TypeScript with proper typing
- **React**: Functional components with hooks
- **CSS**: TailwindCSS utility classes

### Git Workflow
1. Create feature branch from main
2. Implement changes with proper testing
3. Ensure all linting passes
4. Submit pull request for review

## Support

### Documentation
- **Backend**: See `docs/backend/` for detailed backend documentation
- **Frontend**: See `docs/frontend/` for detailed frontend documentation

### Common Issues
- **Docker**: Ensure Docker and Docker Compose are properly installed
- **Permissions**: Check file permissions for storage directories
- **API Keys**: Ensure all required AI provider API keys are configured
- **Database**: Verify database connections and migrations

## Future Enhancements

### Planned Features
- **Advanced RAG**: Enhanced retrieval-augmented generation
- **Multi-modal AI**: Image and video processing capabilities
- **Real-time Collaboration**: Multi-user chat sessions
- **Advanced Analytics**: User behavior and performance analytics
- **Mobile App**: React Native mobile application

### Technical Improvements
- **Microservices**: Service decomposition for scalability
- **Advanced Caching**: Multi-layer caching strategy
- **Load Balancing**: Horizontal scaling capabilities
- **CDN Integration**: Global content delivery network

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contact

For support and questions, please refer to the project documentation or create an issue in the repository.
