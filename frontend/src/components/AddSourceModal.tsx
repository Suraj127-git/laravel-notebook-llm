import { AnimatePresence, motion } from 'framer-motion'
import { useRef, useState } from 'react'
import { toast } from 'sonner'
import api from '../lib/api'

type Props = {
  notebookId: number
  onAdded: () => void
}

type Tab = 'file' | 'url'

const ACCEPTED = '.pdf,.txt,.docx,.csv,.xlsx'

export default function AddSourceModal({ notebookId, onAdded }: Props) {
  const [open, setOpen] = useState(false)
  const [tab, setTab] = useState<Tab>('file')
  const [url, setUrl] = useState('')
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const close = () => { setOpen(false); setUrl('') }

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    const form = new FormData()
    form.append('file', file)
    form.append('notebook_id', String(notebookId))

    setUploading(true)
    try {
      await api.post('/documents', form, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast.success('File uploaded — processing started')
      onAdded()
      close()
    } catch {
      toast.error('Upload failed')
    } finally {
      setUploading(false)
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  const handleUrl = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!url.trim()) return

    setUploading(true)
    try {
      await api.post('/documents/url', { url: url.trim(), notebook_id: notebookId })
      toast.success('URL imported — processing started')
      onAdded()
      close()
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? 'Failed to import URL')
    } finally {
      setUploading(false)
    }
  }

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="w-full border border-dashed border-white/20 hover:border-violet-500/50 hover:bg-violet-500/5 text-slate-400 hover:text-violet-300 rounded-xl py-2.5 text-xs transition-all flex items-center justify-center gap-1.5"
      >
        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
        Add source
      </button>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            onClick={(e) => { if (e.target === e.currentTarget) close() }}
          >
            <motion.div
              initial={{ scale: 0.95, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.95, opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="backdrop-blur-xl bg-slate-900/90 border border-white/10 rounded-2xl w-full max-w-md shadow-2xl shadow-black/50"
            >
              <div className="flex items-center justify-between p-4 border-b border-white/10">
                <h2 className="text-sm font-semibold text-white">Add Source</h2>
                <button onClick={close} className="text-slate-400 hover:text-white transition-colors">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              {/* Tabs */}
              <div className="flex gap-1 p-3 border-b border-white/10">
                {(['file', 'url'] as const).map((t) => (
                  <button
                    key={t}
                    onClick={() => setTab(t)}
                    className={`flex-1 py-1.5 text-xs font-medium rounded-lg transition-all ${
                      tab === t
                        ? 'bg-violet-600/20 text-violet-300 border border-violet-500/30'
                        : 'text-slate-500 hover:text-slate-300 hover:bg-white/5'
                    }`}
                  >
                    {t === 'file' ? '📄 Upload File' : '🌐 Website URL'}
                  </button>
                ))}
              </div>

              <div className="p-4">
                {tab === 'file' ? (
                  <label className="flex flex-col items-center justify-center border-2 border-dashed border-white/20 hover:border-violet-500/40 rounded-xl py-8 px-4 text-center cursor-pointer transition-colors group">
                    <svg className="w-8 h-8 text-slate-500 group-hover:text-violet-400 mb-2 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p className="text-sm text-slate-300">
                      {uploading ? 'Uploading…' : 'Drop file or click to browse'}
                    </p>
                    <p className="text-xs text-slate-500 mt-1">PDF, TXT, DOCX, CSV, XLSX (max 10 MB)</p>
                    <input
                      ref={fileRef}
                      type="file"
                      accept={ACCEPTED}
                      onChange={handleFile}
                      disabled={uploading}
                      className="hidden"
                    />
                  </label>
                ) : (
                  <form onSubmit={handleUrl} className="space-y-3">
                    <input
                      type="url"
                      value={url}
                      onChange={(e) => setUrl(e.target.value)}
                      placeholder="https://example.com/article"
                      required
                      className="w-full bg-white/5 border border-white/10 focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/30 rounded-xl px-3 py-2.5 text-sm text-slate-200 placeholder:text-slate-600 outline-none transition-colors"
                    />
                    <button
                      type="submit"
                      disabled={uploading || !url.trim()}
                      className="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 disabled:opacity-50 text-white rounded-xl py-2.5 text-sm font-medium transition-all"
                    >
                      {uploading ? 'Importing…' : 'Import URL'}
                    </button>
                  </form>
                )}
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  )
}
