import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../lib/api'

/** Nouveau mot de passe — port de reset-password.html (token en query string). */
export default function ResetPassword() {
  const [params] = useSearchParams()
  const token = params.get('token') || ''
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; text: string } | null>(null)
  const navigate = useNavigate()

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlert(null)

    if (password !== confirm) {
      setAlert({ type: 'danger', text: 'Les mots de passe ne correspondent pas.' })
      return
    }
    try {
      const data = await api.post<{ message: string }>('/api/auth/reset-password', {
        token,
        password,
      })
      setAlert({ type: 'success', text: data.message })
      setTimeout(() => navigate('/login?reset=success', { replace: true }), 1500)
    } catch (err) {
      setAlert({ type: 'danger', text: err instanceof Error ? err.message : 'Erreur inconnue' })
    }
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '420px', width: '100%' }}>
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-key"></i> Nouveau mot de passe
          </h2>

          {!token && <div className="alert alert-danger">Lien de réinitialisation invalide.</div>}
          {alert && <div className={`alert alert-${alert.type}`}>{alert.text}</div>}

          <form onSubmit={onSubmit} autoComplete="off" style={{ display: token ? undefined : 'none' }}>
            <div className="mb-3">
              <label className="form-label">
                Nouveau mot de passe <small className="text-muted">(8 caractères min.)</small>
              </label>
              <input
                type="password"
                className="form-control"
                minLength={8}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <div className="mb-3">
              <label className="form-label">Confirmer le mot de passe</label>
              <input
                type="password"
                className="form-control"
                minLength={8}
                value={confirm}
                onChange={(e) => setConfirm(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="btn btn-primary w-100 fw-bold">
              <i className="fa-solid fa-key"></i> Réinitialiser le mot de passe
            </button>
          </form>
          <Link to="/login" className="btn btn-outline-primary w-100 mt-3 fw-bold">
            Retour à la connexion
          </Link>
        </div>
      </div>
    </div>
  )
}
