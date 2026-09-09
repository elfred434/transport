import { type ReactNode } from 'react'
import { Link, NavLink, Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

/**
 * Layouts — port des structures vanilla :
 *  - AppLayout : barre latérale des pages authentifiées (UI.renderSidebar).
 */

interface SidebarLink {
  to: string
  icon: string
  label: string
  show?: boolean
}

export function AppLayout() {
  const { me, loading, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  // `navigate` sert uniquement au clic de déconnexion (effet), jamais au rendu.

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '100vh' }}>
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Chargement…</span>
        </div>
      </div>
    )
  }

  if (!me) {
    // Session absente/expirée : retour à la connexion en conservant la cible.
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  const links: SidebarLink[] = [
    { to: '/dashboard', icon: 'fa-home', label: 'Accueil' },
    { to: '/poster-colis', icon: 'fa-box', label: 'Postez Colis' },
    { to: '/profil', icon: 'fa-user', label: 'Profil' },
    { to: '/liste-messagerie', icon: 'fa-envelope', label: 'Messages' },
    { to: '/messagerie-admin', icon: 'fa-headset', label: "Contacter l'admin" },
    { to: '/devenir-transporteur', icon: 'fa-id-badge', label: 'Devenir Transporteur' },
    { to: '/colis', icon: 'fa-box-open', label: 'Colis disponibles' },
    { to: '/suivi', icon: 'fa-search-location', label: 'Suivi' },
    { to: '/recherche', icon: 'fa-magnifying-glass', label: 'Recherche' },
    { to: '/transporteur-stats', icon: 'fa-chart-line', label: 'Mes statistiques', show: !!me.is_transporteur },
    { to: '/reponses', icon: 'fa-reply', label: 'Mes réponses' },
    { to: '/contact', icon: 'fa-phone-alt', label: 'Contact' },
    { to: '/admin', icon: 'fa-shield-halved', label: 'Administration', show: me.role === 'admin' },
  ]

  const onLogout = async (e: React.MouseEvent) => {
    e.preventDefault()
    await logout()
    navigate('/login')
  }

  return (
    <>
      <div id="sidebar">
        <div className="vertical-menu">
          <div className="logo-container">
            <img src="/assets/img/OIG1.jpeg" alt="Logo SPIISTMOVE" className="logo" />
          </div>
          <div className="user-box">
            {me.photo_url ? <img src={me.photo_url} alt="" className="user-avatar" /> : null}
            <div className="user-name">
              {me.prenom} {me.nom}
            </div>
            <span className="badge bg-light text-dark">{me.role}</span>
          </div>
          <ul className="menu-items">
            {links
              .filter((l) => l.show !== false)
              .map((l) => (
                <li key={l.to}>
                  <NavLink to={l.to} className={({ isActive }) => (isActive ? 'active' : '')} end={l.to === '/dashboard'}>
                    <i className={`fas ${l.icon}`}></i> {l.label}
                  </NavLink>
                </li>
              ))}
            <li>
              <a href="#" onClick={onLogout}>
                <i className="fas fa-sign-out-alt"></i> Déconnexion
              </a>
            </li>
          </ul>
        </div>
      </div>
      <div className="content">
        <Outlet />
      </div>
    </>
  )
}

/** Garde admin : équivalent de UI.requireAdmin(). */
export function AdminGate({ children }: { children: ReactNode }) {
  const { me, loading } = useAuth()

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '100vh' }}>
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Chargement…</span>
        </div>
      </div>
    )
  }

  if (!me) return null // AppLayout a déjà redirigé

  if (me.role !== 'admin') {
    return (
      <div className="p-5 text-center">
        <h3>Accès réservé aux administrateurs</h3>
        <Link className="btn btn-primary mt-3" to="/">
          Retour à l'accueil
        </Link>
      </div>
    )
  }

  return <>{children}</>
}
