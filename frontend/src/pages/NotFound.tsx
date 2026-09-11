import { Link } from 'react-router-dom'

export default function NotFound() {
  return (
    <div className="d-flex align-items-center justify-content-center" style={{ minHeight: '60vh' }}>
      <div className="text-center">
        <h1 className="display-1 fw-bold text-primary">404</h1>
        <h2 className="mb-3">Page introuvable</h2>
        <p className="text-muted mb-4">
          La page que vous cherchez n'existe pas ou a été déplacée.
        </p>
        <Link to="/dashboard" className="btn btn-primary btn-lg">
          <i className="fa-solid fa-house me-2" /> Retour au tableau de bord
        </Link>
      </div>
    </div>
  )
}
