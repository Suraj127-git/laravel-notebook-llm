# Backend Code Explanation

## Core Components

### 1. ChatController.php

**Purpose**: Handles all chat-related HTTP requests including regular and streaming responses.

**Key Methods**:

#### `chat(Request $request)`
- **Purpose**: Handles standard chat requests with immediate response
- **Validation**: Requires `notebook_id` and `message` fields
- **Process**:
  1. Logs request details (user, notebook, message, IP, user agent)
  2. Validates input parameters
  3. Creates KnowledgeAgent instance
  4. Processes message through AI agent
  5. Returns JSON response with AI answer
- **Error Handling**: Comprehensive exception logging and 500 error response

#### `stream(Request $request): StreamedResponse`
- **Purpose**: Handles real-time streaming chat responses
- **Authentication**: Supports both standard auth and token-based auth via query parameter
- **Process**:
  1. Token authentication check for EventSource compatibility
  2. Input validation
  3. Creates KnowledgeAgent
  4. Sets up Server-Sent Events stream
  5. Streams response chunks with progress logging
- **Response Headers**:
  - `Content-Type: text/event-stream`
  - `Cache-Control: no-cache, no-transform`
  - `X-Accel-Buffering: no`

**Logging**: Extensive logging at every step for debugging and monitoring

### 2. StreamAuth Middleware

**Purpose**: Custom authentication middleware for streaming endpoints.

**Features**:
- Supports standard Laravel authentication
- Token-based authentication via query parameter (for EventSource)
- Logs authentication attempts
- Returns 401 for unauthenticated requests

**Process**:
1. Check if user already authenticated → continue
2. Check for token in query parameters
3. Validate token with Sanctum
4. Set authenticated user if token valid
5. Return 401 if no valid authentication

### 3. KnowledgeAgent.php

**Purpose**: Main AI agent for processing chat requests.

**Constructor**:
- Accepts optional authenticated user
- Logs agent initialization with user details

#### `prompt(string $message)`
- **Purpose**: Process message and return immediate response
- **Current Implementation**: Returns placeholder response
- **Response Format**: Anonymous class with `content()` method
- **Logging**: Request and response details

#### `stream(string $message): \Generator`
- **Purpose**: Stream responses word by word
- **Process**:
  1. Create response text
  2. Split into words
  3. Yield each word as a chunk
  4. Progress logging every 5 words
- **Generator Pattern**: Uses PHP generators for memory efficiency

**Note**: Currently returns placeholder responses - AI integration pending

## Configuration Files

### AI Configuration (`config/ai.php`)

**Structure**:
- **Default Providers**: Different providers for different AI tasks
- **Caching Configuration**: Embedding caching settings
- **Provider Definitions**: 13+ AI providers with API key configuration

**Provider Support**:
- **Text Generation**: OpenAI, Anthropic, Gemini, Azure, Groq, DeepSeek, Mistral, Ollama, OpenRouter, xAI
- **Image Generation**: Gemini (default), others configurable
- **Audio Processing**: OpenAI (default)
- **Embeddings**: OpenAI (default)
- **Reranking**: Cohere (default)

**Environment Variables**:
Each provider requires corresponding `API_KEY` environment variable.

### Composer Configuration

**Key Dependencies**:
- **Laravel Framework 12.0**: Core framework
- **Laravel AI 0.2.5**: AI integration layer
- **Laravel Sanctum 4.0**: API authentication
- **smalot/pdfparser 2.0**: PDF processing

**Development Scripts**:
- **`setup`**: Complete project initialization
- **`dev`**: Concurrent development server (PHP, queue, logs, Vite)
- **`test`**: Test suite execution

## API Routes (`routes/api.php`)

### Authentication Routes (Guest)
```php
POST /api/register    // User registration
POST /api/login       // User login
```

### Protected Routes (Auth:sanctum)
```php
GET /api/user         // Current user info
POST /api/logout      // User logout
GET /api/documents    // List documents
POST /api/documents   // Upload document
POST /api/chat        // Send chat message
POST /api/images/generate  // Generate images
POST /api/audio/transcribe  // Transcribe audio
```

### Streaming Route (Custom Auth)
```php
GET/POST /api/chat/stream  // Stream chat responses
```

## Authentication System

### Laravel Sanctum Integration

**Features**:
- API token generation and management
- Token-based authentication for API endpoints
- Ability to revoke tokens
- Token expiration handling

**Usage in Controllers**:
- Standard middleware: `auth:sanctum`
- Custom middleware: `stream.auth` for streaming endpoints

### Token Authentication Flow

1. User logs in → receives API token
2. Token stored in frontend (localStorage)
3. Token sent with API requests (Authorization header)
4. Token validated by Sanctum middleware
5. User authenticated for request duration

## Error Handling & Logging

### Comprehensive Logging Strategy

**Request Logging**:
- User ID, notebook ID, message content
- IP address, user agent
- Authentication status
- Request timestamps

**Error Logging**:
- Full exception messages
- Stack traces
- Context information (user, request details)
- Error categorization

**Progress Logging**:
- Stream progress monitoring
- Chunk count tracking
- Performance metrics

### Error Response Format

**Standard Error**:
```json
{
  "error": "Human-readable error message"
}
```

**Stream Error**:
```json
{
  "error": "Stream processing failed"
}
```

## Database Configuration

### Environment Setup

**Default Configuration**:
- **Connection**: SQLite (development)
- **Docker**: PostgreSQL with pgvector
- **Cache**: Database or Redis
- **Sessions**: Database
- **Queue**: Database

### Migration System

**Purpose**: Database schema version control
**Location**: `database/migrations/`
**Features**: Rollback support, seeding capabilities

## Development Tools

### Laravel Artisan Commands

**Available Commands**:
- `php artisan serve` - Development server
- `php artisan migrate` - Database migrations
- `php artisan tinker` - Interactive REPL
- `php artisan queue:work` - Queue processing
- `php artisan pail` - Real-time log monitoring

### Testing Framework

**PHPUnit Integration**:
- Feature and unit tests
- Database testing with transactions
- API endpoint testing
- Model factory support

## Security Features

### Input Validation

**Request Validation**:
- Required field validation
- Type checking (string, integer, etc.)
- Custom validation rules
- Error message customization

### Authentication Security

**Token Management**:
- Secure token generation
- Token revocation capability
- Expiration handling
- Scope-based access control

### CORS & Headers

**Security Headers**:
- CORS configuration
- Content-Type validation
- Cache control for streaming
- Buffering prevention for real-time responses

## Performance Considerations

### Streaming Implementation

**Memory Efficiency**:
- PHP generators for memory-efficient streaming
- Chunk-by-chunk response delivery
- Immediate output flushing

**Real-time Features**:
- Server-Sent Events (SSE)
- No buffering for immediate delivery
- Progressive response rendering

### Caching Strategy

**AI Response Caching**:
- Configurable embedding caching
- Store-based caching (Redis, database)
- Cache invalidation strategies

## Future Enhancements

### AI Integration

**Pending Implementation**:
- Actual AI provider integration
- Vector database connectivity
- RAG (Retrieval-Augmented Generation) system
- Document embedding and search

### Advanced Features

**Planned Features**:
- Multi-modal AI capabilities
- Advanced document processing
- Real-time collaboration
- Advanced user management

## Code Quality

### Standards

**PHP Standards**:
- PSR-4 autoloading
- Type hints and return types
- Comprehensive documentation
- Error handling best practices

**Laravel Conventions**:
- MVC pattern adherence
- Service container usage
- Middleware pipeline
- Eloquent ORM patterns
