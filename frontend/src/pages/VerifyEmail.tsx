import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../context/AuthContext'

/** Page de vérification d'email à l'inscription.
 *
 *  Deux usages :
 *   - Arrivée depuis /register après une inscription réussie (state.pendingEmail)
 *   - Atterrissage direct si l'utilisateur a un token (compte non vérifié) qui
 *     tente d'accéder à une page protégée (on lui demande alors de saisir le
 *     code associé à l'email du compte connecté).
 */
export default function VerifyEmail() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { setToken, me } = useAuth()
  const emailRef = useRef(params.get('email') || sessionStorage.getItem('verify_email') || me?.email || '')
  const [email] = useState<string>(emailRef.current || '')
  const [code, setCode] = useState<string[]>(['', '', '', '', '', ''])
  const inputsRef = useRef<(HTMLInputElement | null)[]>([])
  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [resendCooldown, setResendCooldown] = useState(0)

  useEffect(() => {
    // Si pas du tout connecté et aucun email dans sessionStorage, retour à register
    if (!email) navigate('/register', { replace: true })
    // Focus première case
    inputsRef.current[0]?.focus()
    // Cooldown de 60s au départ
    const left = parseInt(sessionStorage.getItem('verify_resend_at') || '0', 10)
    const remaining = Math.max(0, Math.floor((left - Date.now()) / 1000))
    if (remaining > 0) {
      setResendCooldown(remaining)
      const tick = setInterval(() => {
        setResendCooldown((s) => {
          if (s <= 1) { clearInterval(tick); return 0 }
          return s - 1
        })
      }, 1000)
      return () => clearInterval(tick)
    }
  }, [email, navigate])

  function handleChange(idx: number, val: string) {
    setError(null)
    const v = val.replace(/\D/g, '').slice(0, 1)
    const next = [...code]
    next[idx] = v
    setCode(next)
    if (v && idx < 5) inputsRef.current[idx + 1]?.focus()
    if (v && idx === 5) submit(next.join(''))
  }

  function handleKeyDown(idx: number, e: React.KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Backspace' && !code[idx] && idx > 0) {
      inputsRef.current[idx - 1]?.focus()
      const next = [...code]; next[idx - 1] = ''; setCode(next)
    }
  }

  function handlePaste(e: React.ClipboardEvent<HTMLInputElement>) {
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
    if (pasted.length === 6) {
      setCode(pasted.split(''))
      submit(pasted)
    }
  }

  async function submit(c: string) {
    if (c.length !== 6) return
    setLoading(true); setError(null); setInfo(null)
    try {
      const payload: Record<string, string> = { code: c }
      // Si connecté, pas besoin d'email (JWT suffit), sinon on envoie l'email
      if (!me?.email) payload.email = email
      const data = await api.post<{
        token?: string
        refresh?: string
        user?: any
        email_verified?: boolean
        message?: string
      }>('/api/auth/verify-email', payload)
      if (data.token && data.refresh) await setToken({ token: data.token, refresh: data.refresh })
      else if (data.token) {
        // Récupéré depuis /me ? Login auto — ne se produit pas en pratique
      }
      sessionStorage.removeItem('verify_email')
      sessionStorage.removeItem('verify_resend_at')
      setInfo('Email vérifié avec succès ! Redirection...')
      setTimeout(() => navigate('/dashboard', { replace: true }), 800)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Code invalide')
      // Vider les cases et refocus
      setCode(['', '', '', '', '', ''])
      inputsRef.current[0]?.focus()
    } finally {
      setLoading(false)
    }
  }

  async function resend() {
    if (resendCooldown > 0) return
    setError(null); setInfo(null); setLoading(true)
    try {
      const payload: Record<string, string> = {}
      if (!me?.email) payload.email = email
      await api.post('/api/auth/resend-code', payload)
      const until = Date.now() + 60_000
      sessionStorage.setItem('verify_resend_at', String(until))
      setResendCooldown(60)
      setInfo('Un nouveau code a été envoyé à ton adresse email.')
      const tick = setInterval(() => {
        setResendCooldown((s) => {
          if (s <= 1) { clearInterval(tick); return 0 }
          return s - 1
        })
      }, 1000)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Impossible de renvoyer le code')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '460px', width: '100%' }}>
          <div className="text-center mb-4">
            <div style={{ fontSize: '44px' }}>📧</div>
            <h2 className="text-primary mt-2 mb-1">Vérifie ton email</h2>
            <p className="text-muted mb-0">
              Un code à 6 chiffres vient d'être envoyé à<br />
              <strong>{email}</strong>
            </p>
          </div>

          {error && <div className="alert alert-danger py-2">{error}</div>}
          {info && <div className="alert alert-success py-2">{info}</div>}

          <div className="d-flex justify-content-between gap-2 mb-3" onPaste={handlePaste}>
            {code.map((digit, i) => (
              <input
                key={i}
                ref={(el) => { inputsRef.current[i] = el }}
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={1}
                value={digit}
                onChange={(e) => handleChange(i, e.target.value)}
                onKeyDown={(e) => handleKeyDown(i, e)}
                className="form-control text-center"
                style={{ fontSize: '24px', fontWeight: 700, height: '56px', width: '100%' }}
                disabled={loading}
              />
            ))}
          </div>

          <p className="text-center text-muted small mb-3">
            Le code expire dans 15 minutes.
          </p>

          <button
            type="button"
            className="btn btn-primary w-100 py-2"
            disabled={loading || code.some((c) => !c)}
            onClick={() => submit(code.join(''))}
          >
            {loading ? (<><span className="spinner-border spinner-border-sm me-2"></span>Vérification...</>) : 'Vérifier mon email'}
          </button>

          <div className="text-center mt-3">
            {resendCooldown > 0 ? (
              <span className="text-muted small">Renvoyer dans {resendCooldown}s</span>
            ) : (
              <button type="button" className="btn btn-link btn-sm" onClick={resend} disabled={loading}>
                Renvoyer le code
              </button>
            )}
          </div>

          <div className="text-center mt-2">
            <Link to="/register" className="small text-decoration-none">← Retour à l'inscription</Link>
          </div>
        </div>
      </div>
    </div>
  )
}
