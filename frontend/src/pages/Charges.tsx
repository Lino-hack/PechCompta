import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { IceCream, Truck, Plus, Trash2, Loader2, Save } from 'lucide-react'
import { Button } from '@/components/ui/button'
import api from '@/lib/api'
import { formatMontant, todayISO } from '@/lib/format'
import { queryKeys, useTodayCharges, useParametres } from '@/hooks/useApiHooks'
import { useAuth } from '@/hooks/useAuth'
import type { ChargeJournaliere, ChargeLibre, ParametrePrix } from '@/lib/types'

export default function Charges() {
  const { data: charge, isLoading } = useTodayCharges()
  const { data: parametres } = useParametres()
  const { canEdit } = useAuth()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Charges du jour</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">Glace, transport et charges libres — {todayISO()}</p>
      </div>

      {isLoading ? (
        <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-6 text-center text-slate-400">
          Chargement…
        </section>
      ) : (
        <ChargeForm charge={charge} parametres={parametres ?? []} readOnly={!canEdit} />
      )}
    </div>
  )
}

function ChargeForm({ charge, parametres, readOnly }: { charge: ChargeJournaliere | undefined; parametres: ParametrePrix[]; readOnly: boolean }) {
  const queryClient = useQueryClient()

  const prixBacDefaut = useMemo(
    () => Number(parametres.find((p) => p.nom === 'prix_bac_glace')?.valeur_defaut ?? 1400),
    [parametres],
  )
  const transportParBac = useMemo(
    () => Number(parametres.find((p) => p.nom === 'transport_par_bac')?.valeur_defaut ?? 100),
    [parametres],
  )

  const [nbBacs, setNbBacs] = useState(String(charge?.nb_bagues_glace ?? 0))
  const [prixBac, setPrixBac] = useState(String(charge?.prix_bague_utilise ?? prixBacDefaut))
  const [transport, setTransport] = useState(
    String(charge?.transport ?? Number(charge?.nb_bagues_glace ?? 0) * transportParBac),
  )
  const [transportManual, setTransportManual] = useState(() => {
    const saved = Number(charge?.transport ?? 0)
    const auto = Number(charge?.nb_bagues_glace ?? 0) * transportParBac
    return saved !== 0 && saved !== auto
  })
  const [libres, setLibres] = useState<ChargeLibre[]>(charge?.charges_libres ?? [])

  const [libelle, setLibelle] = useState('')
  const [montant, setMontant] = useState('')

  function handleNbBacsChange(value: string) {
    setNbBacs(value)
    if (!transportManual) {
      setTransport(String(Number(value || 0) * transportParBac))
    }
  }

  const mutation = useMutation({
    mutationFn: async () => {
      const response = await api.post('/charges/today', {
        nb_bagues_glace: nbBacs,
        prix_bague_utilise: prixBac,
        transport,
        charges_libres: libres.map((libre) => ({ libelle: libre.libelle, montant: libre.montant })),
      })
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chargesToday })
      queryClient.invalidateQueries({ queryKey: queryKeys.stats })
    },
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    mutation.mutate()
  }

  function addLibre() {
    if (!libelle.trim() || !montant) return
    setLibres((current) => [
      ...current,
      { id: current.length + 1, charge_journaliere_id: charge?.id ?? 0, libelle: libelle.trim(), montant },
    ])
    setLibelle('')
    setMontant('')
  }

  function removeLibre(index: number) {
    setLibres((current) => current.filter((_, i) => i !== index))
  }

  const totalGlace = Number(nbBacs || 0) * Number(prixBac || 0)
  const totalLibres = libres.reduce((sum, libre) => sum + Number(libre.montant || 0), 0)
  const total = totalGlace + Number(transport || 0) + totalLibres

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
        <div className="p-4 flex items-center gap-3">
          <span className="h-9 w-9 rounded-lg bg-cyan-100 dark:bg-cyan-900/40 flex items-center justify-center">
            <IceCream className="h-4 w-4 text-cyan-600 dark:text-cyan-400" />
          </span>
          <div>
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Glace</h2>
            <p className="text-xs text-slate-400">Bacs de glace consommés aujourd’hui</p>
          </div>
        </div>
        <div className="p-4 grid grid-cols-2 gap-3">
          <div>
            <label htmlFor="nb-bacs" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
              Nombre de bacs
            </label>
            <input
              id="nb-bacs"
              type="number"
              inputMode="numeric"
              min="0"
              value={nbBacs}
              onChange={(event) => handleNbBacsChange(event.target.value)}
              disabled={readOnly}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
          <div>
            <label htmlFor="prix-bac" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
              Prix / bac (FCFA)
            </label>
            <input
              id="prix-bac"
              type="number"
              inputMode="numeric"
              min="0"
              value={prixBac}
              onChange={(event) => setPrixBac(event.target.value)}
              disabled={readOnly}
              className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
          <div className="col-span-2 text-sm text-slate-500 dark:text-slate-400">
            Total glace : <span className="font-semibold text-slate-900 dark:text-white">{formatMontant(totalGlace)}</span>
          </div>
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
        <div className="p-4 flex items-center gap-3">
          <span className="h-9 w-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
            <Truck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
          </span>
          <div>
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Transport</h2>
            <p className="text-xs text-slate-400">Calculé automatiquement ({formatMontant(transportParBac)} / bac) — modifiable</p>
          </div>
        </div>
        <div className="p-4">
          <label htmlFor="transport" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
            Montant transport
          </label>
          <input
            id="transport"
            type="number"
            inputMode="numeric"
            min="0"
            value={transport}
            onChange={(event) => { setTransport(event.target.value); setTransportManual(true) }}
            disabled={readOnly}
            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-sky-500"
          />
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
        <div className="p-4">
          <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Charges libres</h2>
          <p className="text-xs text-slate-400 mt-0.5">Nourriture, salaires, imprévus…</p>
        </div>
        <div className="p-4 space-y-2">
          <div className="grid grid-cols-[1fr_auto_auto] gap-2">
            <input
              value={libelle}
              onChange={(event) => setLibelle(event.target.value)}
              disabled={readOnly}
              placeholder="Libellé"
              className="rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-sky-500"
            />
            <input
              type="number"
              inputMode="numeric"
              min="0"
              value={montant}
              onChange={(event) => setMontant(event.target.value)}
              disabled={readOnly}
              placeholder="Montant"
              className="w-28 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-sky-500"
            />
            {!readOnly && (
              <Button type="button" variant="secondary" size="icon" onClick={addLibre} aria-label="Ajouter la charge">
                <Plus className="h-4 w-4" />
              </Button>
            )}
          </div>

          {libres.length === 0 ? (
            <p className="text-xs text-slate-400 text-center py-4">Aucune charge libre.</p>
          ) : (
            <ul className="space-y-1.5">
              {libres.map((libre, index) => (
                <li
                  key={libre.id ?? `${libre.libelle}-${index}`}
                  className="flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800/50 px-3 py-2"
                >
                  <span className="text-sm text-slate-800 dark:text-slate-200">{libre.libelle}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-slate-900 dark:text-white">
                      {formatMontant(libre.montant)}
                    </span>
                    {!readOnly && (
                      <button
                        onClick={() => removeLibre(index)}
                        className="p-1.5 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950 transition-colors"
                        aria-label="Retirer la charge"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>

      <section className="rounded-xl border border-sky-200 dark:border-sky-900 bg-sky-50 dark:bg-sky-950/40 p-4 flex items-center justify-between">
        <div>
          <p className="text-xs text-sky-600 dark:text-sky-400 font-medium">TOTAL DU JOUR</p>
          <p className="text-2xl font-bold text-slate-900 dark:text-white mt-0.5">{formatMontant(total)}</p>
        </div>
        {!readOnly && (
          <Button type="submit" disabled={mutation.isPending} className="h-12 px-6 text-base font-semibold">
            {mutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {mutation.isPending ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        )}
      </section>
    </form>
  )
}