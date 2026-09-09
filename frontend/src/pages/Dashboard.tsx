import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, datetime, money, SmartImg, StatusBadge } from '../lib/format'
import { useAuth, type Me } from '../context/AuthContext'
import { useToast } from '../components/Toasts'

/**
 * Tableau de bord — port de dashboard.html.
 * 4 onglets : mes colis, mes voyages, réservations reçues, mes paiements.
 * Les onglets et la modale d'édition sont gérés en état React (le vanilla
 * utilisait les plugins Bootstrap tab/modal — rendu visuel identique).
 */

interface ReservationAffectee {
  transporteur_id: number
  nom: string
  prenom: string
  note_moyenne: number | null
}

interface ColisMine {
  id: number
  nom_colis: string
  image_url: string | null
  statut: string
  statut_livraison: string | null
  suivi_confirme_par_admin: boolean
  demande_livraison: boolean
  ville: string
  pays: string
  poids: string | number
  type_produit: string | null
  prix_estime: string | number
  numero_suivi: string
  date_limite: string
  reservations: ReservationAffectee[]
}

interface ReservationDetail {
  id: number
  statut: string
  date_reservation: string
  transporteur_id: number
  nom: string
  prenom: string
  email: string | null
  telephone: string | null
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
}

interface VoyageMine {
  id: number
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
  poids_max: string | number
  nb_reservations: number
  statut: string
}

interface ReservationRecue {
  colis_id: number
  nom_colis: string
  image_url: string | null
  numero_suivi: string
  client_id: number | null
  client_nom: string | null
  client_prenom: string | null
  client_tel: string | null
  pays_depart: string
  pays_destination: string
  date_depart: string
  prix_estime: string | number
  statut: string
  statut_suivi: string
}

interface PaiementMine {
  id: number
  colis_id: number
  nom_colis: string | null
  montant: string | number
  methode_paiement: string | null
  details_paiement: { numero_masque?: string; operateur?: string } | null
  reference: string
  numero_transaction: string | null
  date_creation: string
  statut: string
}

interface PaiementsStats {
  montant_total: number
  montant_paye: number
  montant_en_attente: number
}

type Tab = 'colis' | 'voyages' | 'reservations' | 'paiements'

export default function Dashboard() {
  const { me } = useAuth()
  const { toast } = useToast()
  const [tab, setTab] = useState<Tab>('colis')

  return (
    <>
      <h2 className="mb-4">Tableau de bord</h2>

      <ul className="nav nav-tabs mb-4" role="tablist">
        {(
          [
            ['colis', 'Mes colis'],
            ['voyages', 'Mes voyages'],
            ['reservations', 'Réservations reçues'],
            ['paiements', 'Mes paiements'],
          ] as [Tab, string][]
        ).map(([key, label]) => (
          <li className="nav-item" key={key}>
            <button
              type="button"
              className={'nav-link' + (tab === key ? ' active' : '')}
              onClick={() => setTab(key)}
            >
              {label}
            </button>
          </li>
        ))}
      </ul>

      <div className="tab-content">
        {tab === 'colis' && <TabColis me={me} toast={toast} />}
        {tab === 'voyages' && <TabVoyages me={me} />}
        {tab === 'reservations' && <TabReservations me={me} toast={toast} />}
        {tab === 'paiements' && <TabPaiements />}
      </div>
    </>
  )
}

interface ToastFn {
  toast: (message: string, type?: 'success' | 'error' | 'info') => void
}

/* =============================== MES COLIS =============================== */

function TabColis({ me, toast }: { me: Me | null } & ToastFn) {
  const [list, setList] = useState<ColisMine[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<ColisMine | null>(null)

  const load = useCallback(async () => {
    try {
      setError(null)
      setList(await api.get<ColisMine[]>('/api/colis/mine'))
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  void me

  return (
    <div className="tab-pane fade show active">
      <div className="page-card">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h5 className="mb-0">Mes colis</h5>
          <Link to="/poster-colis" className="btn btn-primary">
            <i className="fa-solid fa-box"></i> Poster un colis
          </Link>
        </div>

        {error && <div className="alert alert-danger">{error}</div>}
        {!list && !error && <div className="text-center text-muted py-4">Chargement…</div>}
        {list && list.length === 0 && (
          <p className="text-muted text-center py-4">
            Aucun colis pour le moment. <Link to="/poster-colis">Poster un colis</Link>
          </p>
        )}

        {list?.map((c) => (
          <ColisRow key={c.id} c={c} toast={toast} onEdit={() => setEditing(c)} onReload={load} />
        ))}
      </div>

      {editing && (
        <EditColisModal
          colisId={editing.id}
          onClose={() => setEditing(null)}
          onSaved={(prix) => {
            setEditing(null)
            toast('Colis mis à jour. Nouveau prix estimé : ' + money(prix))
            load()
          }}
        />
      )}
    </div>
  )
}

function ColisRow({
  c,
  toast,
  onEdit,
  onReload,
}: {
  c: ColisMine
  onEdit: () => void
  onReload: () => void
} & ToastFn) {
  const [showRes, setShowRes] = useState(false)
  const [resList, setResList] = useState<ReservationDetail[] | null>(null)
  const [resError, setResError] = useState<string | null>(null)

  const toggleReservations = async () => {
    if (showRes) {
      setShowRes(false)
      return
    }
    setShowRes(true)
    setResList(null)
    setResError(null)
    try {
      setResList(await api.get<ReservationDetail[]>(`/api/colis/${c.id}/reservations`))
    } catch (e) {
      setResError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }

  const action = async (id: number, act: string) => {
    const labels: Record<string, string> = {
      accepte: 'accepter',
      refuse: 'refuser',
      annule: 'annuler (supprime le suivi)',
    }
    if (!window.confirm('Confirmez-vous : ' + labels[act] + ' ?')) return
    try {
      await api.post(`/api/reservations/${id}/action`, { action: act })
      toast('Réservation mise à jour.')
      onReload()
      // Recharger aussi le détail ouvert.
      setResList(await api.get<ReservationDetail[]>(`/api/colis/${c.id}/reservations`))
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Erreur inconnue', 'error')
    }
  }

  return (
    <div className="border rounded-3 p-3 mb-3">
      <div className="d-flex flex-wrap gap-3 align-items-center">
        <SmartImg url={c.image_url} alt={c.nom_colis} className="img-colis" />
        <div className="flex-grow-1">
          <div className="fw-bold">
            <Link to={`/colis/${c.id}`} className="text-decoration-none">
              {c.nom_colis}
            </Link>{' '}
            <StatusBadge statut={c.statut} />{' '}
            {c.statut_livraison && <StatusBadge statut={c.statut_livraison} />}{' '}
            {c.demande_livraison && !c.suivi_confirme_par_admin && (
              <span className="badge bg-info text-dark">
                Livraison en attente de confirmation admin
              </span>
            )}
          </div>
          <div className="small text-muted">
            {c.ville}, {c.pays} · {c.poids} kg · {c.type_produit || ''} · Prix :{' '}
            <strong>{money(c.prix_estime)}</strong> · Suivi : <code>{c.numero_suivi}</code> ·
            Limite : {date(c.date_limite)}
          </div>
          {c.reservations && c.reservations.length > 0 && (
            <div className="small mt-1">
              <i className="fa-solid fa-truck text-primary"></i> Transporteur(s) affecté(s) :{' '}
              {c.reservations.map((r, i) => (
                <span key={r.transporteur_id}>
                  {i > 0 && ', '}
                  <Link to={`/profil-transporteur?id=${r.transporteur_id}`}>
                    {r.prenom} {r.nom}
                  </Link>
                  {r.note_moyenne ? ` (${r.note_moyenne}★)` : ''}
                </span>
              ))}
            </div>
          )}
        </div>
        <div className="d-flex gap-2 flex-wrap">
          {c.statut === 'en_attente' && (
            <>
              <button className="btn btn-sm btn-outline-primary" onClick={onEdit}>
                <i className="fa-solid fa-pen"></i> Modifier
              </button>
              <Link className="btn btn-sm btn-outline-success" to={`/paiement?colis_id=${c.id}`}>
                <i className="fa-solid fa-credit-card"></i> Payer
              </Link>
            </>
          )}
          {c.statut === 'approuve' && (
            <>
              <Link
                className="btn btn-sm btn-outline-info"
                to={'/suivi?numero_suivi=' + encodeURIComponent(c.numero_suivi)}
              >
                <i className="fa-solid fa-search-location"></i> Suivre
              </Link>
              <Link className="btn btn-sm btn-outline-secondary" to={`/recherche?colis_id=${c.id}`}>
                <i className="fa-solid fa-plane"></i> Voyages compatibles
              </Link>
              <button className="btn btn-sm btn-outline-dark" onClick={toggleReservations}>
                <i className="fa-solid fa-list"></i> Réservations
              </button>
            </>
          )}
        </div>
      </div>

      {showRes && (
        <div className="mt-3">
          {resError && <div className="alert alert-danger small">{resError}</div>}
          {!resList && !resError && <p className="text-muted small">Chargement…</p>}
          {resList && resList.length === 0 && (
            <p className="text-muted small mb-0">Aucune réservation pour ce colis.</p>
          )}
          {resList && resList.length > 0 && (
            <div className="table-responsive">
              <table className="table table-sm align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th>Transporteur</th>
                    <th>Voyage</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th className="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {resList.map((r) => (
                    <tr key={r.id}>
                      <td>
                        <Link to={`/profil-transporteur?id=${r.transporteur_id}`}>
                          {r.prenom} {r.nom}
                        </Link>
                        <div className="small text-muted">
                          {r.email || ''} · {r.telephone || ''}
                        </div>
                      </td>
                      <td>
                        {r.pays_depart} → {r.pays_destination}
                        <div className="small text-muted">
                          {date(r.date_depart)} {r.heure_depart || ''}
                        </div>
                      </td>
                      <td className="small">{datetime(r.date_reservation)}</td>
                      <td>
                        <StatusBadge statut={r.statut} />
                      </td>
                      <td className="text-end">
                        {r.statut === 'en_attente' && (
                          <>
                            <button
                              className="btn btn-sm btn-success me-1"
                              title="Accepter"
                              onClick={() => action(r.id, 'accepte')}
                            >
                              <i className="fa-solid fa-check"></i>
                            </button>
                            <button
                              className="btn btn-sm btn-danger"
                              title="Refuser"
                              onClick={() => action(r.id, 'refuse')}
                            >
                              <i className="fa-solid fa-xmark"></i>
                            </button>
                          </>
                        )}
                        {r.statut === 'accepte' && (
                          <button
                            className="btn btn-sm btn-outline-secondary me-1"
                            title="Annuler"
                            onClick={() => action(r.id, 'annule')}
                          >
                            <i className="fa-solid fa-ban"></i>
                          </button>
                        )}
                        <Link
                          className="btn btn-sm btn-outline-primary"
                          to={`/messagerie?destinataire_id=${r.transporteur_id}`}
                          title="Message"
                        >
                          <i className="fa-solid fa-envelope"></i>
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

/* ====================== MODALE D'ÉDITION D'UN COLIS ====================== */

interface ColisEditFields {
  nom_colis: string
  type_produit: string
  nombre_produits: string
  poids: string
  dimensions: string
  date_limite: string
  pays: string
  ville: string
  adresse_depart: string
  adresse_destination: string
}

function EditColisModal({
  colisId,
  onClose,
  onSaved,
}: {
  colisId: number
  onClose: () => void
  onSaved: (prixEstime: number) => void
}) {
  const [fields, setFields] = useState<ColisEditFields | null>(null)
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    ;(async () => {
      try {
        const c = await api.get<Record<string, unknown>>(`/api/colis/${colisId}`)
        setFields({
          nom_colis: String(c.nom_colis ?? ''),
          type_produit: String(c.type_produit ?? 'autre'),
          nombre_produits: String(c.nombre_produits ?? 1),
          poids: String(c.poids ?? ''),
          dimensions: String(c.dimensions ?? ''),
          date_limite: String(c.date_limite ?? '').substring(0, 10),
          pays: String(c.pays ?? ''),
          ville: String(c.ville ?? ''),
          adresse_depart: String(c.adresse_depart ?? ''),
          adresse_destination: String(c.adresse_destination ?? ''),
        })
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      } finally {
        setLoading(false)
      }
    })()
  }, [colisId])

  const set = (key: keyof ColisEditFields) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setFields((f) => (f ? { ...f, [key]: e.target.value } : f))

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!fields) return
    setError(null)
    try {
      const data = await api.upload<{ prix_estime: number }>(
        `/api/colis/${colisId}`,
        { ...fields },
        { image_colis: file },
      )
      onSaved(data.prix_estime)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,.5)' }}>
      <div className="modal-dialog modal-lg">
        <div className="modal-content">
          <form onSubmit={onSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Modifier le colis</h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {loading && <p className="text-muted text-center py-3">Chargement…</p>}
              {fields && (
                <div className="row g-3">
                  <div className="col-md-6">
                    <label className="form-label">Nom du colis</label>
                    <input type="text" className="form-control" maxLength={100} value={fields.nom_colis} onChange={set('nom_colis')} required />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label">Type de produit</label>
                    <select className="form-select" value={fields.type_produit} onChange={set('type_produit')} required>
                      <option value="alimentaire">Alimentaire</option>
                      <option value="electronique">Électronique</option>
                      <option value="vetements">Vêtements</option>
                      <option value="documents">Documents</option>
                      <option value="autre">Autre</option>
                    </select>
                  </div>
                  <div className="col-md-3">
                    <label className="form-label">Nombre de produits</label>
                    <input type="number" min={1} max={10000} className="form-control" value={fields.nombre_produits} onChange={set('nombre_produits')} required />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label">Poids (kg)</label>
                    <input type="number" step="0.01" min="0.01" max="1000" className="form-control" value={fields.poids} onChange={set('poids')} required />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label">Dimensions (LxlxH cm)</label>
                    <input type="text" className="form-control" maxLength={50} value={fields.dimensions} onChange={set('dimensions')} />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label">Date limite</label>
                    <input type="date" className="form-control" value={fields.date_limite} onChange={set('date_limite')} required />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Pays (destination)</label>
                    <input type="text" className="form-control" value={fields.pays} onChange={set('pays')} required />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Ville (destination)</label>
                    <input type="text" className="form-control" value={fields.ville} onChange={set('ville')} required />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Adresse de départ</label>
                    <input type="text" className="form-control" value={fields.adresse_depart} onChange={set('adresse_depart')} required />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Adresse de destination</label>
                    <input type="text" className="form-control" value={fields.adresse_destination} onChange={set('adresse_destination')} required />
                  </div>
                  <div className="col-12">
                    <label className="form-label">Nouvelle photo (optionnel)</label>
                    <input
                      type="file"
                      className="form-control"
                      accept="image/jpeg,image/png,image/gif,image/webp"
                      onChange={(e) => setFile(e.target.files?.[0] || null)}
                    />
                  </div>
                </div>
              )}
              <p className="text-muted small mt-3 mb-0">
                <i className="fa-solid fa-circle-info"></i> Le prix estimé est recalculé
                automatiquement selon le poids.
              </p>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>
                Annuler
              </button>
              <button type="submit" className="btn btn-primary" disabled={!fields}>
                Enregistrer
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

/* =============================== MES VOYAGES ============================== */

function TabVoyages({ me }: { me: Me | null }) {
  const [list, setList] = useState<VoyageMine[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!me?.is_transporteur) return
    ;(async () => {
      try {
        setList(await api.get<VoyageMine[]>('/api/voyages/mine'))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [me])

  return (
    <div className="tab-pane fade show active">
      <div className="page-card">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h5 className="mb-0">Mes voyages</h5>
          <span>
            {me?.is_transporteur && (
              <span className="badge bg-success me-2">Solde : {money(me.solde)}</span>
            )}
            <Link to="/devenir-transporteur" className="btn btn-primary">
              <i className="fa-solid fa-plane-departure"></i> Proposer un voyage
            </Link>
          </span>
        </div>

        {!me?.is_transporteur ? (
          <div className="alert alert-info mb-0">
            Proposez un voyage pour devenir transporteur.{' '}
            <Link to="/devenir-transporteur" className="alert-link">
              Candidater
            </Link>
          </div>
        ) : (
          <>
            {error && <div className="alert alert-danger">{error}</div>}
            {!list && !error && <div className="text-center text-muted py-4">Chargement…</div>}
            {list && list.length === 0 && (
              <p className="text-muted text-center py-4">
                Aucun voyage proposé. <Link to="/devenir-transporteur">Proposer un voyage</Link>
              </p>
            )}
            {list && list.length > 0 && (
              <div className="table-responsive">
                <table className="table align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>Trajet</th>
                      <th>Départ</th>
                      <th>Poids max</th>
                      <th>Réservations</th>
                      <th>Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                    {list.map((v) => (
                      <tr key={v.id}>
                        <td>
                          {v.pays_depart} → {v.pays_destination}
                        </td>
                        <td>
                          {date(v.date_depart)}{' '}
                          <span className="text-muted small">
                            {(v.heure_depart || '').substring(0, 5)}
                          </span>
                        </td>
                        <td>{v.poids_max} kg</td>
                        <td>
                          <span className="badge bg-secondary">{v.nb_reservations}</span>
                        </td>
                        <td>
                          <StatusBadge statut={v.statut} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}

/* ========================= RÉSERVATIONS REÇUES ============================ */

function TabReservations({ me, toast }: { me: Me | null } & ToastFn) {
  const [list, setList] = useState<ReservationRecue[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    try {
      setError(null)
      setList(await api.get<ReservationRecue[]>('/api/reservations/recues'))
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [])

  useEffect(() => {
    if (me?.is_transporteur) load()
  }, [me, load])

  const onSuivi = async (colisId: number, statut: string) => {
    if (!statut) return
    if (statut === 'Livré' && !window.confirm("Marquer comme livré ? L'agence devra confirmer.")) {
      load() // réinitialiser le select
      return
    }
    try {
      const data = await api.post<{ message?: string }>('/api/suivi', { colis_id: colisId, statut })
      toast(data.message || 'Statut mis à jour.')
      load()
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Erreur inconnue', 'error')
      load()
    }
  }

  return (
    <div className="tab-pane fade show active">
      <div className="page-card">
        <h5 className="mb-3">Réservations sur mes voyages</h5>

        {!me?.is_transporteur ? (
          <p className="text-muted text-center py-4">Réservé aux transporteurs.</p>
        ) : (
          <>
            {error && <div className="alert alert-danger">{error}</div>}
            {!list && !error && <div className="text-center text-muted py-4">Chargement…</div>}
            {list && list.length === 0 && (
              <p className="text-muted text-center py-4">
                Aucune réservation sur vos voyages. <Link to="/colis">Voir les colis disponibles</Link>
              </p>
            )}
            {list && list.length > 0 && (
              <>
                <div className="table-responsive">
                  <table className="table align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>Colis</th>
                        <th>Client</th>
                        <th>Voyage</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Suivi</th>
                        <th className="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {list.map((r, idx) => (
                        <tr key={`${r.colis_id}-${idx}`}>
                          <td>
                            <SmartImg url={r.image_url} alt={r.nom_colis} className="img-colis" />
                            <Link to={`/colis/${r.colis_id}`} className="ms-2">
                              {r.nom_colis}
                            </Link>
                            <div className="small text-muted">
                              <code>{r.numero_suivi}</code>
                            </div>
                          </td>
                          <td>
                            {r.client_prenom || ''} {r.client_nom || ''}
                            <div className="small text-muted">{r.client_tel || ''}</div>
                          </td>
                          <td>
                            {r.pays_depart} → {r.pays_destination}
                            <div className="small text-muted">{date(r.date_depart)}</div>
                          </td>
                          <td>{money(r.prix_estime)}</td>
                          <td>
                            <StatusBadge statut={r.statut} />
                          </td>
                          <td>
                            <StatusBadge statut={r.statut_suivi} />
                          </td>
                          <td className="text-end">
                            {r.statut === 'accepte' && r.statut_suivi !== 'Livré' && (
                              <select
                                className="form-select form-select-sm d-inline-block me-1"
                                style={{ width: 160 }}
                                defaultValue=""
                                onChange={(e) => onSuivi(r.colis_id, e.target.value)}
                              >
                                <option value="">Mettre à jour…</option>
                                <option value="En attente">En attente</option>
                                <option value="En cours">En cours</option>
                                <option value="Livré">Livré</option>
                              </select>
                            )}
                            {r.client_id && (
                              <Link
                                className="btn btn-sm btn-outline-primary"
                                to={`/messagerie?destinataire_id=${r.client_id}`}
                                title="Message"
                              >
                                <i className="fa-solid fa-envelope"></i>
                              </Link>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="text-muted small mb-0">
                  <i className="fa-solid fa-circle-info"></i> Marquer « Livré » envoie une demande
                  de confirmation à l'agence. La commission de 5 % est versée à la confirmation.
                </p>
              </>
            )}
          </>
        )}
      </div>
    </div>
  )
}

/* ============================== MES PAIEMENTS ============================= */

function TabPaiements() {
  const [list, setList] = useState<PaiementMine[] | null>(null)
  const [stats, setStats] = useState<PaiementsStats | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        const data = await api.get<{ paiements: PaiementMine[]; stats: PaiementsStats }>(
          '/api/paiements/mine',
        )
        setList(data.paiements || [])
        setStats(data.stats || null)
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [])

  const methode = (p: PaiementMine) =>
    p.methode_paiement === 'carte_credit' ? (
      <>
        <i className="fa-solid fa-credit-card"></i> Carte
      </>
    ) : p.methode_paiement === 'mobile_money' ? (
      <>
        <i className="fa-solid fa-mobile-screen"></i> Mobile Money
      </>
    ) : (
      p.methode_paiement || '—'
    )

  return (
    <div className="tab-pane fade show active">
      <div className="page-card">
        <h5 className="mb-3">Mes paiements</h5>

        {stats && (
          <div className="row g-3 mb-3">
            <div className="col-md-4">
              <div className="border rounded-3 p-3 text-center">
                <div className="text-muted small">Total</div>
                <div className="fs-5 fw-bold">{money(stats.montant_total)}</div>
              </div>
            </div>
            <div className="col-md-4">
              <div className="border rounded-3 p-3 text-center">
                <div className="text-muted small">Payé</div>
                <div className="fs-5 fw-bold text-success">{money(stats.montant_paye)}</div>
              </div>
            </div>
            <div className="col-md-4">
              <div className="border rounded-3 p-3 text-center">
                <div className="text-muted small">En attente</div>
                <div className="fs-5 fw-bold text-warning">{money(stats.montant_en_attente)}</div>
              </div>
            </div>
          </div>
        )}

        {error && <div className="alert alert-danger">{error}</div>}
        {!list && !error && <div className="text-center text-muted py-4">Chargement…</div>}
        {list && list.length === 0 && <p className="text-muted text-center py-4">Aucun paiement.</p>}

        {list && list.length > 0 && (
          <div className="table-responsive">
            <table className="table align-middle">
              <thead className="table-light">
                <tr>
                  <th>Colis</th>
                  <th>Montant</th>
                  <th>Méthode</th>
                  <th>Détails</th>
                  <th>Référence</th>
                  <th>Date</th>
                  <th>Statut</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {list.map((p) => (
                  <tr key={p.id}>
                    <td>{p.nom_colis || 'Colis #' + p.colis_id}</td>
                    <td>{money(p.montant)}</td>
                    <td>{methode(p)}</td>
                    <td className="small">
                      {(p.details_paiement &&
                        (p.details_paiement.numero_masque || p.details_paiement.operateur)) ||
                        '—'}
                    </td>
                    <td className="small text-muted">
                      {p.reference}
                      {p.numero_transaction && (
                        <>
                          <br />
                          {p.numero_transaction}
                        </>
                      )}
                    </td>
                    <td className="small">{datetime(p.date_creation)}</td>
                    <td>
                      <StatusBadge statut={p.statut} />
                    </td>
                    <td>
                      {p.statut === 'en_attente' && (
                        <Link
                          className="btn btn-sm btn-outline-success"
                          to={`/paiement?colis_id=${p.colis_id}`}
                        >
                          <i className="fa-solid fa-credit-card"></i> Payer
                        </Link>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
