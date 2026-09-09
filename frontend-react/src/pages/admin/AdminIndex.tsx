import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../../lib/api'
import { date, datetime, money, SmartImg, StatusBadge } from '../../lib/format'
import { useToast } from '../../components/Toasts'
import { useAuth } from '../../context/AuthContext'

/** Administration — port de admin/index.html (sections stats/users/colis/voyages/transporteurs/paiements/avis/contact). */

type Section =
  | 'stats'
  | 'users'
  | 'colis'
  | 'voyages'
  | 'transporteurs'
  | 'paiements'
  | 'avis'
  | 'contact'

interface AdminStats {
  nb_users: number
  nb_colis: number
  nb_voyages: number
  nb_transporteurs: number
  colis_en_attente: number
  voyages_en_attente: number
  avis_en_attente: number
  demandes_livraison: number
  nb_paiements: number
  montant_total_paye: string | number
  messages_contact_non_lus: number
  nb_avis: number
}

interface AdminUser {
  id: number
  nom: string
  prenom: string
  email: string
  telephone: string | null
  photo_url: string | null
  role: string
  date_inscription: string
}

interface AdminColis {
  id: number
  nom_colis: string
  image_url: string | null
  numero_suivi: string
  prenom: string | null
  nom: string | null
  email: string | null
  ville: string
  pays: string
  poids: string | number
  prix_estime: string | number
  statut: string
  statut_livraison: string | null
  demande_livraison_id: number | null
}

interface AdminVoyage {
  id: number
  prenom: string | null
  nom: string | null
  email: string | null
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
  poids_max: string | number
  nb_reservations: number
  statut: string
}

interface AdminTransporteur {
  id: number
  user_id: number
  prenom: string
  nom: string
  email: string
  telephone: string | null
  vehicule: string | null
  numero_permis: string | null
  compagnie: string | null
  ville: string | null
  pays: string | null
  nb_colis_transportes: number
  solde: string | number
}

interface AdminPaiement {
  id: number
  reference: string
  numero_transaction: string | null
  prenom: string | null
  nom: string | null
  email: string | null
  nom_colis: string | null
  numero_suivi: string | null
  montant: string | number
  methode_paiement: string | null
  operateur: string | null
  date_creation: string
  statut: string
}

interface AdminAvis {
  id: number
  user_prenom: string
  user_nom: string
  transporteur_id: number
  transporteur_prenom: string
  transporteur_nom: string
  note: number
  commentaire: string
  date_avis: string
  statut: string
}

interface AdminContact {
  id: number
  nom: string
  email: string
  message: string
  reponse: string | null
  date_envoi: string
  date_reponse: string | null
}

const errMsg = (e: unknown) => (e instanceof ApiError ? e.message : 'Erreur inconnue')

function StatutSelect({
  value,
  options,
  onChange,
}: {
  value: string
  options: string[]
  onChange: (v: string) => void
}) {
  return (
    <select
      className="form-select form-select-sm"
      style={{ width: 'auto', display: 'inline-block' }}
      value={value}
      onChange={(e) => onChange(e.target.value)}
    >
      {options.map((o) => (
        <option key={o} value={o}>
          {o}
        </option>
      ))}
    </select>
  )
}

export default function AdminIndex() {
  const { toast } = useToast()
  const { me } = useAuth()
  const [section, setSection] = useState<Section>('stats')

  // ---------- données par section ----------
  const [stats, setStats] = useState<AdminStats | null>(null)
  const [users, setUsers] = useState<AdminUser[] | null>(null)
  const [colisList, setColisList] = useState<AdminColis[] | null>(null)
  const [voyages, setVoyages] = useState<AdminVoyage[] | null>(null)
  const [transporteurs, setTransporteurs] = useState<AdminTransporteur[] | null>(null)
  const [paiements, setPaiements] = useState<AdminPaiement[] | null>(null)
  const [avis, setAvis] = useState<AdminAvis[] | null>(null)
  const [contacts, setContacts] = useState<AdminContact[] | null>(null)
  const [sectionError, setSectionError] = useState<string | null>(null)

  // ---------- filtres ----------
  const [uSearch, setUSearch] = useState('')
  const [uRole, setURole] = useState('')
  const [cSearch, setCSearch] = useState('')
  const [cStatut, setCStatut] = useState('')
  const [vSearch, setVSearch] = useState('')
  const [vStatut, setVStatut] = useState('')
  const [tSearch, setTSearch] = useState('')
  const [pSearch, setPSearch] = useState('')
  const [pStatut, setPStatut] = useState('')
  const [aStatut, setAStatut] = useState('')
  const [ctRepondu, setCtRepondu] = useState('')

  // ---------- modales ----------
  const [userModal, setUserModal] = useState(false)
  const [userForm, setUserForm] = useState({ nom: '', prenom: '', email: '', password: '', role: 'utilisateur' })
  const [userModalError, setUserModalError] = useState<string | null>(null)
  const [replyModal, setReplyModal] = useState<{ id: number; nom: string; message: string } | null>(null)
  const [replyText, setReplyText] = useState('')

  // ---------- loaders ----------
  const loadStats = useCallback(async () => {
    setSectionError(null)
    try {
      setStats(await api.get<AdminStats>('/api/admin/stats'))
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [])

  const loadUsers = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (uSearch.trim()) p.set('search', uSearch.trim())
    if (uRole) p.set('role', uRole)
    try {
      setUsers(await api.get<AdminUser[]>('/api/admin/users' + (p.toString() ? '?' + p : '')))
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [uSearch, uRole])

  const loadColis = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (cSearch.trim()) p.set('search', cSearch.trim())
    if (cStatut) p.set('statut', cStatut)
    try {
      setColisList(await api.get<AdminColis[]>('/api/admin/colis' + (p.toString() ? '?' + p : '')))
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [cSearch, cStatut])

  const loadVoyages = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (vSearch.trim()) p.set('search', vSearch.trim())
    if (vStatut) p.set('statut', vStatut)
    try {
      setVoyages(await api.get<AdminVoyage[]>('/api/admin/voyages' + (p.toString() ? '?' + p : '')))
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [vSearch, vStatut])

  const loadTransporteurs = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (tSearch.trim()) p.set('search', tSearch.trim())
    try {
      setTransporteurs(
        await api.get<AdminTransporteur[]>('/api/admin/transporteurs' + (p.toString() ? '?' + p : '')),
      )
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [tSearch])

  const loadPaiements = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (pSearch.trim()) p.set('search', pSearch.trim())
    if (pStatut) p.set('statut', pStatut)
    try {
      setPaiements(
        await api.get<AdminPaiement[]>('/api/admin/paiements' + (p.toString() ? '?' + p : '')),
      )
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [pSearch, pStatut])

  const loadAvis = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (aStatut) p.set('statut', aStatut)
    try {
      setAvis(await api.get<AdminAvis[]>('/api/admin/avis' + (p.toString() ? '?' + p : '')))
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [aStatut])

  const loadContact = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (ctRepondu) p.set('repondu', ctRepondu)
    try {
      setContacts(
        await api.get<AdminContact[]>('/api/admin/contact-messages' + (p.toString() ? '?' + p : '')),
      )
    } catch (e) {
      setSectionError(errMsg(e))
    }
  }, [ctRepondu])

  // Chargement à chaque changement de section (comme showSection → loaders[name]()).
  useEffect(() => {
    const loaders: Record<Section, () => void> = {
      stats: loadStats,
      users: loadUsers,
      colis: loadColis,
      voyages: loadVoyages,
      transporteurs: loadTransporteurs,
      paiements: loadPaiements,
      avis: loadAvis,
      contact: loadContact,
    }
    loaders[section]()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [section])

  // ---------- actions ----------
  const changeStatut = async (url: string, statut: string, okMsg: string, reload: () => void) => {
    try {
      await api.post(url, { statut })
      toast(okMsg)
    } catch (e) {
      toast(errMsg(e), 'error')
      reload()
    }
  }

  const del = async (url: string, confirmMsg: string, okMsg: string, reload: () => void) => {
    if (!window.confirm(confirmMsg)) return
    try {
      await api.del(url)
      toast(okMsg)
      reload()
    } catch (e) {
      toast(errMsg(e), 'error')
    }
  }

  const livraisonDecision = async (id: number, decision: 'confirmer' | 'refuser') => {
    const msg =
      decision === 'confirmer'
        ? 'Confirmer la livraison ? Le transporteur recevra sa commission de 5 %.'
        : 'Refuser cette demande de livraison ?'
    if (!window.confirm(msg)) return
    try {
      const data = await api.post<{ message: string; commission_transporteur?: string | number | null }>(
        `/api/admin/suivi/${id}/livraison`,
        { decision },
      )
      toast(
        data.message +
          (data.commission_transporteur ? ' — commission : ' + money(data.commission_transporteur) : ''),
      )
      loadColis()
    } catch (e) {
      toast(errMsg(e), 'error')
    }
  }

  const createUser = async (e: React.FormEvent) => {
    e.preventDefault()
    setUserModalError(null)
    try {
      await api.post('/api/admin/users', { ...userForm })
      setUserModal(false)
      setUserForm({ nom: '', prenom: '', email: '', password: '', role: 'utilisateur' })
      toast('Utilisateur créé.')
      loadUsers()
    } catch (err) {
      setUserModalError(errMsg(err))
    }
  }

  const sendReply = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!replyModal) return
    try {
      await api.post(`/api/admin/contact-messages/${replyModal.id}/repondre`, { reponse: replyText })
      setReplyModal(null)
      setReplyText('')
      toast('Réponse envoyée.')
      loadContact()
    } catch (err) {
      toast(errMsg(err), 'error')
    }
  }

  // ---------- rendu ----------
  const navItems: { key: Section; icon: string; label: string }[] = [
    { key: 'stats', icon: 'fa-chart-pie', label: 'Statistiques' },
    { key: 'users', icon: 'fa-users', label: 'Utilisateurs' },
    { key: 'colis', icon: 'fa-box', label: 'Colis' },
    { key: 'voyages', icon: 'fa-plane', label: 'Voyages' },
    { key: 'transporteurs', icon: 'fa-truck', label: 'Transporteurs' },
    { key: 'paiements', icon: 'fa-credit-card', label: 'Paiements' },
    { key: 'avis', icon: 'fa-star', label: 'Avis' },
    { key: 'contact', icon: 'fa-envelope-open-text', label: 'Messages contact' },
  ]

  const statCard = (icon: string, label: string, value: React.ReactNode, color: string) => (
    <div className="col-md-3 col-6" key={label}>
      <div className="page-card text-center h-100">
        <i className={`fa-solid ${icon} text-${color}`} style={{ fontSize: '1.8rem' }}></i>
        <div className="fs-4 fw-bold mt-2">{value}</div>
        <div className="text-muted small">{label}</div>
      </div>
    </div>
  )

  return (
    <>
      {/* Navigation par sections (équivalent du menu admin vanilla) */}
      <ul className="nav nav-pills mb-4 flex-wrap gap-1">
        {navItems.map((n) => (
          <li className="nav-item" key={n.key}>
            <button
              type="button"
              className={`nav-link ${section === n.key ? 'active' : ''}`}
              onClick={() => setSection(n.key)}
            >
              <i className={`fas ${n.icon}`}></i> {n.label}
            </button>
          </li>
        ))}
        <li className="nav-item">
          <Link className="nav-link" to="/admin/messagerie">
            <i className="fas fa-headset"></i> Messagerie admin
          </Link>
        </li>
        <li className="nav-item">
          <Link className="nav-link" to="/dashboard">
            <i className="fas fa-gauge"></i> Vue utilisateur
          </Link>
        </li>
      </ul>

      {sectionError && <div className="alert alert-danger">{sectionError}</div>}

      {/* ============================ STATS ============================ */}
      {section === 'stats' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-chart-pie text-primary"></i> Statistiques
          </h2>
          {!stats ? (
            <div className="text-muted">Chargement…</div>
          ) : (
            <div className="row g-3">
              {statCard('fa-users', 'Utilisateurs', stats.nb_users, 'primary')}
              {statCard('fa-box', 'Colis', stats.nb_colis, 'primary')}
              {statCard('fa-plane', 'Voyages', stats.nb_voyages, 'primary')}
              {statCard('fa-truck', 'Transporteurs', stats.nb_transporteurs, 'primary')}
              {statCard('fa-hourglass-half', 'Colis en attente', stats.colis_en_attente, 'warning')}
              {statCard('fa-hourglass-half', 'Voyages en attente', stats.voyages_en_attente, 'warning')}
              {statCard('fa-star', 'Avis en attente', stats.avis_en_attente, 'warning')}
              {statCard('fa-truck-fast', 'Demandes de livraison', stats.demandes_livraison, 'danger')}
              {statCard('fa-credit-card', 'Paiements', stats.nb_paiements, 'info')}
              {statCard('fa-money-bill-wave', 'Total payé', money(stats.montant_total_paye), 'success')}
              {statCard('fa-comment-dots', 'Messages contact non lus', stats.messages_contact_non_lus, 'secondary')}
              {statCard('fa-star', 'Avis publiés', stats.nb_avis, 'warning')}
            </div>
          )}
        </section>
      )}

      {/* ============================ USERS ============================ */}
      {section === 'users' && (
        <section>
          <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 className="mb-0">
              <i className="fa-solid fa-users text-primary"></i> Utilisateurs
            </h2>
            <button className="btn btn-primary" onClick={() => setUserModal(true)}>
              <i className="fa-solid fa-user-plus"></i> Créer un utilisateur
            </button>
          </div>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadUsers()
              }}
            >
              <div className="col-md-4">
                <label className="form-label">Recherche</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Nom, prénom, email…"
                  value={uSearch}
                  onChange={(e) => setUSearch(e.target.value)}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label">Rôle</label>
                <select className="form-select" value={uRole} onChange={(e) => setURole(e.target.value)}>
                  <option value="">Tous</option>
                  <option value="utilisateur">Utilisateur</option>
                  <option value="transporteur">Transporteur</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!users ? (
              <div className="text-muted">Chargement…</div>
            ) : users.length === 0 ? (
              <p className="text-muted">Aucun utilisateur.</p>
            ) : (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>#</th>
                      <th>Nom</th>
                      <th>Email</th>
                      <th>Téléphone</th>
                      <th>Rôle</th>
                      <th>Inscription</th>
                      <th className="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {users.map((u) => (
                      <tr key={u.id}>
                        <td>{u.id}</td>
                        <td>
                          {u.photo_url && (
                            <img
                              src={u.photo_url}
                              className="rounded-circle me-2"
                              style={{ width: 32, height: 32, objectFit: 'cover' }}
                              alt=""
                            />
                          )}
                          {u.prenom} {u.nom}
                        </td>
                        <td>{u.email}</td>
                        <td>{u.telephone || '—'}</td>
                        <td>
                          <span
                            className={`badge ${
                              u.role === 'admin'
                                ? 'bg-danger'
                                : u.role === 'transporteur'
                                  ? 'bg-info text-dark'
                                  : 'bg-secondary'
                            }`}
                          >
                            {u.role}
                          </span>
                        </td>
                        <td className="small">{date(u.date_inscription)}</td>
                        <td className="text-end">
                          <Link
                            className="btn btn-sm btn-outline-primary"
                            to={'/admin/messagerie?user_id=' + u.id}
                            title="Message"
                          >
                            <i className="fa-solid fa-envelope"></i>
                          </Link>{' '}
                          {u.id !== me?.id && (
                            <button
                              className="btn btn-sm btn-outline-danger"
                              onClick={() =>
                                del(
                                  '/api/admin/users/' + u.id,
                                  'Supprimer définitivement ' + u.prenom + ' ' + u.nom + ' ?',
                                  'Utilisateur supprimé.',
                                  loadUsers,
                                )
                              }
                            >
                              <i className="fa-solid fa-trash"></i>
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ============================ COLIS ============================ */}
      {section === 'colis' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-box text-primary"></i> Colis
          </h2>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadColis()
              }}
            >
              <div className="col-md-4">
                <label className="form-label">Recherche</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Nom, suivi, ville…"
                  value={cSearch}
                  onChange={(e) => setCSearch(e.target.value)}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label">Statut</label>
                <select className="form-select" value={cStatut} onChange={(e) => setCStatut(e.target.value)}>
                  <option value="">Tous</option>
                  <option value="en_attente">En attente</option>
                  <option value="approuve">Approuvé</option>
                  <option value="refuse">Refusé</option>
                </select>
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!colisList ? (
              <div className="text-muted">Chargement…</div>
            ) : colisList.length === 0 ? (
              <p className="text-muted">Aucun colis.</p>
            ) : (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>Colis</th>
                      <th>Client</th>
                      <th>Destination</th>
                      <th>Prix</th>
                      <th>Statut</th>
                      <th>Livraison</th>
                      <th className="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {colisList.map((c) => (
                      <tr key={c.id}>
                        <td>
                          <SmartImg url={c.image_url} alt={c.nom_colis} className="img-colis" />
                          <Link to={`/colis/${c.id}`} className="ms-2">
                            {c.nom_colis}
                          </Link>
                          <div className="small text-muted">
                            <code>{c.numero_suivi}</code>
                          </div>
                        </td>
                        <td>
                          {c.prenom || ''} {c.nom || ''}
                          <div className="small text-muted">{c.email || ''}</div>
                        </td>
                        <td>
                          {c.ville}, {c.pays}
                          <div className="small text-muted">{c.poids} kg</div>
                        </td>
                        <td>{money(c.prix_estime)}</td>
                        <td>
                          <StatutSelect
                            value={c.statut}
                            options={['en_attente', 'approuve', 'refuse']}
                            onChange={(v) =>
                              changeStatut(
                                '/api/admin/colis/' + c.id + '/statut',
                                v,
                                'Statut du colis mis à jour.',
                                loadColis,
                              )
                            }
                          />
                        </td>
                        <td>
                          {c.statut_livraison ? <StatusBadge statut={c.statut_livraison} /> : '—'}
                          {c.demande_livraison_id && (
                            <div className="mt-1 d-flex gap-1">
                              <button
                                className="btn btn-sm btn-success"
                                title="Confirmer la livraison (+5% transporteur)"
                                onClick={() => livraisonDecision(c.demande_livraison_id!, 'confirmer')}
                              >
                                <i className="fa-solid fa-check"></i>
                              </button>
                              <button
                                className="btn btn-sm btn-danger"
                                title="Refuser"
                                onClick={() => livraisonDecision(c.demande_livraison_id!, 'refuser')}
                              >
                                <i className="fa-solid fa-xmark"></i>
                              </button>
                            </div>
                          )}
                        </td>
                        <td className="text-end">
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() =>
                              del('/api/admin/colis/' + c.id, 'Supprimer ce colis ?', 'Colis supprimé.', loadColis)
                            }
                          >
                            <i className="fa-solid fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ============================ VOYAGES ============================ */}
      {section === 'voyages' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-plane text-primary"></i> Voyages
          </h2>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadVoyages()
              }}
            >
              <div className="col-md-4">
                <label className="form-label">Recherche</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Pays, transporteur…"
                  value={vSearch}
                  onChange={(e) => setVSearch(e.target.value)}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label">Statut</label>
                <select className="form-select" value={vStatut} onChange={(e) => setVStatut(e.target.value)}>
                  <option value="">Tous</option>
                  <option value="en_attente">En attente</option>
                  <option value="approuve">Approuvé</option>
                  <option value="refuse">Refusé</option>
                </select>
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!voyages ? (
              <div className="text-muted">Chargement…</div>
            ) : voyages.length === 0 ? (
              <p className="text-muted">Aucun voyage.</p>
            ) : (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>#</th>
                      <th>Transporteur</th>
                      <th>Trajet</th>
                      <th>Départ</th>
                      <th>Poids max</th>
                      <th>Réservations</th>
                      <th>Statut</th>
                      <th className="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {voyages.map((v) => (
                      <tr key={v.id}>
                        <td>{v.id}</td>
                        <td>
                          {v.prenom || ''} {v.nom || ''}
                          <div className="small text-muted">{v.email || ''}</div>
                        </td>
                        <td>
                          {v.pays_depart} → {v.pays_destination}
                        </td>
                        <td>
                          {date(v.date_depart)}{' '}
                          <span className="small text-muted">{(v.heure_depart || '').substring(0, 5)}</span>
                        </td>
                        <td>{v.poids_max} kg</td>
                        <td>
                          <span className="badge bg-secondary">{v.nb_reservations}</span>
                        </td>
                        <td>
                          <StatutSelect
                            value={v.statut}
                            options={['en_attente', 'approuve', 'refuse']}
                            onChange={(val) =>
                              changeStatut(
                                '/api/admin/voyages/' + v.id + '/statut',
                                val,
                                'Statut du voyage mis à jour.',
                                loadVoyages,
                              )
                            }
                          />
                        </td>
                        <td className="text-end">
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() =>
                              del('/api/admin/voyages/' + v.id, 'Supprimer ce voyage ?', 'Voyage supprimé.', loadVoyages)
                            }
                          >
                            <i className="fa-solid fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ============================ TRANSPORTEURS ============================ */}
      {section === 'transporteurs' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-truck text-primary"></i> Transporteurs
          </h2>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadTransporteurs()
              }}
            >
              <div className="col-md-4">
                <label className="form-label">Recherche</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Nom, compagnie, ville…"
                  value={tSearch}
                  onChange={(e) => setTSearch(e.target.value)}
                />
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!transporteurs ? (
              <div className="text-muted">Chargement…</div>
            ) : transporteurs.length === 0 ? (
              <p className="text-muted">Aucun transporteur.</p>
            ) : (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>Transporteur</th>
                      <th>Véhicule</th>
                      <th>Compagnie</th>
                      <th>Zone</th>
                      <th>Colis transportés</th>
                      <th>Solde</th>
                      <th className="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {transporteurs.map((t) => (
                      <tr key={t.id}>
                        <td>
                          <Link to={'/profil-transporteur?id=' + t.user_id}>
                            {t.prenom} {t.nom}
                          </Link>
                          <div className="small text-muted">
                            {t.email} · {t.telephone || ''}
                          </div>
                        </td>
                        <td>
                          {t.vehicule || '—'}
                          <div className="small text-muted">Permis : {t.numero_permis || '—'}</div>
                        </td>
                        <td>{t.compagnie || '—'}</td>
                        <td>
                          {t.ville || ''} {t.pays || ''}
                        </td>
                        <td>{t.nb_colis_transportes}</td>
                        <td>
                          <strong className="text-success">{money(t.solde)}</strong>
                        </td>
                        <td className="text-end">
                          <Link className="btn btn-sm btn-outline-primary" to={'/admin/messagerie?user_id=' + t.user_id}>
                            <i className="fa-solid fa-envelope"></i>
                          </Link>{' '}
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() =>
                              del(
                                '/api/admin/transporteurs/' + t.id,
                                'Supprimer cette fiche transporteur ? (le compte utilisateur est conservé)',
                                'Fiche supprimée.',
                                loadTransporteurs,
                              )
                            }
                          >
                            <i className="fa-solid fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ============================ PAIEMENTS ============================ */}
      {section === 'paiements' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-credit-card text-primary"></i> Paiements
          </h2>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadPaiements()
              }}
            >
              <div className="col-md-4">
                <label className="form-label">Recherche</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Référence, colis, client…"
                  value={pSearch}
                  onChange={(e) => setPSearch(e.target.value)}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label">Statut</label>
                <select className="form-select" value={pStatut} onChange={(e) => setPStatut(e.target.value)}>
                  <option value="">Tous</option>
                  <option value="en_attente">En attente</option>
                  <option value="paye">Payé</option>
                  <option value="echec">Échec</option>
                  <option value="annule">Annulé</option>
                </select>
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!paiements ? (
              <div className="text-muted">Chargement…</div>
            ) : paiements.length === 0 ? (
              <p className="text-muted">Aucun paiement.</p>
            ) : (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>Référence</th>
                      <th>Client</th>
                      <th>Colis</th>
                      <th>Montant</th>
                      <th>Méthode</th>
                      <th>Date</th>
                      <th>Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paiements.map((p) => (
                      <tr key={p.id}>
                        <td className="small">
                          <code>{p.reference}</code>
                          {p.numero_transaction && <div className="text-muted">{p.numero_transaction}</div>}
                        </td>
                        <td>
                          {p.prenom || ''} {p.nom || ''}
                          <div className="small text-muted">{p.email || ''}</div>
                        </td>
                        <td>
                          {p.nom_colis || '—'}
                          <div className="small text-muted">{p.numero_suivi || ''}</div>
                        </td>
                        <td>{money(p.montant)}</td>
                        <td className="small">
                          {p.methode_paiement || '—'}
                          {p.operateur ? ' (' + p.operateur + ')' : ''}
                        </td>
                        <td className="small">{datetime(p.date_creation)}</td>
                        <td>
                          <StatutSelect
                            value={p.statut}
                            options={['en_attente', 'paye', 'echec', 'annule']}
                            onChange={(v) =>
                              changeStatut(
                                '/api/admin/paiements/' + p.id + '/statut',
                                v,
                                'Statut du paiement mis à jour.',
                                loadPaiements,
                              )
                            }
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ============================ AVIS ============================ */}
      {section === 'avis' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-star text-primary"></i> Avis
          </h2>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadAvis()
              }}
            >
              <div className="col-md-3">
                <label className="form-label">Statut</label>
                <select className="form-select" value={aStatut} onChange={(e) => setAStatut(e.target.value)}>
                  <option value="">Tous</option>
                  <option value="en_attente">En attente</option>
                  <option value="approuve">Approuvé</option>
                  <option value="refuse">Refusé</option>
                </select>
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!avis ? (
              <div className="text-muted">Chargement…</div>
            ) : avis.length === 0 ? (
              <p className="text-muted">Aucun avis.</p>
            ) : (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>Auteur</th>
                      <th>Transporteur</th>
                      <th>Note</th>
                      <th>Commentaire</th>
                      <th>Date</th>
                      <th>Statut</th>
                      <th className="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {avis.map((a) => (
                      <tr key={a.id}>
                        <td>
                          {a.user_prenom} {a.user_nom}
                        </td>
                        <td>
                          <Link to={'/profil-transporteur?id=' + a.transporteur_id}>
                            {a.transporteur_prenom} {a.transporteur_nom}
                          </Link>
                        </td>
                        <td>{'★'.repeat(a.note) + '☆'.repeat(5 - a.note)}</td>
                        <td className="small" style={{ maxWidth: 320 }}>
                          {a.commentaire}
                        </td>
                        <td className="small">{date(a.date_avis)}</td>
                        <td>
                          <StatutSelect
                            value={a.statut}
                            options={['en_attente', 'approuve', 'refuse']}
                            onChange={(v) =>
                              changeStatut(
                                '/api/admin/avis/' + a.id + '/statut',
                                v,
                                "Statut de l'avis mis à jour.",
                                loadAvis,
                              )
                            }
                          />
                        </td>
                        <td className="text-end">
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() =>
                              del('/api/admin/avis/' + a.id, 'Supprimer cet avis ?', 'Avis supprimé.', loadAvis)
                            }
                          >
                            <i className="fa-solid fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ============================ CONTACT ============================ */}
      {section === 'contact' && (
        <section>
          <h2 className="mb-4">
            <i className="fa-solid fa-envelope-open-text text-primary"></i> Messages de contact
          </h2>
          <div className="page-card">
            <form
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                loadContact()
              }}
            >
              <div className="col-md-3">
                <label className="form-label">État</label>
                <select className="form-select" value={ctRepondu} onChange={(e) => setCtRepondu(e.target.value)}>
                  <option value="">Tous</option>
                  <option value="non">Sans réponse</option>
                  <option value="oui">Répondus</option>
                </select>
              </div>
              <div className="col-md-2">
                <button className="btn btn-outline-primary w-100" type="submit">
                  Filtrer
                </button>
              </div>
            </form>

            {!contacts ? (
              <div className="text-muted">Chargement…</div>
            ) : contacts.length === 0 ? (
              <p className="text-muted">Aucun message de contact.</p>
            ) : (
              contacts.map((m) => (
                <div key={m.id} className="border rounded-3 p-3 mb-3">
                  <div className="d-flex justify-content-between flex-wrap">
                    <div>
                      <strong>{m.nom}</strong>
                      <a href={'mailto:' + m.email} className="small ms-2">
                        {m.email}
                      </a>
                      {m.reponse ? (
                        <span className="badge bg-success ms-2">Répondu</span>
                      ) : (
                        <span className="badge bg-warning text-dark ms-2">Sans réponse</span>
                      )}
                    </div>
                    <small className="text-muted">{datetime(m.date_envoi)}</small>
                  </div>
                  <p className="mt-2 mb-2" style={{ whiteSpace: 'pre-line' }}>
                    {m.message}
                  </p>
                  {m.reponse && (
                    <div className="alert alert-success small mb-2">
                      <strong>Réponse ({datetime(m.date_reponse)}) :</strong>
                      <br />
                      <span style={{ whiteSpace: 'pre-line' }}>{m.reponse}</span>
                    </div>
                  )}
                  <div className="d-flex gap-2">
                    <button
                      className="btn btn-sm btn-primary"
                      onClick={() => {
                        setReplyModal({ id: m.id, nom: m.nom, message: m.message })
                        setReplyText('')
                      }}
                    >
                      <i className="fa-solid fa-reply"></i>{' '}
                      {m.reponse ? 'Modifier la réponse' : 'Répondre'}
                    </button>
                    <button
                      className="btn btn-sm btn-outline-danger"
                      onClick={() =>
                        del(
                          '/api/admin/contact-messages/' + m.id,
                          'Supprimer ce message ?',
                          'Message supprimé.',
                          loadContact,
                        )
                      }
                    >
                      <i className="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </section>
      )}

      {/* ---------- Modale création utilisateur ---------- */}
      {userModal && (
        <>
          <div className="modal fade show d-block" tabIndex={-1}>
            <div className="modal-dialog">
              <div className="modal-content">
                <form onSubmit={createUser}>
                  <div className="modal-header">
                    <h5 className="modal-title">Créer un utilisateur</h5>
                    <button type="button" className="btn-close" onClick={() => setUserModal(false)}></button>
                  </div>
                  <div className="modal-body">
                    {userModalError && <div className="alert alert-danger">{userModalError}</div>}
                    <div className="mb-3">
                      <label className="form-label">Nom *</label>
                      <input
                        type="text"
                        className="form-control"
                        required
                        value={userForm.nom}
                        onChange={(e) => setUserForm((f) => ({ ...f, nom: e.target.value }))}
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label">Prénom *</label>
                      <input
                        type="text"
                        className="form-control"
                        required
                        value={userForm.prenom}
                        onChange={(e) => setUserForm((f) => ({ ...f, prenom: e.target.value }))}
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label">Email *</label>
                      <input
                        type="email"
                        className="form-control"
                        required
                        value={userForm.email}
                        onChange={(e) => setUserForm((f) => ({ ...f, email: e.target.value }))}
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label">Mot de passe * (8 car. min.)</label>
                      <input
                        type="password"
                        className="form-control"
                        minLength={8}
                        required
                        value={userForm.password}
                        onChange={(e) => setUserForm((f) => ({ ...f, password: e.target.value }))}
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label">Rôle *</label>
                      <select
                        className="form-select"
                        required
                        value={userForm.role}
                        onChange={(e) => setUserForm((f) => ({ ...f, role: e.target.value }))}
                      >
                        <option value="utilisateur">Utilisateur</option>
                        <option value="transporteur">Transporteur</option>
                        <option value="admin">Admin</option>
                      </select>
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button type="button" className="btn btn-secondary" onClick={() => setUserModal(false)}>
                      Annuler
                    </button>
                    <button type="submit" className="btn btn-primary">
                      Créer
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <div className="modal-backdrop fade show" style={{ backgroundColor: 'rgba(0,0,0,.5)' }}></div>
        </>
      )}

      {/* ---------- Modale réponse contact ---------- */}
      {replyModal && (
        <>
          <div className="modal fade show d-block" tabIndex={-1}>
            <div className="modal-dialog">
              <div className="modal-content">
                <form onSubmit={sendReply}>
                  <div className="modal-header">
                    <h5 className="modal-title">Répondre au message</h5>
                    <button type="button" className="btn-close" onClick={() => setReplyModal(null)}></button>
                  </div>
                  <div className="modal-body">
                    <div className="alert alert-light small">
                      <strong>{replyModal.nom}</strong>
                      <br />
                      <span style={{ whiteSpace: 'pre-line' }}>{replyModal.message}</span>
                    </div>
                    <div className="mb-3">
                      <label className="form-label">Votre réponse *</label>
                      <textarea
                        className="form-control"
                        rows={4}
                        maxLength={5000}
                        required
                        value={replyText}
                        onChange={(e) => setReplyText(e.target.value)}
                      />
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button type="button" className="btn btn-secondary" onClick={() => setReplyModal(null)}>
                      Annuler
                    </button>
                    <button type="submit" className="btn btn-primary">
                      Envoyer
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <div className="modal-backdrop fade show" style={{ backgroundColor: 'rgba(0,0,0,.5)' }}></div>
        </>
      )}
    </>
  )
}
