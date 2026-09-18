import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Fish,
  Scale,
  Coins,
  IceCream,
  Trash2,
  Plus,
  Loader2,
  FileSpreadsheet,
  FileText,
  ShipWheel,
  Pencil,
  Check,
  X,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import api from '@/lib/api'
import { downloadExport } from '@/lib/export'
import { queryKeys, useStats, useTodayAchats, useTypesPoisson, usePecheurs, useDetaillants } from '@/hooks/useApiHooks'
import { useAuth } from '@/hooks/useAuth'
import { formatMontantCourt, formatMontant, formatPoids, formatHeureFr, formatDateFr, todayISO } from '@/lib/format'
import type { LigneAchat, SourceAchatType } from '@/lib/types'

export default function Dashboard() {
  const { canEdit } = useAuth()

  return (
    <div className="space-y-6">
      <Header />
      <Kpis />
      {canEdit ? <QuickAddForm /> : null}
      <TodayList />
    </div>
  )
}

function Header() {
  const today = new Date()
  const [from, setFrom] = useState(todayISO())
  const [to, setTo] = useState(todayISO())

  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Journée en cours</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1 capitalize">{formatDateFr(today)}</p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <div className="flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-1.5 shadow-sm">
          <label className="text-xs font-medium text-slate-500 dark:text-slate-400" htmlFor="from-date">
            Du
          </label>
          <input
            id="from-date"
            type="date"
            value={from}
            max={to}
            onChange={(event) => setFrom(event.target.value)}
            className="bg-transparent text-sm text-slate-800 dark:text-slate-200 outline-none min-w-0"
          />
          <label className="text-xs font-medium text-slate-500 dark:text-slate-400" htmlFor="to-date">
            au
          </label>
          <input
            id="to-date"
            type="date"
            value={to}
            min={from}
            onChange={(event) => setTo(event.target.value)}
            className="bg-transparent text-sm text-slate-800 dark:text-slate-200 outline-none min-w-0"
          />
        </div>
        <Button variant="outline" size="sm" onClick={() => downloadExport(`/export/excel?from=${from}&to=${to}&include_cycles=0`, `rapport-achats-${from}-${to}.xlsx`)}>
          <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
          Excel
        </Button>
        <Button variant="outline" size="sm" onClick={() => downloadExport(`/export/pdf?from=${from}&to=${to}&include_cycles=0`, `rapport-achats-${from}-${to}.pdf`)}>
          <FileText className="h-4 w-4 text-red-600" />
          PDF
        </Button>
      </div>
    </div>
  )
}

function Kpis() {
  const { data: stats, isLoading } = useStats()

  const kpis = [
    {
      label: 'Poids total',
      value: stats ? formatPoids(stats.today.achats.poids_total) : '—',
      icon: <Scale className="h-5 w-5 text-sky-600 dark:text-sky-400" />,
      detail: `${stats?.today.achats.nb_lignes ?? 0} ligne(s) · ${stats?.today.achats.nb_sources ?? 0} source(s)`,
    },
    {
      label: 'Achats',
      value: stats ? formatMontantCourt(stats.today.achats.montant_total) : '—',
      icon: <Coins className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />,
      detail: 'Montant total payé',
    },
    {
      label: 'Charges',
      value: stats ? formatMontantCourt(stats.today.charges.total) : '—',
      icon: <IceCream className="h-5 w-5 text-cyan-600 dark:text-cyan-400" />,
      detail: 'Glace + transport + libres',
    },
    {
      label: 'Dépenses totales',
      value: stats ? formatMontantCourt(stats.today.total_depenses) : '—',
      icon: <Fish className="h-5 w-5 text-slate-600 dark:text-slate-400" />,
      detail: 'Achats + charges',
    },
  ]

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
      {kpis.map((kpi) => (
        <div
          key={kpi.label}
          className={`rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm ${
            isLoading ? 'opacity-60 animate-pulse' : ''
          }`}
        >
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">{kpi.label}</span>
            {kpi.icon}
          </div>
          <p className="text-xl font-bold text-slate-900 dark:text-white">{kpi.value}</p>
          <p className="text-xs text-slate-400 mt-1">{kpi.detail}</p>
        </div>
      ))}
    </div>
  )
}

function QuickAddForm() {
  const queryClient = useQueryClient()
  const { data: types } = useTypesPoisson()
  const { data: pecheurs } = usePecheurs()
  const { data: detaillants } = useDetaillants()

  const [type, setType] = useState<SourceAchatType>('pirogue')
  const [nom, setNom] = useState('')
  const [typePoissonId, setTypePoissonId] = useState('')
  const [poidsKg, setPoidsKg] = useState('')
  const [prix, setPrix] = useState('')
  const [heure, setHeure] = useState(() => new Date().toTimeString().slice(0, 5))
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!typePoissonId) {
      const defaut = (types ?? []).find((typePoisson) => typePoisson.is_default)
      if (defaut) {
        setTypePoissonId(String(defaut.id))
      }
    }
  }, [types, typePoissonId])

  const noms = useMemo(
    () => (type === 'pirogue' ? (pecheurs ?? []).map((p) => p.nom) : (detaillants ?? []).map((d) => d.nom)),
    [type, pecheurs, detaillants],
  )

  const mutation = useMutation({
    mutationFn: async () => {
      const response = await api.post('/achats', {
        type,
        nom,
        type_poisson_id: typePoissonId,
        poids_kg: poidsKg,
        prix,
        heure: heure || undefined,
      })
      return response.data
    },
    onSuccess: () => {
      setNom('')
      setPoidsKg('')
      setPrix('')
      setError(null)
      queryClient.invalidateQueries({ queryKey: queryKeys.achatsToday })
      queryClient.invalidateQueries({ queryKey: queryKeys.stats })
      queryClient.invalidateQueries({ queryKey: queryKeys.referentiels.pecheurs })
      queryClient.invalidateQueries({ queryKey: queryKeys.referentiels.detaillants })
    },
    onError: () => {
      setError('Impossible d’enregistrer l’achat. Vérifiez les champs.')
    },
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!nom.trim() || !typePoissonId || !poidsKg || !prix) {
      setError('Tous les champs sont requis.')
      return
    }
    mutation.mutate()
  }

  return (
    <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
      <div className="p-4 border-b border-slate-100 dark:border-slate-800">
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Ajouter un achat</h2>
      </div>
      <form onSubmit={handleSubmit} className="p-4 space-y-3">
        <div className="grid grid-cols-2 gap-2 rounded-lg bg-slate-100 dark:bg-slate-800 p-1">
          {(
            [
              { value: 'pirogue', label: 'Pêcheur (Pirogue)', icon: <ShipWheel className="h-4 w-4" /> },
              { value: 'detaillant', label: 'Détaillant', icon: <Fish className="h-4 w-4" /> },
            ] as { value: SourceAchatType; label: string; icon: React.ReactNode }[]
          ).map((option) => (
            <button
              key={option.value}
              type="button"
              onClick={() => {
                setType(option.value)
                setNom('')
              }}
              className={`flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                type === option.value
                  ? 'bg-white dark:bg-slate-900 text-sky-700 dark:text-sky-300 shadow'
                  : 'text-slate-600 dark:text-slate-400'
              }`}
            >
              {option.icon}
              {option.label}
            </button>
          ))}
        </div>

        <div>
          <label htmlFor="nom" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
            Nom du {type === 'pirogue' ? 'pêcheur' : 'détaillant'}
          </label>
          <input
            id="nom"
            list="sources-noms"
            value={nom}
            onChange={(event) => setNom(event.target.value)}
            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 text-base outline-none focus:ring-2 focus:ring-sky-500"
            placeholder={type === 'pirogue' ? 'Ex : Moussa Diop' : 'Ex : Awa Ndiaye'}
            autoComplete="off"
          />
          <datalist id="sources-noms">
            {noms.map((nomOption) => (
              <option key={nomOption} value={nomOption} />
            ))}
          </datalist>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="col-span-2 sm:col-span-1">
            <label htmlFor="type-poisson" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
              Poisson
            </label>
            <select
              id="type-poisson"
              value={typePoissonId}
              onChange={(event) => setTypePoissonId(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 text-base outline-none focus:ring-2 focus:ring-sky-500"
            >
              <option value="">Choisir…</option>
              {(types ?? []).map((typePoisson) => (
                <option key={typePoisson.id} value={typePoisson.id}>
                  {typePoisson.nom}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="poids" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
              Poids (kg)
            </label>
            <input
              id="poids"
              type="number"
              inputMode="decimal"
              step="0.1"
              min="0"
              value={poidsKg}
              onChange={(event) => setPoidsKg(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 text-base outline-none focus:ring-2 focus:ring-sky-500"
              placeholder="0"
            />
          </div>
          <div>
            <label htmlFor="prix" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
              Prix (FCFA)
            </label>
            <input
              id="prix"
              type="number"
              inputMode="numeric"
              min="0"
              value={prix}
              onChange={(event) => setPrix(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 text-base outline-none focus:ring-2 focus:ring-sky-500"
              placeholder="0"
            />
          </div>
          <div>
            <label htmlFor="heure" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
              Heure
            </label>
            <input
              id="heure"
              type="time"
              value={heure}
              onChange={(event) => setHeure(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 text-base outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
        </div>

        {error !== null && (
          <p className="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950 rounded-lg px-3 py-2">{error}</p>
        )}

        <Button type="submit" disabled={mutation.isPending} className="w-full h-12 text-base font-semibold">
          {mutation.isPending ? (
            <Loader2 className="h-5 w-5 animate-spin" />
          ) : (
            <Plus className="h-5 w-5" />
          )}
          {mutation.isPending ? 'Enregistrement…' : 'Enregistrer l’achat'}
        </Button>
      </form>
    </section>
  )
}

function TodayList() {
  const queryClient = useQueryClient()
  const { canEdit } = useAuth()
  const { data: types } = useTypesPoisson()
  const { data: sources, isLoading } = useTodayAchats()
  const [editingId, setEditingId] = useState<number | null>(null)

  const deleteMutation = useMutation({
    mutationFn: async (ligneId: number) => {
      await api.delete(`/achats/lignes/${ligneId}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.achatsToday })
      queryClient.invalidateQueries({ queryKey: queryKeys.stats })
    },
  })

  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Record<string, string | number> }) => {
      const response = await api.put<LigneAchat>(`/achats/lignes/${id}`, payload)
      return response.data
    },
    onSuccess: () => {
      setEditingId(null)
      queryClient.invalidateQueries({ queryKey: queryKeys.achatsToday })
      queryClient.invalidateQueries({ queryKey: queryKeys.stats })
    },
  })

  if (isLoading) {
    return (
      <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6 text-center text-slate-400">
        Chargement des achats…
      </section>
    )
  }

  if (!sources || sources.length === 0) {
    return (
      <section className="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-8 text-center">
        <Fish className="h-10 w-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
        <p className="text-slate-500 dark:text-slate-400">Aucun achat aujourd’hui</p>
        <p className="text-sm text-slate-400 mt-1">
          {canEdit ? 'Utilisez le formulaire ci-dessus pour commencer.' : 'Revenez plus tard pour consulter les achats.'}
        </p>
      </section>
    )
  }

  return (
    <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
      <div className="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Achats du jour</h2>
        <span className="text-xs text-slate-400">{sources.length} source(s)</span>
      </div>
      <ul className="divide-y divide-slate-100 dark:divide-slate-800">
        {sources.map((source) => {
          const nomSource = source.type === 'pirogue' ? source.pecheur?.nom : source.detaillant?.nom
          return (
            <li key={source.id} className="p-4">
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                  <span className="h-8 w-8 rounded-lg bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center">
                    {source.type === 'pirogue' ? (
                      <ShipWheel className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                    ) : (
                      <Fish className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                    )}
                  </span>
                  <div>
                    <p className="font-medium text-slate-900 dark:text-white text-sm">{nomSource ?? '—'}</p>
                    <p className="text-xs text-slate-400">
                      {source.type === 'pirogue' ? 'Pêcheur' : 'Détaillant'} ·{' '}
                      {source.lignes_achats?.length ?? 0} ligne(s)
                    </p>
                  </div>
                </div>
              </div>
              <ul className="space-y-1.5">
                {(source.lignes_achats ?? []).map((ligne) => (
                  <LigneItem
                    key={ligne.id}
                    ligne={ligne}
                    types={types ?? []}
                    canEdit={canEdit}
                    isEditing={editingId === ligne.id}
                    isDeleting={deleteMutation.isPending}
                    isSaving={updateMutation.isPending && editingId === ligne.id}
                    onEdit={() => setEditingId(ligne.id)}
                    onCancel={() => setEditingId(null)}
                    onSave={(payload) => updateMutation.mutate({ id: ligne.id, payload })}
                    onDelete={() => deleteMutation.mutate(ligne.id)}
                  />
                ))}
              </ul>
            </li>
          )
        })}
      </ul>
    </section>
  )
}

function LigneItem({
  ligne,
  types,
  canEdit,
  isEditing,
  isDeleting,
  isSaving,
  onEdit,
  onCancel,
  onSave,
  onDelete,
}: {
  ligne: LigneAchat
  types: { id: number; nom: string }[]
  canEdit: boolean
  isEditing: boolean
  isDeleting: boolean
  isSaving: boolean
  onEdit: () => void
  onCancel: () => void
  onSave: (payload: Record<string, string | number>) => void
  onDelete: () => void
}) {
  const [typePoissonId, setTypePoissonId] = useState(String(ligne.type_poisson_id ?? ''))
  const [poidsKg, setPoidsKg] = useState(String(ligne.poids_kg ?? ''))
  const [prix, setPrix] = useState(String(ligne.prix ?? ''))
  const [heure, setHeure] = useState(ligne.heure ? String(ligne.heure).slice(0, 5) : '')
  const [error, setError] = useState<string | null>(null)

  function handleSave() {
    if (!typePoissonId || !poidsKg || !prix) {
      setError('Tous les champs sont requis.')
      return
    }
    onSave({
      type_poisson_id: typePoissonId,
      poids_kg: poidsKg,
      prix,
      heure,
    })
  }

  if (isEditing) {
    return (
      <li className="rounded-lg bg-sky-50 dark:bg-slate-800/50 px-3 py-2">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div className="col-span-2 sm:col-span-1">
            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Poisson</label>
            <select
              value={typePoissonId}
              onChange={(event) => setTypePoissonId(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-sky-500"
            >
              <option value="">Choisir…</option>
              {types.map((typePoisson) => (
                <option key={typePoisson.id} value={typePoisson.id}>
                  {typePoisson.nom}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Poids (kg)</label>
            <input
              type="number"
              inputMode="decimal"
              step="0.1"
              min="0"
              value={poidsKg}
              onChange={(event) => setPoidsKg(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Prix (FCFA)</label>
            <input
              type="number"
              inputMode="numeric"
              min="0"
              value={prix}
              onChange={(event) => setPrix(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Heure</label>
            <input
              type="time"
              value={heure}
              onChange={(event) => setHeure(event.target.value)}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
        </div>
        {error !== null && <p className="text-xs text-red-600 dark:text-red-400 mt-2">{error}</p>}
        <div className="flex justify-end gap-2 mt-2">
          <Button type="button" size="sm" variant="outline" onClick={onCancel} disabled={isSaving}>
            <X className="h-3.5 w-3.5" />
            Annuler
          </Button>
          <Button type="button" size="sm" onClick={handleSave} disabled={isSaving}>
            {isSaving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
            Enregistrer
          </Button>
        </div>
      </li>
    )
  }

  return (
    <li className="flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
      <div>
        <p className="text-sm text-slate-800 dark:text-slate-200">{ligne.type_poisson?.nom ?? '—'}</p>
        <p className="text-xs text-slate-400">
          {formatHeureFr(ligne.heure) ? `${formatHeureFr(ligne.heure)} · ` : ''}
          {formatPoids(ligne.poids_kg)}
        </p>
      </div>
      <div className="flex items-center gap-1">
        <span className="text-sm font-semibold text-slate-900 dark:text-white">{formatMontant(ligne.prix)}</span>
        {canEdit && (
          <>
            <button
              onClick={onEdit}
              disabled={isDeleting}
              className="p-1.5 rounded-md text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950 transition-colors"
              aria-label="Modifier la ligne"
            >
              <Pencil className="h-4 w-4" />
            </button>
            <button
              onClick={onDelete}
              disabled={isDeleting}
              className="p-1.5 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950 transition-colors"
              aria-label="Supprimer la ligne"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </>
        )}
      </div>
    </li>
  )
}