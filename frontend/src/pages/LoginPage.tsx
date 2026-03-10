import { motion } from 'framer-motion'
import { useActionState, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useAuth } from '../hooks/useAuth'

type FormState = { error: string | null }

const inputClass =
  'w-full bg-white/5 border border-white/10 focus:border-violet-500/60 focus:ring-2 focus:ring-violet-500/20 rounded-xl px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-600 outline-none transition-all'

export function LoginPage() {
  const { login, register } = useAuth()
  const navigate = useNavigate()
  const [mode, setMode] = useState<'login' | 'register'>('login')

  const [state, action, pending] = useActionState<FormState, FormData>(
    async (_prev, formData) => {
      const name = formData.get('name') as string
      const email = formData.get('email') as string
      const password = formData.get('password') as string
      const confirm = formData.get('confirm') as string

      try {
        if (mode === 'login') {
          await login(email, password)
        } else {
          await register(name, email, password, confirm)
        }
        toast.success(mode === 'login' ? 'Welcome back!' : 'Account created!')
        navigate('/')
        return { error: null }
      } catch {
        const msg = 'Authentication failed. Check your credentials.'
        toast.error(msg)
        return { error: msg }
      }
    },
    { error: null },
  )

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, y: 16, scale: 0.97 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.4, ease: 'easeOut' }}
        className="w-full max-w-md"
      >
        {/* Logo */}
        <div className="flex items-center justify-center gap-3 mb-8">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-900/50">
            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <span className="text-xl font-semibold text-white">NotebookLLM</span>
        </div>

        {/* Card */}
        <div className="backdrop-blur-xl bg-white/5 border border-white/10 rounded-2xl p-6 shadow-2xl shadow-black/40">
          <h1 className="text-lg font-semibold text-white mb-1">
            {mode === 'login' ? 'Welcome back' : 'Create account'}
          </h1>
          <p className="text-sm text-slate-400 mb-5">
            {mode === 'login' ? 'Sign in to your AI knowledge workspace.' : 'Start exploring your documents with AI.'}
          </p>

          {state.error && (
            <div className="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-xs text-red-300">
              {state.error}
            </div>
          )}

          <form action={action} className="space-y-3">
            {mode === 'register' && (
              <div>
                <label className="mb-1.5 block text-xs font-medium text-slate-400">Name</label>
                <input name="name" className={inputClass} placeholder="Your name" required />
              </div>
            )}

            <div>
              <label className="mb-1.5 block text-xs font-medium text-slate-400">Email</label>
              <input name="email" type="email" className={inputClass} placeholder="you@example.com" required />
            </div>

            <div>
              <label className="mb-1.5 block text-xs font-medium text-slate-400">Password</label>
              <input name="password" type="password" className={inputClass} placeholder="••••••••" required />
            </div>

            {mode === 'register' && (
              <div>
                <label className="mb-1.5 block text-xs font-medium text-slate-400">Confirm password</label>
                <input name="confirm" type="password" className={inputClass} placeholder="••••••••" required />
              </div>
            )}

            <button
              type="submit"
              disabled={pending}
              className="mt-1 w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 disabled:opacity-50 text-white rounded-xl py-2.5 text-sm font-medium transition-all shadow-lg shadow-violet-900/30"
            >
              {pending ? 'Please wait…' : mode === 'login' ? 'Sign in' : 'Create account'}
            </button>
          </form>

          <button
            type="button"
            onClick={() => setMode(mode === 'login' ? 'register' : 'login')}
            className="mt-4 w-full text-center text-xs text-slate-500 hover:text-slate-300 transition-colors"
          >
            {mode === 'login' ? "Don't have an account? Sign up" : 'Already have an account? Sign in'}
          </button>
        </div>
      </motion.div>
    </div>
  )
}
