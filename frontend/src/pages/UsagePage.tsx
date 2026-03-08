import { useEffect, useState } from 'react'
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

export function UsagePage() {
  const [rows, setRows] = useState<UsageRow[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api
      .get<UsageRow[]>('/usage')
      .then(({ data }) => setRows(data))
      .catch(() => toast.error('Failed to load usage data'))
      .finally(() => setLoading(false))
  }, [])

  // Aggregate tokens per month for the chart
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

  if (loading) {
    return <div className="p-6 text-sm text-slate-400">Loading usage data…</div>
  }

  return (
    <div className="mx-auto max-w-3xl space-y-8 p-6 text-slate-50">
      <h1 className="text-xl font-semibold">AI Usage</h1>

      {rows.length === 0 ? (
        <p className="text-sm text-slate-400">No usage data yet. Start chatting!</p>
      ) : (
        <>
          {/* Chart */}
          <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
            <h2 className="mb-4 text-sm font-semibold text-slate-300">Monthly Tokens</h2>
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={chartData}>
                <XAxis dataKey="month" stroke="#94a3b8" tick={{ fontSize: 11 }} />
                <YAxis stroke="#94a3b8" tick={{ fontSize: 11 }} />
                <Tooltip
                  contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #334155' }}
                  labelStyle={{ color: '#e2e8f0' }}
                />
                <Bar dataKey="tokens" fill="#6366f1" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </section>

          {/* Table */}
          <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
            <h2 className="mb-4 text-sm font-semibold text-slate-300">Breakdown</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b border-slate-800 text-left text-slate-400">
                    <th className="pb-2 pr-4">Month</th>
                    <th className="pb-2 pr-4">Provider</th>
                    <th className="pb-2 pr-4">Model</th>
                    <th className="pb-2 pr-4">Op</th>
                    <th className="pb-2 pr-4 text-right">Tokens In</th>
                    <th className="pb-2 pr-4 text-right">Tokens Out</th>
                    <th className="pb-2 pr-4 text-right">Reqs</th>
                    <th className="pb-2 text-right">Cost ($)</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, i) => (
                    <tr key={i} className="border-b border-slate-800/50 text-slate-300">
                      <td className="py-1.5 pr-4">{row.month}</td>
                      <td className="pr-4">{row.provider}</td>
                      <td className="pr-4 font-mono text-[10px]">{row.model}</td>
                      <td className="pr-4">{row.operation}</td>
                      <td className="pr-4 text-right">{row.tokens_in?.toLocaleString() ?? '—'}</td>
                      <td className="pr-4 text-right">{row.tokens_out?.toLocaleString() ?? '—'}</td>
                      <td className="pr-4 text-right">{row.requests}</td>
                      <td className="text-right">{row.cost_usd?.toFixed(4) ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </div>
  )
}
