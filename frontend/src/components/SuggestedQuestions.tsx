import { motion } from 'framer-motion'
import { useEffect, useState } from 'react'
import api from '../lib/api'

type Props = {
  notebookId: number
  lastAnswer: string | null
  onSelect: (question: string) => void
}

export default function SuggestedQuestions({ notebookId, lastAnswer, onSelect }: Props) {
  const [questions, setQuestions] = useState<string[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!lastAnswer) {
      setQuestions([])
      return
    }

    let cancelled = false
    setLoading(true)
    setQuestions([])

    api
      .post('/chat/suggest-questions', { notebook_id: notebookId, last_answer: lastAnswer })
      .then((res) => {
        if (!cancelled) setQuestions(res.data.questions ?? [])
      })
      .catch(() => {})
      .finally(() => { if (!cancelled) setLoading(false) })

    return () => { cancelled = true }
  }, [lastAnswer, notebookId])

  if (loading) {
    return (
      <div className="flex gap-1.5 px-4 pb-2">
        {[1, 2, 3].map((i) => (
          <div
            key={i}
            className="h-7 rounded-full bg-white/5 border border-white/10 animate-pulse"
            style={{ width: `${60 + i * 20}px` }}
          />
        ))}
      </div>
    )
  }

  if (questions.length === 0) return null

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      className="flex flex-wrap gap-2 px-4 pb-3"
    >
      {questions.map((q, i) => (
        <motion.button
          key={i}
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: i * 0.08 }}
          onClick={() => onSelect(q)}
          className="backdrop-blur-sm bg-white/5 border border-white/10 hover:border-violet-500/40 hover:bg-violet-500/10 text-slate-300 hover:text-violet-300 rounded-full px-3 py-1.5 text-xs transition-all duration-200"
        >
          {q}
        </motion.button>
      ))}
    </motion.div>
  )
}
