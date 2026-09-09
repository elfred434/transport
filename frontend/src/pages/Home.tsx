import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'

/** Page d'accueil publique — port de index.html. */
export default function Home() {
  const [numero, setNumero] = useState('')
  const navigate = useNavigate()

  const onTrack = (e: React.FormEvent) => {
    e.preventDefault()
    const num = numero.trim()
    if (num) navigate('/suivi?numero_suivi=' + encodeURIComponent(num))
  }

  return (
    <>
      <style>{`
        .hero { background: linear-gradient(135deg, #3498db 0%, #34495e 100%); color: #fff; padding: 90px 20px; text-align: center; }
        .hero h1 { font-size: 2.6rem; font-weight: bold; }
        .step-card { border: none; box-shadow: 0 2px 10px #0001; border-radius: 12px; height: 100%; }
        .step-icon { font-size: 2.4rem; color: var(--primary); }
        .topbar { background: #fff; box-shadow: 0 1px 6px #0001; padding: 12px 24px; }
      `}</style>

      <div className="hero">
        <h1>
          <i className="fa-solid fa-truck-fast"></i> Agence de Transport de Colis
        </h1>
        <p className="lead mt-3">
          Postez vos colis, trouvez des transporteurs vérifiés et suivez vos envois en temps réel.
        </p>
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
            <label className="form-label fw-bold" htmlFor="track-num">
              <i className="fa-solid fa-search-location"></i> Suivre un colis
            </label>
            <form onSubmit={onTrack} className="d-flex gap-2">
              <input
                type="text"
                id="track-num"
                className="form-control"
                placeholder="Numéro de suivi (ex. COLIS...)"
                value={numero}
                onChange={(e) => setNumero(e.target.value)}
                required
              />
              <button className="btn btn-primary fw-bold" type="submit">
                Suivre
              </button>
            </form>
          </div>
        </div>
      </div>

      <div className="container py-5">
        <h2 className="text-center text-primary mb-5">Comment ça marche ?</h2>
        <div className="row g-4">
          <div className="col-md-4">
            <div className="card step-card p-4 text-center">
              <div className="step-icon">
                <i className="fa-solid fa-box"></i>
              </div>
              <h5 className="mt-3">1. Postez votre colis</h5>
              <p className="text-muted mb-0">
                Décrivez votre colis (poids, destination, photos) et recevez un numéro de suivi
                unique.
              </p>
            </div>
          </div>
          <div className="col-md-4">
            <div className="card step-card p-4 text-center">
              <div className="step-icon">
                <i className="fa-solid fa-handshake"></i>
              </div>
              <h5 className="mt-3">2. Choisissez un transporteur</h5>
              <p className="text-muted mb-0">
                Comparez les voyages disponibles, les avis, puis acceptez une réservation.
              </p>
            </div>
          </div>
          <div className="col-md-4">
            <div className="card step-card p-4 text-center">
              <div className="step-icon">
                <i className="fa-solid fa-search-location"></i>
              </div>
              <h5 className="mt-3">3. Suivez la livraison</h5>
              <p className="text-muted mb-0">
                Suivez chaque étape jusqu'à la livraison, confirmée par l'agence.
              </p>
            </div>
          </div>
        </div>

        <div className="text-center mt-5">
          <Link to="/contact" className="btn btn-outline-primary btn-lg">
            <i className="fa-solid fa-phone-alt"></i> Nous contacter
          </Link>
        </div>
      </div>
    </>
  )
}
