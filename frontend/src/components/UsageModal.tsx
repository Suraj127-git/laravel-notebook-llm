import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { toast } from 'sonner'
import { api } from '../lib/api'

type UsageRow = {
  provider: string
  model: string
  operation: string
  tokens_in: number
  tokens_out: number
  cost_usd: number
  requests: number
  month: string
}

export default function UsageModal() {
  const [open, setOpen] = useState(false)
  const [rows, setRows] = useState<UsageRow[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!open) return
    setLoading(true)
    api
      .get<UsageRow[]>('/usage')
      .then(({ data }) => setRows(data))
      .catch(() => toast.error('Failed to load usage data'))
      .finally(() => setLoading(false))
  }, [open])

  // Lock body scroll while open
  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : ''
    return () => { document.body.style.overflow = '' }
  }, [open])

  const chartData = Object.values(
    rows.reduce<Record<string, { month: string; tokens: number; cost: number }>>(
      (acc, row) => {
        const key = row.month
        if (!acc[key]) acc[key] = { month: key, tokens: 0, cost: 0 }
        acc[key].tokens += (row.tokens_in ?? 0) + (row.tokens_out ?? 0)
        acc[key].cost += row.cost_usd ?? 0
        return acc
      },
      {},
    ),
  ).sort((a, b) => a.month.localeCompare(b.month))

  const modal = (
    <AnimatePresence>
      {open && (
        <motion.div
          style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
        >
          {/* Backdrop */}
          <div
            style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(4px)' }}
            onClick={() => setOpen(false)}
          />

          {/* Panel */}
          <motion.div
            style={{ position: 'relative', zIndex: 1, width: '100%', maxWidth: '672px', maxHeight: '85vh', overflowY: 'auto', borderRadius: '1rem', border: '1px solid rgba(139,92,246,0.25)', background: 'linear-gradient(145deg, #1a1035 0%, #130d2a 100%)', boxShadow: '0 25px 60px rgba(109,40,217,0.25), 0 0 0 1px rgba(139,92,246,0.1)' }}
            initial={{ scale: 0.95, opacity: 0, y: 12 }}
            animate={{ scale: 1, opacity: 1, y: 0 }}
            exit={{ scale: 0.95, opacity: 0, y: 12 }}
            transition={{ duration: 0.18 }}
          >
            {/* Header */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '1rem 1.25rem', borderBottom: '1px solid rgba(139,92,246,0.2)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <svg style={{ width: '16px', height: '16px', color: '#818cf8' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span style={{ fontSize: '0.875rem', fontWeight: 600, color: '#f1f5f9' }}>AI Usage</span>
              </div>
              <button
                onClick={() => setOpen(false)}
                style={{ color: '#94a3b8', padding: '0.25rem', borderRadius: '0.5rem', background: 'transparent', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center' }}
                onMouseEnter={e => (e.currentTarget.style.color = '#f1f5f9')}
                onMouseLeave={e => (e.currentTarget.style.color = '#94a3b8')}
              >
                <svg style={{ width: '16px', height: '16px' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Body */}
            <div style={{ padding: '1.25rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {loading ? (
                <p style={{ fontSize: '0.875rem', color: '#94a3b8', textAlign: 'center', padding: '2rem 0' }}>Loading usage data…</p>
              ) : rows.length === 0 ? (
                <p style={{ fontSize: '0.875rem', color: '#94a3b8', textAlign: 'center', padding: '2rem 0' }}>No usage data yet. Start chatting!</p>
              ) : (
                <>
                  {/* Chart */}
                  <section style={{ borderRadius: '0.75rem', border: '1px solid rgba(139,92,246,0.2)', background: 'rgba(30,16,60,0.6)', padding: '1rem' }}>
                    <h3 style={{ fontSize: '0.7rem', fontWeight: 600, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '0.75rem' }}>Monthly Tokens</h3>
                    <ResponsiveContainer width="100%" height={170}>
                      <BarChart data={chartData}>
                        <XAxis dataKey="month" stroke="#6d28d9" tick={{ fill: '#a78bfa', fontSize: 11 }} />
                        <YAxis stroke="#6d28d9" tick={{ fill: '#a78bfa', fontSize: 11 }} />
                        <Tooltip
                          contentStyle={{ backgroundColor: '#1e1040', border: '1px solid rgba(139,92,246,0.4)', borderRadius: '8px' }}
                          labelStyle={{ color: '#e2e8f0', fontSize: 12 }}
                          itemStyle={{ color: '#c4b5fd' }}
                        />
                        <Bar dataKey="tokens" fill="#7c3aed" radius={[4, 4, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </section>

                  {/* Table */}
                  <section style={{ borderRadius: '0.75rem', border: '1px solid rgba(139,92,246,0.2)', background: 'rgba(30,16,60,0.6)', padding: '1rem' }}>
                    <h3 style={{ fontSize: '0.7rem', fontWeight: 600, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '0.75rem' }}>Breakdown</h3>
                    <div style={{ overflowX: 'auto' }}>
                      <table style={{ width: '100%', fontSize: '0.75rem', borderCollapse: 'collapse' }}>
                        <thead>
                          <tr style={{ borderBottom: '1px solid rgba(139,92,246,0.2)', color: '#a78bfa', textAlign: 'left' }}>
                            {['Month', 'Provider', 'Model', 'Op', 'Tokens In', 'Tokens Out', 'Reqs', 'Cost ($)'].map((h, i) => (
                              <th key={h} style={{ paddingBottom: '0.5rem', paddingRight: i < 7 ? '1rem' : 0, textAlign: i >= 4 ? 'right' : 'left', fontWeight: 500 }}>{h}</th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {rows.map((row, i) => (
                            <tr key={i} style={{ borderBottom: '1px solid rgba(139,92,246,0.12)', color: '#e2d9f3' }}>
                              <td style={{ padding: '0.4rem 1rem 0.4rem 0' }}>{row.month}</td>
                              <td style={{ paddingRight: '1rem' }}>{row.provider}</td>
                              <td style={{ paddingRight: '1rem', fontFamily: 'monospace', fontSize: '10px', color: '#c4b5fd' }}>{row.model}</td>
                              <td style={{ paddingRight: '1rem' }}>{row.operation}</td>
                              <td style={{ paddingRight: '1rem', textAlign: 'right' }}>{row.tokens_in?.toLocaleString() ?? '—'}</td>
                              <td style={{ paddingRight: '1rem', textAlign: 'right' }}>{row.tokens_out?.toLocaleString() ?? '—'}</td>
                              <td style={{ paddingRight: '1rem', textAlign: 'right' }}>{row.requests}</td>
                              <td style={{ textAlign: 'right' }}>{row.cost_usd != null ? row.cost_usd.toFixed(4) : '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </section>
                </>
              )}
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  )

  return (
    <>
      {/* Trigger button */}
      <button
        onClick={() => setOpen(true)}
        className="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-white/5 transition-colors"
        title="AI Usage"
      >
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
      </button>

      {/* Portal: renders directly on <body>, escaping any stacking context */}
      {createPortal(modal, document.body)}
    </>
  )
}
