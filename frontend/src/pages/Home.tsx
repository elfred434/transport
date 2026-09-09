import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Auth } from '../lib/api'

/**
 * Page d'accueil — design vanilla (frontend/index.html)
 * Topbar SPIISTMOVE + logo.jpeg, hero dégradé, suivi colis, 3 étapes, footer.
 */
export default function Home() {
  const { me } = useAuth()
  const navigate = useNavigate()
  const loggedIn = me !== null || Auth.isLoggedIn()
  const [trackNum, setTrackNum] = useState('')

  const onTrack = (e: React.FormEvent) => {
    e.preventDefault()
    const num = trackNum.trim()
    if (num) navigate(`/suivi?numero_suivi=${encodeURIComponent(num)}`)
  }

  return (
    <div className="public-body">
      <div className="topbar d-flex justify-content-between align-items-center">
        <div className="fw-bold text-primary">
          <img src="/assets/img/logo.jpeg" alt="" style={{ height: 38, borderRadius: 6 }} className="me-2" />
          SPIISTMOVE
        </div>
        <div>
          {loggedIn ? (
            <Link to="/dashboard" className="btn btn-primary fw-bold">
              <i className="fa-solid fa-gauge"></i> Mon espace
            </Link>
          ) : (
            <>
              <Link to="/login" className="btn btn-outline-primary fw-bold me-2">
                Connexion
              </Link>
              <Link to="/register" className="btn btn-primary fw-bold">
                Inscription
              </Link>
            </>
          )}
        </div>
      </div>

      <div className="hero">
        <h1><i className="fa-solid fa-truck-fast"></i> Agence de Transport de Colis</h1>
        <p className="lead mt-3">Postez vos colis, trouvez des transporteurs vérifiés et suivez vos envois en temps réel.</p>
        <div className="mt-4 d-flex gap-3 justify-content-center flex-wrap">
          <Link to="/register" className="btn btn-light btn-lg fw-bold">
            <i className="fa-solid fa-user-plus"></i> Créer un compte
          </Link>
          <Link to="/login" className="btn btn-outline-light btn-lg fw-bold">
            <i className="fa-solid fa-right-to-bracket"></i> Se connecter
          </Link>
        </div>

        <div className="container mt-5" style={{ maxWidth: 640 }}>
          <div className="card shadow border-0 rounded-4 p-3 text-start text-dark">
            <label className="form-label fw-bold"><i className="fa-solid fa-search-location"></i> Suivre un colis</label>
            <form onSubmit={onTrack} className="d-flex gap-2">
              <input
                type="text"
                className="form-control"
                placeholder="Numéro de suivi (ex. COLIS...)"
                value={trackNum}
                onChange={(e) => setTrackNum(e.target.value)}
                required
              />
              <button className="btn btn-primary fw-bold" type="submit">Suivre</button>
            </form>
          </div>
        </div>
      </div>

      <div className="container py-5">
        <h2 className="text-center text-primary mb-5">Comment ça marche ?</h2>
        <div className="row g-4">
          <div className="col-md-4">
            <div className="card step-card p-4 text-center">
              <div className="step-icon"><i className="fa-solid fa-box"></i></div>
              <h5 className="mt-3">1. Postez votre colis</h5>
              <p className="text-muted mb-0">Décrivez votre colis (poids, destination, photos) et recevez un numéro de suivi unique.</p>
            </div>
          </div>
          <div className="col-md-4">
            <div className="card step-card p-4 text-center">
              <div className="step-icon"><i className="fa-solid fa-handshake"></i></div>
              <h5 className="mt-3">2. Choisissez un transporteur</h5>
              <p className="text-muted mb-0">Comparez les voyages disponibles, les avis, puis acceptez une réservation.</p>
            </div>
          </div>
          <div className="col-md-4">
            <div className="card step-card p-4 text-center">
              <div className="step-icon"><i className="fa-solid fa-search-location"></i></div>
              <h5 className="mt-3">3. Suivez la livraison</h5>
              <p className="text-muted mb-0">Suivez chaque étape jusqu'à la livraison, confirmée par l'agence.</p>
            </div>
          </div>
        </div>

        <div className="text-center mt-5">
          <Link to="/contact" className="btn btn-outline-primary btn-lg">
            <i className="fa-solid fa-phone-alt"></i> Nous contacter
          </Link>
        </div>
      </div>

      <footer className="text-center text-muted py-4 border-top bg-white">
        <p className="mb-1"><strong>Agence de Transport de Colis</strong> — Porto-Novo, quartier Hinkoudé, Bénin</p>
        <p className="mb-0 small">© 2025 SPIISTMOVE</p>
      </footer>
    </div>
  )
}
