# Backend Directory Structure

## Root Directory Files

### Configuration Files
- **`composer.json`** - PHP dependencies and project configuration
- **`.env.example`** - Environment variable template
- **`.gitignore`** - Git ignore patterns
- **`.gitattributes`** - Git file attributes
- **`.editorconfig`** - Editor configuration for consistent coding style
- **`phpunit.xml`** - PHPUnit testing configuration
- **`phpunit.xml`** - PHPUnit testing configuration

### Build & Deployment
- **`Dockerfile`** - Docker container configuration
- **`docker-entrypoint.sh`** - Container startup script
- **`artisan`** - Laravel command-line interface

### Documentation
- **`README.md`** - Project documentation and setup instructions

## Application Structure (`app/`)

### AI Layer (`app/Ai/`)
- **`Agents/`** - AI agent implementations
  - **`KnowledgeAgent.php`** - Main AI chat agent with streaming support

### HTTP Layer (`app/Http/`)
- **`Controllers/`** - HTTP request handlers
  - **`ChatController.php`** - Handles chat requests and streaming
  - **`AuthController.php`** - User authentication
  - **`DocumentController.php`** - Document management
  - **`ImageController.php`** - Image generation
  - **`AudioController.php`** - Audio transcription
- **`Middleware/`** - HTTP middleware
  - **`StreamAuth.php`** - Custom authentication for streaming endpoints

### Models (`app/Models/`)
- User and data model definitions (Eloquent ORM)

### Jobs (`app/Jobs/`)
- Background job processing

### Providers (`app/Providers/`)
- Service providers for dependency injection

### Services (`app/Services/`)
- Business logic and external service integrations

## Configuration (`config/`)

### Application Configuration
- **`app.php`** - Core application settings
- **`ai.php`** - AI provider configurations and settings
- **`auth.php`** - Authentication configuration
- **`database.php`** - Database connections and settings
- **`cache.php`** - Caching configuration
- **`queue.php`** - Queue system configuration
- **`filesystems.php`** - File storage configuration
- **`mail.php`** - Email configuration
- **`services.php`** - External service configurations

## Database (`database/`)

### Migrations (`database/migrations/`)
- Database schema definitions and version control

### Seeders (`database/seeders/`)
- Database seeding for development and testing

### Factories (`database/factories/`)
- Model factories for testing

## Routing (`routes/`)

### API Routes (`routes/api.php`)
- RESTful API endpoints definition
- Authentication middleware configuration
- Route grouping and protection

### Web Routes (`routes/web.php`)
- Web application routes (if any)

### Console Routes (`routes/console.php`)
- Artisan command definitions

## Public Assets (`public/`)

### Static Files
- **`index.php`** - Application entry point
- **`css/`** - Stylesheets
- **`js/`** - JavaScript files
- **`images/`** - Image assets

## Resources (`resources/`)

### Views (`resources/views/`)
- Blade templates (if any)

### Assets (`resources/assets/`)
- Frontend asset source files

## Storage (`storage/`)

### Application Storage
- **`app/`** - Application-specific files
- **`framework/`** - Framework cache and logs
- **`logs/`** - Application log files
- **`framework/cache/`** - Cache files
- **`framework/views/`** - Compiled view files

## Bootstrap (`bootstrap/`)

### Application Bootstrap
- **`app.php`** - Application bootstrap configuration
- **`cache/`** - Bootstrap cache files
- **`providers.php`** - Service provider registration

## Testing (`tests/`)

### Test Suites
- **`Feature/`** - Feature tests
- **`Unit/`** - Unit tests
- **`TestCase.php`** - Base test case class

## Vendor (`vendor/`)

### Composer Dependencies
- Third-party PHP packages and libraries
- Managed by Composer package manager

## Stubs (`stubs/`)

### Code Stubs
- Template files for code generation

## Key Configuration Details

### AI Configuration (`config/ai.php`)
- **Default Providers**: OpenAI for text, Gemini for images, OpenAI for audio
- **Provider Support**: Anthropic, Azure, Cohere, DeepSeek, Eleven, Gemini, Groq, Jina, Mistral, Ollama, OpenAI, OpenRouter, VoyageAI, xAI
- **Caching**: Configurable caching for embeddings
- **Multi-modal**: Support for text, image, audio, and embeddings

### Environment Variables (`.env.example`)
- **Database**: SQLite (default), PostgreSQL, MySQL support
- **Authentication**: Sanctum token configuration
- **Caching**: Redis, database, file cache options
- **AI Providers**: API keys for all supported providers
- **File Storage**: Local, S3, and other filesystem options

### Composer Scripts
- **`setup`** - Complete project setup
- **`dev`** - Development server with concurrent processes
- **`test`** - Run test suite
- **`post-autoload-dump`** - Package discovery and optimization

## Development Workflow

### Local Development
1. Install dependencies: `composer install`
2. Configure environment: `cp .env.example .env`
3. Generate application key: `php artisan key:generate`
4. Run migrations: `php artisan migrate`
5. Start development server: `php artisan serve`

### Docker Development
1. Build containers: `docker-compose build`
2. Start services: `docker-compose up -d`
3. Run migrations: `docker-compose exec backend php artisan migrate`

### Production Deployment
1. Optimize autoloader: `composer install --optimize-autoloader --no-dev`
2. Cache configuration: `php artisan config:cache`
3. Cache routes: `php artisan route:cache`
4. Optimize views: `php artisan view:cache`
