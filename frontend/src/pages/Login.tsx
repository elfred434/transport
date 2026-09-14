import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { api, Auth, ApiError } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import GoogleOneTap from '../components/GoogleOneTap'
import PasswordField from '../components/PasswordField'

interface LoginResponse {
  token: string
  refresh?: string
  user: { id: number; role: string }
}
interface TwoFaResponse {
  require_2fa: boolean
  challenge_id: string
  message: string
  expires_in_minutes: number
}

/** Connexion — port de login.html (mêmes alertes par query string). */
export default function Login() {
  const [email, setEmail] = useState(() => localStorage.getItem('remember_email') || '')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(Boolean(localStorage.getItem('remember_email')))
  const [alert, setAlert] = useState<{ type: 'danger' | 'success' | 'info'; text: string } | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [twofa, setTwofa] = useState<{ challengeId: string; code: string } | null>(null)
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const location = useLocation()
  const { setToken } = useAuth()

  // Déjà connecté → espace (comme le vanilla).
  useEffect(() => {
    if (Auth.isLoggedIn()) navigate('/dashboard', { replace: true })
  }, [navigate])

  // Messages passés en query string (?error=1, ?register=success, ...).
  useEffect(() => {
    const err = params.get('error')
    if (err === '1') setAlert({ type: 'danger', text: 'Email ou mot de passe incorrect.' })
    else if (err === 'invalid_token')
      setAlert({ type: 'danger', text: 'Lien de réinitialisation invalide ou expiré.' })
    if (params.get('register') === 'success' || params.get('reset') === 'success')
      setAlert({ type: 'success', text: 'Opération réussie, vous pouvez vous connecter.' })
  }, [params])

  const doLogin = async () => {
    if (submitting) return
    setSubmitting(true)
    setAlert(null)
    try {
      // Mémoriser email si remember coché
      if (remember) localStorage.setItem('remember_email', email); else localStorage.removeItem('remember_email')
      const data = await api.post<LoginResponse | TwoFaResponse>('/api/auth/login', { email, password, remember_me: remember })
      // Cas 2FA admin requis
      if ('require_2fa' in data && data.require_2fa) {
        setTwofa({ challengeId: data.challenge_id, code: '' })
        setAlert({ type: 'info', text: data.message || 'Code envoyé par email.' })
        setSubmitting(false)
        return
      }
      const d = data as LoginResponse
      await setToken({ token: d.token, refresh: d.refresh })
      const from = (location.state as { from?: string } | null)?.from
      if (from) navigate(from, { replace: true })
      else navigate(d.user.role === 'admin' ? '/admin' : '/dashboard', { replace: true })
    } catch (err) {
      let msg = 'Erreur inconnue'
      if (err instanceof ApiError) {
        msg = err.message || `Erreur HTTP ${err.status}`
      } else if (err instanceof Error) {
        msg = err.message
      }
      if (msg === 'Failed to fetch' || msg.includes('injoignable')) {
        msg = 'Connexion au serveur impossible. Vérifie ta connexion ou désactive ton adblock.'
      }
      setAlert({ type: 'danger', text: msg })
    } finally {
      setSubmitting(false)
    }
  }

  const doTwoFa = async () => {
    if (submitting || !twofa) return
    setSubmitting(true)
    setAlert(null)
    try {
      const data = await api.post<LoginResponse>('/api/auth/2fa/verify', {
        email,
        challenge_id: twofa.challengeId,
        code: twofa.code,
      })
      await setToken({ token: data.token, refresh: data.refresh })
      navigate(data.user.role === 'admin' ? '/admin' : '/dashboard', { replace: true })
    } catch (err) {
      setAlert({ type: 'danger', text: err instanceof Error ? err.message : 'Code invalide' })
    } finally {
      setSubmitting(false)
    }
  }

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    e.stopPropagation()
    doLogin()
    return false
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '420px', width: '100%' }}>
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-right-to-bracket"></i> Connexion
          </h2>

          {alert && <div className={`alert alert-${alert.type}`}>{alert.text}</div>}

          {twofa ? (
            <form onSubmit={(e) => { e.preventDefault(); doTwoFa() }} autoComplete="off">
              <div className="mb-3">
                <label className="form-label">Code à 6 chiffres envoyé à {email}</label>
                <input
                  type="text"
                  inputMode="numeric"
                  maxLength={6}
                  className="form-control form-control-lg text-center"
                  style={{ letterSpacing: '0.7em', fontSize: '1.4rem', fontFamily: 'monospace' }}
                  value={twofa.code}
                  onChange={(e) => setTwofa({ ...twofa, code: e.target.value.replace(/\D/g, '').slice(0, 6) })}
                  autoFocus
                  required
                />
                <small className="form-text text-muted">Saisis le code reçu par email (valable 10 minutes).</small>
              </div>
              <button type="submit" className="btn btn-primary w-100 fw-bold" disabled={submitting || twofa.code.length !== 6}>
                {submitting ? (<><span className="spinner-border spinner-border-sm me-1"></span>Vérification…</>) : 'Vérifier'}
              </button>
              <button type="button" className="btn btn-link w-100 mt-2 text-decoration-none small"
                onClick={() => { setTwofa(null); setAlert(null) }}>
                ← Utiliser un autre compte
              </button>
            </form>
          ) : (
          <form onSubmit={onSubmit} autoComplete="off">
            <div className="mb-3">
              <label htmlFor="email" className="form-label">
                Email
              </label>
              <input
                type="email"
                id="email"
                className="form-control"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="mb-3">
              <label htmlFor="password" className="form-label">
                Mot de passe
              </label>
              <PasswordField
                label={null as any}
                name="password"
                autoComplete="current-password"
                value={password}
                onChange={setPassword}
              />
            </div>
            <div className="form-check mb-3">
              <input
                type="checkbox"
                className="form-check-input"
                id="remember"
                checked={remember}
                onChange={(e) => setRemember(e.target.checked)}
              />
              <label className="form-check-label small" htmlFor="remember">
                <i className="fa-solid fa-circle-check me-1"></i> Se souvenir de moi (30 jours)
              </label>
            </div>
            <div className="mb-2 text-end">
              <Link to="/reset-request" className="text-primary text-decoration-underline">
                Mot de passe oublié ?
              </Link>
            </div>
            <button
              type="submit"
              className="btn btn-primary w-100 fw-bold"
              disabled={submitting}
              onClick={(e) => {
                // Double-sécurité anti-rechargement (onSubmit a déjà preventDefault,
                // mais si le formulaire n'est pas monté par React on l'intercepte ici aussi).
                e.preventDefault()
                doLogin()
              }}
            >
              {submitting ? (
                <>
                  <span className="spinner-border spinner-border-sm me-1"></span>Connexion…
                </>
              ) : (
                <>
                  <i className="fa-solid fa-right-to-bracket"></i> Se connecter
                </>
              )}
            </button>
            <div className="text-center mt-3">
              Pas encore de compte ?{' '}
              <Link to="/register" className="text-primary text-decoration-underline">
                Inscrivez-vous
              </Link>
            </div>
          </form>
          )}
          {!twofa && <GoogleOneTap mode="login" />}
        </div>
      </div>
    </div>
  )
}
