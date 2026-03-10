import { useCallback, useEffect, useRef, useState, type ChangeEvent } from 'react'
import { api } from '../lib/api'

type DocRecord = {
  id: number
  title: string
  status: 'uploaded' | 'processing' | 'ready' | 'failed'
}

const TERMINAL = new Set<DocRecord['status']>(['ready', 'failed'])

export function DocumentUpload({ notebookId }: { notebookId: number }) {
  const [docs, setDocs] = useState<DocRecord[]>([])
  const [uploading, setUploading] = useState(false)
  const [deleting, setDeleting] = useState<number | null>(null)
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const fetchDocs = useCallback(async () => {
    const { data } = await api.get<DocRecord[]>('/documents', {
      params: { notebook_id: notebookId },
    })
    setDocs(data)
    return data
  }, [notebookId])

  // Load docs when notebook changes
  useEffect(() => {
    fetchDocs()
  }, [fetchDocs])

  // Poll while any doc is non-terminal
  useEffect(() => {
    const hasPending = docs.some((d) => !TERMINAL.has(d.status))

    if (hasPending && !pollRef.current) {
      pollRef.current = setInterval(fetchDocs, 3000)
    } else if (!hasPending && pollRef.current) {
      clearInterval(pollRef.current)
      pollRef.current = null
    }

    return () => {
      if (pollRef.current) {
        clearInterval(pollRef.current)
        pollRef.current = null
      }
    }
  }, [docs, fetchDocs])

  const handleDelete = async (doc: DocRecord) => {
    if (!confirm(`Remove "${doc.title}"?`)) return
    setDeleting(doc.id)
    try {
      await api.delete(`/documents/${doc.id}`)
      setDocs((prev) => prev.filter((d) => d.id !== doc.id))
    } finally {
      setDeleting(null)
    }
  }

  const handleFileChange = async (e: ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files?.length) return
    setUploading(true)

    try {
      const file = e.target.files[0]
      const form = new FormData()
      form.append('file', file)
      form.append('notebook_id', String(notebookId))

      const { data } = await api.post<DocRecord>('/documents', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      setDocs((prev) => [data, ...prev])
    } finally {
      setUploading(false)
      e.target.value = ''
    }
  }

  return (
    <div className="flex h-full flex-col border-l border-slate-800">
      <div className="border-b border-slate-800 p-4">
        <h2 className="mb-2 text-sm font-semibold text-slate-100">Documents</h2>
        <label className="flex cursor-pointer items-center justify-center rounded-lg border border-dashed border-slate-600 px-3 py-2 text-xs text-slate-300 hover:border-slate-400">
          <input
            type="file"
            className="hidden"
            accept=".pdf,.txt,.docx,.csv,.xlsx"
            onChange={handleFileChange}
          />
          {uploading ? 'Uploading…' : 'Upload document'}
        </label>
      </div>

      <div className="flex-1 space-y-2 overflow-y-auto p-3 text-xs">
        {docs.map((doc) => (
          <div
            key={doc.id}
            className="flex items-center justify-between rounded border border-slate-800 bg-slate-900 px-2 py-1"
          >
            <span className="max-w-[8rem] truncate">{doc.title}</span>
            <div className="flex items-center gap-2">
              <span
                className={
                  {
                    uploaded: 'text-yellow-400',
                    processing: 'text-blue-400 animate-pulse',
                    ready: 'text-emerald-400',
                    failed: 'text-red-400',
                  }[doc.status]
                }
              >
                {doc.status}
              </span>
              <button
                onClick={() => handleDelete(doc)}
                disabled={deleting === doc.id}
                className="text-slate-500 hover:text-red-400 disabled:opacity-40"
                title="Remove document"
              >
                {deleting === doc.id ? '…' : '✕'}
              </button>
            </div>
          </div>
        ))}

        {!docs.length && <p className="text-slate-500">No documents yet.</p>}
      </div>
    </div>
  )
}
