# Frontend Directory Structure

## Root Directory Files

### Configuration Files
- **`package.json`** - Node.js dependencies and project configuration
- **`package-lock.json`** - Locked dependency versions
- **`tsconfig.json`** - TypeScript configuration
- **`tsconfig.app.json`** - Application-specific TypeScript config
- **`tsconfig.node.json`** - Node.js TypeScript configuration
- **`vite.config.ts`** - Vite build tool configuration
- **`eslint.config.js`** - ESLint linting configuration
- **`.gitignore`** - Git ignore patterns

### Build & Deployment
- **`Dockerfile`** - Docker container configuration
- **`index.html`** - Application entry point HTML

### Documentation
- **`README.md`** - Project documentation and setup instructions

## Source Code (`src/`)

### Application Entry Point
- **`main.tsx`** - React application bootstrap and rendering
- **`App.tsx`** - Root component with routing configuration

### Styling
- **`index.css`** - Global CSS styles and TailwindCSS imports
- **`App.css`** - Application-specific styles

### Components (`src/components/`)

#### ChatPanel.tsx
**Purpose**: Interactive chat interface component
**Features**:
- Message input and display
- Real-time streaming responses
- Message history management
- Notebook selection integration
**Size**: 2,930 bytes

#### DocumentUpload.tsx
**Purpose**: Document upload interface
**Features**:
- File selection and upload
- Upload progress tracking
- File validation
- Success/error feedback
**Size**: 2,261 bytes

#### ProtectedRoute.tsx
**Purpose**: Authentication guard component
**Features**:
- Authentication status checking
- Redirect logic for unauthenticated users
- Loading state handling
- Children component rendering
**Size**: 476 bytes

### Pages (`src/pages/`)

#### DashboardPage.tsx
**Purpose**: Main application dashboard
**Features**:
- Chat interface integration
- Document management
- User information display
- Navigation components
**Size**: 1,274 bytes

#### LoginPage.tsx
**Purpose**: User authentication interface
**Features**:
- Login form with email/password
- Form validation and error handling
- Loading states during authentication
- Redirect after successful login
**Size**: 4,648 bytes

### Hooks (`src/hooks/`)
**Purpose**: Custom React hooks for logic reuse
**Contents**: Custom hooks for authentication, API calls, and state management

### Utilities (`src/lib/`)

#### streaming.ts
**Purpose**: Real-time streaming functionality
**Features**:
- EventSource management for Server-Sent Events
- Stream data handling and parsing
- Error management and cleanup
- Token authentication for streams
**Size**: 1,042 bytes

#### Additional utilities (inferred)
- API client configuration
- Authentication helpers
- Type definitions
- Constants and configuration

### Assets (`src/assets/`)
**Purpose**: Static assets and resources
**Contents**: Images, icons, fonts, and other static files

## Configuration Details

### Package.json Analysis

#### Dependencies
```json
{
  "axios": "^1.7.9",           // HTTP client
  "framer-motion": "^12.4.0",  // Animation library
  "react": "^19.2.0",          // React core
  "react-dom": "^19.2.0",      // React DOM renderer
  "react-router-dom": "^7.0.2" // Routing library
}
```

#### Development Dependencies
```json
{
  "@eslint/js": "^9.39.1",           // ESLint configuration
  "@tailwindcss/vite": "^4.2.1",     // TailwindCSS Vite plugin
  "@types/node": "^24.10.1",         // Node.js type definitions
  "@types/react": "^19.2.7",         // React type definitions
  "@types/react-dom": "^19.2.3",     // React DOM types
  "@vitejs/plugin-react": "^5.1.1",  // Vite React plugin
  "autoprefixer": "^10.4.27",        // CSS autoprefixer
  "eslint": "^9.39.1",               // Code linting
  "eslint-plugin-react-hooks": "^7.0.1", // React hooks linting
  "eslint-plugin-react-refresh": "^0.4.24", // React refresh linting
  "globals": "^16.5.0",              // Global variables for ESLint
  "postcss": "^8.5.8",               // CSS processing
  "tailwindcss": "^4.2.1",           // CSS framework
  "typescript": "~5.9.3",            // TypeScript compiler
  "typescript-eslint": "^8.48.0",    // TypeScript ESLint
  "vite": "^7.3.1"                   // Build tool
}
```

#### Scripts
```json
{
  "dev": "vite",                    // Development server
  "build": "tsc -b && vite build", // Production build
  "lint": "eslint .",               // Code linting
  "preview": "vite preview"         // Preview production build
}
```

### Vite Configuration (`vite.config.ts`)

#### Plugin Configuration
```typescript
export default defineConfig({
  plugins: [
    react(),      // React Fast Refresh
    tailwindcss() // TailwindCSS integration
  ],
})
```

#### Development Server Proxy
```typescript
server: {
  proxy: {
    '/api': 'http://backend:8000',      // API proxy to backend
    '/sanctum': 'http://backend:8000',  // Sanctum CSRF proxy
  },
}
```

**Purpose**: Forward API requests to backend during development
**Benefits**: Avoid CORS issues, seamless development experience

### TypeScript Configuration

#### tsconfig.json (Root)
- **Base TypeScript configuration**
- **Project references** to app and node configs
- **Compiler options** for overall project

#### tsconfig.app.json (Application)
- **React-specific TypeScript configuration**
- **JSX support** for React components
- **Module resolution** for modern imports
- **Strict type checking** enabled

#### tsconfig.node.json (Node.js)
- **Node.js TypeScript configuration**
- **Vite configuration** type support
- **ES modules** support

### ESLint Configuration (`eslint.config.js`)

#### Linting Rules
- **React Hooks** rules for proper hook usage
- **React Refresh** rules for Fast Refresh compatibility
- **TypeScript** integration for type checking
- **Modern JavaScript** standards

#### Global Configuration
- **Browser environment** globals
- **ES2022** language features
- **Module** system support

## Build Process

### Development Build
```bash
npm run dev
```
- **Vite dev server** with Hot Module Replacement
- **TypeScript compilation** with error checking
- **Proxy setup** for API requests
- **Fast Refresh** for React components

### Production Build
```bash
npm run build
```
**Process**:
1. **TypeScript compilation** (`tsc -b`)
2. **Vite bundling** (`vite build`)
3. **Asset optimization** and minification
4. **Code splitting** for optimal loading

### Build Output
- **dist/** directory - Production-ready files
- **Optimized assets** - Minified CSS and JavaScript
- **Source maps** - For debugging (development)
- **Asset hashing** - Cache busting

## Development Workflow

### File Organization Strategy

#### Component-Based Structure
- **Pages** - Main application screens
- **Components** - Reusable UI components
- **Hooks** - Custom React hooks
- **Lib** - Utilities and configurations
- **Assets** - Static resources

#### Import Patterns
```typescript
// React imports
import { useState, useEffect } from 'react'

// Component imports
import { ChatPanel } from './components/ChatPanel'

// Utility imports
import { createChatStream } from './lib/streaming'
```

### Code Conventions

#### TypeScript Usage
- **Strict typing** for all components
- **Interface definitions** for props and state
- **Generic types** for reusable components
- **Enum usage** for constants

#### React Patterns
- **Functional components** with hooks
- **Props destructuring** for clean interfaces
- **Custom hooks** for logic reuse
- **Error boundaries** for error handling

## Asset Management

### Static Assets
- **Images** stored in `src/assets/`
- **Icons** and graphics
- **Fonts** and typography
- **CSS files** for custom styles

### Build Assets
- **Automatic optimization** during build
- **Hashing** for cache busting
- **Compression** for smaller file sizes
- **Lazy loading** for better performance

## Testing Structure (Planned)

### Test Organization
```
src/
├── __tests__/          # Test files
├── components/         # Component tests
├── pages/             # Page tests
├── hooks/             # Hook tests
└── utils/             # Utility tests
```

### Testing Tools
- **React Testing Library** - Component testing
- **Jest** - Test runner
- **Vitest** - Vite-integrated testing
- **Cypress** - E2E testing (optional)

## Performance Considerations

### Bundle Optimization
- **Code splitting** with React.lazy
- **Tree shaking** for unused code
- **Dynamic imports** for heavy dependencies
- **Bundle analysis** for size monitoring

### Runtime Performance
- **React.memo** for component memoization
- **useCallback/useMemo** for expensive computations
- **Virtual scrolling** for large lists
- **Debouncing** for user inputs

### Network Performance
- **API request optimization**
- **Response caching** strategies
- **Image optimization** and lazy loading
- **Service worker** for offline support

## Security Configuration

### Content Security Policy
- **Script sources** properly defined
- **Style sources** for CSS loading
- **Image sources** for asset loading
- **Connect sources** for API calls

### Development Security
- **HTTPS** in development (optional)
- **Environment variables** for sensitive data
- **Dependency scanning** for vulnerabilities
- **Secure defaults** for all configurations

This structure provides a modern, scalable foundation for the React frontend with proper separation of concerns, type safety, and development tooling.
