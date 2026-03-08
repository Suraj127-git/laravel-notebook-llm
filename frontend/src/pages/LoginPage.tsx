import { useActionState, useState } from 'react'
import { motion } from 'framer-motion'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useAuth } from '../hooks/useAuth'

type FormState = { error: string | null }

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
    <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4 text-slate-50">
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-black/40"
      >
        <h1 className="mb-1 text-xl font-semibold">
          {mode === 'login' ? 'Sign in to your notebook' : 'Create your notebook account'}
        </h1>
        <p className="mb-4 text-sm text-slate-400">
          AI-powered knowledge workspace built with Laravel &amp; React.
        </p>

        {state.error && (
          <div className="mb-3 rounded-lg border border-red-500/60 bg-red-500/10 px-3 py-2 text-xs text-red-200">
            {state.error}
          </div>
        )}

        <form action={action} className="space-y-3 text-sm">
          {mode === 'register' && (
            <div>
              <label className="mb-1 block text-xs font-medium text-slate-300">Name</label>
              <input
                name="name"
                className="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-50 outline-none ring-indigo-500 focus:ring-2"
                required
              />
            </div>
          )}

          <div>
            <label className="mb-1 block text-xs font-medium text-slate-300">Email</label>
            <input
              name="email"
              type="email"
              className="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-50 outline-none ring-indigo-500 focus:ring-2"
              required
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-slate-300">Password</label>
            <input
              name="password"
              type="password"
              className="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-50 outline-none ring-indigo-500 focus:ring-2"
              required
            />
          </div>

          {mode === 'register' && (
            <div>
              <label className="mb-1 block text-xs font-medium text-slate-300">
                Confirm password
              </label>
              <input
                name="confirm"
                type="password"
                className="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-50 outline-none ring-indigo-500 focus:ring-2"
                required
              />
            </div>
          )}

          <button
            type="submit"
            disabled={pending}
            className="mt-2 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            {pending ? 'Please wait…' : mode === 'login' ? 'Sign in' : 'Create account'}
          </button>
        </form>

        <button
          type="button"
          onClick={() => setMode(mode === 'login' ? 'register' : 'login')}
          className="mt-4 w-full text-center text-xs text-slate-400 hover:text-slate-200"
        >
          {mode === 'login'
            ? "Don't have an account? Sign up"
            : 'Already have an account? Sign in'}
        </button>
      </motion.div>
    </div>
  )
}
