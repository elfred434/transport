import { useEffect, useRef, useState, useCallback } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../context/AuthContext'

declare global {
  interface Window {
    google?: {
      accounts: {
        id: {
          initialize: (opts: any) => void
          renderButton: (el: HTMLElement, opts: any) => void
          prompt: () => void
        }
      }
    }
  }
}

const GSI_SCRIPT = 'https://accounts.google.com/gsi/client'
const GOOGLE_CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID || ''

function loadGsi(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${GSI_SCRIPT}"]`)) {
      resolve()
      return
    }
    const s = document.createElement('script')
    s.src = GSI_SCRIPT
    s.async = true
    s.defer = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('Impossible de charger Google Identity'))
    document.head.appendChild(s)
  })
}

export default function GoogleOneTap({ mode = 'login' }: { mode?: 'login' | 'register' }) {
  const btnRef = useRef<HTMLDivElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()
  const { setToken } = useAuth()

  const handleCredential = useCallback(async (credential: string) => {
    setLoading(true)
    setError(null)
    try {
      const data = await api.post<{ token: string; user: { role: string } }>('/api/auth/google/one-tap', {
        credential,
      })
      await setToken(data.token)
      const from = (location.state as { from?: string } | null)?.from
      if (from) navigate(from, { replace: true })
      else navigate(data.user.role === 'admin' || data.user.role === 'super_admin' ? '/admin' : '/dashboard', { replace: true })
    } catch (e: any) {
      setError(e?.message || 'Connexion Google échouée')
    } finally {
      setLoading(false)
    }
  }, [navigate, location.state, setToken])

  useEffect(() => {
    if (!GOOGLE_CLIENT_ID) return
    loadGsi()
      .then(() => {
        if (!window.google || !btnRef.current) return
        window.google.accounts.id.initialize({
          client_id: GOOGLE_CLIENT_ID,
          callback: (resp: { credential: string }) => {
            handleCredential(resp.credential)
          },
          auto_select: false,
          cancel_on_tap_outside: true,
        })
        window.google.accounts.id.renderButton(btnRef.current, {
          theme: 'outline',
          size: 'large',
          width: 350,
          text: mode === 'register' ? 'signup_with' : 'signin_with',
          locale: 'fr',
        })
        // Optionnel One Tap prompt
        // window.google.accounts.id.prompt()
      })
      .catch((err) => setError(err.message))
  }, [handleCredential, mode])

  if (!GOOGLE_CLIENT_ID) {
    return (
      <div className="alert alert-warning small py-2">
        <i className="fa-brands fa-google"></i> Google login non configuré – ajoutez <code>VITE_GOOGLE_CLIENT_ID</code> dans <code>frontend/.env</code><br/>
        <small>Voir guide dans <code>GOOGLE_LOGIN_GUIDE.md</code></small>
      </div>
    )
  }

  return (
    <div className="mt-3">
      <div className="d-flex align-items-center my-3">
        <hr className="flex-grow-1" />
        <span className="mx-2 small text-muted">ou</span>
        <hr className="flex-grow-1" />
      </div>
      <div ref={btnRef} className="d-flex justify-content-center"></div>
      {loading && <div className="text-center small text-muted mt-2"><span className="spinner-border spinner-border-sm me-1"></span>Connexion Google…</div>}
      {error && <div className="alert alert-danger small mt-2">{error}</div>}
    </div>
  )
}
