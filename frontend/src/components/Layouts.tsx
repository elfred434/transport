import { type ReactNode } from 'react'
import { Link, NavLink, Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

interface SidebarLink {
  to: string
  icon: string
  label: string
  show?: boolean
  badge?: string
}

export function AppLayout() {
  const { me, loading, logout, isSuperAdmin, isAdmin } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

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
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  const links: SidebarLink[] = [
    { to: '/dashboard', icon: 'fa-home', label: 'Accueil' },
    { to: '/poster-colis', icon: 'fa-box', label: 'Postez Colis', show: me.role === 'client' || me.role === 'transporteur' || isAdmin },
    { to: '/profil', icon: 'fa-user', label: 'Profil' },
    { to: '/liste-messagerie', icon: 'fa-envelope', label: 'Messages' },
    { to: '/messagerie-admin', icon: 'fa-headset', label: "Contacter l'admin" },
    { to: '/devenir-transporteur', icon: 'fa-id-badge', label: 'Devenir Transporteur', show: !me.is_transporteur && me.role !== 'transporteur' },
    { to: '/colis', icon: 'fa-box-open', label: 'Colis disponibles', show: me.role === 'transporteur' || isAdmin },
    { to: '/suivi', icon: 'fa-search-location', label: 'Suivi' },
    { to: '/recherche', icon: 'fa-magnifying-glass', label: 'Recherche' },
    { to: '/transporteur-stats', icon: 'fa-chart-line', label: 'Mes statistiques', show: !!me.is_transporteur },
    { to: '/reponses', icon: 'fa-reply', label: 'Mes réponses' },
    { to: '/contact', icon: 'fa-phone-alt', label: 'Contact' },
    { to: '/admin', icon: 'fa-shield-halved', label: 'Administration', show: isAdmin, badge: isSuperAdmin ? 'Super' : 'Admin' },
    { to: '/super-admin', icon: 'fa-crown', label: 'Super Admin', show: isSuperAdmin, badge: 'Super' },
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
            <Link to="/">
              <img src="/assets/img/logo.jpeg" alt="Logo SPIISTMOVE" className="logo" />
            </Link>
          </div>
          <div className="user-box">
            {me.photo_url ? <img src={me.photo_url} alt="" className="user-avatar" /> : null}
            <div className="user-name">
              {me.prenom} {me.nom}
            </div>
            <div className="d-flex gap-1 justify-content-center flex-wrap">
              <span className="badge bg-light text-dark">{me.role === 'utilisateur' ? 'client' : me.role}</span>
              {isSuperAdmin && <span className="badge bg-warning text-dark"><i className="fa-solid fa-crown"></i> Super Admin</span>}
              {me.role === 'admin' && <span className="badge bg-info text-dark">Admin</span>}
              {me.is_transporteur && <span className="badge bg-success">Transporteur</span>}
            </div>
          </div>
          <ul className="menu-items">
            {links.filter(l=>l.show!==false).map(l=>(
              <li key={l.to}>
                <NavLink to={l.to} className={({ isActive }) => (isActive ? 'active' : '')} end={l.to==='/dashboard'}>
                  <i className={`fas ${l.icon}`}></i> {l.label} {l.badge && <span className="badge bg-light text-dark ms-2" style={{fontSize:'0.6em'}}>{l.badge}</span>}
                </NavLink>
              </li>
            ))}
            <li><a href="#" onClick={onLogout}><i className="fas fa-sign-out-alt"></i> Déconnexion</a></li>
          </ul>
        </div>
      </div>
      <div className="content">
        <Outlet />
      </div>
    </>
  )
}

export function AdminGate({ children, superOnly = false }: { children: ReactNode, superOnly?: boolean }) {
  const { me, loading, isSuperAdmin, isAdmin } = useAuth()
  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '100vh' }}>
        <div className="spinner-border text-primary" role="status"><span className="visually-hidden">Chargement…</span></div>
      </div>
    )
  }
  if (!me) return null
  if (superOnly) {
    if (!isSuperAdmin) {
      return (
        <div className="p-5 text-center">
          <h3><i className="fa-solid fa-crown text-warning"></i> Accès réservé au Super Admin</h3>
          <p className="text-muted">Vous devez être Super Admin pour accéder à cette section.</p>
          <Link className="btn btn-primary mt-3" to="/">Retour à l'accueil</Link>
        </div>
      )
    }
  } else {
    if (!isAdmin) {
      return (
        <div className="p-5 text-center">
          <h3>Accès réservé aux administrateurs</h3>
          <Link className="btn btn-primary mt-3" to="/">Retour à l'accueil</Link>
        </div>
      )
    }
  }
  return <>{children}</>
}
