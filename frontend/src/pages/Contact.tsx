import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../context/AuthContext'

/**
 * Contact — design vanilla (contact.html)
 * public-container avec adresse + formulaire
 */
export default function Contact() {
  const { me } = useAuth()
  const [nom, setNom] = useState('')
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('')
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)

  useEffect(() => {
    if (!me) return
    setNom(v => v || [me.prenom, me.nom].filter(Boolean).join(' '))
    setEmail(v => v || me.email || '')
  }, [me])

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSuccess(false)
    setError('')
    setSending(true)
    try {
      await api.post('/api/contact', { nom, email, message })
      setSuccess(true)
      setMessage('')
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur lors de l'envoi.")
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="public-body">
      <div className="public-container" style={{ maxWidth: 900 }}>
        <div className="d-flex justify-content-between align-items-center mb-4">
          <h2 className="text-primary mb-0"><i className="fa-solid fa-phone-alt"></i> Nous contacter</h2>
          <Link to="/" className="btn btn-outline-primary">← Accueil</Link>
        </div>

        <div className="row g-4">
          <div className="col-md-5">
            <div className="page-card h-100">
              <h5><i className="fa-solid fa-location-dot text-primary"></i> Adresse</h5>
              <p>Porto-Novo, quartier Hinkoudé, Bénin</p>
              <h5 className="mt-4"><i className="fa-solid fa-envelope text-primary"></i> Email</h5>
              <p><a href="mailto:contact@spiistmove.bj">contact@spiistmove.bj</a></p>
              <div className="alert alert-info small mb-0">
                Connecté ? Utilisez <Link to="/messagerie-admin" className="alert-link">la messagerie admin</Link> pour un suivi de vos demandes.
              </div>
              <div className="mt-3">
                <Link to="/reponses" className="btn btn-outline-primary btn-sm"><i className="fas fa-reply"></i> Voir mes réponses</Link>
              </div>
            </div>
          </div>
          <div className="col-md-7">
            <div className="page-card h-100">
              {success && <div className="alert alert-success">Votre message a bien été envoyé. Merci !</div>}
              {error && <div className="alert alert-danger">{error}</div>}
              <form onSubmit={onSubmit} autoComplete="off">
                <div className="mb-3">
                  <label className="form-label">Votre nom</label>
                  <input type="text" className="form-control" required value={nom} onChange={e=>setNom(e.target.value)} />
                </div>
                <div className="mb-3">
                  <label className="form-label">Votre email</label>
                  <input type="email" className="form-control" required value={email} onChange={e=>setEmail(e.target.value)} />
                </div>
                <div className="mb-3">
                  <label className="form-label">Message</label>
                  <textarea className="form-control" rows={4} required value={message} onChange={e=>setMessage(e.target.value)}></textarea>
                </div>
                <button type="submit" className="btn btn-primary w-100 fw-bold" disabled={sending}>
                  <i className="fa-solid fa-paper-plane"></i> {sending ? 'Envoi…' : 'Envoyer'}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
