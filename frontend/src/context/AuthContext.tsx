import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react'
import { api, Auth, ApiError } from '../lib/api'

/**
 * Session utilisateur.
 *
 * Reproduit UI.requireUser()/requireAdmin() du frontend vanilla :
 *  - au montage, si un jeton est présent, GET /api/auth/me charge le profil
 *    (avec is_transporteur et solde) ;
 *  - 401 pendant le chargement → jeton effacé, état "déconnecté" ;
 *  - login/register enregistrent le jeton puis rechargent le profil.
 */

export interface Me {
  id: number
  nom: string
  prenom: string
  email: string
  telephone: string | null
  photo_url: string | null
  role: 'utilisateur' | 'transporteur' | 'admin'
  date_inscription: string | null
  is_transporteur?: boolean
  solde?: number
}

interface AuthContextValue {
  me: Me | null
  /** true tant que la session initiale n'est pas résolue */
  loading: boolean
  refresh: () => Promise<Me | null>
  setToken: (token: string) => Promise<Me | null>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue>({
  me: null,
  loading: true,
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
    async (token: string) => {
      Auth.save(token)
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
      await api.post('/api/auth/logout')
    } catch {
      /* le jeton est effacé quoi qu'il arrive */
    }
    Auth.clear()
    setMe(null)
  }, [])

  return (
    <AuthContext.Provider value={{ me, loading, refresh, setToken, logout }}>
      {children}
    </AuthContext.Provider>
  )
}
