import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import { toast } from 'sonner'
import api from '../lib/api'

type ContentType = 'study_guide' | 'faq' | 'timeline' | 'briefing'

const TYPES: { value: ContentType; label: string; icon: string }[] = [
  { value: 'study_guide', label: 'Study Guide', icon: '📚' },
  { value: 'faq', label: 'FAQ', icon: '❓' },
  { value: 'timeline', label: 'Timeline', icon: '📅' },
  { value: 'briefing', label: 'Briefing', icon: '📋' },
]

type Props = {
  notebookId: number
  notebookName: string
}

export default function ContentGenerationModal({ notebookId, notebookName }: Props) {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState<ContentType | null>(null)
  const [content, setContent] = useState('')
  const [selectedType, setSelectedType] = useState<ContentType | null>(null)

  const generate = async (type: ContentType) => {
    setLoading(type)
    setContent('')
    setSelectedType(type)
    try {
      const res = await api.post(`/notebooks/${notebookId}/generate-content`, { type })
      setContent(res.data.content ?? '')
    } catch {
      toast.error('Content generation failed')
      setOpen(false)
    } finally {
      setLoading(null)
    }
  }

  const handleDownload = () => {
    if (!content || !selectedType) return
    const label = TYPES.find((t) => t.value === selectedType)?.label ?? selectedType
    const blob = new Blob([content], { type: 'text/markdown' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${notebookName}-${label}.md`.toLowerCase().replace(/\s+/g, '-')
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <>
      {/* Trigger button */}
      <div className="relative group">
        <button
          onClick={() => setOpen(true)}
          className="border border-white/10 hover:border-violet-500/40 text-slate-400 hover:text-violet-300 rounded-lg px-3 py-1.5 text-xs transition-all flex items-center gap-1.5"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Generate
        </button>
      </div>

      {/* Modal */}
      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            onClick={(e) => { if (e.target === e.currentTarget) { setOpen(false); setContent('') } }}
          >
            <motion.div
              initial={{ scale: 0.95, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.95, opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="backdrop-blur-xl bg-slate-900/90 border border-white/10 rounded-2xl w-full max-w-2xl max-h-[80vh] flex flex-col shadow-2xl shadow-black/50"
            >
              <div className="flex items-center justify-between p-4 border-b border-white/10">
                <h2 className="text-sm font-semibold text-white">Generate Content</h2>
                <button
                  onClick={() => { setOpen(false); setContent('') }}
                  className="text-slate-400 hover:text-white transition-colors"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              {!content && !loading && (
                <div className="p-4 grid grid-cols-2 gap-2">
                  {TYPES.map((type) => (
                    <button
                      key={type.value}
                      onClick={() => generate(type.value)}
                      className="backdrop-blur-sm bg-white/5 border border-white/10 hover:border-violet-500/40 hover:bg-violet-500/10 rounded-xl p-4 text-left transition-all group"
                    >
                      <div className="text-2xl mb-2">{type.icon}</div>
                      <div className="text-sm font-medium text-white group-hover:text-violet-300 transition-colors">{type.label}</div>
                      <div className="text-xs text-slate-500 mt-0.5">
                        {type.value === 'study_guide' && 'Structured notes with key concepts'}
                        {type.value === 'faq' && 'Common questions and answers'}
                        {type.value === 'timeline' && 'Chronological sequence of events'}
                        {type.value === 'briefing' && 'Executive summary of key points'}
                      </div>
                    </button>
                  ))}
                </div>
              )}

              {loading && (
                <div className="flex flex-col items-center justify-center p-12 gap-3">
                  <div className="w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full animate-spin" />
                  <p className="text-sm text-slate-400">Generating {TYPES.find((t) => t.value === loading)?.label}…</p>
                </div>
              )}

              {content && (
                <>
                  <div className="flex-1 overflow-y-auto p-4">
                    <pre className="text-xs text-slate-200 whitespace-pre-wrap leading-relaxed font-sans">{content}</pre>
                  </div>
                  <div className="flex gap-2 p-4 border-t border-white/10">
                    <button
                      onClick={() => { setContent(''); setSelectedType(null) }}
                      className="border border-white/10 hover:border-white/20 text-slate-400 hover:text-white rounded-lg px-3 py-1.5 text-xs transition-all"
                    >
                      ← Back
                    </button>
                    <button
                      onClick={() => { navigator.clipboard.writeText(content); toast.success('Copied!') }}
                      className="border border-white/10 hover:border-white/20 text-slate-400 hover:text-white rounded-lg px-3 py-1.5 text-xs transition-all"
                    >
                      Copy
                    </button>
                    <button
                      onClick={handleDownload}
                      className="bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white rounded-lg px-3 py-1.5 text-xs font-medium transition-all ml-auto"
                    >
                      Download .md
                    </button>
                  </div>
                </>
              )}
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  )
}
