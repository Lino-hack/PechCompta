import { API_BASE_URL } from '@/lib/api'

export async function downloadExport(path: string, filename: string): Promise<boolean> {
  const token = localStorage.getItem('peche_token')
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: { Authorization: `Bearer ${token}` },
  })
  if (!response.ok) {
    if (response.status === 401) {
      localStorage.removeItem('peche_token')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return false
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
  return true
}