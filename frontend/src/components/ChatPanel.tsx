import { useEffect, useRef, useState, type FormEvent } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { toast } from 'sonner'
import { createChatStream, type Source } from '../lib/streaming'
import { api } from '../lib/api'
import { exportAsMarkdown } from '../lib/export'

type Message = {
  id: string
  role: 'user' | 'assistant'
  content: string
  streaming?: boolean
  sources?: Source[]
}

type HistoryMessage = {
  id: number
  role: 'user' | 'assistant' | 'system'
  content: string
  metadata?: { sources?: Source[] } | null
}

export function ChatPanel({ notebookId, notebookName = 'Notebook' }: { notebookId: number; notebookName?: string }) {
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(false)
  const [historyLoading, setHistoryLoading] = useState(false)
  const bottomRef = useRef<HTMLDivElement | null>(null)

  // Load chat history whenever notebookId changes
  useEffect(() => {
    if (!notebookId) return

    setMessages([])
    setHistoryLoading(true)

    api
      .get<HistoryMessage[]>(`/chat/history/${notebookId}`)
      .then(({ data }) => {
        const loaded: Message[] = data
          .filter((m) => m.role !== 'system')
          .map((m) => ({
            id: String(m.id),
            role: m.role as 'user' | 'assistant',
            content: m.content,
            sources: m.metadata?.sources ?? undefined,
          }))
        setMessages(loaded)
        setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: 'instant' }), 50)
      })
      .catch((err) => { console.error('Failed to load chat history', err); toast.error('Failed to load chat history') })
      .finally(() => setHistoryLoading(false))
  }, [notebookId])

  const scrollToBottom = () => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    if (!input.trim() || loading) return

    const userMessage: Message = {
      id: crypto.randomUUID(),
      role: 'user',
      content: input.trim(),
    }

    const assistantId = crypto.randomUUID()
    const assistantMessage: Message = {
      id: assistantId,
      role: 'assistant',
      content: '',
      streaming: true,
    }

    setMessages((prev) => [...prev, userMessage, assistantMessage])
    setInput('')
    setLoading(true)

    createChatStream(
      notebookId,
      userMessage.content,
      (delta) => {
        setMessages((prev) =>
          prev.map((m) => (m.id === assistantId ? { ...m, content: m.content + delta } : m)),
        )
        scrollToBottom()
      },
      () => {
        setMessages((prev) =>
          prev.map((m) => (m.id === assistantId ? { ...m, streaming: false } : m)),
        )
        setLoading(false)
      },
      () => {
        setMessages((prev) =>
          prev.map((m) => (m.id === assistantId ? { ...m, streaming: false } : m)),
        )
        setLoading(false)
        toast.error('Streaming failed. Please try again.')
      },
      (sources) => {
        setMessages((prev) =>
          prev.map((m) => (m.id === assistantId ? { ...m, sources } : m)),
        )
      },
    )
  }

  const handleExport = () => {
    if (messages.length === 0) { toast.error('No messages to export'); return }
    exportAsMarkdown(messages, notebookName)
    toast.success('Chat exported!')
  }

  return (
    <div className="flex h-full flex-col">
      {/* Header with export */}
      <div className="flex items-center justify-between border-b border-slate-800 px-4 py-2">
        <span className="text-xs font-semibold text-slate-400">Chat</span>
        <button
          onClick={handleExport}
          title="Export chat as Markdown"
          className="text-xs text-slate-500 hover:text-slate-200"
        >
          Export ↓
        </button>
      </div>

      {/* Message list */}
      <div className="flex-1 space-y-3 overflow-y-auto p-4">
        {historyLoading && (
          <p className="text-center text-xs text-slate-500">Loading history…</p>
        )}

        {!historyLoading && messages.length === 0 && (
          <p className="mt-8 text-center text-sm text-slate-500">
            Ask a question about your uploaded documents.
          </p>
        )}

        {messages.map((m) => (
          <motion.div
            key={m.id}
            initial={{ opacity: 0, y: 4 }}
            animate={{ opacity: 1, y: 0 }}
            className={`max-w-xl ${m.role === 'user' ? 'ml-auto' : 'mr-auto'}`}
          >
            <div
              className={`rounded-lg px-3 py-2 text-sm ${
                m.role === 'user'
                  ? 'bg-indigo-600 text-white'
                  : 'bg-slate-800 text-slate-50'
              }`}
            >
              {m.content || (m.streaming ? <span className="opacity-60">Thinking…</span> : '')}
            </div>

            {/* Source citations — shown below assistant messages */}
            {m.role === 'assistant' && !m.streaming && m.sources && m.sources.length > 0 && (
              <SourceCitations sources={m.sources} />
            )}
          </motion.div>
        ))}

        <div ref={bottomRef} />
      </div>

      {/* Input form */}
      <form onSubmit={handleSubmit} className="flex gap-2 border-t border-slate-800 p-3">
        <input
          className="flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-50 outline-none ring-indigo-500 focus:ring-2"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Ask about your documents…"
          disabled={loading}
        />
        <button
          type="submit"
          disabled={loading || !input.trim()}
          className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
        >
          Send
        </button>
      </form>
    </div>
  )
}

function SourceCitations({ sources }: { sources: Source[] }) {
  const [open, setOpen] = useState(false)

  return (
    <div className="mt-1">
      <button
        onClick={() => setOpen((o) => !o)}
        className="text-xs text-slate-400 hover:text-slate-200"
      >
        {open ? '▾' : '▸'} {sources.length} source{sources.length !== 1 ? 's' : ''}
      </button>

      <AnimatePresence>
        {open && (
          <motion.ul
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="mt-1 space-y-0.5 overflow-hidden"
          >
            {sources.map((s, i) => (
              <li
                key={i}
                className="rounded bg-slate-700/50 px-2 py-0.5 text-xs text-slate-300"
              >
                {s.title}
              </li>
            ))}
          </motion.ul>
        )}
      </AnimatePresence>
    </div>
  )
}

