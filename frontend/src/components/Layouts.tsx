import { type ReactNode } from 'react'
import { Link, Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Sidebar, SidebarProvider, TopbarMobile, SidebarBackdrop, BottomNav } from './Sidebar'

export function AppLayout() {
  return (
    <SidebarProvider>
      <AppLayoutInner />
    </SidebarProvider>
  )
}

function AppLayoutInner() {
  const { me, loading } = useAuth()
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

  return (
    <>
      <TopbarMobile />
      <SidebarBackdrop />
      <div id="sidebar">
        <Sidebar />
      </div>
      <div className="content">
        <Outlet />
      </div>
      <BottomNav />
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
        <div className="p-4 p-md-5 text-center">
          <h3><i className="fa-solid fa-crown text-warning me-2"></i>Accès réservé au Super Admin</h3>
          <p className="text-muted">Vous devez être Super Admin pour accéder à cette section.</p>
          <Link className="btn btn-primary mt-3" to="/">Retour à l'accueil</Link>
        </div>
      )
    }
  } else {
    if (!isAdmin) {
      return (
        <div className="p-4 p-md-5 text-center">
          <h3><i className="fa-solid fa-lock text-danger me-2"></i>Accès réservé aux administrateurs</h3>
          <Link className="btn btn-primary mt-3" to="/">Retour à l'accueil</Link>
        </div>
      )
    }
  }
  return <>{children}</>
}
