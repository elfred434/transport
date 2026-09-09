import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'

interface ResetRequestResponse {
  message: string
  dev_reset_link?: string
}

/** Demande de réinitialisation — port de reset-request.html. */
export default function ResetRequest() {
  const [email, setEmail] = useState('')
  const [result, setResult] = useState<ResetRequestResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setResult(null)
    setError(null)
    try {
      const data = await api.post<ResetRequestResponse>('/api/auth/reset-request', { email })
      setResult(data)
      setEmail('')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '420px', width: '100%' }}>
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-key"></i> Réinitialisation du mot de passe
          </h2>

          {error && <div className="alert alert-danger">{error}</div>}
          {result && (
            <>
              <div className="alert alert-success">{result.message}</div>
              {result.dev_reset_link && (
                <div className="alert alert-info small text-break">
                  Mode développement — lien généré :{' '}
                  <a href={result.dev_reset_link}>{result.dev_reset_link}</a>
                </div>
              )}
            </>
          )}

          <form onSubmit={onSubmit} autoComplete="off">
            <div className="mb-3">
              <label className="form-label">Votre email</label>
              <input
                type="email"
                name="email"
                className="form-control"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="btn btn-primary w-100 fw-bold">
              <i className="fa-solid fa-paper-plane"></i> Envoyer le lien de réinitialisation
            </button>
          </form>
          <Link to="/login" className="btn btn-outline-primary w-100 mt-3 fw-bold">
            <i className="fa-solid fa-right-to-bracket"></i> Retour à la connexion
          </Link>
        </div>
      </div>
    </div>
  )
}
