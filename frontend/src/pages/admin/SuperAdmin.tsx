import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../../lib/api'
import { date, money } from '../../lib/format'
import { useToast } from '../../components/Toasts'

interface AdminUser {
  id: number
  nom: string
  prenom: string
  email: string
  role: string
  date_inscription: string
}
interface Stats {
  nb_users: number
  nb_clients: number
  nb_transporteurs_users: number
  nb_admins: number
  nb_super_admins: number
  nb_colis: number
  nb_voyages: number
  montant_total_paye: number
}

const errMsg = (e: unknown) => (e instanceof ApiError ? e.message : 'Erreur inconnue')

export default function SuperAdmin() {
  const { toast } = useToast()
  const [stats, setStats] = useState<Stats | null>(null)
  const [admins, setAdmins] = useState<AdminUser[] | null>(null)
  const [users, setUsers] = useState<AdminUser[] | null>(null)
  const [search, setSearch] = useState('')

  const loadStats = useCallback(async () => {
    try { setStats(await api.get<Stats>('/api/admin/stats')) } catch {}
  }, [])
  const loadAdmins = useCallback(async () => {
    try { setAdmins(await api.get<AdminUser[]>('/api/super-admin/admins')) } catch (e) { toast(errMsg(e), 'error') }
  }, [toast])
  const loadUsers = useCallback(async () => {
    const p = search.trim() ? `?search=${encodeURIComponent(search.trim())}` : ''
    try { setUsers(await api.get<AdminUser[]>(`/api/admin/users${p}`)) } catch {}
  }, [search])

  useEffect(() => { loadStats(); loadAdmins(); loadUsers() }, [loadStats, loadAdmins, loadUsers])

  const changeRole = async (id: number, role: string) => {
    if (!window.confirm(`Changer rôle en ${role} ?`)) return
    try {
      await api.post(`/api/admin/users/${id}/role`, { role })
      toast(`Rôle changé en ${role}`)
      loadAdmins()
      loadUsers()
      loadStats()
    } catch (e) { toast(errMsg(e), 'error') }
  }

  const deleteUser = async (id: number) => {
    if (!window.confirm('Supprimer ?')) return
    try {
      await api.del(`/api/admin/users/${id}`)
      toast('Supprimé')
      loadAdmins()
      loadUsers()
      loadStats()
    } catch (e) { toast(errMsg(e), 'error') }
  }

  return (
    <>
      <h2 className="mb-4"><i className="fa-solid fa-crown text-warning"></i> Super Administration</h2>

      <div className="alert alert-warning">
        <strong>Super Admin</strong> — Vous avez tous les droits : gérer les admins, promouvoir/rétrograder, voir toutes les stats, gérer tous les rôles (client, transporteur, admin, super_admin).
        <br/>Un <strong>Admin simple</strong> ne peut gérer que les clients et transporteurs, pas les admins.
      </div>

      {stats && (
        <div className="row g-3 mb-4">
          <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_users}</div><div className="small text-muted">Utilisateurs</div><div className="small mt-2">Clients: {stats.nb_clients} | Transp. users: {stats.nb_transporteurs_users}<br/>Admins: {stats.nb_admins} | Super: {stats.nb_super_admins}</div></div></div>
          <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_colis}</div><div className="small text-muted">Colis</div></div></div>
          <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_voyages}</div><div className="small text-muted">Voyages</div></div></div>
          <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{money(stats.montant_total_paye)}</div><div className="small text-muted">Total payé</div></div></div>
        </div>
      )}

      <div className="page-card">
        <h5><i className="fa-solid fa-crown text-warning"></i> Liste des Administrateurs</h5>
        {!admins && <div className="text-muted">Chargement…</div>}
        {admins && (
          <div className="table-responsive"><table className="table">
            <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscrit</th><th>Actions</th></tr></thead>
            <tbody>{admins.map(a=>(
              <tr key={a.id}>
                <td>{a.id}</td>
                <td>{a.prenom} {a.nom}</td>
                <td>{a.email}</td>
                <td>{a.role==='super_admin' ? <span className="badge bg-warning text-dark"><i className="fa-solid fa-crown"></i> Super Admin</span> : <span className="badge bg-info text-dark">Admin</span>}</td>
                <td className="small">{date(a.date_inscription)}</td>
                <td>
                  <div className="d-flex gap-1">
                    {a.role==='admin' && <button className="btn btn-sm btn-warning" onClick={()=>changeRole(a.id,'super_admin')}>Promouvoir Super</button>}
                    {a.role==='super_admin' && <button className="btn btn-sm btn-outline-info" onClick={()=>changeRole(a.id,'admin')}>Rétrograder Admin</button>}
                    <button className="btn btn-sm btn-outline-primary" onClick={()=>changeRole(a.id,'client')}>→ Client</button>
                    <button className="btn btn-sm btn-outline-danger" onClick={()=>deleteUser(a.id)}><i className="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>

      <div className="page-card">
        <h5>Tous les utilisateurs (gestion des rôles)</h5>
        <div className="row g-2 mb-3">
          <div className="col-md-4"><input className="form-control form-control-sm" placeholder="Recherche" value={search} onChange={e=>setSearch(e.target.value)} /></div>
          <div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadUsers}>Filtrer</button></div>
        </div>
        {!users && <div className="text-muted">Chargement…</div>}
        {users && (
          <div className="table-responsive"><table className="table table-sm">
            <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rôle actuel</th><th>Changer rôle</th><th>Actions</th></tr></thead>
            <tbody>{users.map(u=>(
              <tr key={u.id}>
                <td>{u.id}</td>
                <td>{u.prenom} {u.nom}</td>
                <td className="small">{u.email}</td>
                <td><span className="badge bg-light text-dark">{u.role}</span></td>
                <td>
                  <select className="form-select form-select-sm" style={{width:'auto'}} value={u.role} onChange={e=>changeRole(u.id, e.target.value)}>
                    <option value="client">Client</option>
                    <option value="transporteur">Transporteur</option>
                    <option value="admin">Admin simple</option>
                    <option value="super_admin">Super Admin</option>
                  </select>
                </td>
                <td><button className="btn btn-sm btn-outline-danger" onClick={()=>deleteUser(u.id)}><i className="fa-solid fa-trash"></i></button></td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>

      <div className="page-card">
        <h5>Matrice des permissions</h5>
        <div className="table-responsive"><table className="table table-bordered table-sm">
          <thead><tr><th>Action</th><th>Client</th><th>Transporteur</th><th>Admin simple</th><th>Super Admin</th></tr></thead>
          <tbody>
            <tr><td>Poster colis, payer, suivi, messagerie</td><td className="text-success">✅</td><td className="text-success">✅</td><td className="text-success">✅</td><td className="text-success">✅</td></tr>
            <tr><td>Proposer voyage, réserver colis, stats transporteur</td><td>❌</td><td className="text-success">✅</td><td className="text-success">✅</td><td className="text-success">✅</td></tr>
            <tr><td>Modérer colis/voyages/avis/contact</td><td>❌</td><td>❌</td><td className="text-success">✅</td><td className="text-success">✅</td></tr>
            <tr><td>Gérer clients & transporteurs</td><td>❌</td><td>❌</td><td className="text-success">✅</td><td className="text-success">✅</td></tr>
            <tr><td>Gérer admins, changer rôles admin/super_admin</td><td>❌</td><td>❌</td><td>❌</td><td className="text-success">✅</td></tr>
            <tr><td>Supprimer dernier Super Admin</td><td>❌</td><td>❌</td><td>❌</td><td>❌ (bloqué)</td></tr>
          </tbody>
        </table></div>
        <div className="mt-3">
          <Link to="/admin" className="btn btn-outline-primary me-2"><i className="fa-solid fa-shield-halved"></i> Retour Admin</Link>
          <Link to="/" className="btn btn-outline-secondary">Accueil</Link>
        </div>
      </div>
    </>
  )
}
