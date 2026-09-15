import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  ChargeJournaliere,
  CycleCamion,
  CycleDetails,
  Detaillant,
  ParametrePrix,
  Pecheur,
  SourceAchat,
  Stats,
  TypePoisson,
  User,
} from '@/lib/types'

export function useUser() {
  return useQuery({
    queryKey: ['user'],
    queryFn: async () => (await api.get<User>('/user')).data,
    staleTime: Infinity,
  })
}

export function useTodayAchats() {
  return useQuery({
    queryKey: ['achats', 'today'],
    queryFn: async () => (await api.get<SourceAchat[]>('/achats/today')).data,
  })
}

export function useStats(from?: string, to?: string) {
  return useQuery({
    queryKey: ['stats', from, to],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (from) params.set('from', from)
      if (to) params.set('to', to)
      const query = params.toString()
      return (await api.get<Stats>(`/stats${query ? `?${query}` : ''}`)).data
    },
  })
}

export function useTodayCharges() {
  return useQuery({
    queryKey: ['charges', 'today'],
    queryFn: async () => (await api.get<ChargeJournaliere>('/charges/today')).data,
  })
}

export function useTypesPoisson() {
  return useQuery({
    queryKey: ['referentiels', 'types-poisson'],
    queryFn: async () => (await api.get<TypePoisson[]>('/referentiels/types-poisson')).data,
  })
}

export function useParametres() {
  return useQuery({
    queryKey: ['referentiels', 'parametres'],
    queryFn: async () => (await api.get<ParametrePrix[]>('/referentiels/parametres')).data,
  })
}

export function usePecheurs() {
  return useQuery({
    queryKey: ['referentiels', 'pecheurs'],
    queryFn: async () => (await api.get<Pecheur[]>('/referentiels/pecheurs')).data,
  })
}

export function useDetaillants() {
  return useQuery({
    queryKey: ['referentiels', 'detaillants'],
    queryFn: async () => (await api.get<Detaillant[]>('/referentiels/detaillants')).data,
  })
}

export function useCycles() {
  return useQuery({
    queryKey: ['cycles'],
    queryFn: async () => (await api.get<CycleCamion[]>('/cycles')).data,
  })
}

export function useCycleDetails(id: number | null) {
  return useQuery({
    queryKey: ['cycles', id],
    queryFn: async () => (await api.get<CycleDetails>(`/cycles/${id}`)).data,
    enabled: id !== null,
  })
}

export const queryKeys = {
  achatsToday: ['achats', 'today'] as const,
  chargesToday: ['charges', 'today'] as const,
  stats: ['stats'] as const,
  cycles: ['cycles'] as const,
  referentiels: {
    types: ['referentiels', 'types-poisson'] as const,
    parametres: ['referentiels', 'parametres'] as const,
    pecheurs: ['referentiels', 'pecheurs'] as const,
    detaillants: ['referentiels', 'detaillants'] as const,
  },
}