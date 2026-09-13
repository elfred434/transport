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
          disableAutoSelect: () => void
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
    const existing = document.querySelector<HTMLScriptElement>(`script[src="${GSI_SCRIPT}"]`)
    if (existing) {
      if ((existing as HTMLScriptElement & { _loaded?: boolean })._loaded) {
        resolve()
        return
      }
      const onLoad = () => { cleanup(); resolve() }
      const onErr = () => { cleanup(); reject(new Error('Impossible de charger Google Identity')) }
      const cleanup = () => {
        existing.removeEventListener('load', onLoad)
        existing.removeEventListener('error', onErr)
      }
      existing.addEventListener('load', onLoad)
      existing.addEventListener('error', onErr)
      return
    }
    const s = document.createElement('script') as HTMLScriptElement & { _loaded?: boolean }
    s.src = GSI_SCRIPT
    s.async = true
    s.defer = true
    s.nonce = ''
    s.onload = () => {
      s._loaded = true
      resolve()
    }
    s.onerror = () => reject(new Error('Impossible de charger Google Identity (connexion bloquée par un adblock/filter ou réseau)'))
    document.head.appendChild(s)
  })
}

// Initialise GSI une SEULE fois pour toute la session de page
let initPromise: Promise<void> | null = null
function ensureGsiInitialized(): Promise<void> {
  if (!GOOGLE_CLIENT_ID) return Promise.reject(new Error('GOOGLE_CLIENT_ID manquant'))
  if (initPromise) return initPromise
  initPromise = loadGsi().then(() => {
    if (window._googleOneTapInitialized) return
    // Callback global relais : il redirige vers cbRef.current qui pointe
    // toujours vers le dernier handleCredential (login ou register)
    ;(window as any)._googleOneTapCallback = (resp: { credential: string }) => {
      ;(window as any)._googleOneTapHandler?.(resp.credential)
    }
    window.google!.accounts.id.initialize({
      client_id: GOOGLE_CLIENT_ID,
      callback: (resp: { credential: string }) => {
        ;(window as any)._googleOneTapHandler?.(resp.credential)
      },
      auto_select: false,
      cancel_on_tap_outside: true,
      use_fedcm_for_prompt: false,
    })
    window._googleOneTapInitialized = true
  })
  return initPromise
}

export default function GoogleOneTap({ mode = 'login' }: { mode?: 'login' | 'register' }) {
  const btnRef = useRef<HTMLDivElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [ready, setReady] = useState(false)
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
      const msg = e?.message || 'Connexion Google échouée'
      if (msg.includes('cURL error 60') || msg.includes('SSL certificate') || msg.includes('unable to get local issuer')) {
        setError('Erreur SSL backend (cURL 60) – contacter l\'administrateur')
      } else {
        setError(msg)
      }
    } finally {
      setLoading(false)
    }
  }, [navigate, location.state, setToken])

  // Brancher notre handler courant sur le callback global GSI
  useEffect(() => {
    if (!GOOGLE_CLIENT_ID) return
    ;(window as any)._googleOneTapHandler = handleCredential
    return () => {
      // Nettoyage si composant démonté pour éviter appel sur instance périmée
      if ((window as any)._googleOneTapHandler === handleCredential) {
        delete (window as any)._googleOneTapHandler
      }
    }
  }, [handleCredential])

  useEffect(() => {
    if (!GOOGLE_CLIENT_ID) return
    let cancelled = false

    ensureGsiInitialized()
      .then(() => {
        if (cancelled) return
        if (!window.google || !btnRef.current) return
        // Render le bouton seulement — initialize() est déjà appelé une fois
        try {
          // Vider d'abord pour éviter les doublons de bouton StrictMode
          if (btnRef.current.firstChild) btnRef.current.innerHTML = ''
          window.google.accounts.id.renderButton(btnRef.current, {
            theme: 'outline',
            size: 'large',
            width: 350,
            text: mode === 'register' ? 'signup_with' : 'signin_with',
            locale: 'fr',
          })
          if (!cancelled) setReady(true)
        } catch (e: any) {
          if (!cancelled) setError('Impossible d\'afficher le bouton Google: ' + (e?.message || e))
        }
      })
      .catch((err) => {
        if (!cancelled) setError(err.message || 'Erreur de chargement Google')
      })

    return () => { cancelled = true }
  }, [mode])

  if (!GOOGLE_CLIENT_ID) {
    return (
      <div className="alert alert-warning small py-2">
        <i className="fa-brands fa-google"></i> Google login non configuré – ajoutez <code>VITE_GOOGLE_CLIENT_ID</code> dans <code>frontend/.env</code>
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
      {!ready && !error && (
        <div className="small text-muted mt-2 text-center">Chargement du bouton Google…</div>
      )}
    </div>
  )
}
