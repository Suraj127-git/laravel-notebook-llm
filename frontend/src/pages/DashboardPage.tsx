import { motion } from 'framer-motion'
import { ChatPanel } from '../components/ChatPanel'
import { DocumentUpload } from '../components/DocumentUpload'
import { NotebookSidebar } from '../components/NotebookSidebar'
import { useNotebooks } from '../hooks/useNotebooks'

export function DashboardPage() {
  const { notebooks, loading, selected, setSelected, create, destroy } = useNotebooks()

  return (
    <div className="flex h-screen bg-slate-950 text-slate-50">
      {/* Sidebar */}
      <aside className="hidden w-56 flex-col border-r border-slate-800 p-3 md:flex">
        <NotebookSidebar
          notebooks={notebooks}
          selected={selected}
          loading={loading}
          onSelect={setSelected}
          onCreate={create}
          onDelete={destroy}
        />
      </aside>

      <motion.main
        initial={{ opacity: 0, x: 24 }}
        animate={{ opacity: 1, x: 0 }}
        className="flex flex-1 flex-col"
      >
        <header className="flex items-center justify-between border-b border-slate-800 px-4 py-3">
          <h1 className="text-sm font-semibold">
            {selected ? `${selected.emoji} ${selected.name}` : 'NotebookLLM'}
          </h1>
          <span className="text-xs text-slate-400">
            {selected?.documents_count ?? 0} doc{(selected?.documents_count ?? 0) !== 1 ? 's' : ''}
          </span>
        </header>

        <div className="flex flex-1 overflow-hidden">
          <section className="flex flex-1 flex-col border-r border-slate-800">
            {selected ? (
              <ChatPanel notebookId={selected.id} notebookName={selected.name} />
            ) : (
              <div className="flex flex-1 items-center justify-center text-sm text-slate-500">
                {loading ? 'Loading…' : 'Create a notebook to get started'}
              </div>
            )}
          </section>

          <section className="hidden w-80 flex-col lg:flex">
            {selected && <DocumentUpload notebookId={selected.id} />}
          </section>
        </div>
      </motion.main>
    </div>
  )
}

