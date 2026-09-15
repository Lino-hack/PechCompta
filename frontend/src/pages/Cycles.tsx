import { useState } from 'react'
import type { FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Truck, Plus, Loader2, ChevronDown, ChevronUp, Lock, FileSpreadsheet, FileText, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import api from '@/lib/api'
import { downloadExport } from '@/lib/export'
import { formatMontant, formatDateFr, todayISO } from '@/lib/format'
import { queryKeys, useCycles, useCycleDetails } from '@/hooks/useApiHooks'
import type { CycleCamion } from '@/lib/types'

export default function Cycles() {
  const { data: cycles, isLoading } = useCycles()
  const [selectedId, setSelectedId] = useState<number | null>(null)

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Cycles camion</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-1">Périodes de route et frais associés</p>
        </div>
        <NewCycleButton />
      </div>

      {isLoading ? (
        <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6 text-center text-slate-400">
          Chargement…
        </section>
      ) : !cycles || cycles.length === 0 ? (
        <section className="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-8 text-center">
          <Truck className="h-10 w-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
          <p className="text-slate-500 dark:text-slate-400">Aucun cycle pour le moment</p>
        </section>
      ) : (
        <ul className="space-y-3">
          {cycles.map((cycle) => (
            <div key={cycle.id} className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
              <li>
                <button
                  onClick={() => setSelectedId(selectedId === cycle.id ? null : cycle.id)}
                  className="w-full p-4 flex items-center justify-between text-left gap-3"
                >
                  <div className="flex items-center gap-3">
                    <span
                      className={`h-10 w-10 rounded-xl flex items-center justify-center ${
                        cycle.statut === 'ouvert'
                          ? 'bg-emerald-100 dark:bg-emerald-900/40'
                          : 'bg-slate-100 dark:bg-slate-800'
                      }`}
                    >
                      <Truck className={`h-5 w-5 ${cycle.statut === 'ouvert' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'}`} />
                    </span>
                    <div>
                      <p className="font-medium text-slate-900 dark:text-white text-sm">
                        Du {formatDateFr(cycle.date_debut)}
                        {cycle.date_fin ? ` au ${formatDateFr(cycle.date_fin)}` : ' (en cours)'}
                      </p>
                      <p className="text-xs text-slate-400">
                        {cycle.statut === 'ouvert' ? 'Cycle ouvert' : 'Cycle clôturé'} · Frais de route : {formatMontant(cycle.frais_route)}
                      </p>
                    </div>
                  </div>
                  {selectedId === cycle.id ? <ChevronUp className="h-4 w-4 text-slate-400" /> : <ChevronDown className="h-4 w-4 text-slate-400" />}
                </button>
              </li>
              {selectedId === cycle.id ? <CycleDetail cycle={cycle} /> : null}
            </div>
          ))}
        </ul>
      )}
    </div>
  )
}

function NewCycleButton() {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [dateDebut, setDateDebut] = useState(todayISO())
  const [dateFin, setDateFin] = useState('')
  const [fraisRoute, setFraisRoute] = useState('')
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: async () => {
      const response = await api.post('/cycles', {
        date_debut: dateDebut,
        date_fin: dateFin || null,
        frais_route: fraisRoute || 0,
      })
      return response.data
    },
    onSuccess: () => {
      setOpen(false)
      setDateFin('')
      setFraisRoute('')
      setError(null)
      queryClient.invalidateQueries({ queryKey: queryKeys.cycles })
    },
    onError: () => setError('Impossible de créer le cycle.'),
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    mutation.mutate()
  }

  if (!open) {
    return (
      <Button onClick={() => setOpen(true)}>
        <Plus className="h-4 w-4" />
        Nouveau cycle
      </Button>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="w-full sm:w-80 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-4 space-y-3">
      <p className="text-sm font-semibold text-slate-900 dark:text-white">Nouveau cycle</p>
      <div>
        <label htmlFor="date-debut" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
          Date de début
        </label>
        <input
          id="date-debut"
          type="date"
          value={dateDebut}
          max={dateFin || undefined}
          onChange={(event) => setDateDebut(event.target.value)}
          className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-sky-500"
        />
      </div>
      <div>
        <label htmlFor="date-fin" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
          Date de fin (optionnelle)
        </label>
        <input
          id="date-fin"
          type="date"
          value={dateFin}
          min={dateDebut}
          onChange={(event) => setDateFin(event.target.value)}
          className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-sky-500"
        />
      </div>
      <div>
        <label htmlFor="frais-route" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
          Frais de route (FCFA)
        </label>
        <input
          id="frais-route"
          type="number"
          inputMode="numeric"
          min="0"
          value={fraisRoute}
          onChange={(event) => setFraisRoute(event.target.value)}
          className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-sky-500"
          placeholder="0"
        />
      </div>
      {error !== null && <p className="text-xs text-red-600 dark:text-red-400">{error}</p>}
      <div className="flex gap-2">
        <Button type="submit" disabled={mutation.isPending} className="flex-1">
          {mutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
          Créer
        </Button>
        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
          Annuler
        </Button>
      </div>
    </form>
  )
}

function CycleDetail({ cycle }: { cycle: CycleCamion }) {
  const { data: details, isLoading } = useCycleDetails(cycle.id)
  const queryClient = useQueryClient()

  const [libelle, setLibelle] = useState('')
  const [montant, setMontant] = useState('')
  const [fraisError, setFraisError] = useState<string | null>(null)

  const closeMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/cycles/${cycle.id}/close`, {})
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.cycles })
    },
  })

  const addFraisMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/cycles/${cycle.id}/frais-libres`, { libelle, montant })
      return response.data
    },
    onSuccess: () => {
      setLibelle('')
      setMontant('')
      setFraisError(null)
      queryClient.invalidateQueries({ queryKey: queryKeys.cycles })
    },
    onError: () => setFraisError('Impossible d’ajouter ce frais.'),
  })

  const deleteFraisMutation = useMutation({
    mutationFn: async (fraisId: number) => {
      await api.delete(`/cycles/${cycle.id}/frais-libres/${fraisId}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.cycles })
    },
  })

  function handleAddFrais() {
    if (!libelle.trim() || !montant) {
      setFraisError('Libellé et montant sont requis.')
      return
    }
    addFraisMutation.mutate()
  }

  async function downloadCycleExport(format: 'excel' | 'pdf') {
    await downloadExport(
      `/cycles/${cycle.id}/export?format=${format}`,
      `cycle-${cycle.id}-rapport.${format === 'excel' ? 'xlsx' : 'pdf'}`,
    )
  }

  if (isLoading || !details) {
    return <div className="p-4 text-center text-slate-400 text-sm">Chargement des détails…</div>
  }

  const achats = details.achats ?? []
  const lignes = achats.flatMap((source) => source.lignes_achats ?? [])
  const montantAchats = lignes.reduce((sum, ligne) => sum + Number(ligne.prix || 0), 0)
  const chargesTotal = (details.charges ?? []).reduce((sum, charge) => {
    const glace = Number(charge.nb_bagues_glace || 0) * Number(charge.prix_bague_utilise || 0)
    const libres = (charge.charges_libres ?? []).reduce((s, l) => s + Number(l.montant || 0), 0)
    return sum + glace + Number(charge.transport || 0) + libres
  }, 0)
  const fraisLibres = details.cycle.frais_libres ?? []
  const totalFraisLibres = fraisLibres.reduce((sum, frais) => sum + Number(frais.montant || 0), 0)

  return (
    <div className="p-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div className="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3">
          <p className="text-xs text-slate-400">Achats</p>
          <p className="text-lg font-bold text-slate-900 dark:text-white">{formatMontant(montantAchats)}</p>
        </div>
        <div className="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3">
          <p className="text-xs text-slate-400">Charges</p>
          <p className="text-lg font-bold text-slate-900 dark:text-white">{formatMontant(chargesTotal)}</p>
        </div>
        <div className="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3">
          <p className="text-xs text-slate-400">Frais de route</p>
          <p className="text-lg font-bold text-slate-900 dark:text-white">{formatMontant(cycle.frais_route)}</p>
        </div>
        <div className="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3">
          <p className="text-xs text-slate-400">Autres frais</p>
          <p className="text-lg font-bold text-slate-900 dark:text-white">{formatMontant(totalFraisLibres)}</p>
        </div>
        <div className="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3">
          <p className="text-xs text-slate-400">Total dépenses</p>
          <p className="text-lg font-bold text-slate-900 dark:text-white">
            {formatMontant(montantAchats + chargesTotal + Number(cycle.frais_route || 0) + totalFraisLibres)}
          </p>
        </div>
      </div>

      <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
        <div className="p-3">
          <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Autres frais du cycle</h3>
          <p className="text-xs text-slate-400 mt-0.5">Douane, péages, réparations, divers…</p>
        </div>
        <div className="p-3 space-y-2">
          {fraisLibres.length === 0 ? (
            <p className="text-xs text-slate-400 text-center py-2">Aucun frais ajouté.</p>
          ) : (
            <ul className="space-y-1.5">
              {fraisLibres.map((frais) => (
                <li
                  key={frais.id}
                  className="flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800/50 px-3 py-2"
                >
                  <span className="text-sm text-slate-800 dark:text-slate-200">{frais.libelle}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-slate-900 dark:text-white">
                      {formatMontant(frais.montant)}
                    </span>
                    {cycle.statut === 'ouvert' && (
                      <button
                        onClick={() => deleteFraisMutation.mutate(frais.id)}
                        disabled={deleteFraisMutation.isPending}
                        className="p-1.5 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950 transition-colors"
                        aria-label="Retirer le frais"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
          {cycle.statut === 'ouvert' && (
            <div className="flex flex-wrap items-end gap-2 pt-1">
              <div className="flex-1 min-w-32">
                <label htmlFor="frais-libelle" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                  Libellé
                </label>
                <input
                  id="frais-libelle"
                  value={libelle}
                  onChange={(event) => setLibelle(event.target.value)}
                  placeholder="Ex : Douane, péage…"
                  className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-sky-500"
                />
              </div>
              <div>
                <label htmlFor="frais-montant" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                  Montant
                </label>
                <input
                  id="frais-montant"
                  type="number"
                  inputMode="numeric"
                  min="0"
                  value={montant}
                  onChange={(event) => setMontant(event.target.value)}
                  className="w-32 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-sky-500"
                  placeholder="0"
                />
              </div>
              <Button type="button" size="sm" onClick={handleAddFrais} disabled={addFraisMutation.isPending}>
                {addFraisMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                Ajouter
              </Button>
            </div>
          )}
          {fraisError !== null && <p className="text-xs text-red-600 dark:text-red-400">{fraisError}</p>}
        </div>
      </section>

      <div className="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" onClick={() => downloadCycleExport('excel')}>
          <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
          Exporter en Excel
        </Button>
        <Button variant="outline" size="sm" onClick={() => downloadCycleExport('pdf')}>
          <FileText className="h-4 w-4 text-red-600" />
          Exporter en PDF
        </Button>
        {cycle.statut === 'ouvert' && (
          <Button size="sm" variant="secondary" onClick={() => closeMutation.mutate()} disabled={closeMutation.isPending}>
            {closeMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Lock className="h-4 w-4" />}
            Clôturer le cycle
          </Button>
        )}
      </div>
    </div>
  )
}