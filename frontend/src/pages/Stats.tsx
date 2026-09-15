import {
  ResponsiveContainer,
  ComposedChart,
  Bar,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  PieChart,
  Pie,
  Cell,
  Legend,
} from 'recharts'
import { BarChart3, TrendingUp, Wallet, ShoppingCart } from 'lucide-react'
import { useStats } from '@/hooks/useApiHooks'
import { formatMontantCourt, formatMontant, formatNumber } from '@/lib/format'

const CHART_COLORS = ['#0284c7', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']

export default function Stats() {
  const { data: stats, isLoading } = useStats()

  if (isLoading || !stats) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center text-slate-400">
        <p className="animate-pulse">Chargement des statistiques…</p>
      </div>
    )
  }

  const cards = [
    {
      label: 'Achats (30 j)',
      value: formatMontantCourt(stats.totaux_periode.montant_achats),
      icon: <ShoppingCart className="h-5 w-5 text-sky-600 dark:text-sky-400" />,
      detail: `${formatNumber(stats.totaux_periode.poids_total)} kg achetés`,
    },
    {
      label: 'Charges (30 j)',
      value: formatMontantCourt(stats.totaux_periode.charges_total),
      icon: <Wallet className="h-5 w-5 text-amber-600 dark:text-amber-400" />,
      detail: 'Glace, transport, libres',
    },
    {
      label: 'Total dépenses (30 j)',
      value: formatMontantCourt(stats.totaux_periode.total_depenses),
      icon: <TrendingUp className="h-5 w-5 text-rose-600 dark:text-rose-400" />,
      detail: `${formatDateRange(stats.totaux_periode)}`,
    },
  ]

  const series = stats.series_7j.map((day) => ({
    date: new Date(day.date.replace(/-/g, '/')).toLocaleDateString('fr-FR', { weekday: 'short' }),
    achats: day.achats.montant_total,
    charges: day.charges.total,
    total: day.total_depenses,
  }))

  const parType = (stats.today.achats.par_type ?? []).map((entry) => ({
    name: entry.nom,
    value: entry.montant_total,
  }))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <BarChart3 className="h-6 w-6 text-sky-600 dark:text-sky-400" />
          Statistiques
        </h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">Vue d’ensemble sur 30 jours et aujourd’hui</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {cards.map((card) => (
          <div key={card.label} className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-slate-500 dark:text-slate-400">{card.label}</span>
              {card.icon}
            </div>
            <p className="text-xl font-bold text-slate-900 dark:text-white">{card.value}</p>
            <p className="text-xs text-slate-400 mt-1">{card.detail}</p>
          </div>
        ))}
      </div>

      <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-4">
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white mb-4">Dépenses des 7 derniers jours</h2>
        <div className="h-72">
          <ResponsiveContainer width="100%" height="100%">
            <ComposedChart data={series}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" className="dark:stroke-slate-800" />
              <XAxis dataKey="date" tick={{ fontSize: 12 }} stroke="#94a3b8" />
              <YAxis tick={{ fontSize: 12 }} stroke="#94a3b8" tickFormatter={(value: number) => formatNumber(value)} />
              <Tooltip
                formatter={(value, name) => [
                  formatMontant(Number(value ?? 0)),
                  name === 'achats' ? 'Achats' : name === 'charges' ? 'Charges' : 'Total',
                ]}
              />
              <Legend />
              <Bar dataKey="achats" fill="#0284c7" radius={[4, 4, 0, 0]} name="Achats" />
              <Bar dataKey="charges" fill="#f59e0b" radius={[4, 4, 0, 0]} name="Charges" />
              <Line type="monotone" dataKey="total" stroke="#0f172a" strokeWidth={2} name="Total" dot={{ r: 3 }} />
            </ComposedChart>
          </ResponsiveContainer>
        </div>
      </section>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-4">
          <h2 className="text-sm font-semibold text-slate-900 dark:text-white mb-4">Répartition des achats du jour</h2>
          {parType.length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-12">Aucun achat aujourd’hui.</p>
          ) : (
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={parType}
                    dataKey="value"
                    nameKey="name"
                    innerRadius={55}
                    outerRadius={90}
                    paddingAngle={3}
                    label={(entry) => `${entry.name}`}
                  >
                    {parType.map((_, index) => (
                      <Cell key={index} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value) => formatMontant(Number(value ?? 0))} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          )}
        </section>

        <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-4">
          <h2 className="text-sm font-semibold text-slate-900 dark:text-white mb-4">Détail du jour</h2>
          <dl className="space-y-3">
            <DetailRow label="Sources d’achat" value={`${stats.today.achats.nb_sources}`} />
            <DetailRow label="Lignes d’achat" value={`${stats.today.achats.nb_lignes}`} />
            <DetailRow label="Poids total" value={`${formatNumber(stats.today.achats.poids_total)} kg`} />
            <DetailRow label="Montant achats" value={formatMontant(stats.today.achats.montant_total)} />
            <DetailRow label="Glace" value={formatMontant(stats.today.charges.frais_glace)} />
            <DetailRow label="Transport" value={formatMontant(stats.today.charges.transport)} />
            <DetailRow label="Charges libres" value={formatMontant(stats.today.charges.libres_total)} />
            <DetailRow label="Total dépenses du jour" value={formatMontant(stats.today.total_depenses)} highlight />
          </dl>
        </section>
      </div>
    </div>
  )
}

function DetailRow({ label, value, highlight = false }: { label: string; value: string; highlight?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-sm text-slate-500 dark:text-slate-400">{label}</dt>
      <dd className={`text-sm ${highlight ? 'font-bold text-slate-900 dark:text-white' : 'font-medium text-slate-700 dark:text-slate-300'}`}>
        {value}
      </dd>
    </div>
  )
}

function formatDateRange({ from, to }: { from: string; to: string }): string {
  return `${new Date(from).toLocaleDateString('fr-FR')} → ${new Date(to).toLocaleDateString('fr-FR')}`
}