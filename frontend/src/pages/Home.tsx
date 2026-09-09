import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Auth } from '../lib/api'
import '../styles/home-originale.css'

/**
 * Page d'accueil — design d'origine (index.php du premier dépôt) converti en React.
 * Navbar SPIISTMOVE + logo OIG1.jpeg, header dégradé, 4 sections,
 * bouton « vidéo explicative » ouvrant la modale Animaker.mp4, footer.
 */
export default function Home() {
  const { me, logout } = useAuth()
  const navigate = useNavigate()
  const loggedIn = me !== null || Auth.isLoggedIn()

  const [menuOpen, setMenuOpen] = useState(false)
  const [videoOpen, setVideoOpen] = useState(false)
  const videoRef = useRef<HTMLVideoElement>(null)

  // Comportement d'origine : la vidéo démarre à l'ouverture de la modale.
  useEffect(() => {
    if (videoOpen) videoRef.current?.play().catch(() => {})
  }, [videoOpen])

  const closeVideo = () => {
    videoRef.current?.pause()
    setVideoOpen(false)
  }

  const onLogout = async (e: React.MouseEvent) => {
    e.preventDefault()
    await logout()
    navigate('/')
  }

  const closeMenu = () => setMenuOpen(false)

  return (
    <div className="home-originale">
      <nav className="main-navbar">
        <div className="container-navbar">
          <Link to="/" className="nav-logo">
            <img
              src="/assets/img/OIG1.jpeg"
              alt="Logo"
              style={{ height: 38, verticalAlign: 'middle', borderRadius: '50%', marginRight: 8 }}
            />
            <span>SPIISTMOVE</span>
          </Link>
          <button className="menu-toggle" aria-label="Menu" onClick={() => setMenuOpen((o) => !o)}>
            <i className="fas fa-bars"></i>
          </button>
          <ul className={'nav-links' + (menuOpen ? ' active' : '')}>
            <li>
              <Link to="/" onClick={closeMenu}>
                Accueil
              </Link>
            </li>
            {loggedIn ? (
              <>
                <li>
                  <Link to="/profil" onClick={closeMenu}>
                    Profil
                  </Link>
                </li>
                <li>
                  <Link to="/liste-messagerie" onClick={closeMenu}>
                    Messagerie
                  </Link>
                </li>
                <li>
                  <Link to="/poster-colis" onClick={closeMenu}>
                    Poster un colis
                  </Link>
                </li>
                <li>
                  <Link to="/devenir-transporteur" onClick={closeMenu}>
                    Devenir transporteur
                  </Link>
                </li>
                <li>
                  <a href="#" onClick={onLogout}>
                    Déconnexion
                  </a>
                </li>
              </>
            ) : (
              <>
                <li>
                  <Link to="/login" onClick={closeMenu}>
                    Connexion
                  </Link>
                </li>
                <li>
                  <Link to="/register" onClick={closeMenu}>
                    Inscription
                  </Link>
                </li>
              </>
            )}
          </ul>
        </div>
      </nav>

      <header>
        <div className="container header-content">
          <div className="header-text">
            <h1>
              Envoyez vos colis partout,
              <br />
              simplement et rapidement !
            </h1>
          </div>
          <div className="btn-group">
            <Link to="/poster-colis" className="btn btn-primary">
              <i className="fas fa-box"></i> Poster un colis
            </Link>
            <Link to="/devenir-transporteur" className="btn btn-outline">
              <i className="fas fa-truck"></i> Devenir transporteur
            </Link>
          </div>
        </div>
      </header>

      <main className="container">
        <section className="section">
          <h2 className="section-title">Comment ça marche ?</h2>
          <div className="grid grid-3">
            <div className="card">
              <div className="card-icon">
                <i className="fas fa-clipboard-list"></i>
              </div>
              <h3 className="card-title">1. Déposez votre annonce</h3>
              <p>Décrivez votre colis et sa destination en quelques clics.</p>
            </div>
            <div className="card">
              <div className="card-icon">
                <i className="fas fa-user-check"></i>
              </div>
              <h3 className="card-title">2. Trouvez un transporteur</h3>
              <p>Sélectionnez un transporteur disponible et fiable.</p>
            </div>
            <div className="card">
              <div className="card-icon">
                <i className="fas fa-truck-fast"></i>
              </div>
              <h3 className="card-title">3. Envoyez votre colis</h3>
              <p>Suivez la livraison en temps réel jusqu'à la réception.</p>
            </div>
          </div>
        </section>

        <section className="section">
          <h2 className="section-title">Nos avantages</h2>
          <div className="grid grid-3">
            <div className="feature-card">
              <div className="feature-icon">
                <i className="fas fa-shield-halved"></i>
              </div>
              <h3 className="feature-title">Sécurité</h3>
              <p>Assurance colis et suivi en temps réel.</p>
            </div>
            <div className="feature-card">
              <div className="feature-icon">
                <i className="fas fa-bolt"></i>
              </div>
              <h3 className="feature-title">Rapidité</h3>
              <p>Livraison express et transporteurs partout en France.</p>
            </div>
            <div className="feature-card">
              <div className="feature-icon">
                <i className="fas fa-coins"></i>
              </div>
              <h3 className="feature-title">Économie</h3>
              <p>Tarifs compétitifs et offres personnalisées.</p>
            </div>
          </div>
        </section>

        <section className="section">
          <h2 className="section-title">Nos chiffres clés</h2>
          <div className="grid grid-3">
            <div className="stat-card">
              <div className="stat-icon">
                <i className="fas fa-users"></i>
              </div>
              <div className="stat-number">+10 000</div>
              <p>Utilisateurs inscrits</p>
            </div>
            <div className="stat-card">
              <div className="stat-icon">
                <i className="fas fa-boxes-packing"></i>
              </div>
              <div className="stat-number">+25 000</div>
              <p>Colis envoyés</p>
            </div>
            <div className="stat-card">
              <div className="stat-icon">
                <i className="fas fa-globe"></i>
              </div>
              <div className="stat-number">15</div>
              <p>Pays desservis</p>
            </div>
          </div>
        </section>

        <section className="section">
          <h2 className="section-title">Ils nous font confiance</h2>
          <div className="grid grid-3">
            <div className="testimonial">
              <p className="testimonial-text">Service rapide et fiable, mon colis est arrivé en avance !</p>
              <p className="testimonial-author">- Marie D.</p>
            </div>
            <div className="testimonial">
              <p className="testimonial-text">J'ai pu envoyer un colis à l'étranger sans stress. Merci !</p>
              <p className="testimonial-author">- Ahmed B.</p>
            </div>
            <div className="testimonial">
              <p className="testimonial-text">Interface simple et transporteurs très professionnels.</p>
              <p className="testimonial-author">- Sophie L.</p>
            </div>
          </div>
        </section>
      </main>

      {/* Bloc bouton vidéo explicative */}
      <div className="text-center my-5">
        <button
          id="ouvrirModale"
          className="btn btn-primary"
          style={{ fontSize: '1.1rem' }}
          onClick={() => setVideoOpen(true)}
        >
          <i className="fas fa-play-circle"></i> Voir la vidéo explicative
        </button>
      </div>

      {/* Modale vidéo */}
      {videoOpen && (
        <div
          className="modale"
          id="modaleVideo"
          style={{
            display: 'flex',
            position: 'fixed',
            zIndex: 1000,
            left: 0,
            top: 0,
            width: '100vw',
            height: '100vh',
            background: 'rgba(0,0,0,0.6)',
            alignItems: 'center',
            justifyContent: 'center',
          }}
          onClick={(e) => {
            // Clic sur l'arrière-plan = fermer (comportement d'origine)
            if (e.target === e.currentTarget) closeVideo()
          }}
        >
          <div
            className="contenu-modale"
            style={{
              background: '#fff',
              maxWidth: 700,
              width: '90%',
              borderRadius: 12,
              boxShadow: '0 8px 32px #0003',
              position: 'relative',
              padding: '1.5rem',
            }}
          >
            <span
              className="fermer"
              style={{ position: 'absolute', top: 10, right: 20, fontSize: '2rem', cursor: 'pointer', color: '#2563eb' }}
              onClick={closeVideo}
            >
              &times;
            </span>
            <video
              src="/assets/video/Animaker.mp4"
              id="video"
              ref={videoRef}
              style={{ width: '100%', height: 'auto', maxHeight: '60vh', borderRadius: 8 }}
              controls
            />
          </div>
        </div>
      )}

      <footer>
        <div className="container">
          <div className="footer-links">
            <Link to="/contact" className="footer-link">
              Contact
            </Link>
            <a href="#" className="footer-link">
              FAQ
            </a>
            <a href="#" className="footer-link">
              CGU
            </a>
          </div>
          <p className="copyright">&copy; 2025 Agence de Transport de Colis</p>
        </div>
      </footer>
    </div>
  )
}
