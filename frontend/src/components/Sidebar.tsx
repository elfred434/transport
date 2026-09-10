import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export interface SidebarLink {
  to: string
  icon: string
  label: string
  show?: boolean
  badge?: string
  section?: string
}

export function useSidebarLinks() {
  const { me, isSuperAdmin, isAdmin } = useAuth()
  if (!me) return []
  const links: SidebarLink[] = [
    // Section principale
    { to: '/dashboard', icon: 'fa-home', label: 'Tableau de bord', section: 'Général' },
    
    // Section Colis - intégration complète
    { to: '/poster-colis', icon: 'fa-box', label: 'Poster un colis', section: 'Colis', show: me.role === 'client' || me.role === 'transporteur' || isAdmin },
    { to: '/colis', icon: 'fa-boxes-stacked', label: 'Mes colis', section: 'Colis' },
    { to: '/colis', icon: 'fa-box-open', label: 'Colis disponibles', section: 'Colis', show: me.role === 'transporteur' || isAdmin },
    { to: '/recherche', icon: 'fa-magnifying-glass', label: 'Rechercher transporteur', section: 'Colis' },
    { to: '/reservation-colis', icon: 'fa-handshake', label: 'Mes réservations', section: 'Colis' },
    { to: '/suivi', icon: 'fa-truck-fast', label: 'Suivi colis', section: 'Colis' },
    { to: '/paiement', icon: 'fa-credit-card', label: 'Paiements', section: 'Colis' },
    
    // Section Transporteur
    { to: '/devenir-transporteur', icon: 'fa-id-badge', label: 'Devenir Transporteur', section: 'Transporteur', show: !me.is_transporteur && me.role !== 'transporteur' },
    { to: '/transporteur-stats', icon: 'fa-chart-line', label: 'Mes statistiques', section: 'Transporteur', show: !!me.is_transporteur },
    { to: '/profil-transporteur', icon: 'fa-user-tie', label: 'Profil Transporteur', section: 'Transporteur', show: !!me.is_transporteur },
    
    // Section Messagerie
    { to: '/liste-messagerie', icon: 'fa-envelope', label: 'Messages', section: 'Messagerie' },
    { to: '/messagerie-admin', icon: 'fa-headset', label: "Support Admin", section: 'Messagerie' },
    { to: '/reponses', icon: 'fa-reply', label: 'Mes réponses', section: 'Messagerie' },
    
    // Section Compte
    { to: '/profil', icon: 'fa-user', label: 'Mon profil', section: 'Compte' },
    { to: '/modifier-profil', icon: 'fa-user-pen', label: 'Modifier profil', section: 'Compte' },
    { to: '/contact', icon: 'fa-phone-alt', label: 'Contact', section: 'Compte' },
    
    // Section Admin
    { to: '/admin', icon: 'fa-shield-halved', label: 'Administration', section: 'Admin', show: isAdmin, badge: isSuperAdmin ? 'Super' : 'Admin' },
    { to: '/admin/messagerie', icon: 'fa-comments', label: 'Messagerie Admin', section: 'Admin', show: isAdmin },
    { to: '/super-admin', icon: 'fa-crown', label: 'Super Admin', section: 'Admin', show: isSuperAdmin, badge: 'Super' },
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

  // Grouper par section
  const grouped: Record<string, SidebarLink[]> = {}
  links.filter(l=>l.show!==false).forEach(l => {
    const sec = l.section || 'Autre'
    if (!grouped[sec]) grouped[sec] = []
    grouped[sec].push(l)
  })

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
        {Object.entries(grouped).map(([section, items]) => (
          <div key={section}>
            <li className="px-3 pt-3 pb-1 small text-uppercase fw-bold" style={{color: 'rgba(255,255,255,0.6)', fontSize: '0.65rem', letterSpacing: '1px'}}>{section}</li>
            {items.map(l=>(
              <li key={l.to + l.label}>
                <NavLink to={l.to} className={({ isActive }) => (isActive ? 'active' : '')} end={l.to==='/dashboard'}>
                  <i className={`fas ${l.icon}`}></i> {l.label} {l.badge && <span className="badge bg-light text-dark ms-2" style={{fontSize:'0.55em'}}>{l.badge}</span>}
                </NavLink>
              </li>
            ))}
          </div>
        ))}
        <li className="mt-2"><a href="#" onClick={onLogout} style={{borderTop: '1px solid rgba(255,255,255,0.1)', marginTop: 8}}><i className="fas fa-sign-out-alt"></i> Déconnexion</a></li>
      </ul>
    </div>
  )
}
