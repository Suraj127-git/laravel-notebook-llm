import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import api from '../lib/api'
import { exportAsMarkdown } from '../lib/export'
import { createChatStream, type Source } from '../lib/streaming'
import { useCreateNoteMutation } from '../store/api/noteApi'
import SuggestedQuestions from './SuggestedQuestions'
import WelcomeScreen from './WelcomeScreen'

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
  const [lastAnswer, setLastAnswer] = useState<string | null>(null)
  const bottomRef = useRef<HTMLDivElement | null>(null)
  const [createNote] = useCreateNoteMutation()

  useEffect(() => {
    if (!notebookId) return
    setMessages([])
    setLastAnswer(null)
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
        const lastAssistant = loaded.filter((m) => m.role === 'assistant').at(-1)
        if (lastAssistant) setLastAnswer(lastAssistant.content)
        setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: 'instant' }), 50)
      })
      .catch(() => toast.error('Failed to load chat history'))
      .finally(() => setHistoryLoading(false))
  }, [notebookId])

  const scrollToBottom = () => bottomRef.current?.scrollIntoView({ behavior: 'smooth' })

  const sendMessage = (text: string) => {
    if (!text.trim() || loading) return

    const userMessage: Message = { id: crypto.randomUUID(), role: 'user', content: text.trim() }
    const assistantId = crypto.randomUUID()
    const assistantMessage: Message = { id: assistantId, role: 'assistant', content: '', streaming: true }

    setMessages((prev) => [...prev, userMessage, assistantMessage])
    setInput('')
    setLoading(true)
    setLastAnswer(null)

    createChatStream(
      notebookId,
      text.trim(),
      (delta) => {
        setMessages((prev) => prev.map((m) => m.id === assistantId ? { ...m, content: m.content + delta } : m))
        scrollToBottom()
      },
      () => {
        setMessages((prev) => {
          const updated = prev.map((m) => m.id === assistantId ? { ...m, streaming: false } : m)
          const ans = updated.find((m) => m.id === assistantId)
          if (ans) setLastAnswer(ans.content)
          return updated
        })
        setLoading(false)
      },
      () => {
        setMessages((prev) => prev.map((m) => m.id === assistantId ? { ...m, streaming: false } : m))
        setLoading(false)
        toast.error('Streaming failed. Please try again.')
      },
      (sources) => {
        setMessages((prev) => prev.map((m) => m.id === assistantId ? { ...m, sources } : m))
      },
    )
  }

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    sendMessage(input)
  }

  const handleSaveNote = async (content: string) => {
    try {
      await createNote({ notebook_id: notebookId, content }).unwrap()
      toast.success('Saved to Notes')
    } catch {
      toast.error('Failed to save note')
    }
  }

  const handleExport = () => {
    if (messages.length === 0) { toast.error('No messages to export'); return }
    exportAsMarkdown(messages, notebookName)
    toast.success('Chat exported!')
  }

  return (
    <div className="flex flex-col flex-1 overflow-hidden min-w-0">
      {/* Chat header */}
      <div className="flex items-center justify-between border-b border-white/10 px-4 py-2.5 shrink-0">
        <span className="text-xs font-semibold uppercase tracking-widest text-slate-400">Chat</span>
        <button
          onClick={handleExport}
          title="Export chat"
          className="text-xs text-slate-500 hover:text-slate-200 flex items-center gap-1 transition-colors"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Export
        </button>
      </div>

      {/* Message list */}
      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
        {historyLoading && (
          <div className="flex items-center justify-center h-32">
            <div className="w-6 h-6 border-2 border-violet-500 border-t-transparent rounded-full animate-spin" />
          </div>
        )}

        {!historyLoading && messages.length === 0 && (
          <WelcomeScreen onSelect={sendMessage} />
        )}

        <AnimatePresence initial={false}>
          {messages.map((m) => (
            <motion.div
              key={m.id}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -4 }}
              transition={{ duration: 0.25 }}
              className={`flex gap-3 ${m.role === 'user' ? 'flex-row-reverse' : 'flex-row'}`}
            >
              {m.role === 'assistant' && (
                <div className="w-7 h-7 rounded-full bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-violet-900/40">
                  <svg className="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                  </svg>
                </div>
              )}

              <div className={`flex flex-col gap-1 max-w-2xl min-w-0 ${m.role === 'user' ? 'items-end' : 'items-start'}`}>
                <div
                  className={`px-4 py-2.5 text-sm leading-relaxed ${
                    m.role === 'user'
                      ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-2xl rounded-tr-sm shadow-lg shadow-violet-900/30'
                      : 'backdrop-blur-sm bg-white/5 border border-white/10 text-slate-100 rounded-2xl rounded-tl-sm'
                  }`}
                >
                  {m.content || (m.streaming ? (
                    <span className="flex items-center gap-1.5 text-slate-400">
                      <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-bounce" style={{ animationDelay: '0ms' }} />
                      <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-bounce" style={{ animationDelay: '150ms' }} />
                      <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-bounce" style={{ animationDelay: '300ms' }} />
                    </span>
                  ) : '')}
                </div>

                {m.role === 'assistant' && !m.streaming && m.sources && m.sources.length > 0 && (
                  <SourceCitations sources={m.sources} />
                )}

                {m.role === 'assistant' && !m.streaming && m.content && (
                  <button
                    onClick={() => handleSaveNote(m.content)}
                    className="text-[10px] text-slate-600 hover:text-violet-400 transition-colors flex items-center gap-1 px-1"
                  >
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                    Save as note
                  </button>
                )}
              </div>
            </motion.div>
          ))}
        </AnimatePresence>

        <div ref={bottomRef} />
      </div>

      {/* Suggested questions */}
      {lastAnswer && !loading && (
        <SuggestedQuestions
          notebookId={notebookId}
          lastAnswer={lastAnswer}
          onSelect={sendMessage}
        />
      )}

      {/* Input area */}
      <form
        onSubmit={handleSubmit}
        className="shrink-0 flex gap-2 backdrop-blur-xl bg-white/5 border-t border-white/10 px-4 py-3"
      >
        <input
          className="flex-1 bg-white/5 border border-white/10 focus:border-violet-500/50 focus:ring-2 focus:ring-violet-500/20 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-600 outline-none transition-all"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Ask about your sources…"
          disabled={loading}
        />
        <button
          type="submit"
          disabled={loading || !input.trim()}
          className="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 disabled:opacity-50 text-white rounded-xl px-5 py-2.5 text-sm font-medium transition-all shadow-lg shadow-violet-900/30 flex items-center gap-1.5 shrink-0"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
          {loading ? '…' : 'Send'}
        </button>
      </form>
    </div>
  )
}

function SourceCitations({ sources }: { sources: Source[] }) {
  const [open, setOpen] = useState(false)

  return (
    <div className="mt-0.5 ml-1">
      <button
        onClick={() => setOpen((o) => !o)}
        className="text-[10px] text-slate-500 hover:text-slate-300 flex items-center gap-1 transition-colors"
      >
        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        {sources.length} source{sources.length !== 1 ? 's' : ''}
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
              <li key={i} className="backdrop-blur-sm bg-white/5 border border-white/10 rounded-lg px-2 py-1 text-[10px] text-slate-400">
                {s.title}
              </li>
            ))}
          </motion.ul>
        )}
      </AnimatePresence>
    </div>
  )
}
