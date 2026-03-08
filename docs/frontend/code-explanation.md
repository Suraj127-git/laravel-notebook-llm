# Frontend Code Explanation

## Core Components

### 1. App.tsx - Root Component

**Purpose**: Main application component with routing configuration and authentication setup.

```typescript
import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './pages/LoginPage'
import { DashboardPage } from './pages/DashboardPage'
import { ProtectedRoute } from './components/ProtectedRoute'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/*"
        element={
          <ProtectedRoute>
            <DashboardPage />
          </ProtectedRoute>
        }
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
```

**Key Features**:
- **React Router Setup**: Configures application routing
- **Protected Routes**: Main dashboard wrapped in authentication guard
- **Catch-all Route**: Redirects unknown routes to home
- **Lazy Loading**: Efficient component loading (potential improvement)

**Routing Strategy**:
- `/login` - Public login page
- `/*` - Protected dashboard (catches all authenticated routes)
- `*` - Fallback redirect to home

### 2. ProtectedRoute.tsx - Authentication Guard

**Purpose**: Protects routes that require authentication.

```typescript
import { Navigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth' // Inferred hook

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth()
  
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }
  
  return <>{children}</>
}
```

**Features**:
- **Authentication Check**: Verifies user authentication status
- **Automatic Redirect**: Unauthenticated users redirected to login
- **Children Rendering**: Authenticated users see protected content
- **Replace Navigation**: Prevents back button issues

## Pages

### 3. LoginPage.tsx - Authentication Interface

**Purpose**: User login and authentication interface.

**Key Features**:
- **Form Validation**: Email and password validation
- **Error Handling**: Display authentication errors
- **Loading States**: Visual feedback during authentication
- **Token Storage**: Secure token management
- **Auto-redirect**: Redirect to dashboard after successful login

**State Management**:
```typescript
const [email, setEmail] = useState('')
const [password, setPassword] = useState('')
const [error, setError] = useState('')
const [loading, setLoading] = useState(false)
```

**Authentication Flow**:
1. User enters credentials
2. Form validation on submission
3. API call to backend `/api/login`
4. Token storage in localStorage
5. Redirect to dashboard on success

**Error Handling**:
- Network errors
- Invalid credentials
- Server errors
- Validation feedback

### 4. DashboardPage.tsx - Main Application Interface

**Purpose**: Primary application interface after authentication.

**Features**:
- **Chat Integration**: Embeds ChatPanel component
- **Document Management**: Document upload and listing
- **User Interface**: Navigation and user information
- **Responsive Design**: Mobile-friendly layout

**Component Structure**:
```typescript
export function DashboardPage() {
  return (
    <div className="dashboard">
      {/* Navigation Header */}
      {/* Main Content Area */}
      <ChatPanel />
      <DocumentUpload />
      {/* Additional Features */}
    </div>
  )
}
```

## Components

### 5. ChatPanel.tsx - Interactive Chat Interface

**Purpose**: Real-time chat interface with AI agents.

**Key Features**:
- **Message Input**: User message composition
- **Message History**: Conversation thread display
- **Real-time Streaming**: Live AI response streaming
- **Notebook Integration**: Chat sessions by notebook
- **Error Handling**: Stream error management

**State Management**:
```typescript
const [messages, setMessages] = useState<Message[]>([])
const [inputValue, setInputValue] = useState('')
const [isStreaming, setIsStreaming] = useState(false)
const [notebookId, setNotebookId] = useState('')
```

**Streaming Implementation**:
```typescript
const handleSendMessage = async (message: string) => {
  setIsStreaming(true)
  
  createChatStream(
    notebookId,
    message,
    (delta) => {
      // Handle streaming chunks
      updateStreamingMessage(delta)
    },
    () => {
      // Stream completed
      setIsStreaming(false)
    },
    (error) => {
      // Handle stream errors
      setIsStreaming(false)
      showError(error)
    }
  )
}
```

**UI Features**:
- **Auto-scroll**: Auto-scroll to latest messages
- **Typing Indicators**: Show AI is responding
- **Message Formatting**: Rich text display
- **Timestamp Display**: Message timestamps

### 6. DocumentUpload.tsx - File Management

**Purpose**: Document upload and management interface.

**Features**:
- **File Selection**: Drag-and-drop or click to upload
- **File Validation**: Type and size validation
- **Upload Progress**: Visual progress indicators
- **Success/Error Feedback**: Upload status feedback

**Upload Process**:
```typescript
const handleFileUpload = async (file: File) => {
  const formData = new FormData()
  formData.append('document', file)
  
  try {
    setUploading(true)
    const response = await axios.post('/api/documents', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'Authorization': `Bearer ${token}`
      },
      onUploadProgress: (progressEvent) => {
        const progress = Math.round(
          (progressEvent.loaded * 100) / progressEvent.total!
        )
        setUploadProgress(progress)
      }
    })
    
    // Handle success
    showSuccessMessage('Document uploaded successfully')
  } catch (error) {
    // Handle error
    showErrorMessage('Upload failed')
  } finally {
    setUploading(false)
  }
}
```

**Validation Rules**:
- **File Types**: PDF, DOC, DOCX, TXT (configurable)
- **File Size**: Maximum file size limits
- **File Names**: Sanitization and validation

## Custom Hooks

### 7. useAuth Hook (Inferred)

**Purpose**: Authentication state management.

**Features**:
- **Authentication Status**: User login state
- **Token Management**: Token storage and retrieval
- **User Information**: User data management
- **Login/Logout Functions**: Authentication actions

**Implementation**:
```typescript
export function useAuth() {
  const [isAuthenticated, setIsAuthenticated] = useState(false)
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      // Validate token with backend
      validateToken(token)
    } else {
      setLoading(false)
    }
  }, [])

  const login = async (credentials: LoginCredentials) => {
    try {
      const response = await axios.post('/api/login', credentials)
      const { token, user } = response.data
      
      localStorage.setItem('auth_token', token)
      setUser(user)
      setIsAuthenticated(true)
      
      return { success: true }
    } catch (error) {
      return { success: false, error }
    }
  }

  const logout = async () => {
    try {
      await axios.post('/api/logout', {}, {
        headers: { 'Authorization': `Bearer ${getToken()}` }
      })
    } finally {
      localStorage.removeItem('auth_token')
      setUser(null)
      setIsAuthenticated(false)
    }
  }

  return {
    isAuthenticated,
    user,
    loading,
    login,
    logout
  }
}
```

## Utilities

### 8. streaming.ts - Real-time Streaming

**Purpose**: Server-Sent Events implementation for real-time AI responses.

**Core Function**:
```typescript
export function createChatStream(
  notebookId: string,
  message: string,
  onDelta: (delta: string) => void,
  onDone: () => void,
  onError: (err: any) => void,
) {
  const token = localStorage.getItem('auth_token')
  const params = new URLSearchParams({ 
    notebook_id: notebookId, 
    message,
    ...(token && { token })
  })

  const eventSource = new EventSource(`/api/chat/stream?${params.toString()}`, {
    withCredentials: true,
  } as any)

  eventSource.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data)
      if (data.delta) {
        onDelta(data.delta)
      }
      if (data.done) {
        eventSource.close()
        onDone()
      }
    } catch (e) {
      console.error(e)
    }
  }

  eventSource.onerror = (err) => {
    eventSource.close()
    onError(err)
  }

  return eventSource
}
```

**Features**:
- **Token Authentication**: Includes auth token for secure streaming
- **EventSource API**: Native browser SSE support
- **Error Handling**: Comprehensive error management
- **Connection Cleanup**: Automatic connection closure
- **Callback Pattern**: Flexible callback-based API

**Stream Data Format**:
```typescript
// Streaming chunk
{ "delta": "Hello" }

// Stream completion
{ "done": true }

// Error response
{ "error": "Stream failed" }
```

## Configuration Files

### 9. vite.config.ts - Build Configuration

**Purpose**: Vite build tool and development server configuration.

```typescript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/api': 'http://backend:8000',
      '/sanctum': 'http://backend:8000',
    },
  },
})
```

**Plugin Configuration**:
- **React Plugin**: Fast Refresh and HMR support
- **TailwindCSS Plugin**: CSS framework integration

**Proxy Configuration**:
- **API Proxy**: `/api` routes forwarded to backend
- **Sanctum Proxy**: CSRF token handling
- **Development Only**: Only active in development mode

### 10. package.json - Dependencies and Scripts

**Key Dependencies**:
- **React 19.2.0**: Latest React with concurrent features
- **TypeScript 5.9.3**: Type-safe development
- **Vite 7.3.1**: Fast build tool
- **TailwindCSS 4.2.1**: Modern CSS framework
- **Framer Motion 12.4.0**: Animation library

**Development Scripts**:
```json
{
  "dev": "vite",                    // Development server
  "build": "tsc -b && vite build", // Production build
  "lint": "eslint .",               // Code quality
  "preview": "vite preview"         // Production preview
}
```

## Styling Approach

### TailwindCSS Integration

**Configuration**:
- **Utility Classes**: Rapid UI development
- **Responsive Design**: Mobile-first approach
- **Custom Theme**: Brand-specific customization
- **Component Variants**: Consistent design patterns

**Example Usage**:
```typescript
<div className="flex flex-col h-screen bg-gray-50">
  <header className="bg-white shadow-sm border-b">
    <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      {/* Navigation content */}
    </nav>
  </header>
  
  <main className="flex-1 overflow-hidden">
    {/* Main content */}
  </main>
</div>
```

### Animation with Framer Motion

**Purpose**: Smooth animations and transitions.

**Example Implementation**:
```typescript
import { motion, AnimatePresence } from 'framer-motion'

const MessageList = ({ messages }) => (
  <AnimatePresence>
    {messages.map((message) => (
      <motion.div
        key={message.id}
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        exit={{ opacity: 0, y: -20 }}
        transition={{ duration: 0.2 }}
      >
        {/* Message content */}
      </motion.div>
    ))}
  </AnimatePresence>
)
```

## Error Handling Strategy

### Global Error Handling

**Error Boundaries**:
```typescript
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false }
  }

  static getDerivedStateFromError(error) {
    return { hasError: true }
  }

  componentDidCatch(error, errorInfo) {
    console.error('Error caught by boundary:', error, errorInfo)
  }

  render() {
    if (this.state.hasError) {
      return <ErrorFallback />
    }

    return this.props.children
  }
}
```

### API Error Handling

**Axios Interceptors**:
```typescript
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Handle authentication errors
      logout()
      navigate('/login')
    }
    
    return Promise.reject(error)
  }
)
```

## Performance Optimizations

### React Optimizations

**Component Memoization**:
```typescript
const Message = React.memo(({ message, onEdit }) => {
  return (
    <div className="message">
      {/* Message content */}
    </div>
  )
})
```

**Hook Optimizations**:
```typescript
const expensiveValue = useMemo(() => {
  return computeExpensiveValue(data)
}, [data])

const handleEvent = useCallback((event) => {
  // Event handler logic
}, [dependency])
```

### Bundle Optimization

**Code Splitting**:
```typescript
const LazyComponent = React.lazy(() => import('./LazyComponent'))

// Usage with Suspense
<Suspense fallback={<Loading />}>
  <LazyComponent />
</Suspense>
```

## Development Experience

### Hot Module Replacement

**Vite HMR**:
- **Instant Updates**: No full page reloads
- **State Preservation**: Component state maintained
- **Fast Refresh**: React Fast Refresh integration

### TypeScript Integration

**Type Safety**:
- **Component Props**: Strict prop typing
- **API Responses**: Response type definitions
- **State Management**: Typed state and actions
- **Error Handling**: Typed error objects

**Example Types**:
```typescript
interface Message {
  id: string
  content: string
  role: 'user' | 'assistant'
  timestamp: Date
  notebookId: string
}

interface User {
  id: string
  email: string
  name: string
}
```

This frontend provides a modern, type-safe, and performant interface for the Laravel NotebookLLM application with real-time capabilities and excellent developer experience.
