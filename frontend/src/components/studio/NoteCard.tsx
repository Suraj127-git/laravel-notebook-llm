import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import type { Note } from '../../store/api/noteApi'

type Props = {
  note: Note
  onDelete: (id: number) => void
  onTogglePin: (id: number, pinned: boolean) => void
}

export default function NoteCard({ note, onDelete, onTogglePin }: Props) {
  const [expanded, setExpanded] = useState(false)
  const [confirming, setConfirming] = useState(false)

  const preview = note.title ?? note.content.slice(0, 80) + (note.content.length > 80 ? '…' : '')
  const date = new Date(note.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })

  return (
    <motion.div
      layout
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, scale: 0.95 }}
      className="backdrop-blur-sm bg-white/5 border border-white/10 hover:border-white/20 rounded-xl p-3 transition-all duration-200"
    >
      <div className="flex items-start gap-2">
        <button
          onClick={() => setExpanded(!expanded)}
          className="flex-1 min-w-0 text-left"
        >
          <p className="text-xs text-slate-200 font-medium truncate">{preview}</p>
          <p className="text-[10px] text-slate-500 mt-0.5">{date}</p>
        </button>

        <div className="flex items-center gap-1 shrink-0">
          <button
            onClick={() => onTogglePin(note.id, !note.pinned)}
            className={`p-1 rounded transition-colors ${note.pinned ? 'text-violet-400' : 'text-slate-600 hover:text-slate-400'}`}
            title={note.pinned ? 'Unpin' : 'Pin'}
          >
            <svg className="w-3 h-3" fill={note.pinned ? 'currentColor' : 'none'} viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
          </button>

          {confirming ? (
            <>
              <button onClick={() => { onDelete(note.id); setConfirming(false) }} className="text-[9px] text-red-400 hover:text-red-300 px-1.5 py-0.5 rounded border border-red-500/30 transition-colors">Del</button>
              <button onClick={() => setConfirming(false)} className="text-[9px] text-slate-500 hover:text-slate-400 px-1.5 py-0.5 rounded border border-white/10 transition-colors">No</button>
            </>
          ) : (
            <button onClick={() => setConfirming(true)} className="p-1 text-slate-600 hover:text-red-400 rounded transition-colors">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          )}
        </div>
      </div>

      <AnimatePresence>
        {expanded && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="overflow-hidden"
          >
            <p className="text-xs text-slate-300 mt-2 whitespace-pre-wrap leading-relaxed border-t border-white/10 pt-2">
              {note.content}
            </p>
          </motion.div>
        )}
      </AnimatePresence>
    </motion.div>
  )
}
