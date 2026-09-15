import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import api from '@/lib/api'
import type { User } from '@/lib/types'

interface AuthContextValue {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('peche_token'))
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const storedToken = localStorage.getItem('peche_token')
    if (!storedToken) {
      setIsLoading(false)
      return
    }

    api
      .get<User>('/user')
      .then((response) => {
        setUser(response.data)
        setToken(storedToken)
      })
      .catch(() => {
        localStorage.removeItem('peche_token')
        setToken(null)
        setUser(null)
      })
      .finally(() => setIsLoading(false))
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    const response = await api.post<{ access_token: string; token_type: string }>('/login', {
      email,
      password,
    })
    localStorage.setItem('peche_token', response.data.access_token)
    setToken(response.data.access_token)

    const userResponse = await api.get<User>('/user')
    setUser(userResponse.data)
  }, [])

  const logout = useCallback(() => {
    localStorage.removeItem('peche_token')
    setToken(null)
    setUser(null)
  }, [])

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isAuthenticated: user !== null,
        isLoading,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (context === null) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}