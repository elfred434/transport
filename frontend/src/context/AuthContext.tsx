import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react'
import { api, Auth, ApiError } from '../lib/api'

export type UserRole = 'client' | 'utilisateur' | 'transporteur' | 'admin' | 'super_admin'

export interface Me {
  id: number
  nom: string
  prenom: string
  email: string
  telephone: string | null
  photo_url: string | null
  role: UserRole
  date_inscription: string | null
  is_transporteur?: boolean
  solde?: number
}

interface AuthContextValue {
  me: Me | null
  loading: boolean
  isSuperAdmin: boolean
  isAdmin: boolean
  isTransporteur: boolean
  isClient: boolean
  refresh: () => Promise<Me | null>
  setToken: (payload: string | { token: string; refresh?: string }) => Promise<Me | null>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue>({
  me: null,
  loading: true,
  isSuperAdmin: false,
  isAdmin: false,
  isTransporteur: false,
  isClient: false,
  refresh: async () => null,
  setToken: async () => null,
  logout: async () => {},
})

export function useAuth(): AuthContextValue {
  return useContext(AuthContext)
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [me, setMe] = useState<Me | null>(null)
  const [loading, setLoading] = useState<boolean>(Auth.isLoggedIn())

  const loadMe = useCallback(async (): Promise<Me | null> => {
    if (!Auth.isLoggedIn()) {
      setMe(null)
      return null
    }
    try {
      const profile = await api.get<Me>('/api/auth/me')
      if ((profile.role as any) === 'utilisateur') profile.role = 'client'
      setMe(profile)
      return profile
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        Auth.clear()
      }
      setMe(null)
      return null
    }
  }, [])

  useEffect(() => {
    loadMe().finally(() => setLoading(false))
  }, [loadMe])

  const refresh = useCallback(async () => loadMe(), [loadMe])

  const setToken = useCallback(
    async (payload: string | { token: string; refresh?: string }) => {
      if (typeof payload === 'string') {
        Auth.saveAccess(payload)
      } else {
        Auth.saveTokens(payload.token, payload.refresh)
      }
      setLoading(true)
      try {
        return await loadMe()
      } finally {
        setLoading(false)
      }
    },
    [loadMe],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/api/auth/logout', { refresh: Auth.refresh() })
    } catch {}
    Auth.clear()
    setMe(null)
  }, [])

  const isSuperAdmin = me?.role === 'super_admin'
  const isAdmin = me?.role === 'admin' || me?.role === 'super_admin'
  const isTransporteur = !!me?.is_transporteur || me?.role === 'transporteur'
  const isClient = me?.role === 'client' || (me?.role as any) === 'utilisateur'

  return (
    <AuthContext.Provider value={{ me, loading, isSuperAdmin, isAdmin, isTransporteur, isClient, refresh, setToken, logout }}>
      {children}
    </AuthContext.Provider>
  )
}
