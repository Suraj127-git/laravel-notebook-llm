import { motion } from 'framer-motion'
import { useState } from 'react'

type Source = {
  id: number
  title: string
  status: 'uploaded' | 'processing' | 'ready' | 'failed'
  mime_type: string
  source_type?: string
  source_url?: string | null
}

type Props = {
  source: Source
  onDelete: (id: number) => void
}

function FileIcon({ mimeType, sourceType }: { mimeType: string; sourceType?: string }) {
  if (sourceType === 'url') {
    return (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
      </svg>
    )
  }
  if (mimeType.includes('pdf')) {
    return (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
      </svg>
    )
  }
  if (mimeType.includes('csv') || mimeType.includes('spreadsheet') || mimeType.includes('excel')) {
    return (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 10h18M3 14h18M10 3v18M3 3h18a0 0 010 18H3a0 0 010-18z" />
      </svg>
    )
  }
  return (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
  )
}

const statusConfig = {
  uploaded: { label: 'Queued', class: 'bg-yellow-500/15 text-yellow-400 border-yellow-500/20' },
  processing: { label: 'Processing', class: 'bg-blue-500/15 text-blue-400 border-blue-500/20 animate-pulse' },
  ready: { label: 'Ready', class: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20' },
  failed: { label: 'Failed', class: 'bg-red-500/15 text-red-400 border-red-500/20' },
}

export default function SourceCard({ source, onDelete }: Props) {
  const [confirming, setConfirming] = useState(false)
  const cfg = statusConfig[source.status] ?? statusConfig.uploaded

  return (
    <motion.div
      layout
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, scale: 0.95 }}
      className="group backdrop-blur-sm bg-white/5 border border-white/10 hover:border-white/20 rounded-xl p-3 flex items-center gap-2.5 transition-all duration-200"
    >
      <div className="text-slate-400 shrink-0">
        <FileIcon mimeType={source.mime_type} sourceType={source.source_type} />
      </div>

      <div className="flex-1 min-w-0">
        <p className="text-xs text-slate-200 truncate font-medium">{source.title}</p>
        <span className={`inline-flex items-center border rounded-full px-1.5 py-0.5 text-[9px] font-medium mt-0.5 ${cfg.class}`}>
          {cfg.label}
        </span>
      </div>

      {confirming ? (
        <div className="flex gap-1 shrink-0">
          <button
            onClick={() => { onDelete(source.id); setConfirming(false) }}
            className="text-[10px] text-red-400 hover:text-red-300 px-1.5 py-0.5 rounded border border-red-500/30 hover:border-red-500/50 transition-colors"
          >
            Delete
          </button>
          <button
            onClick={() => setConfirming(false)}
            className="text-[10px] text-slate-500 hover:text-slate-400 px-1.5 py-0.5 rounded border border-white/10 transition-colors"
          >
            Cancel
          </button>
        </div>
      ) : (
        <button
          onClick={() => setConfirming(true)}
          className="opacity-0 group-hover:opacity-100 text-slate-600 hover:text-red-400 transition-all shrink-0"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      )}
    </motion.div>
  )
}
