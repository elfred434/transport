import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import '../styles/contact-originale.css'

/**
 * Contactez-nous — port React de contact.php (design d'origine conservé :
 * carte blanche 900px, formulaire + encart d'informations + carte OpenStreetMap).
 * Le nom et l'email sont pré-remplis depuis le profil connecté, comme
 * $nom_session/$email_session de l'original.
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
    setNom((v) => v || [me.prenom, me.nom].filter(Boolean).join(' '))
    setEmail((v) => v || me.email || '')
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
      setError(
        err instanceof Error ? err.message : "Erreur lors de l'enregistrement du message."
      )
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="contact-originale">
      <div className="container">
        <h2>
          <i className="fa-solid fa-envelope"></i> Contactez-nous
        </h2>
        <div className="contact-row">
          <div className="contact-form">
            {success && <div className="msg-success">Votre message a bien été envoyé. Merci !</div>}
            {error && <div className="msg-error">{error}</div>}
            <form onSubmit={onSubmit}>
              <label htmlFor="nom">Nom</label>
              <input
                type="text"
                id="nom"
                name="nom"
                required
                value={nom}
                onChange={(e) => setNom(e.target.value)}
              />

              <label htmlFor="email">Email</label>
              <input
                type="email"
                id="email"
                name="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />

              <label htmlFor="message">Message</label>
              <textarea
                id="message"
                name="message"
                required
                value={message}
                onChange={(e) => setMessage(e.target.value)}
              ></textarea>

              <button type="submit" disabled={sending}>
                <i className="fa-solid fa-paper-plane"></i> Envoyer
              </button>
            </form>
          </div>

          <div>
            <div className="contact-infos">
              <div>
                <i className="fa-solid fa-envelope"></i> contact@transportcolis.com
              </div>
              <div>
                <i className="fa-solid fa-phone"></i> +229 97 00 00 00
              </div>
              <div>
                <i className="fa-solid fa-location-dot"></i> Porto-Novo, Quartier Hinkoudé, Bénin
              </div>
            </div>
            <div className="map-container">
              <iframe
                src="https://www.openstreetmap.org/export/embed.html?bbox=2.6015%2C6.4855%2C2.6115%2C6.4955&amp;layer=mapnik&amp;marker=6.4905,2.6065"
                width="100%"
                height="100%"
                style={{ border: 0 }}
                allowFullScreen
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                title="Localisation — Porto-Novo, Quartier Hinkoudé, Bénin"
              ></iframe>
            </div>
          </div>

          <div className="text-center mt-4">
            <Link to="/reponses" className="btn btn-outline-primary">
              <i className="fas fa-reply"></i> Voir mes réponses
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
