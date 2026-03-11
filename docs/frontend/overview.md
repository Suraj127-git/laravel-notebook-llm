# Frontend Overview

## Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Framework | React | 19.x |
| Language | TypeScript | 5.9 |
| Build | Vite | 7.x |
| State | Redux Toolkit + RTK Query | 2.x |
| Styling | Tailwind CSS | v4 |
| Animations | Framer Motion | 12.x |
| UI Primitives | Shadcn/ui (Radix + CVA) | — |
| Charts | Recharts | — |
| HTTP | Axios | 1.7 |
| Toasts | Sonner | 1.x |
| Router | React Router DOM | 7.x |
| Linting | ESLint 9 + TypeScript ESLint | — |

---

## Application Architecture

### State Management

State is split cleanly between server state (RTK Query) and UI state (Redux slices):

```
Redux Store
├── notebookApi (RTK Query)    ← notebooks CRUD
│   ├── useGetNotebooksQuery
│   ├── useCreateNotebookMutation
│   ├── useUpdateNotebookMutation
│   └── useDeleteNotebookMutation
├── documentApi (RTK Query)    ← documents per notebook
│   ├── useGetDocumentsQuery
│   ├── useUploadDocumentMutation
│   └── useDeleteDocumentMutation
└── (local state in components) ← chat messages, stream buffer, UI toggles
```

RTK Query handles cache invalidation automatically — uploading a document invalidates `getDocuments`, creating a notebook invalidates `getNotebooks`. No manual refetch calls needed.

### Streaming Architecture

The chat streaming path bypasses RTK Query entirely — it uses the browser's native `EventSource` API. This is by design: SSE is a long-lived connection, not a request-response, and RTK Query is optimised for the latter.

```
ChatPanel
  │
  ├── POST /api/chat/stream (EventSource)
  │     │
  │     ├── onmessage: {"delta": "..."}    → append to streaming buffer
  │     ├── onmessage: {"sources": [...]}  → set source citations
  │     ├── onmessage: {"done": true}      → finalize message, close EventSource
  │     └── onmessage: {"error": "..."}   → show error toast, close EventSource
  │
  └── lib/streaming.ts  createChatStream()  ← wraps EventSource lifecycle
```

### Data Flow

```
User types message → ChatPanel local state
  → createChatStream() opens EventSource
  → tokens arrive → streaming buffer in useState
  → done event → message pushed to messages array
  → sources displayed as citation chips below message
  → POST /api/chat/suggest-questions → follow-up question chips
```

---

## Page Structure

| Route | Component | Purpose |
|---|---|---|
| `/login` | `LoginPage` | Auth form with `useActionState` (React 19) |
| `/` | `DashboardPage` | Main app shell |
| `/settings` | `SettingsPage` | Profile, password, delete account |
| `/usage` | `UsagePage` | AI token + cost stats (Recharts bar chart) |

`ProtectedRoute` wraps `/`, `/settings`, `/usage` — redirects to `/login` if no token in localStorage.

---

## Component Hierarchy

```
App
├── ProtectedRoute
│   └── DashboardPage
│       ├── NotebookSidebar     ← useOptimistic for instant notebook creation
│       ├── TopNav              ← user menu, settings link
│       └── MainContent
│           ├── DocumentUpload  ← polls every 3s for non-terminal documents
│           ├── ChatPanel       ← SSE streaming, history, citations, export
│           └── studio/
│               └── AudioOverviewTab  ← generate + play audio summary
├── LoginPage
├── SettingsPage                ← profile + password + danger zone
└── UsagePage                   ← Recharts bar chart of token usage
```

---

## Key Components

### `ChatPanel`

The most complex component. Responsibilities:
- Load chat history on notebook change (`GET /api/chat/history/{notebookId}`)
- Manage streaming state: `streamingContent` buffer + `isStreaming` flag
- Display source citation chips on each assistant message
- Render follow-up question chips (`POST /api/chat/suggest-questions`)
- Export conversation as Markdown (`lib/export.ts`)
- Auto-scroll on new messages

### `NotebookSidebar`

Uses React 19's `useOptimistic` hook for instant notebook creation — the new notebook appears in the list immediately while the POST request is in flight. On error it's rolled back automatically.

### `DocumentUpload`

Polls `/api/documents` every 3 seconds for documents with status `processing` or `uploaded`. Stops polling once all documents reach a terminal state (`ready` or `failed`). Displays a spinner next to processing documents.

### `UsagePage`

Fetches `GET /api/user/usage` and renders a Recharts `BarChart` showing prompt tokens, completion tokens, and estimated cost grouped by date. Gives users visibility into their AI consumption.

---

## TypeScript Conventions

- All imports of types use the `type` keyword: `import { type Foo } from '...'`
- `verbatimModuleSyntax` is enabled in `tsconfig.app.json` — build will fail if type-only imports use the value form
- Component props are always explicit interfaces, never inline object types for reused shapes
- RTK Query endpoints are typed end-to-end: response shape defined as TypeScript interfaces, used in generated hooks

---

## Styling Approach

Tailwind CSS v4 with the `@tailwindcss/vite` plugin (no `tailwind.config.js` needed). Design system:
- Dark theme throughout (slate/zinc palette)
- `cn()` utility from `lib/utils.ts` (clsx + tailwind-merge) for conditional class merging
- CVA (class-variance-authority) for component variants in UI primitives (`Button`, `Input`)
- Framer Motion for page transitions and chat message entrance animations

---

## Development

```bash
cd frontend
npm install
npm run dev       # Vite dev server, port 5173
npm run build     # tsc -b && vite build
npm run lint      # ESLint
npm run preview   # Preview production build
```

Vite proxies `/api` and `/sanctum` to `http://backend:8000` — no CORS issues during development.
