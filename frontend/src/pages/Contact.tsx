import { useEffect, useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import '../styles/contact-originale.css'
import '../styles/home-originale.css'

export default function Contact() {
  const { me, isSuperAdmin, isAdmin, logout } = useAuth()
  const navigate = useNavigate()
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
      setError(err instanceof Error ? err.message : "Erreur lors de l'enregistrement du message.")
    } finally {
      setSending(false)
    }
  }

  const onLogout = async (e: React.MouseEvent) => {
    e.preventDefault()
    await logout()
    navigate('/login')
  }

  // Sidebar links (same as AppLayout but with small logo)
  const links = [
    { to: '/dashboard', icon: 'fa-home', label: 'Accueil', show: !!me },
    { to: '/poster-colis', icon: 'fa-box', label: 'Postez Colis', show: !!me },
    { to: '/profil', icon: 'fa-user', label: 'Profil', show: !!me },
    { to: '/liste-messagerie', icon: 'fa-envelope', label: 'Messages', show: !!me },
    { to: '/messagerie-admin', icon: 'fa-headset', label: "Contacter l'admin", show: !!me },
    { to: '/colis', icon: 'fa-box-open', label: 'Colis disponibles', show: !!me },
    { to: '/suivi', icon: 'fa-search-location', label: 'Suivi', show: !!me },
    { to: '/recherche', icon: 'fa-magnifying-glass', label: 'Recherche', show: !!me },
    { to: '/reponses', icon: 'fa-reply', label: 'Mes réponses', show: !!me },
    { to: '/contact', icon: 'fa-phone-alt', label: 'Contact', show: true },
    { to: '/admin', icon: 'fa-shield-halved', label: 'Administration', show: !!isAdmin },
    { to: '/super-admin', icon: 'fa-crown', label: 'Super Admin', show: !!isSuperAdmin },
  ]

  const contactContent = (
    <div className="home-originale w-100 d-flex flex-column align-items-center">
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 960}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fa-solid fa-envelope" style={{color: 'var(--primary-color)'}}></i> Contactez-nous</h2>
          <div className="card w-100" style={{maxWidth: 900, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            <div className="contact-row d-flex flex-wrap gap-4 justify-content-center">
              <div className="contact-form flex-grow-1" style={{minWidth: 320, maxWidth: 450}}>
                {success && <div className="msg-success text-center p-2 mb-3" style={{background: '#d1fae5', color: '#065f46', borderRadius: 8}}>Votre message a bien été envoyé. Merci !</div>}
                {error && <div className="msg-error text-center p-2 mb-3" style={{background: '#fee2e2', color: '#991b1b', borderRadius: 8}}>{error}</div>}
                <form onSubmit={onSubmit}>
                  <label htmlFor="nom" className="form-label fw-bold">Nom</label>
                  <input type="text" id="nom" className="form-control form-control-lg mb-3" required value={nom} onChange={(e) => setNom(e.target.value)} />
                  <label htmlFor="email" className="form-label fw-bold">Email</label>
                  <input type="email" id="email" className="form-control form-control-lg mb-3" required value={email} onChange={(e) => setEmail(e.target.value)} />
                  <label htmlFor="message" className="form-label fw-bold">Message</label>
                  <textarea id="message" className="form-control form-control-lg mb-3" rows={4} required value={message} onChange={(e) => setMessage(e.target.value)}></textarea>
                  <button type="submit" className="btn btn-primary btn-lg w-100 fw-bold" disabled={sending}><i className="fa-solid fa-paper-plane"></i> {sending ? 'Envoi...' : 'Envoyer'}</button>
                </form>
                <div className="text-center mt-4">
                  <Link to="/reponses" className="btn btn-outline-primary"><i className="fas fa-reply"></i> Voir mes réponses</Link>
                </div>
              </div>
              <div className="flex-grow-1" style={{minWidth: 320, maxWidth: 400}}>
                <div className="contact-infos p-3 mb-3" style={{background: '#f1f8ff', borderRadius: 8}}>
                  <div className="mb-2"><i className="fa-solid fa-envelope" style={{color: 'var(--primary-color)'}}></i> contact@transportcolis.com</div>
                  <div className="mb-2"><i className="fa-solid fa-phone" style={{color: 'var(--primary-color)'}}></i> +229 97 00 00 00</div>
                  <div><i className="fa-solid fa-location-dot" style={{color: 'var(--primary-color)'}}></i> Porto-Novo, Quartier Hinkoudé, Bénin</div>
                </div>
                <div className="map-container" style={{height: 260, borderRadius: 8, overflow: 'hidden'}}>
                  <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=2.6015%2C6.4855%2C2.6115%2C6.4955&amp;layer=mapnik&amp;marker=6.4905,2.6065" width="100%" height="100%" style={{ border: 0 }} allowFullScreen loading="lazy" referrerPolicy="no-referrer-when-downgrade" title="Localisation"></iframe>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  )

  // Si connecté → avec sidebar (comme les autres pages)
  if (me) {
    return (
      <>
        <div id="sidebar">
          <div className="vertical-menu">
            <div className="logo-container" style={{padding: '12px 16px'}}>
              <Link to="/" className="d-flex align-items-center justify-content-center gap-2 text-decoration-none">
                <img src="/assets/img/OIG1.jpeg" alt="Logo" style={{height: 42, width: 42, borderRadius: '50%', objectFit: 'cover'}} />
                <span className="fw-bold" style={{color: 'white', fontSize: '1rem'}}>SPIISTMOVE</span>
              </Link>
            </div>
            <div className="user-box" style={{padding: '10px 16px'}}>
              {me.photo_url ? <img src={me.photo_url} alt="" className="user-avatar" style={{width: 40, height: 40}} /> : <div className="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-1" style={{width: 40, height: 40}}><i className="fas fa-user text-primary"></i></div>}
              <div className="user-name" style={{fontSize: '0.85rem'}}>{me.prenom} {me.nom}</div>
              <div className="d-flex gap-1 justify-content-center flex-wrap" style={{fontSize: '0.7rem'}}>
                <span className="badge bg-light text-dark" style={{fontSize: '0.65rem'}}>{me.role}</span>
                {isSuperAdmin && <span className="badge bg-warning text-dark" style={{fontSize: '0.6rem'}}><i className="fa-solid fa-crown"></i> Super</span>}
              </div>
            </div>
            <ul className="menu-items">
              {links.filter(l=>l.show).map(l=>(
                <li key={l.to}><NavLink to={l.to} className={({ isActive }) => (isActive ? 'active' : '')}><i className={`fas ${l.icon}`}></i> {l.label}</NavLink></li>
              ))}
              <li><a href="#" onClick={onLogout}><i className="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
          </div>
        </div>
        <div className="content">
          {contactContent}
        </div>
      </>
    )
  }

  // Si non connecté → version publique avec topbar (comme Home)
  return (
    <div className="public-body" style={{background: 'var(--light-color)'}}>
      <div className="topbar d-flex justify-content-between align-items-center" style={{background: 'white', boxShadow: '0 1px 6px #0001', padding: '12px 24px'}}>
        <Link to="/" className="fw-bold text-decoration-none" style={{color: 'var(--primary-color)'}}><img src="/assets/img/OIG1.jpeg" alt="" style={{height: 38, width: 38, borderRadius: '50%', marginRight: 8}} />SPIISTMOVE</Link>
        <div className="d-flex gap-2">
          <Link to="/login" className="btn btn-outline-primary btn-sm">Connexion</Link>
          <Link to="/register" className="btn btn-primary btn-sm">Inscription</Link>
        </div>
      </div>
      {contactContent}
      <footer className="text-center text-muted py-4 border-top bg-white mt-4">
        <p className="mb-1"><strong>Agence de Transport de Colis</strong> — Porto-Novo, quartier Hinkoudé, Bénin</p>
        <p className="mb-0 small">© 2025 SPIISTMOVE</p>
      </footer>
    </div>
  )
}
