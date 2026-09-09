import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'

/** Nous contacter — port de contact.html (formulaire public + coordonnées). */
export default function Contact() {
  const [nom, setNom] = useState('')
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('')
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; text: string } | null>(null)

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlert(null)
    try {
      const data = await api.post<{ message: string }>('/api/contact', { nom, email, message })
      setAlert({ type: 'success', text: data.message })
      setNom('')
      setEmail('')
      setMessage('')
    } catch (err) {
      setAlert({ type: 'danger', text: err instanceof Error ? err.message : 'Erreur inconnue' })
    }
  }

  return (
    <div className="container public-container">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="text-primary mb-0">
          <i className="fa-solid fa-phone-alt"></i> Nous contacter
        </h2>
        <Link to="/" className="btn btn-outline-primary">
          ← Accueil
        </Link>
      </div>

      <div className="row g-4">
        <div className="col-md-5">
          <div className="page-card h-100">
            <h5>
              <i className="fa-solid fa-location-dot text-primary"></i> Adresse
            </h5>
            <p>Porto-Novo, quartier Hinkoudé, Bénin</p>
            <h5 className="mt-4">
              <i className="fa-solid fa-envelope text-primary"></i> Email
            </h5>
            <p>
              <a href="mailto:contact@spiistmove.bj">contact@spiistmove.bj</a>
            </p>
            <div className="alert alert-info small mb-0">
              Connecté ? Utilisez{' '}
              <Link to="/messagerie-admin" className="alert-link">
                la messagerie admin
              </Link>{' '}
              pour un suivi de vos demandes.
            </div>
          </div>
        </div>
        <div className="col-md-7">
          <div className="page-card h-100">
            {alert && <div className={`alert alert-${alert.type}`}>{alert.text}</div>}
            <form onSubmit={onSubmit} autoComplete="off">
              <div className="mb-3">
                <label className="form-label">Votre nom</label>
                <input
                  type="text"
                  className="form-control"
                  value={nom}
                  onChange={(e) => setNom(e.target.value)}
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Votre email</label>
                <input
                  type="email"
                  className="form-control"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Message</label>
                <textarea
                  className="form-control"
                  rows={4}
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  required
                />
              </div>
              <button type="submit" className="btn btn-primary w-100 fw-bold">
                <i className="fa-solid fa-paper-plane"></i> Envoyer
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}
