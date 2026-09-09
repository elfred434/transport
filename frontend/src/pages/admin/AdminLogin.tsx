import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'

interface LoginResponse {
  token: string
  user: { id: number; role: string }
}

/** Espace admin — port de admin/login.html (refuse les comptes non admin). */
export default function AdminLogin() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()
  const { setToken } = useAuth()

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    try {
      const data = await api.post<LoginResponse>('/api/auth/login', { email, password })
      if (data.user.role !== 'admin') {
        setError("Ce compte n'est pas administrateur.")
        return
      }
      await setToken(data.token)
      navigate('/admin', { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <div className="public-body" style={{ minHeight: '100vh' }}>
      <div className="container auth-container">
        <div className="card shadow-lg border-0 rounded-4 p-4">
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-shield-halved"></i> Espace admin
          </h2>

          {error && <div className="alert alert-danger">{error}</div>}

          <form onSubmit={onSubmit} autoComplete="off">
            <div className="mb-3">
              <label className="form-label">Email</label>
              <input
                type="email"
                className="form-control"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="mb-3">
              <label className="form-label">Mot de passe</label>
              <input
                type="password"
                className="form-control"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="btn btn-primary w-100 fw-bold">
              <i className="fa-solid fa-right-to-bracket"></i> Se connecter
            </button>
            <div className="text-center mt-3">
              <Link to="/" className="text-muted small">
                ← Retour au site
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
