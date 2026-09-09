import { useState } from 'react'
import { Link, useSearchParams, useNavigate } from 'react-router-dom'
import { api } from '../lib/api'

export default function ResetPassword() {
  const [params] = useSearchParams()
  const token = params.get('token') || ''
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const navigate = useNavigate()

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null); setSuccess(null)
    if (password !== confirm) { setError('Les mots de passe ne correspondent pas.'); return }
    try {
      const data = await api.post<{ message: string }>('/api/auth/reset-password', { token, password })
      setSuccess(data.message)
      setTimeout(()=>navigate('/login?reset=success'), 1500)
    } catch (err) { setError(err instanceof Error ? err.message : 'Erreur inconnue') }
  }

  if (!token) {
    return (
      <div className="public-body"><div className="container auth-container"><div className="card shadow-lg border-0 rounded-4 p-4">
        <div className="alert alert-danger">Lien de réinitialisation invalide.</div>
        <Link to="/login" className="btn btn-outline-primary w-100 mt-3 fw-bold">Retour à la connexion</Link>
      </div></div></div>
    )
  }

  return (
    <div className="public-body">
      <div className="container auth-container">
        <div className="card shadow-lg border-0 rounded-4 p-4">
          <h2 className="text-center text-primary mb-4"><i className="fa-solid fa-key"></i> Nouveau mot de passe</h2>
          {error && <div className="alert alert-danger">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}
          <form onSubmit={onSubmit} autoComplete="off">
            <div className="mb-3"><label className="form-label">Nouveau mot de passe <small className="text-muted">(8 caractères min.)</small></label><input type="password" className="form-control" minLength={8} required value={password} onChange={e=>setPassword(e.target.value)} /></div>
            <div className="mb-3"><label className="form-label">Confirmer le mot de passe</label><input type="password" className="form-control" minLength={8} required value={confirm} onChange={e=>setConfirm(e.target.value)} /></div>
            <button type="submit" className="btn btn-primary w-100 fw-bold"><i className="fa-solid fa-key"></i> Réinitialiser</button>
          </form>
          <Link to="/login" className="btn btn-outline-primary w-100 mt-3 fw-bold">Retour à la connexion</Link>
        </div>
      </div>
    </div>
  )
}
