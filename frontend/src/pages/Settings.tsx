import { useState } from 'react'
import type { FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, Loader2, Settings2, Tag } from 'lucide-react'
import { Button } from '@/components/ui/button'
import api from '@/lib/api'
import { queryKeys, useParametres, useTypesPoisson } from '@/hooks/useApiHooks'
import { formatNumber } from '@/lib/format'
import type { ParametrePrix } from '@/lib/types'

export default function Settings() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Réglages</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">Types de poissons et paramètres de prix</p>
      </div>
      <TypesPoissonManager />
      <ParametresManager />
    </div>
  )
}

function TypesPoissonManager() {
  const { data: types, isLoading } = useTypesPoisson()
  const queryClient = useQueryClient()
  const [nom, setNom] = useState('')
  const [error, setError] = useState<string | null>(null)

  const addMutation = useMutation({
    mutationFn: async () => (await api.post('/referentiels/types-poisson', { nom })).data,
    onSuccess: () => {
      setNom('')
      setError(null)
      queryClient.invalidateQueries({ queryKey: queryKeys.referentiels.types })
    },
    onError: () => setError('Impossible d’ajouter ce type.'),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => await api.delete(`/referentiels/types-poisson/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.referentiels.types })
      queryClient.invalidateQueries({ queryKey: queryKeys.stats })
    },
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!nom.trim()) return
    addMutation.mutate()
  }

  return (
    <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
      <div className="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
        <Tag className="h-4 w-4 text-sky-600 dark:text-sky-400" />
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Types de poisson</h2>
      </div>
      <div className="p-4 space-y-3">
        {isLoading ? (
          <p className="text-center text-slate-400 text-sm py-4">Chargement…</p>
        ) : (
          <ul className="space-y-1.5">
            {(types ?? []).map((type) => (
              <li
                key={type.id}
                className="flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800/50 px-3 py-2"
              >
                <span className="text-sm text-slate-800 dark:text-slate-200">
                  {type.nom}
                  {type.is_default ? (
                    <span className="ml-2 text-[10px] uppercase tracking-wide bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300 rounded px-1.5 py-0.5">
                      par défaut
                    </span>
                  ) : null}
                </span>
                {!type.is_default && (
                  <button
                    onClick={() => deleteMutation.mutate(type.id)}
                    disabled={deleteMutation.isPending}
                    className="p-1.5 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950 transition-colors"
                    aria-label={`Supprimer ${type.nom}`}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}

        <form onSubmit={handleSubmit} className="flex gap-2">
          <input
            value={nom}
            onChange={(event) => setNom(event.target.value)}
            placeholder="Nouveau type de poisson"
            className="flex-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-sky-500"
          />
          <Button type="submit" disabled={addMutation.isPending}>
            {addMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
            Ajouter
          </Button>
        </form>
        {error !== null && <p className="text-xs text-red-600 dark:text-red-400">{error}</p>}
      </div>
    </section>
  )
}

function ParametresManager() {
  const { data: parametres, isLoading } = useParametres()
  const [values, setValues] = useState<Record<string, string>>({})
  const [saved, setSaved] = useState(false)

  const labels: Record<string, string> = {
    prix_bac_glace: 'Prix du bac de glace (FCFA)',
    transport_par_bac: 'Transport par bac (FCFA)',
  }

  const mutation = useMutation({
    mutationFn: async (parametre: ParametrePrix) => {
      await api.post('/referentiels/parametres', {
        nom: parametre.nom,
        valeur_defaut: values[parametre.nom] ?? parametre.valeur_defaut,
      })
    },
    onSuccess: () => {
      setSaved(true)
      setTimeout(() => setSaved(false), 2000)
    },
  })

  return (
    <section className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
      <div className="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
        <Settings2 className="h-4 w-4 text-sky-600 dark:text-sky-400" />
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Paramètres de prix</h2>
      </div>
      <div className="p-4 space-y-3">
        {isLoading ? (
          <p className="text-center text-slate-400 text-sm py-4">Chargement…</p>
        ) : (
          (parametres ?? []).map((parametre) => (
            <div key={parametre.id} className="flex items-center justify-between gap-3">
              <span className="text-sm text-slate-700 dark:text-slate-300">
                {labels[parametre.nom] ?? parametre.nom.replace(/_/g, ' ')}
              </span>
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  inputMode="numeric"
                  min="0"
                  value={values[parametre.nom] ?? String(parametre.valeur_defaut)}
                  onChange={(event) =>
                    setValues((current) => ({ ...current, [parametre.nom]: event.target.value }))
                  }
                  className="w-28 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 text-sm text-right outline-none focus:ring-2 focus:ring-sky-500"
                />
                <Button size="sm" onClick={() => mutation.mutate(parametre)} disabled={mutation.isPending}>
                  {saved && mutation.isSuccess ? '✓' : 'Enregistrer'}
                </Button>
              </div>
            </div>
          ))
        )}
        <p className="text-xs text-slate-400">
          Valeur par défaut du prix du bac de glace :{' '}
          {formatNumber(values.prix_bac_glace ?? parametres?.find((p) => p.nom === 'prix_bac_glace')?.valeur_defaut ?? 0)}{' '}
          FCFA
        </p>
      </div>
    </section>
  )
}