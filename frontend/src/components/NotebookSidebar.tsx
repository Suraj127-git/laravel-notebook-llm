import { AnimatePresence, motion } from 'framer-motion'
import { useCallback, useEffect, useRef, useState, useOptimistic, useTransition, type FormEvent } from 'react'
import { toast } from 'sonner'
import api from '../lib/api'
import type { Notebook } from '../hooks/useNotebooks'
import { useAppSelector } from '../store/hooks'
import type { AudioOverviewUpdate, DocumentFailedUpdate, DocumentUpdate } from '../hooks/useDocumentChannel'
import { useDocumentChannel } from '../hooks/useDocumentChannel'
import SourceCard from './SourceCard'
import AddSourceModal from './AddSourceModal'

type DocRecord = {
  id: number
  title: string
  status: 'uploaded' | 'processing' | 'ready' | 'failed'
  mime_type: string
  source_type?: string
  source_url?: string | null
}

type Props = {
  notebooks: Notebook[]
  selected: Notebook | null
  loading: boolean
  onSelect: (nb: Notebook) => void
  onCreate: (name: string, emoji?: string) => Promise<Notebook>
  onDelete: (id: number) => Promise<void>
  onAudioReady?: (update: AudioOverviewUpdate) => void
}

export function NotebookSidebar({ notebooks, selected, loading, onSelect, onCreate, onDelete, onAudioReady }: Props) {
  const [creating, setCreating] = useState(false)
  const [newName, setNewName] = useState('')
  const [, startTransition] = useTransition()
  const [docs, setDocs] = useState<DocRecord[]>([])
  const token = useAppSelector((s) => s.auth.token)

  const [optimisticNotebooks, addOptimistic] = useOptimistic(
    notebooks,
    (state: Notebook[], newNb: Notebook) => [newNb, ...state],
  )

  // Fetch docs when notebook changes
  const fetchDocs = useCallback(async () => {
    if (!selected) { setDocs([]); return }
    try {
      const { data } = await api.get<DocRecord[]>('/documents', { params: { notebook_id: selected.id } })
      setDocs(data)
      return data
    } catch {}
  }, [selected])

  useEffect(() => { fetchDocs() }, [fetchDocs])

  // Poll every 3s while any doc is non-terminal (fallback if WebSocket misses an event)
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const TERMINAL = new Set(['ready', 'failed'])
  useEffect(() => {
    const hasPending = docs.some((d) => !TERMINAL.has(d.status))
    if (hasPending && !pollRef.current) {
      pollRef.current = setInterval(fetchDocs, 3000)
    } else if (!hasPending && pollRef.current) {
      clearInterval(pollRef.current)
      pollRef.current = null
    }
    return () => {
      if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null }
    }
  }, [docs, fetchDocs])

  // WebSocket real-time document status updates (replaces polling)
  const handleStatusUpdate = useCallback((update: DocumentUpdate) => {
    setDocs((prev) => prev.map((d) => d.id === update.id ? { ...d, status: update.status } : d))
  }, [])

  const handleFailed = useCallback((update: DocumentFailedUpdate) => {
    setDocs((prev) => prev.map((d) => d.id === update.id ? { ...d, status: 'failed' } : d))
    toast.error(`Document "${update.title}" failed to process`)
  }, [])

  useDocumentChannel(selected?.id ?? null, token, handleStatusUpdate, handleFailed, onAudioReady)

  const handleCreate = async (e: FormEvent) => {
    e.preventDefault()
    const name = newName.trim()
    if (!name) return

    const tempNb: Notebook = {
      id: -Date.now(),
      name,
      description: null,
      emoji: '📓',
      documents_count: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }

    startTransition(async () => { addOptimistic(tempNb) })
    setNewName('')
    setCreating(false)
    await onCreate(name)
  }

  const handleDeleteDoc = async (id: number) => {
    try {
      await api.delete(`/documents/${id}`)
      setDocs((prev) => prev.filter((d) => d.id !== id))
      toast.success('Source removed')
    } catch {
      toast.error('Failed to remove source')
    }
  }

  return (
    <motion.aside
      initial={{ x: -20, opacity: 0 }}
      animate={{ x: 0, opacity: 1 }}
      transition={{ duration: 0.3, ease: 'easeOut' }}
      className="w-72 shrink-0 flex flex-col backdrop-blur-xl bg-white/5 border-r border-white/10 overflow-hidden"
    >
      {/* Notebooks section */}
      <div className="flex-shrink-0 border-b border-white/10">
        <div className="flex items-center justify-between px-4 py-3">
          <span className="text-xs font-semibold uppercase tracking-widest text-slate-400">Notebooks</span>
          <button
            onClick={() => setCreating((c) => !c)}
            className="w-6 h-6 rounded-lg bg-white/5 hover:bg-violet-500/20 text-slate-400 hover:text-violet-300 flex items-center justify-center transition-all text-sm"
            title="New notebook"
          >
            +
          </button>
        </div>

        <AnimatePresence>
          {creating && (
            <motion.form
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              onSubmit={handleCreate}
              className="overflow-hidden px-3 pb-2"
            >
              <input
                autoFocus
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                placeholder="Notebook name…"
                className="w-full bg-white/5 border border-violet-500/40 focus:border-violet-500 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 placeholder:text-slate-600 outline-none transition-colors"
                onKeyDown={(e) => e.key === 'Escape' && setCreating(false)}
              />
            </motion.form>
          )}
        </AnimatePresence>

        <div className="overflow-y-auto max-h-48 px-2 pb-2 space-y-0.5">
          {loading && <p className="px-2 text-xs text-slate-500 py-2">Loading…</p>}
          {!loading && optimisticNotebooks.length === 0 && (
            <p className="px-2 text-xs text-slate-500 py-2">No notebooks yet.</p>
          )}
          {optimisticNotebooks.map((nb) => (
            <NotebookItem
              key={nb.id}
              notebook={nb}
              isSelected={selected?.id === nb.id}
              onSelect={onSelect}
              onDelete={onDelete}
            />
          ))}
        </div>
      </div>

      {/* Sources section */}
      {selected && (
        <div className="flex-1 flex flex-col overflow-hidden">
          <div className="px-4 py-3 border-b border-white/10">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-semibold uppercase tracking-widest text-slate-400">Sources</span>
              <span className="text-[10px] text-slate-600">{docs.length} file{docs.length !== 1 ? 's' : ''}</span>
            </div>
            <AddSourceModal notebookId={selected.id} onAdded={fetchDocs} />
          </div>

          <div className="flex-1 overflow-y-auto p-3 space-y-1.5">
            <AnimatePresence mode="popLayout">
              {docs.map((doc) => (
                <SourceCard
                  key={doc.id}
                  source={doc}
                  onDelete={handleDeleteDoc}
                />
              ))}
            </AnimatePresence>
            {docs.length === 0 && (
              <p className="text-xs text-slate-600 text-center py-4">No sources yet. Add one above.</p>
            )}
          </div>
        </div>
      )}
    </motion.aside>
  )
}

function NotebookItem({
  notebook,
  isSelected,
  onSelect,
  onDelete,
}: {
  notebook: Notebook
  isSelected: boolean
  onSelect: (nb: Notebook) => void
  onDelete: (id: number) => Promise<void>
}) {
  const [confirmDelete, setConfirmDelete] = useState(false)

  return (
    <div
      className={`group flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-2 text-xs transition-all ${
        isSelected
          ? 'bg-violet-600/20 border border-violet-500/30 text-white'
          : 'text-slate-400 hover:bg-white/5 hover:text-slate-200 border border-transparent'
      }`}
      onClick={() => onSelect(notebook)}
    >
      <span className="flex items-center gap-1.5 min-w-0">
        <span>{notebook.emoji}</span>
        <span className="truncate">{notebook.name}</span>
        {notebook.documents_count !== undefined && (
          <span className="text-slate-600 text-[10px]">{notebook.documents_count}</span>
        )}
      </span>

      <span className="hidden group-hover:flex items-center gap-1 ml-1 shrink-0">
        {confirmDelete ? (
          <>
            <button
              onClick={(e) => { e.stopPropagation(); onDelete(notebook.id) }}
              className="text-red-400 hover:text-red-300 text-[9px] px-1 py-0.5 rounded border border-red-500/30"
            >
              confirm
            </button>
            <button
              onClick={(e) => { e.stopPropagation(); setConfirmDelete(false) }}
              className="text-slate-500 hover:text-slate-300 text-[9px] px-1 py-0.5 rounded border border-white/10"
            >
              cancel
            </button>
          </>
        ) : (
          <button
            onClick={(e) => { e.stopPropagation(); setConfirmDelete(true) }}
            className="text-slate-600 hover:text-red-400 p-0.5 rounded transition-colors"
          >
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}
      </span>
    </div>
  )
}
