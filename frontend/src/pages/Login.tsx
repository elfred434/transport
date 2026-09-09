import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { api, Auth } from '../lib/api'
import { useAuth } from '../context/AuthContext'

interface LoginResponse {
  token: string
  user: { id: number; role: string }
}

/** Connexion — port de login.html (mêmes alertes par query string). */
export default function Login() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; text: string } | null>(null)
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

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlert(null)
    try {
      const data = await api.post<LoginResponse>('/api/auth/login', { email, password })
      await setToken(data.token)
      const from = (location.state as { from?: string } | null)?.from
      if (from) navigate(from, { replace: true })
      else navigate(data.user.role === 'admin' ? '/admin' : '/dashboard', { replace: true })
    } catch (err) {
      setAlert({ type: 'danger', text: err instanceof Error ? err.message : 'Erreur inconnue' })
    }
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '420px', width: '100%' }}>
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-right-to-bracket"></i> Connexion
          </h2>

          {alert && <div className={`alert alert-${alert.type}`}>{alert.text}</div>}

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
              <input
                type="password"
                id="password"
                className="form-control"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <div className="mb-2 text-end">
              <Link to="/reset-request" className="text-primary text-decoration-underline">
                Mot de passe oublié ?
              </Link>
            </div>
            <button type="submit" className="btn btn-primary w-100 fw-bold">
              <i className="fa-solid fa-right-to-bracket"></i> Se connecter
            </button>
            <div className="text-center mt-3">
              Pas encore de compte ?{' '}
              <Link to="/register" className="text-primary text-decoration-underline">
                Inscrivez-vous
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
