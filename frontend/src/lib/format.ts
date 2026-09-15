export function formatMontant(value: number | string | null | undefined): string {
  const num = Number(value ?? 0)
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(num) + ' FCFA'
}

export function formatMontantCourt(value: number | string | null | undefined): string {
  const num = Number(value ?? 0)
  if (num >= 1000000) {
    return (num / 1000000).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' M FCFA'
  }
  if (num >= 1000) {
    return (num / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' k FCFA'
  }
  return num.toString() + ' FCFA'
}

export function formatPoids(value: number | string | null | undefined): string {
  const num = Number(value ?? 0)
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(num) + ' kg'
}

export function formatNumber(value: number | string | null | undefined): string {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(value ?? 0))
}

export function formatDateFr(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date + (date.length === 10 ? 'T00:00:00' : '')) : date
  return d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
}

export function todayISO(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 10)
}