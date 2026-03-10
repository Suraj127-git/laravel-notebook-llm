import { motion } from 'framer-motion'
import { useState } from 'react'
import type { AudioOverviewUpdate } from '../hooks/useDocumentChannel'
import AudioOverviewTab from './studio/AudioOverviewTab'
import NotesTab from './studio/NotesTab'
import ContentGenerationModal from './ContentGenerationModal'

type Tab = 'notes' | 'audio'

type Props = {
  notebookId: number
  notebookName: string
  audioReadySignal: AudioOverviewUpdate | null
}

export default function StudioPanel({ notebookId, notebookName, audioReadySignal }: Props) {
  const [tab, setTab] = useState<Tab>('notes')

  return (
    <motion.aside
      initial={{ x: 20, opacity: 0 }}
      animate={{ x: 0, opacity: 1 }}
      transition={{ duration: 0.3, ease: 'easeOut' }}
      className="w-80 shrink-0 flex flex-col backdrop-blur-xl bg-white/5 border-l border-white/10 overflow-hidden"
    >
      {/* Header */}
      <div className="px-4 pt-4 pb-2 border-b border-white/10">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-xs font-semibold uppercase tracking-widest text-slate-400">Studio</h2>
          <ContentGenerationModal notebookId={notebookId} notebookName={notebookName} />
        </div>

        {/* Tab bar */}
        <div className="flex gap-1">
          {(['notes', 'audio'] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`flex-1 py-1.5 text-xs font-medium rounded-lg transition-all ${
                tab === t
                  ? 'bg-violet-600/20 text-violet-300 border border-violet-500/30'
                  : 'text-slate-500 hover:text-slate-300 hover:bg-white/5'
              }`}
            >
              {t === 'notes' ? '📝 Notes' : '🎙️ Audio'}
            </button>
          ))}
        </div>
      </div>

      {/* Tab content */}
      <div className="flex-1 overflow-hidden">
        {tab === 'notes' ? (
          <NotesTab notebookId={notebookId} />
        ) : (
          <AudioOverviewTab notebookId={notebookId} audioReadySignal={audioReadySignal} />
        )}
      </div>
    </motion.aside>
  )
}
