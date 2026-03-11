# Frontend Code Explanation

Deep-dives into the most important frontend components and patterns.

---

## Redux Store & RTK Query

`src/store/index.ts`

```ts
export const store = configureStore({
  reducer: {
    [notebookApi.reducerPath]: notebookApi.reducer,
    [documentApi.reducerPath]: documentApi.reducer,
    [audioApi.reducerPath]:    audioApi.reducer,
  },
  middleware: (getDefault) =>
    getDefault().concat(notebookApi.middleware, documentApi.middleware, audioApi.middleware),
})
```

The store is minimal — no custom slices. All server state lives in RTK Query's generated reducers. The middleware chain is required for RTK Query's cache lifecycle (polling, invalidation, refetching).

### Why RTK Query for server state?

Traditional approach: `useEffect` → `axios.get()` → `useState`. Problems: loading/error states duplicated everywhere, stale data on navigation, manual cache invalidation.

RTK Query approach: declare endpoints once, get typed hooks (`useGetNotebooksQuery`, `useCreateNotebookMutation`). Cache invalidation is declarative via `providesTags` / `invalidatesTags` — creating a notebook automatically triggers a refetch of the notebook list.

### Tag-based invalidation example

```ts
// notebookApi.ts
getNotebooks: builder.query({
  query: () => '/notebooks',
  providesTags: ['Notebook'],
}),
createNotebook: builder.mutation({
  query: (body) => ({ url: '/notebooks', method: 'POST', body }),
  invalidatesTags: ['Notebook'],    // ← auto-refetch getNotebooks after create
}),
```

---

## Chat Streaming — EventSource Pattern

`src/lib/streaming.ts`

### Why not `fetch` with streaming?

`fetch` with `ReadableStream` works but requires manual parsing of SSE format, reconnection logic, and cleanup. The `EventSource` API handles reconnection automatically and provides a clean `onmessage` callback. The trade-off is that `EventSource` is GET-only, which is why the chat stream uses query parameters rather than a request body.

### `createChatStream()` internals

```ts
export function createChatStream(
  notebookId: number,
  message: string,
  token: string,
  callbacks: {
    onDelta: (text: string) => void
    onSources: (sources: Source[]) => void
    onDone: () => void
    onError: (msg: string) => void
  }
): () => void {   // returns cleanup function

  const params = new URLSearchParams({ token, notebook_id: String(notebookId), message })
  const es = new EventSource(`/api/chat/stream?${params}`)

  es.onmessage = (e) => {
    const data = JSON.parse(e.data)
    if (data.delta)   callbacks.onDelta(data.delta)
    if (data.sources) callbacks.onSources(data.sources)
    if (data.done)    { callbacks.onDone(); es.close() }
    if (data.error)   { callbacks.onError(data.error); es.close() }
  }

  es.onerror = () => { callbacks.onError('Connection lost'); es.close() }

  return () => es.close()  // caller invokes this on unmount
}
```

The caller (ChatPanel) stores the cleanup function in a ref and calls it in the `useEffect` cleanup.

---

## ChatPanel — Streaming State Machine

The component manages a mini state machine for the streaming lifecycle:

```
idle
  → (user submits) → streaming
      → (onDelta received) → streaming (append to buffer)
      → (onDone received) → idle (move buffer to messages)
      → (onError received) → error (show toast, reset)
```

State:
```ts
const [messages, setMessages] = useState<Message[]>([])
const [streamingContent, setStreamingContent] = useState('')
const [isStreaming, setIsStreaming] = useState(false)
const esCleanupRef = useRef<(() => void) | null>(null)
```

On component unmount (notebook change or page navigation), `useEffect` cleanup calls `esCleanupRef.current?.()` to close any open EventSource. This prevents stale stream callbacks from updating unmounted component state.

### Source citations

After a response completes, `metadata.sources` is an array of `{ title, document_id }` objects. ChatPanel renders these as small chip badges beneath each assistant message.

### Chat export

`lib/export.ts` → `exportAsMarkdown()` serializes the `messages` array into a `.md` file and triggers a download via a programmatic `<a download>` click.

---

## `useOptimistic` in NotebookSidebar

React 19's `useOptimistic` is purpose-built for this pattern:

```tsx
const [optimisticNotebooks, addOptimistic] = useOptimistic(
  notebooks ?? [],
  (state, newItem: Notebook) => [...state, newItem]
)

const handleCreate = async () => {
  const temp = { id: -1, title: 'New Notebook', created_at: new Date().toISOString() }
  addOptimistic(temp)                            // instant UI update
  await createNotebook({ title: temp.title })   // server call (may fail)
  // RTK Query invalidates cache → real notebook replaces temp
}
```

If the mutation throws, React reverts `optimisticNotebooks` to `notebooks`. No manual rollback code needed.

---

## `useActionState` in LoginPage

React 19's `useActionState` replaces the `useState` + `onSubmit` + loading flag pattern:

```tsx
const [state, formAction, isPending] = useActionState(
  async (_prev: unknown, formData: FormData) => {
    try {
      const res = await axios.post('/api/login', {
        email: formData.get('email'),
        password: formData.get('password'),
      })
      localStorage.setItem('auth_token', res.data.token)
      navigate('/')
      return { error: null }
    } catch {
      return { error: 'Invalid credentials' }
    }
  },
  { error: null }
)

// In JSX:
<form action={formAction}>
  <button disabled={isPending}>
    {isPending ? 'Signing in...' : 'Sign In'}
  </button>
</form>
```

`isPending` is true automatically while the async action is running. No `useState` needed for loading state.

---

## RTK Query's `baseQuery` with Auth

```ts
const baseQuery = fetchBaseQuery({
  baseUrl: '/api',
  prepareHeaders: (headers) => {
    const token = localStorage.getItem('auth_token')
    if (token) headers.set('Authorization', `Bearer ${token}`)
    return headers
  },
})
```

Every RTK Query request gets the auth header injected automatically. No Axios interceptors, no global defaults — colocated with the API slice.

---

## CVA-based UI Primitives

`src/components/ui/Button.tsx`

```ts
const buttonVariants = cva(
  'inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors',
  {
    variants: {
      variant: {
        default:  'bg-blue-600 text-white hover:bg-blue-700',
        outline:  'border border-slate-600 text-slate-200 hover:bg-slate-700',
        ghost:    'text-slate-400 hover:text-slate-200 hover:bg-slate-700',
        danger:   'bg-red-600 text-white hover:bg-red-700',
      },
      size: {
        sm: 'h-8 px-3',
        md: 'h-9 px-4',
        lg: 'h-10 px-6',
      },
    },
    defaultVariants: { variant: 'default', size: 'md' },
  }
)
```

`cva` generates class strings based on variant combinations. `cn()` merges them with any additional classes passed as props. This eliminates runtime style logic and keeps the component surface minimal.

---

## Document Upload — Polling

The document processing polling runs only when there are documents in a non-terminal state:

```ts
const hasProcessingDocs = documents?.some(
  d => d.status === 'processing' || d.status === 'uploaded'
)

useEffect(() => {
  if (!hasProcessingDocs) return
  const id = setInterval(() => refetch(), 3000)
  return () => clearInterval(id)
}, [hasProcessingDocs, refetch])
```

A WebSocket push (via Reverb) would be more elegant, but polling at 3-second intervals is reliable and easy to reason about. The interval stops automatically when all documents reach a terminal state.

---

## UsagePage — Recharts Integration

```tsx
<BarChart data={usage.byDate}>
  <XAxis dataKey="date" />
  <YAxis />
  <Tooltip />
  <Bar dataKey="prompt_tokens"     fill="#3b82f6" name="Input tokens" />
  <Bar dataKey="completion_tokens" fill="#8b5cf6" name="Output tokens" />
</BarChart>
```

Data comes from `GET /api/user/usage` which aggregates `ai_usage_logs` by date. The chart gives users visibility into their AI consumption — essential for cost transparency.
