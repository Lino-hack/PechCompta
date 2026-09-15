export interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'viewer'
}

export interface Pecheur {
  id: number
  nom: string
}

export interface Detaillant {
  id: number
  nom: string
}

export interface TypePoisson {
  id: number
  nom: string
  is_default: boolean
  lignes_achats_count?: number
}

export interface ParametrePrix {
  id: number
  nom: string
  valeur_defaut: number
}

export type SourceAchatType = 'pirogue' | 'detaillant'

export interface LigneAchat {
  id: number
  source_achat_id: number
  type_poisson_id: number
  poids_kg: number | string
  prix: number | string
  type_poisson?: TypePoisson
  source_achat?: SourceAchat
}

export interface SourceAchat {
  id: number
  type: SourceAchatType
  pecheur_id: number | null
  detaillant_id: number | null
  date: string
  pecheur?: Pecheur | null
  detaillant?: Detaillant | null
  lignes_achats?: LigneAchat[]
}

export interface ChargeLibre {
  id: number
  charge_journaliere_id: number
  libelle: string
  montant: number | string
}

export interface ChargeJournaliere {
  id: number
  date: string
  nb_bagues_glace: number
  prix_bague_utilise: number | string
  transport: number | string
  charges_libres?: ChargeLibre[]
}

export interface CycleFraisLibre {
  id: number
  cycle_camion_id: number
  libelle: string
  montant: number | string
}

export interface CycleCamion {
  id: number
  date_debut: string
  date_fin: string | null
  frais_route: number | string
  statut: 'ouvert' | 'cloture'
  frais_libres?: CycleFraisLibre[]
}

export interface CycleDetails {
  cycle: CycleCamion
  achats: SourceAchat[]
  charges: ChargeJournaliere[]
}

export interface DayStats {
  date: string
  achats: {
    nb_sources: number
    nb_lignes: number
    poids_total: number
    montant_total: number
    par_type: { id: number; nom: string; poids_total: number; montant_total: number }[]
  }
  charges: {
    frais_glace: number
    transport: number
    libres_total: number
    total: number
  }
  total_depenses: number
}

export interface Stats {
  today: DayStats
  series_7j: DayStats[]
  totaux_periode: {
    from: string
    to: string
    poids_total: number
    montant_achats: number
    charges_total: number
    total_depenses: number
  }
}