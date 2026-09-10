import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export interface SidebarLink {
  to: string
  icon: string
  label: string
  show?: boolean
  badge?: string
}

export function useSidebarLinks() {
  const { me, isSuperAdmin, isAdmin } = useAuth()
  if (!me) return []
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
  return links
}

export function Sidebar() {
  const { me, isSuperAdmin, logout } = useAuth()
  const navigate = useNavigate()
  const links = useSidebarLinks()

  if (!me) return null

  const onLogout = async (e: React.MouseEvent) => {
    e.preventDefault()
    await logout()
    navigate('/login')
  }

  return (
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
          <span className="badge bg-light text-dark" style={{fontSize: '0.65rem'}}>{me.role === 'utilisateur' ? 'client' : me.role}</span>
          {isSuperAdmin && <span className="badge bg-warning text-dark" style={{fontSize: '0.6rem'}}><i className="fa-solid fa-crown"></i> Super</span>}
          {me.role === 'admin' && <span className="badge bg-info text-dark" style={{fontSize: '0.6rem'}}>Admin</span>}
          {me.is_transporteur && <span className="badge bg-success" style={{fontSize: '0.6rem'}}>Transp.</span>}
        </div>
      </div>
      <ul className="menu-items">
        {links.filter(l=>l.show!==false).map(l=>(
          <li key={l.to}>
            <NavLink to={l.to} className={({ isActive }) => (isActive ? 'active' : '')} end={l.to==='/dashboard'}>
              <i className={`fas ${l.icon}`}></i> {l.label} {l.badge && <span className="badge bg-light text-dark ms-2" style={{fontSize:'0.55em'}}>{l.badge}</span>}
            </NavLink>
          </li>
        ))}
        <li><a href="#" onClick={onLogout}><i className="fas fa-sign-out-alt"></i> Déconnexion</a></li>
      </ul>
    </div>
  )
}
