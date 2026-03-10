import { motion } from 'framer-motion'

type WelcomeScreenProps = {
  onSelect: (prompt: string) => void
}

const PROMPTS = [
  'Summarize all sources',
  'What are the key themes?',
  'Create a study guide',
  'Generate a FAQ',
]

export default function WelcomeScreen({ onSelect }: WelcomeScreenProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, ease: 'easeOut' }}
      className="flex flex-col items-center justify-center h-full px-6 text-center gap-8"
    >
      <div className="space-y-3">
        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center mx-auto shadow-lg shadow-violet-900/40">
          <svg className="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
          </svg>
        </div>
        <h2 className="text-2xl font-semibold text-white">What would you like to explore?</h2>
        <p className="text-slate-400 text-sm max-w-sm">
          Upload sources and ask questions, or try one of these prompts to get started.
        </p>
      </div>

      <div className="grid grid-cols-2 gap-2 w-full max-w-sm">
        {PROMPTS.map((prompt) => (
          <motion.button
            key={prompt}
            onClick={() => onSelect(prompt)}
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            className="backdrop-blur-sm bg-white/5 border border-white/10 hover:border-violet-500/40 hover:bg-white/8 text-slate-300 hover:text-white rounded-xl px-3 py-2.5 text-xs text-left transition-all duration-200"
          >
            {prompt}
          </motion.button>
        ))}
      </div>
    </motion.div>
  )
}
