import { useCallback, useEffect, useState } from 'react'
import { api } from '../lib/api'

export type Notebook = {
  id: number
  name: string
  description: string | null
  emoji: string
  documents_count?: number
  created_at: string
  updated_at: string
}

type UseNotebooksReturn = {
  notebooks: Notebook[]
  loading: boolean
  selected: Notebook | null
  setSelected: (nb: Notebook) => void
  create: (name: string, emoji?: string) => Promise<Notebook>
  update: (id: number, data: Partial<Pick<Notebook, 'name' | 'description' | 'emoji'>>) => Promise<void>
  destroy: (id: number) => Promise<void>
}

export function useNotebooks(): UseNotebooksReturn {
  const [notebooks, setNotebooks] = useState<Notebook[]>([])
  const [loading, setLoading] = useState(true)
  const [selected, setSelectedState] = useState<Notebook | null>(null)

  useEffect(() => {
    api
      .get<Notebook[]>('/notebooks')
      .then(({ data }) => {
        setNotebooks(data)
        // Auto-select the first notebook (or most recently updated)
        if (data.length > 0) {
          setSelectedState(data[0])
        }
      })
      .catch(console.error)
      .finally(() => setLoading(false))
  }, [])

  const setSelected = useCallback((nb: Notebook) => {
    setSelectedState(nb)
  }, [])

  const create = useCallback(async (name: string, emoji = '📓'): Promise<Notebook> => {
    const { data } = await api.post<Notebook>('/notebooks', { name, emoji })
    setNotebooks((prev) => [data, ...prev])
    setSelectedState(data)
    return data
  }, [])

  const update = useCallback(
    async (id: number, patch: Partial<Pick<Notebook, 'name' | 'description' | 'emoji'>>) => {
      const { data } = await api.patch<Notebook>(`/notebooks/${id}`, patch)
      setNotebooks((prev) => prev.map((nb) => (nb.id === id ? { ...nb, ...data } : nb)))
      setSelectedState((prev) => (prev?.id === id ? { ...prev, ...data } : prev))
    },
    [],
  )

  const destroy = useCallback(async (id: number) => {
    await api.delete(`/notebooks/${id}`)
    setNotebooks((prev) => prev.filter((nb) => nb.id !== id))
    setSelectedState((prev) => {
      if (prev?.id !== id) return prev
      const remaining = notebooks.filter((nb) => nb.id !== id)
      return remaining[0] ?? null
    })
  }, [notebooks])

  return { notebooks, loading, selected, setSelected, create, update, destroy }
}
