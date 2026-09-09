import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { AdminGate, AppLayout } from './components/Layouts'
import Home from './pages/Home'
import Login from './pages/Login'
import Register from './pages/Register'
import ResetRequest from './pages/ResetRequest'
import ResetPassword from './pages/ResetPassword'
import Contact from './pages/Contact'
import Suivi from './pages/Suivi'
import Dashboard from './pages/Dashboard'
import PosterColis from './pages/PosterColis'
import ColisDisponibles from './pages/ColisDisponibles'
import ColisDetail from './pages/ColisDetail'
import Recherche from './pages/Recherche'
import ReservationColis from './pages/ReservationColis'
import Paiement from './pages/Paiement'
import Profil from './pages/Profil'
import ModifierProfil from './pages/ModifierProfil'
import DevenirTransporteur from './pages/DevenirTransporteur'
import TransporteurStats from './pages/TransporteurStats'
import ProfilTransporteur from './pages/ProfilTransporteur'
import ListeMessagerie from './pages/ListeMessagerie'
import Messagerie from './pages/Messagerie'
import MessagerieAdmin from './pages/MessagerieAdmin'
import Reponses from './pages/Reponses'
import AdminIndex from './pages/admin/AdminIndex'
import AdminMessagerie from './pages/admin/AdminMessagerie'
import AdminLogin from './pages/admin/AdminLogin'

/**
 * Routage du SPA — une route par page du frontend vanilla :
 *
 *   index.html              → /
 *   login.html              → /login          register.html       → /register
 *   reset-request.html      → /reset-request  reset-password.html → /reset-password
 *   contact.html            → /contact        dashboard.html      → /dashboard
 *   poster-colis.html       → /poster-colis   colis.html          → /colis
 *   colis-detail.html?id=   → /colis/:id      recherche.html      → /recherche
 *   reservation-colis.html  → /reservation-colis?id=
 *   paiement.html?id=       → /paiement?id=   suivi.html          → /suivi
 *   profil.html             → /profil         modifier-profil     → /modifier-profil
 *   devenir-transporteur    → /devenir-transporteur
 *   transporteur-stats      → /transporteur-stats
 *   profil-transporteur?id= → /profil-transporteur?id=
 *   liste-messagerie.html   → /liste-messagerie
 *   messagerie.html?destinataire_id= → /messagerie?destinataire_id=
 *   messagerie-admin.html   → /messagerie-admin
 *   reponses.html           → /reponses
 *   admin/login.html        → /admin/login    admin/index.html    → /admin
 *   admin/messagerie.html   → /admin/messagerie
 *
 * Les anciens chemins `*.html` (liens de mails, favoris, backend FRONTEND_URL)
 * sont redirigés vers les routes SPA en conservant la query string.
 */

/** Redirection d'un ancien chemin `.html` vers sa route SPA, query string conservée. */
function LegacyRedirect({ to }: { to: string }) {
  const { search } = useLocation()
  // Cas particulier : colis-detail.html?id=N → /colis/N
  if (to === '/colis/:id') {
    const id = new URLSearchParams(search).get('id')
    return <Navigate to={id ? '/colis/' + encodeURIComponent(id) : '/colis'} replace />
  }
  return <Navigate to={to + search} replace />
}

const LEGACY_ROUTES: [string, string][] = [
  ['/index.html', '/'],
  ['/login.html', '/login'],
  ['/register.html', '/register'],
  ['/reset-request.html', '/reset-request'],
  ['/reset-password.html', '/reset-password'],
  ['/contact.html', '/contact'],
  ['/suivi.html', '/suivi'],
  ['/dashboard.html', '/dashboard'],
  ['/poster-colis.html', '/poster-colis'],
  ['/colis.html', '/colis'],
  ['/colis-detail.html', '/colis/:id'],
  ['/recherche.html', '/recherche'],
  ['/reservation-colis.html', '/reservation-colis'],
  ['/paiement.html', '/paiement'],
  ['/profil.html', '/profil'],
  ['/modifier-profil.html', '/modifier-profil'],
  ['/devenir-transporteur.html', '/devenir-transporteur'],
  ['/transporteur-stats.html', '/transporteur-stats'],
  ['/profil-transporteur.html', '/profil-transporteur'],
  ['/liste-messagerie.html', '/liste-messagerie'],
  ['/messagerie.html', '/messagerie'],
  ['/messagerie-admin.html', '/messagerie-admin'],
  ['/reponses.html', '/reponses'],
  ['/admin/login.html', '/admin/login'],
  ['/admin/index.html', '/admin'],
  ['/admin/messagerie.html', '/admin/messagerie'],
]

export default function App() {
  return (
    <Routes>
      {/* --- Accueil : design d'origine (navbar + footer intégrés à la page) --- */}
      <Route path="/" element={<Home />} />

      {/* --- Authentification (carte centrée, sans topbar) --- */}
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/reset-request" element={<ResetRequest />} />
      <Route path="/reset-password" element={<ResetPassword />} />
      <Route path="/admin/login" element={<AdminLogin />} />

      {/* --- Espace utilisateur (sidebar) --- */}
      <Route element={<AppLayout />}>
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/poster-colis" element={<PosterColis />} />
        <Route path="/colis" element={<ColisDisponibles />} />
        <Route path="/colis/:id" element={<ColisDetail />} />
        <Route path="/recherche" element={<Recherche />} />
        <Route path="/reservation-colis" element={<ReservationColis />} />
        <Route path="/paiement" element={<Paiement />} />
        <Route path="/suivi" element={<Suivi />} />
        <Route path="/profil" element={<Profil />} />
        <Route path="/modifier-profil" element={<ModifierProfil />} />
        <Route path="/devenir-transporteur" element={<DevenirTransporteur />} />
        <Route path="/transporteur-stats" element={<TransporteurStats />} />
        <Route path="/profil-transporteur" element={<ProfilTransporteur />} />
        <Route path="/liste-messagerie" element={<ListeMessagerie />} />
        <Route path="/messagerie" element={<Messagerie />} />
        <Route path="/messagerie-admin" element={<MessagerieAdmin />} />
        <Route path="/reponses" element={<Reponses />} />
        <Route path="/contact" element={<Contact />} />

        {/* --- Administration --- */}
        <Route
          path="/admin"
          element={
            <AdminGate>
              <AdminIndex />
            </AdminGate>
          }
        />
        <Route
          path="/admin/messagerie"
          element={
            <AdminGate>
              <AdminMessagerie />
            </AdminGate>
          }
        />
      </Route>

      {/* --- Anciens chemins .html → routes SPA (query string conservée) --- */}
      {LEGACY_ROUTES.map(([from, to]) => (
        <Route key={from} path={from} element={<LegacyRedirect to={to} />} />
      ))}
    </Routes>
  )
}
