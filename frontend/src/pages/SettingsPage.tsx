import { useActionState, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { api } from '../lib/api'
import { useAuth } from '../hooks/useAuth'

type FieldState = { error: string | null }

export function SettingsPage() {
  const { logout } = useAuth()
  const navigate = useNavigate()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const [profileState, profileAction, profilePending] = useActionState<FieldState, FormData>(
    async (_prev, formData) => {
      try {
        await api.patch('/user', {
          name: formData.get('name'),
          email: formData.get('email'),
        })
        toast.success('Profile updated')
        return { error: null }
      } catch {
        toast.error('Failed to update profile')
        return { error: 'Failed to update profile' }
      }
    },
    { error: null },
  )

  const [passwordState, passwordAction, passwordPending] = useActionState<FieldState, FormData>(
    async (_prev, formData) => {
      try {
        await api.put('/user/password', {
          current_password: formData.get('current_password'),
          password: formData.get('password'),
          password_confirmation: formData.get('password_confirmation'),
        })
        toast.success('Password updated')
        return { error: null }
      } catch (err: unknown) {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to update password'
        toast.error(msg)
        return { error: msg }
      }
    },
    { error: null },
  )

  const handleDeleteAccount = async () => {
    try {
      await api.delete('/user')
      logout()
      navigate('/login')
    } catch {
      toast.error('Failed to delete account')
    }
  }

  return (
    <div className="mx-auto max-w-lg space-y-8 p-6 text-slate-50">
      <h1 className="text-xl font-semibold">Settings</h1>

      {/* Profile */}
      <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 className="mb-4 text-sm font-semibold text-slate-300">Profile</h2>
        <form action={profileAction} className="space-y-3 text-sm">
          {profileState.error && (
            <p className="text-xs text-red-400">{profileState.error}</p>
          )}
          <div>
            <label className="mb-1 block text-xs text-slate-400">Name</label>
            <input name="name" className="input-field" />
          </div>
          <div>
            <label className="mb-1 block text-xs text-slate-400">Email</label>
            <input name="email" type="email" className="input-field" />
          </div>
          <button
            type="submit"
            disabled={profilePending}
            className="rounded bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            {profilePending ? 'Saving…' : 'Save profile'}
          </button>
        </form>
      </section>

      {/* Password */}
      <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 className="mb-4 text-sm font-semibold text-slate-300">Change Password</h2>
        <form action={passwordAction} className="space-y-3 text-sm">
          {passwordState.error && (
            <p className="text-xs text-red-400">{passwordState.error}</p>
          )}
          <div>
            <label className="mb-1 block text-xs text-slate-400">Current password</label>
            <input name="current_password" type="password" className="input-field" required />
          </div>
          <div>
            <label className="mb-1 block text-xs text-slate-400">New password</label>
            <input name="password" type="password" className="input-field" required />
          </div>
          <div>
            <label className="mb-1 block text-xs text-slate-400">Confirm new password</label>
            <input name="password_confirmation" type="password" className="input-field" required />
          </div>
          <button
            type="submit"
            disabled={passwordPending}
            className="rounded bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            {passwordPending ? 'Updating…' : 'Update password'}
          </button>
        </form>
      </section>

      {/* Danger zone */}
      <section className="rounded-xl border border-red-900/50 bg-red-950/20 p-5">
        <h2 className="mb-2 text-sm font-semibold text-red-400">Danger Zone</h2>
        <p className="mb-3 text-xs text-slate-400">
          Deleting your account is permanent and cannot be undone.
        </p>
        {confirmDelete ? (
          <div className="flex gap-2">
            <button
              onClick={handleDeleteAccount}
              className="rounded bg-red-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-red-500"
            >
              Yes, delete my account
            </button>
            <button
              onClick={() => setConfirmDelete(false)}
              className="rounded border border-slate-700 px-4 py-1.5 text-xs text-slate-400 hover:text-slate-200"
            >
              Cancel
            </button>
          </div>
        ) : (
          <button
            onClick={() => setConfirmDelete(true)}
            className="rounded border border-red-700 px-4 py-1.5 text-xs text-red-400 hover:bg-red-900/30"
          >
            Delete account
          </button>
        )}
      </section>
    </div>
  )
}
