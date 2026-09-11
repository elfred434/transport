import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

/* ------------------------------------------------------------------ */
/*  Context global pour l'état ouvert/fermé du menu mobile.            */
/*  Permet au bouton hamburger (dans TopbarMobile) et à la sidebar    */
/*  de partager le même état, et à AppLayout de piloter le backdrop.  */
/* ------------------------------------------------------------------ */
interface SidebarCtx {
  open: boolean
  toggle: () => void
  close: () => void
}
const SidebarContext = createContext<SidebarCtx>({ open: false, toggle: () => {}, close: () => {} })

export function SidebarProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false)
  const toggle = useCallback(() => setOpen(o => !o), [])
  const close = useCallback(() => setOpen(false), [])

  // Ferme le menu quand on resize au-dessus du breakpoint md
  useEffect(() => {
    const onResize = () => { if (window.innerWidth >= 768) setOpen(false) }
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

  // Verrouille le scroll du body quand le menu est ouvert (mobile)
  useEffect(() => {
    document.body.classList.toggle('has-sidebar', true)
    if (open) {
      document.body.style.overflow = 'hidden'
      // Remet la sidebar tout en haut quand on l'ouvre : on d'abord frame d'animation
      // pour être sûr que l'élément est monté/visible, puis on attend la fin de la
      // transition transform (300ms) avant de forcer scrollTop=0 (sinon le scroll
      // peut rester à la position desktop ou précédente et couper le haut du menu).
      const reset = () => {
        const el = document.querySelector('.vertical-menu') as HTMLElement | null
        if (el) el.scrollTop = 0
      }
      requestAnimationFrame(() => {
        reset()
        const t = window.setTimeout(reset, 350)
        window.addEventListener('scroll', reset, { once: true })
        return () => {
          window.clearTimeout(t)
          window.removeEventListener('scroll', reset)
        }
      })
    } else {
      document.body.style.overflow = ''
    }
    return () => { document.body.style.overflow = '' }
  }, [open])

  return (
    <SidebarContext.Provider value={{ open, toggle, close }}>
      {children}
    </SidebarContext.Provider>
  )
}

export function useSidebar() { return useContext(SidebarContext) }

/* ------------------------------------------------------------------ */
/*  Topbar mobile avec bouton hamburger — visible uniquement < md.     */
/* ------------------------------------------------------------------ */
export function TopbarMobile() {
  const { toggle } = useSidebar()
  return (
    <div className="topbar-mobile">
      <button
        className="hamburger"
        onClick={toggle}
        aria-label="Ouvrir le menu"
      >
        <i className="fa-solid fa-bars" />
      </button>
      <Link to="/dashboard" className="brand text-decoration-none text-white" onClick={useSidebar().close}>
        <img src="/assets/img/OIG1.jpeg" alt="" style={{ height: 30, width: 30, borderRadius: '50%', objectFit: 'cover' }} />
        SPIISTMOVE
      </Link>
      <div style={{ width: 44 }} /> {/* espace pour équilibrer */}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Backdrop semi-transparent qui ferme le menu au clic.              */
/* ------------------------------------------------------------------ */
export function SidebarBackdrop() {
  const { open, close } = useSidebar()
  return <div className={`sidebar-backdrop${open ? ' show' : ''}`} onClick={close} />
}

/* ------------------------------------------------------------------ */
/*  Barre de navigation inférieure globale (mobile uniquement).        */
/* ------------------------------------------------------------------ */
export function BottomNav() {
  return (
    <nav className="bottom-nav" aria-label="Navigation principale">
      <ul>
        <li>
          <NavLink to="/dashboard" end>
            <i className="fas fa-home" />
            <span>Accueil</span>
          </NavLink>
        </li>
        <li>
          <NavLink to="/colis">
            <i className="fas fa-box" />
            <span>Colis</span>
          </NavLink>
        </li>
        <li>
          <NavLink to="/liste-messagerie">
            <i className="fas fa-envelope" />
            <span>Messages</span>
          </NavLink>
        </li>
        <li>
          <NavLink to="/profil">
            <i className="fas fa-user" />
            <span>Profil</span>
          </NavLink>
        </li>
      </ul>
    </nav>
  )
}

/* ------------------------------------------------------------------ */
/*  Sidebar links & Sidebar                                           */
/* ------------------------------------------------------------------ */
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
    { to: '/dashboard', icon: 'fa-home', label: 'Tableau de bord', section: 'Général' },
    { to: '/poster-colis', icon: 'fa-box', label: 'Poster un colis', section: 'Colis', show: me.role === 'client' || me.role === 'transporteur' || isAdmin },
    { to: '/colis', icon: 'fa-boxes-stacked', label: 'Mes colis', section: 'Colis' },
    { to: '/colis', icon: 'fa-box-open', label: 'Colis disponibles', section: 'Colis', show: me.role === 'transporteur' || isAdmin },
    { to: '/recherche', icon: 'fa-magnifying-glass', label: 'Rechercher transporteur', section: 'Colis' },
    { to: '/reservation-colis', icon: 'fa-handshake', label: 'Mes réservations', section: 'Colis' },
    { to: '/suivi', icon: 'fa-truck-fast', label: 'Suivi colis', section: 'Colis' },
    { to: '/paiement', icon: 'fa-credit-card', label: 'Paiements', section: 'Colis' },
    { to: '/devenir-transporteur', icon: 'fa-id-badge', label: 'Devenir Transporteur', section: 'Transporteur', show: !me.is_transporteur && me.role !== 'transporteur' },
    { to: '/transporteur-stats', icon: 'fa-chart-line', label: 'Mes statistiques', section: 'Transporteur', show: !!me.is_transporteur },
    { to: `/profil-transporteur?id=${me.id}`, icon: 'fa-user-tie', label: 'Profil Transporteur', section: 'Transporteur', show: !!me.is_transporteur },
    { to: '/liste-messagerie', icon: 'fa-envelope', label: 'Messages', section: 'Messagerie' },
    { to: '/messagerie-admin', icon: 'fa-headset', label: 'Support Admin', section: 'Messagerie' },
    { to: '/reponses', icon: 'fa-reply', label: 'Mes réponses', section: 'Messagerie' },
    { to: '/profil', icon: 'fa-user', label: 'Mon profil', section: 'Compte' },
    { to: '/modifier-profil', icon: 'fa-user-pen', label: 'Modifier profil', section: 'Compte' },
    { to: '/contact', icon: 'fa-phone-alt', label: 'Contact', section: 'Compte' },
    { to: '/admin', icon: 'fa-shield-halved', label: 'Administration', section: 'Admin', show: isAdmin, badge: isSuperAdmin ? 'Super' : 'Admin' },
    { to: '/admin/messagerie', icon: 'fa-comments', label: 'Messagerie Admin', section: 'Admin', show: isAdmin },
    { to: '/super-admin', icon: 'fa-crown', label: 'Super Admin', section: 'Admin', show: isSuperAdmin, badge: 'Super' },
  ]
  return links
}

export function Sidebar() {
  const { me, isSuperAdmin, isTransporteur: isTransp, logout } = useAuth()
  const navigate = useNavigate()
  const { open, close } = useSidebar()
  const links = useSidebarLinks()

  if (!me) return null

  const onLogout = async (e: React.MouseEvent) => {
    e.preventDefault()
    close()
    await logout()
    navigate('/login')
  }

  const grouped: Record<string, SidebarLink[]> = {}
  links.filter(l => l.show !== false).forEach(l => {
    const sec = l.section || 'Autre'
    if (!grouped[sec]) grouped[sec] = []
    grouped[sec].push(l)
  })

  return (
    <div className={`vertical-menu${open ? ' show' : ''}`} aria-hidden={!open}>
      <div className="logo-container">
        <Link to="/dashboard" className="d-flex align-items-center justify-content-between gap-2 text-decoration-none text-white" onClick={close}>
          <span className="d-flex align-items-center gap-2">
            <img src="/assets/img/OIG1.jpeg" alt="" style={{ height: 38, width: 38, borderRadius: '50%', objectFit: 'cover' }} />
            <span className="fw-bold" style={{ color: 'white', fontSize: '1rem' }}>SPIISTMOVE</span>
          </span>
          <button
            type="button"
            className="sidebar-close d-md-none"
            onClick={(e) => { e.preventDefault(); close() }}
            aria-label="Fermer le menu"
          >
            <i className="fa-solid fa-xmark" />
          </button>
        </Link>
      </div>
      <div className="user-box">
        {me.photo_url ? (
          <img src={me.photo_url} alt="" className="user-avatar" />
        ) : (
          <div className="user-avatar d-flex align-items-center justify-content-center mx-auto mb-1" style={{ background: 'rgba(255,255,255,.15)', color: '#fff' }}>
            <i className="fas fa-user" />
          </div>
        )}
        <div className="user-name">{me.prenom} {me.nom}</div>
        <div className="user-role">
          {me.role === 'client' ? 'Client' : me.role === 'transporteur' ? 'Transporteur' : me.role === 'super_admin' ? 'Super Admin' : 'Admin'}
        </div>
        <div className="d-flex gap-1 justify-content-center flex-wrap mt-1">
          {isSuperAdmin && <span className="badge bg-warning text-dark"><i className="fa-solid fa-crown" /> Super</span>}
          {me.role === 'admin' && !isSuperAdmin && <span className="badge bg-info text-dark">Admin</span>}
          {isTransp && <span className="badge bg-success">Transp.</span>}
        </div>
      </div>
      <ul className="menu-items">
        {Object.entries(grouped).map(([section, items]) => (
          <div key={section}>
            <li className="px-3 pt-3 pb-1 small text-uppercase fw-bold" style={{ color: 'rgba(255,255,255,.5)', fontSize: '.65rem', letterSpacing: '1px' }}>{section}</li>
            {items.map(l => (
              <li key={l.to + l.label}>
                <NavLink
                  to={l.to}
                  end={l.to === '/dashboard'}
                  className={({ isActive }) => (isActive ? 'active' : '')}
                  onClick={close}
                >
                  <i className={`fas ${l.icon}`} /> {l.label}
                  {l.badge && <span className="badge bg-light text-dark ms-auto" style={{ fontSize: '.65em' }}>{l.badge}</span>}
                </NavLink>
              </li>
            ))}
          </div>
        ))}
      </ul>
      <button type="button" className="logout-link" onClick={onLogout}>
        <i className="fas fa-sign-out-alt" /> Déconnexion
      </button>
    </div>
  )
}
