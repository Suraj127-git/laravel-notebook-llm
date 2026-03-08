import { useOptimistic, useState, useTransition, type FormEvent } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { type Notebook } from '../hooks/useNotebooks'

type Props = {
  notebooks: Notebook[]
  selected: Notebook | null
  loading: boolean
  onSelect: (nb: Notebook) => void
  onCreate: (name: string, emoji?: string) => Promise<Notebook>
  onDelete: (id: number) => Promise<void>
}

export function NotebookSidebar({
  notebooks,
  selected,
  loading,
  onSelect,
  onCreate,
  onDelete,
}: Props) {
  const [creating, setCreating] = useState(false)
  const [newName, setNewName] = useState('')
  const [, startTransition] = useTransition()

  // Optimistically add the new notebook before server responds
  const [optimisticNotebooks, addOptimistic] = useOptimistic(
    notebooks,
    (state: Notebook[], newNb: Notebook) => [newNb, ...state],
  )

  const handleCreate = async (e: FormEvent) => {
    e.preventDefault()
    const name = newName.trim()
    if (!name) return

    const tempNb: Notebook = {
      id: -Date.now(), // temp negative id, replaced after server response
      name,
      description: null,
      emoji: '📓',
      documents_count: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }

    startTransition(async () => {
      addOptimistic(tempNb)
    })

    setNewName('')
    setCreating(false)

    await onCreate(name)
  }

  return (
    <aside className="flex h-full flex-col">
      <div className="flex items-center justify-between px-1 py-2">
        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
          Notebooks
        </span>
        <button
          onClick={() => setCreating((c) => !c)}
          title="New notebook"
          className="rounded p-1 text-slate-400 hover:text-slate-100"
        >
          +
        </button>
      </div>

      {/* New notebook input */}
      <AnimatePresence>
        {creating && (
          <motion.form
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            onSubmit={handleCreate}
            className="mb-2 overflow-hidden"
          >
            <input
              autoFocus
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              placeholder="Notebook name…"
              className="w-full rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 outline-none focus:border-indigo-500"
              onKeyDown={(e) => e.key === 'Escape' && setCreating(false)}
            />
          </motion.form>
        )}
      </AnimatePresence>

      {/* Notebook list */}
      <div className="flex-1 overflow-y-auto space-y-0.5">
        {loading && (
          <p className="px-1 text-xs text-slate-500">Loading…</p>
        )}

        {!loading && optimisticNotebooks.length === 0 && (
          <p className="px-1 text-xs text-slate-500">No notebooks yet. Create one!</p>
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
    </aside>
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
      className={`group flex cursor-pointer items-center justify-between rounded px-2 py-1.5 text-xs transition-colors ${
        isSelected
          ? 'bg-indigo-600/30 text-slate-100'
          : 'text-slate-300 hover:bg-slate-800'
      }`}
      onClick={() => onSelect(notebook)}
    >
      <span className="flex items-center gap-1.5 min-w-0">
        <span>{notebook.emoji}</span>
        <span className="truncate">{notebook.name}</span>
        {notebook.documents_count !== undefined && (
          <span className="text-slate-500">{notebook.documents_count}</span>
        )}
      </span>

      {/* Delete button — shown on hover */}
      <span className="hidden group-hover:flex items-center gap-1 ml-1 flex-shrink-0">
        {confirmDelete ? (
          <>
            <button
              onClick={(e) => { e.stopPropagation(); onDelete(notebook.id) }}
              className="text-red-400 hover:text-red-300 text-[10px]"
            >
              confirm
            </button>
            <button
              onClick={(e) => { e.stopPropagation(); setConfirmDelete(false) }}
              className="text-slate-400 hover:text-slate-200 text-[10px]"
            >
              cancel
            </button>
          </>
        ) : (
          <button
            onClick={(e) => { e.stopPropagation(); setConfirmDelete(true) }}
            className="text-slate-500 hover:text-red-400 text-[10px]"
            title="Delete notebook"
          >
            ✕
          </button>
        )}
      </span>
    </div>
  )
}
