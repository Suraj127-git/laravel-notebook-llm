import { useCallback, useState } from 'react'
import { ChatPanel } from '../components/ChatPanel'
import { NotebookSidebar } from '../components/NotebookSidebar'
import StudioPanel from '../components/StudioPanel'
import TopNav from '../components/TopNav'
import type { AudioOverviewUpdate } from '../hooks/useDocumentChannel'
import { useNotebooks } from '../hooks/useNotebooks'

export function DashboardPage() {
  const { notebooks, loading, selected, setSelected, create, destroy } = useNotebooks()
  const [audioReadySignal, setAudioReadySignal] = useState<AudioOverviewUpdate | null>(null)

  const handleAudioReady = useCallback((update: AudioOverviewUpdate) => {
    setAudioReadySignal(update)
  }, [])

  return (
    <div className="flex flex-col h-screen overflow-hidden">
      <TopNav notebook={selected} />

      <div className="flex flex-1 overflow-hidden">
        {/* Sources sidebar */}
        <NotebookSidebar
          notebooks={notebooks}
          selected={selected}
          loading={loading}
          onSelect={setSelected}
          onCreate={create}
          onDelete={destroy}
          onAudioReady={handleAudioReady}
        />

        {/* Chat area */}
        <main className="flex flex-1 overflow-hidden min-w-0">
          {selected ? (
            <ChatPanel notebookId={selected.id} notebookName={selected.name} />
          ) : (
            <div className="flex flex-1 items-center justify-center">
              <div className="text-center space-y-3">
                <div className="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto">
                  <svg className="w-8 h-8 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                  </svg>
                </div>
                <p className="text-sm text-slate-400">
                  {loading ? 'Loading notebooks…' : 'Select or create a notebook to get started'}
                </p>
              </div>
            </div>
          )}
        </main>

        {/* Studio panel */}
        {selected && (
          <StudioPanel
            notebookId={selected.id}
            notebookName={selected.name}
            audioReadySignal={audioReadySignal}
          />
        )}
      </div>
    </div>
  )
}
