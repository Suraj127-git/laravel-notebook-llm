# Frontend Directory Structure

```
frontend/
├── index.html                              ← Vite entry point
├── vite.config.ts                          ← React plugin, Tailwind plugin, API proxy
├── tsconfig.json                           ← Project references root
├── tsconfig.app.json                       ← App TS config (verbatimModuleSyntax, strict)
├── tsconfig.node.json                      ← Node/Vite config TS
├── eslint.config.js                        ← ESLint 9 flat config
├── package.json
├── Dockerfile                              ← Multi-stage: dev | build | production (nginx)
└── src/
    ├── main.tsx                            ← ReactDOM.createRoot + Redux Provider + Router
    ├── App.tsx                             ← Route definitions + ProtectedRoute wiring
    ├── index.css                           ← Tailwind v4 @import
    │
    ├── store/
    │   ├── index.ts                        ← configureStore (notebookApi + documentApi)
    │   ├── hooks.ts                        ← useAppDispatch, useAppSelector (typed)
    │   └── api/
    │       ├── notebookApi.ts              ← RTK Query: getNotebooks, create, update, delete
    │       ├── documentApi.ts              ← RTK Query: getDocuments, upload, delete
    │       └── audioApi.ts                 ← RTK Query: generate audio overview
    │
    ├── pages/
    │   ├── LoginPage.tsx                   ← useActionState (React 19) + Sonner toasts
    │   ├── DashboardPage.tsx               ← main app shell, notebook + document layout
    │   ├── SettingsPage.tsx                ← profile, password change, delete account
    │   └── UsagePage.tsx                   ← AI usage stats + Recharts bar chart
    │
    ├── components/
    │   ├── ProtectedRoute.tsx              ← redirects to /login if no auth_token
    │   ├── NotebookSidebar.tsx             ← list + create (useOptimistic), select, delete
    │   ├── TopNav.tsx                      ← user menu, nav links
    │   ├── ChatPanel.tsx                   ← SSE streaming, history, citations, export
    │   ├── DocumentUpload.tsx              ← upload + 3s status polling
    │   ├── UsageModal.tsx                  ← quick usage popup
    │   └── studio/
    │       └── AudioOverviewTab.tsx        ← generate + stream + play audio overview
    │
    ├── components/ui/                      ← Shadcn/ui-style primitives
    │   ├── Button.tsx                      ← CVA variants: default, outline, ghost, danger
    │   └── Input.tsx                       ← CVA variants: default, error
    │
    └── lib/
        ├── streaming.ts                    ← createChatStream() EventSource wrapper
        ├── export.ts                       ← exportAsMarkdown() chat export
        └── utils.ts                        ← cn() (clsx + tailwind-merge)
```

---

## Key File Details

### `src/main.tsx`

```tsx
<Provider store={store}>
  <BrowserRouter>
    <App />
  </BrowserRouter>
</Provider>
```

Redux `Provider` wraps the entire tree so RTK Query hooks are available everywhere.

### `src/App.tsx`

Route map:
```
/login          → LoginPage (public)
/               → DashboardPage (protected)
/settings       → SettingsPage (protected)
/usage          → UsagePage (protected)
```

`ProtectedRoute` checks `localStorage.getItem('auth_token')`. If null, renders `<Navigate to="/login" />`.

### `src/store/api/notebookApi.ts`

RTK Query `createApi` with `baseQuery: fetchBaseQuery({ baseUrl: '/api', ... })`. Endpoints:
- `getNotebooks` → `GET /api/notebooks` (provides tag `Notebook`)
- `createNotebook` → `POST /api/notebooks` (invalidates `Notebook`)
- `updateNotebook` → `PUT /api/notebooks/{id}` (invalidates `Notebook`)
- `deleteNotebook` → `DELETE /api/notebooks/{id}` (invalidates `Notebook`)

The `prepareHeaders` callback reads `auth_token` from localStorage and injects `Authorization: Bearer <token>` on every request.

### `src/store/api/documentApi.ts`

Same pattern as notebookApi. Upload uses `FormData` with `content-type: multipart/form-data`. Provides/invalidates `Document` tags scoped to `notebookId`.

### `src/pages/LoginPage.tsx`

Uses React 19's `useActionState` hook for form submission, which provides a pending state and result without external form libraries. Error messages displayed inline; success triggers `localStorage.setItem('auth_token', token)` then `navigate('/')`.

### `src/components/ChatPanel.tsx`

State shape:
```ts
messages: Array<{role, content, sources?, id}>
streamingContent: string          // buffer while streaming
isStreaming: boolean
suggestedQuestions: string[]
```

On submit:
1. Append user message to `messages` optimistically
2. Call `createChatStream(notebookId, message, token, callbacks)` from `lib/streaming.ts`
3. `onDelta`: append to `streamingContent`
4. `onSources`: store on last message
5. `onDone`: move `streamingContent` into `messages`, clear buffer
6. `onError`: show Sonner toast, clear streaming state

### `src/lib/streaming.ts`

`createChatStream()` constructs the EventSource URL with token + params, attaches `onmessage` and `onerror` handlers, and returns a cleanup function that closes the EventSource. Callers invoke the cleanup in a `useEffect` return or on component unmount.

### `src/components/NotebookSidebar.tsx`

`useOptimistic` usage:
```ts
const [optimisticNotebooks, addOptimistic] = useOptimistic(notebooks, (state, newNotebook) => [...state, newNotebook])
```

When the user clicks "New Notebook", the UI adds a placeholder instantly. If the server request fails, React automatically reverts to the previous state.

### `src/components/DocumentUpload.tsx`

Polling logic:
```ts
useEffect(() => {
  if (!hasProcessingDocs) return
  const interval = setInterval(() => refetch(), 3000)
  return () => clearInterval(interval)
}, [hasProcessingDocs])
```

`hasProcessingDocs` is derived from RTK Query cache — no manual state needed. When the last document transitions to `ready`, the effect cleanup fires and polling stops.

### `src/lib/utils.ts`

```ts
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
```

Used everywhere for conditional class application without specificity conflicts.

---

## Vite Configuration

```ts
// vite.config.ts
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',   // required for Docker
    port: 5173,
    proxy: {
      '/api':     'http://backend:8000',
      '/sanctum': 'http://backend:8000',
    },
  },
})
```

The proxy eliminates CORS issues during development — all requests go through `localhost:5173` from the browser's perspective.

---

## TypeScript Configuration Highlights

`tsconfig.app.json` key settings:
```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "verbatimModuleSyntax": true,
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true
  }
}
```

`verbatimModuleSyntax: true` is the strict TypeScript setting that enforces `import type` for type-only imports. This prevents Vite from emitting runtime `import` statements for types, which would cause errors in some bundler configurations.
