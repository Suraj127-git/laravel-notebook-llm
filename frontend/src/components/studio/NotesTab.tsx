import { AnimatePresence } from 'framer-motion'
import { useState } from 'react'
import { toast } from 'sonner'
import { useCreateNoteMutation, useDeleteNoteMutation, useGetNotesQuery, useUpdateNoteMutation } from '../../store/api/noteApi'
import NoteCard from './NoteCard'

type Props = {
  notebookId: number
}

export default function NotesTab({ notebookId }: Props) {
  const { data: notes = [], isLoading } = useGetNotesQuery(notebookId)
  const [createNote] = useCreateNoteMutation()
  const [deleteNote] = useDeleteNoteMutation()
  const [updateNote] = useUpdateNoteMutation()
  const [draft, setDraft] = useState('')
  const [saving, setSaving] = useState(false)

  const handleSave = async () => {
    if (!draft.trim()) return
    setSaving(true)
    try {
      await createNote({ notebook_id: notebookId, content: draft.trim() }).unwrap()
      setDraft('')
      toast.success('Note saved')
    } catch {
      toast.error('Failed to save note')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="flex flex-col h-full">
      {/* Composer */}
      <div className="p-3 border-b border-white/10 space-y-2">
        <textarea
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          placeholder="Add a note…"
          rows={3}
          className="w-full bg-white/5 border border-white/10 focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/30 rounded-xl px-3 py-2 text-xs text-slate-200 placeholder:text-slate-600 outline-none resize-none transition-colors"
        />
        <button
          onClick={handleSave}
          disabled={saving || !draft.trim()}
          className="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 disabled:opacity-40 text-white rounded-lg py-1.5 text-xs font-medium transition-all"
        >
          {saving ? 'Saving…' : 'Save Note'}
        </button>
      </div>

      {/* Notes list */}
      <div className="flex-1 overflow-y-auto p-3 space-y-2">
        {isLoading ? (
          <div className="flex items-center justify-center h-20">
            <div className="w-5 h-5 border-2 border-violet-500 border-t-transparent rounded-full animate-spin" />
          </div>
        ) : notes.length === 0 ? (
          <div className="text-center py-8 text-slate-500 text-xs">
            <p>No notes yet.</p>
            <p className="mt-1">Save an AI response or write one above.</p>
          </div>
        ) : (
          <AnimatePresence mode="popLayout">
            {notes.map((note) => (
              <NoteCard
                key={note.id}
                note={note}
                onDelete={(id) => deleteNote({ id, notebook_id: notebookId }).then(() => toast.success('Note deleted'))}
                onTogglePin={(id, pinned) => updateNote({ id, notebook_id: notebookId, pinned })}
              />
            ))}
          </AnimatePresence>
        )}
      </div>
    </div>
  )
}
