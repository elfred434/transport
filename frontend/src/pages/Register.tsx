import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, Auth } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import GoogleOneTap from '../components/GoogleOneTap'
import PasswordGenerator from '../components/PasswordGenerator'
import PasswordStrength from '../components/PasswordStrength'
import PhoneInput from '../components/PhoneInput'

interface RegisterResponse {
  token?: string
  refresh?: string
  user?: { id: number }
  message?: string
  email?: string
  email_verified?: boolean
  verification_required?: boolean
  code_envoye?: boolean
}

/** Inscription — port de register.html (multipart avec photo optionnelle). */
export default function Register() {
  const formRef = useRef<HTMLFormElement>(null)
  const passwordRef = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [pwdValue, setPwdValue] = useState('')
  const [emailValue, setEmailValue] = useState('')
  const [emailState, setEmailState] = useState<'idle' | 'checking' | 'ok' | 'invalid' | 'exists'>('idle')
  const navigate = useNavigate()
  const { setToken } = useAuth()

  // Validation email en temps réel (ROADMAP #22) : format basique + vérification anti-énumération discrète côté backend
  useEffect(() => {
    const email = emailValue.trim().toLowerCase()
    if (!email) { setEmailState('idle'); return }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setEmailState('invalid'); return }
    setEmailState('checking')
    const t = setTimeout(async () => {
      try {
        // Endpoint discret (anti-énumération) : /api/auth/check-email
        const r = await fetch(`${import.meta.env.VITE_API_BASE_URL || ''}/api/auth/check-email?email=${encodeURIComponent(email)}`, { credentials: 'include' })
        if (r.ok) setEmailState('ok')
        else setEmailState('ok') // ne jamais afficher "existe" (anti-énumération)
      } catch { setEmailState('ok') }
    }, 500)
    return () => clearTimeout(t)
  }, [emailValue])

  useEffect(() => {
    if (Auth.isLoggedIn()) navigate('/dashboard', { replace: true })
  }, [navigate])

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    const form = formRef.current
    if (!form) return

    const fd = new FormData(form)
    const file = (fd.get('photo_profil') as File | null) || null
    const email = String(fd.get('email') || '')

    try {
      const data = await api.upload<RegisterResponse>(
        '/api/auth/register',
        {
          nom: String(fd.get('nom') || ''),
          prenom: String(fd.get('prenom') || ''),
          email,
          password: String(fd.get('password') || ''),
          telephone: String(fd.get('tel') || ''),
        },
        { photo_profil: file && file.size > 0 ? file : null },
      )
      if (data.verification_required) {
        // Email non vérifié : rediriger vers la page de saisie du code
        sessionStorage.setItem('verify_email', email)
        navigate(`/verify-email?email=${encodeURIComponent(email)}`, { replace: true })
        return
      }
      // Cas historique (compte déjà vérifié, ex: Google One Tap ou future évolution)
      if (data.token && data.refresh) {
        await setToken({ token: data.token, refresh: data.refresh })
        navigate('/dashboard', { replace: true })
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '420px', width: '100%' }}>
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-user-plus"></i> Inscription
          </h2>

          {error && <div className="alert alert-danger">{error}</div>}

          <form ref={formRef} onSubmit={onSubmit} autoComplete="off">
            <div className="mb-3">
              <label className="form-label">Nom</label>
              <input type="text" name="nom" className="form-control" required />
            </div>
            <div className="mb-3">
              <label className="form-label">Prénom</label>
              <input type="text" name="prenom" className="form-control" required />
            </div>
            <div className="mb-3">
              <label className="form-label">Email</label>
              <input type="email" name="email" className="form-control" required
                value={emailValue}
                onChange={(e) => setEmailValue(e.target.value)}
                onInput={(e) => setEmailValue((e.target as HTMLInputElement).value)}
                autoComplete="email" />
              {emailState === 'invalid' && <small className="text-danger">Format d'email invalide.</small>}
            </div>
            <div className="mb-3">
              <label className="form-label">
                Mot de passe <small className="text-muted">(8 caractères min. + 1 chiffre)</small>
              </label>
              <input ref={passwordRef} type="password" name="password" className="form-control" minLength={8} required
                value={pwdValue}
                onChange={(e) => {
                  setPwdValue(e.target.value)
                  // Synchroniser avec le generative password qui utilise la valeur du ref
                  if (passwordRef.current) passwordRef.current.value = e.target.value
                }}
                autoComplete="new-password" />
              <PasswordStrength password={pwdValue} />
              <PasswordGenerator
                onSelect={(p) => {
                  if (passwordRef.current) {
                    passwordRef.current.value = p
                    setPwdValue(p)
                  }
                }}
                inputRef={passwordRef}
              />
            </div>
            <div className="mb-3">
              <label className="form-label">Téléphone</label>
              <PhoneInput name="tel" required />
              <small className="form-text text-muted">Numéro mobile (indicatif pays auto).</small>
            </div>
            <div className="mb-3">
              <label className="form-label">Photo de profil</label>
              <input
                type="file"
                name="photo_profil"
                className="form-control"
                accept="image/*"
              />
            </div>
            <button type="submit" className="btn btn-primary w-100 fw-bold">
              <i className="fa-solid fa-user-plus"></i> S'inscrire
            </button>
            <div className="text-center mt-3">
              Déjà un compte ?{' '}
              <Link to="/login" className="text-primary text-decoration-underline">
                Connectez-vous
              </Link>
            </div>
          </form>
          <GoogleOneTap mode="register" />
        </div>
      </div>
    </div>
  )
}
