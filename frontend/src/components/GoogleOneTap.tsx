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
          cancel: () => void
        }
      }
    }
    _googleOneTapInitialized?: boolean
  }
}

const GSI_SCRIPT = 'https://accounts.google.com/gsi/client'
const GOOGLE_CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID || ''

function loadGsi(): Promise<void> {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${GSI_SCRIPT}"]`) as HTMLScriptElement | null
    if (existing) {
      if ((existing as any)._loaded) resolve()
      else {
        existing.addEventListener('load', () => resolve())
        existing.addEventListener('error', () => reject(new Error('Impossible de charger Google Identity')))
      }
      return
    }
    const s = document.createElement('script') as HTMLScriptElement & { _loaded?: boolean }
    s.src = GSI_SCRIPT
    s.async = true
    s.defer = true
    s.onload = () => {
      s._loaded = true
      resolve()
    }
    s.onerror = () => reject(new Error('Impossible de charger Google Identity'))
    document.head.appendChild(s)
  })
}

export default function GoogleOneTap({ mode = 'login' }: { mode?: 'login' | 'register' }) {
  const btnRef = useRef<HTMLDivElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const initializedRef = useRef(false)
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
      // Erreur 500 SSL cURL 60 -> message explicite
      const msg = e?.message || 'Connexion Google échouée'
      if (msg.includes('cURL error 60') || msg.includes('SSL certificate') || msg.includes('unable to get local issuer')) {
        setError('Erreur SSL Windows (cURL 60) – ajoutez cacert.pem à php.ini ou mettez GOOGLE_SKIP_SSL_VERIFY=true dans backend/.env (voir GUIDE)')
      } else {
        setError(msg)
      }
    } finally {
      setLoading(false)
    }
  }, [navigate, location.state, setToken])

  useEffect(() => {
    if (!GOOGLE_CLIENT_ID) return
    if (initializedRef.current) return
    let cancelled = false

    loadGsi()
      .then(() => {
        if (cancelled) return
        if (!window.google || !btnRef.current) return
        // Eviter double init (React StrictMode)
        if (window._googleOneTapInitialized && initializedRef.current) {
          // Juste re-render le bouton
          try {
            window.google.accounts.id.renderButton(btnRef.current, {
              theme: 'outline',
              size: 'large',
              width: 350,
              text: mode === 'register' ? 'signup_with' : 'signin_with',
              locale: 'fr',
            })
          } catch {}
          return
        }

        window.google.accounts.id.initialize({
          client_id: GOOGLE_CLIENT_ID,
          callback: (resp: { credential: string }) => {
            handleCredential(resp.credential)
          },
          auto_select: false,
          cancel_on_tap_outside: true,
          // Eviter FedCM qui cause Cross-Origin-Opener-Policy warnings
          use_fedcm_for_prompt: false,
        })
        window._googleOneTapInitialized = true
        initializedRef.current = true

        if (btnRef.current) {
          window.google.accounts.id.renderButton(btnRef.current, {
            theme: 'outline',
            size: 'large',
            width: 350,
            text: mode === 'register' ? 'signup_with' : 'signin_with',
            locale: 'fr',
          })
        }
      })
      .catch((err) => {
        if (!cancelled) setError(err.message)
      })

    return () => {
      cancelled = true
      try {
        window.google?.accounts.id.cancel()
      } catch {}
    }
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
      {error && <div className="alert alert-danger small mt-2" style={{whiteSpace:'pre-wrap'}}>{error}</div>}
      <div className="small text-muted mt-2 text-center" style={{fontSize:'0.7rem'}}>
        Si bouton bloqué par adblock, désactivez-le pour ce site. Erreurs Permissions-Policy / favicon 404 sont normales en dev.
      </div>
    </div>
  )
}
