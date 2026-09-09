import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, datetime, money } from '../lib/format'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../components/Toasts'

/* Types repris du vanilla */
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
  client_id: number
  client_nom: string
  client_prenom: string
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

function Badge({ statut }: { statut: string }) {
  if (statut === 'en_attente') return <span className="badge bg-warning text-dark">En attente</span>
  if (statut === 'approuve' || statut === 'accepte' || statut === 'paye') return <span className="badge bg-success">{statut}</span>
  if (statut === 'refuse' || statut === 'echec') return <span className="badge bg-danger">{statut}</span>
  if (statut === 'annule') return <span className="badge bg-secondary">Annulé</span>
  if (statut === 'En cours') return <span className="badge bg-info text-dark">En cours</span>
  if (statut === 'Livré') return <span className="badge bg-success">Livré</span>
  return <span className="badge bg-light text-dark">{statut}</span>
}

export default function Dashboard() {
  const { me } = useAuth()
  const { toast } = useToast()
  const navigate = useNavigate()

  const [activeTab, setActiveTab] = useState<'colis' | 'voyages' | 'reservations' | 'paiements'>('colis')
  const [colis, setColis] = useState<ColisMine[] | null>(null)
  const [voyages, setVoyages] = useState<VoyageMine[] | null>(null)
  const [reservations, setReservations] = useState<ReservationRecue[] | null>(null)
  const [paiements, setPaiements] = useState<PaiementMine[] | null>(null)
  const [stats, setStats] = useState<PaiementsStats | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [searchColisId, setSearchColisId] = useState('')

  // Edit colis modal state (vanilla has modal)
  const [editColis, setEditColis] = useState<ColisMine | null>(null)
  const [editForm, setEditForm] = useState<any>({})

  const loadColis = useCallback(async () => {
    try {
      setColis(await api.get<ColisMine[]>('/api/colis/mine'))
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [])
  const loadVoyages = useCallback(async () => {
    try { setVoyages(await api.get<VoyageMine[]>('/api/voyages/mine')) } catch { setVoyages([]) }
  }, [])
  const loadReservations = useCallback(async () => {
    try { setReservations(await api.get<ReservationRecue[]>('/api/reservations/recues')) } catch { setReservations([]) }
  }, [])
  const loadPaiements = useCallback(async () => {
    try {
      const data = await api.get<{ paiements: PaiementMine[]; stats: PaiementsStats }>('/api/paiements/mine')
      setPaiements(data.paiements || [])
      setStats(data.stats || null)
    } catch { setPaiements([]) }
  }, [])

  useEffect(() => { loadColis(); loadVoyages(); loadPaiements() }, [loadColis, loadVoyages, loadPaiements])
  useEffect(() => { if (me?.is_transporteur) loadReservations() }, [me, loadReservations])

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (searchColisId) navigate('/recherche?colis_id=' + encodeURIComponent(searchColisId))
  }

  const onSuivi = async (colisId: number, statut: string) => {
    if (!statut) return
    if (statut === 'Livré' && !window.confirm("Marquer comme livré ? L'agence devra confirmer.")) return
    try {
      const data = await api.post<{ message?: string }>('/api/suivi', { colis_id: colisId, statut })
      toast(data.message || 'Statut mis à jour.')
      loadReservations()
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Erreur inconnue', 'error')
    }
  }

  const openEdit = (c: ColisMine) => {
    setEditColis(c)
    setEditForm({
      id: c.id,
      nom_colis: c.nom_colis,
      type_produit: c.type_produit || '',
      poids: c.poids,
      date_limite: c.date_limite?.substring(0,10) || '',
      pays: c.pays,
      ville: c.ville,
    })
  }

  const submitEdit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!editColis) return
    try {
      const data = await api.upload<{ prix_estime: number }>('/api/colis/' + editColis.id, {
        nom_colis: editForm.nom_colis,
        type_produit: editForm.type_produit,
        poids: editForm.poids,
        date_limite: editForm.date_limite,
        pays: editForm.pays,
        ville: editForm.ville,
        adresse_depart: editForm.adresse_depart || '',
        adresse_destination: editForm.adresse_destination || '',
      }, {})
      toast('Colis mis à jour. Nouveau prix : ' + money(data.prix_estime))
      setEditColis(null)
      loadColis()
    } catch (err) {
      toast(err instanceof Error ? err.message : 'Erreur', 'error')
    }
  }

  return (
    <>
      <h2 className="mb-4">Tableau de bord</h2>
      {error && <div className="alert alert-danger">{error}</div>}

      {/* Recherche transporteur */}
      <div className="card shadow-sm mb-4">
        <div className="card-body">
          <form onSubmit={onSearch} className="row g-2 align-items-end">
            <div className="col-md-8">
              <label className="form-label fw-bold"><i className="fa-solid fa-search"></i> Rechercher un transporteur :</label>
              <select className="form-select" required value={searchColisId} onChange={e => setSearchColisId(e.target.value)}>
                <option value="">-- Sélectionnez un colis --</option>
                {(colis || []).map(c => <option key={c.id} value={c.id}>{c.nom_colis} ({c.pays})</option>)}
              </select>
            </div>
            <div className="col-md-4">
              <button type="submit" className="btn btn-primary w-100"><i className="fa-solid fa-truck"></i> Rechercher</button>
            </div>
          </form>
        </div>
      </div>

      <ul className="nav nav-tabs mb-4" role="tablist">
        <li className="nav-item"><button className={`nav-link ${activeTab==='colis'?'active':''}`} onClick={()=>setActiveTab('colis')} type="button">Mes colis</button></li>
        <li className="nav-item"><button className={`nav-link ${activeTab==='voyages'?'active':''}`} onClick={()=>setActiveTab('voyages')} type="button">Mes voyages</button></li>
        <li className="nav-item"><button className={`nav-link ${activeTab==='reservations'?'active':''}`} onClick={()=>setActiveTab('reservations')} type="button">Réservations reçues</button></li>
        <li className="nav-item"><button className={`nav-link ${activeTab==='paiements'?'active':''}`} onClick={()=>setActiveTab('paiements')} type="button">Mes paiements</button></li>
      </ul>

      <div className="tab-content">
        {/* MES COLIS */}
        {activeTab==='colis' && (
          <div className="tab-pane fade show active">
            <div className="page-card">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <h5 className="mb-0">Mes colis</h5>
                <Link to="/poster-colis" className="btn btn-primary"><i className="fa-solid fa-box"></i> Poster un colis</Link>
              </div>
              {!colis && <div className="text-center text-muted py-4">Chargement…</div>}
              {colis && colis.length===0 && <p className="text-muted text-center py-4">Aucun colis pour le moment. <Link to="/poster-colis">Poster un colis</Link></p>}
              {(colis||[]).map(c=>(
                <div key={c.id} className="border rounded-3 p-3 mb-3">
                  <div className="d-flex flex-wrap gap-3 align-items-center">
                    {c.image_url ? <img src={c.image_url} alt={c.nom_colis} className="img-colis" /> : <i className="fa-solid fa-box fa-2x text-muted"></i>}
                    <div className="flex-grow-1">
                      <div className="fw-bold"><Link to={`/colis/${c.id}`} className="text-decoration-none">{c.nom_colis}</Link> <Badge statut={c.statut} /> {c.statut_livraison && <Badge statut={c.statut_livraison} />}</div>
                      <div className="small text-muted">{c.ville}, {c.pays} · {c.poids} kg · {c.type_produit || ''} · Prix : <strong>{money(c.prix_estime)}</strong> · Suivi : <code>{c.numero_suivi}</code> · Limite : {date(c.date_limite)}</div>
                      {c.reservations?.length ? <div className="small mt-1"><i className="fa-solid fa-truck text-primary"></i> Transporteur(s) : {c.reservations.map(r=>`${r.prenom} ${r.nom}${r.note_moyenne?` (${r.note_moyenne}★)`:''}`).join(', ')}</div> : null}
                    </div>
                    <div className="d-flex gap-2 flex-wrap">
                      {c.statut==='en_attente' && <>
                        <button className="btn btn-sm btn-outline-primary" onClick={()=>openEdit(c)}><i className="fa-solid fa-pen"></i> Modifier</button>
                        <Link className="btn btn-sm btn-outline-success" to={`/paiement?colis_id=${c.id}`}><i className="fa-solid fa-credit-card"></i> Payer</Link>
                      </>}
                      {c.statut==='approuve' && <>
                        <Link className="btn btn-sm btn-outline-info" to={`/suivi?numero_suivi=${encodeURIComponent(c.numero_suivi)}`}><i className="fa-solid fa-search-location"></i> Suivre</Link>
                        <Link className="btn btn-sm btn-outline-secondary" to={`/recherche?colis_id=${c.id}`}><i className="fa-solid fa-plane"></i> Voyages</Link>
                      </>}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* MES VOYAGES */}
        {activeTab==='voyages' && (
          <div className="tab-pane fade show active">
            <div className="page-card">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <h5 className="mb-0">Mes voyages</h5>
                <span>
                  {me?.is_transporteur && <span className="badge bg-success me-2">Solde : {money(me.solde as any)}</span>}
                  <Link to="/devenir-transporteur" className="btn btn-primary"><i className="fa-solid fa-plane-departure"></i> Proposer un voyage</Link>
                </span>
              </div>
              {!me?.is_transporteur && <div className="alert alert-info mb-0">Proposez un voyage pour devenir transporteur. <Link to="/devenir-transporteur" className="alert-link">Candidater</Link></div>}
              {me?.is_transporteur && !voyages && <div className="text-center text-muted py-4">Chargement…</div>}
              {me?.is_transporteur && voyages && voyages.length===0 && <p className="text-muted text-center py-4">Aucun voyage proposé. <Link to="/devenir-transporteur">Proposer un voyage</Link></p>}
              {voyages && voyages.length>0 && (
                <div className="table-responsive"><table className="table align-middle">
                  <thead className="table-light"><tr><th>Trajet</th><th>Départ</th><th>Poids max</th><th>Réservations</th><th>Statut</th></tr></thead>
                  <tbody>{voyages.map(v=><tr key={v.id}><td>{v.pays_depart} → {v.pays_destination}</td><td>{date(v.date_depart)} <span className="text-muted small">{(v.heure_depart||'').substring(0,5)}</span></td><td>{v.poids_max} kg</td><td><span className="badge bg-secondary">{v.nb_reservations}</span></td><td><Badge statut={v.statut} /></td></tr>)}</tbody>
                </table></div>
              )}
            </div>
          </div>
        )}

        {/* RESERVATIONS */}
        {activeTab==='reservations' && (
          <div className="tab-pane fade show active">
            <div className="page-card">
              <h5 className="mb-3">Réservations sur mes voyages</h5>
              {!me?.is_transporteur && <p className="text-muted text-center py-4">Réservé aux transporteurs.</p>}
              {me?.is_transporteur && !reservations && <div className="text-center text-muted py-4">Chargement…</div>}
              {me?.is_transporteur && reservations && reservations.length===0 && <p className="text-muted text-center py-4">Aucune réservation sur vos voyages. <Link to="/colis">Voir les colis disponibles</Link></p>}
              {reservations && reservations.length>0 && (
                <>
                <div className="table-responsive"><table className="table align-middle">
                  <thead className="table-light"><tr><th>Colis</th><th>Client</th><th>Voyage</th><th>Prix</th><th>Statut</th><th>Suivi</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>{reservations.map(r=><tr key={`${r.colis_id}-${r.client_id}`}>
                    <td><div className="d-flex align-items-center gap-2">{r.image_url && <img src={r.image_url} alt="" className="img-colis" />}<div><Link to={`/colis/${r.colis_id}`}>{r.nom_colis}</Link><div className="small text-muted"><code>{r.numero_suivi}</code></div></div></div></td>
                    <td>{r.client_prenom} {r.client_nom}<div className="small text-muted">{r.client_tel||''}</div></td>
                    <td>{r.pays_depart} → {r.pays_destination}<div className="small text-muted">{date(r.date_depart)}</div></td>
                    <td>{money(r.prix_estime)}</td>
                    <td><Badge statut={r.statut} /></td>
                    <td><Badge statut={r.statut_suivi} /></td>
                    <td className="text-end">
                      {r.statut==='accepte' && r.statut_suivi!=='Livré' && (
                        <select className="form-select form-select-sm" style={{width:160, display:'inline-block'}} defaultValue="" onChange={e=>onSuivi(r.colis_id, e.target.value)}>
                          <option value="">Mettre à jour…</option>
                          <option value="En attente">En attente</option>
                          <option value="En cours">En cours</option>
                          <option value="Livré">Livré</option>
                        </select>
                      )}
                      <Link className="btn btn-sm btn-outline-primary ms-1" to={`/messagerie?destinataire_id=${r.client_id}`}><i className="fa-solid fa-envelope"></i></Link>
                    </td>
                  </tr>)}</tbody>
                </table></div>
                <p className="text-muted small mb-0"><i className="fa-solid fa-circle-info"></i> Marquer « Livré » envoie une demande de confirmation à l'agence.</p>
                </>
              )}
            </div>
          </div>
        )}

        {/* PAIEMENTS */}
        {activeTab==='paiements' && (
          <div className="tab-pane fade show active">
            <div className="page-card">
              <h5 className="mb-3">Mes paiements</h5>
              {stats && (
                <div className="row g-3 mb-3">
                  <div className="col-md-4"><div className="border rounded-3 p-3 text-center"><div className="text-muted small">Total</div><div className="fs-5 fw-bold">{money(stats.montant_total)}</div></div></div>
                  <div className="col-md-4"><div className="border rounded-3 p-3 text-center"><div className="text-muted small">Payé</div><div className="fs-5 fw-bold text-success">{money(stats.montant_paye)}</div></div></div>
                  <div className="col-md-4"><div className="border rounded-3 p-3 text-center"><div className="text-muted small">En attente</div><div className="fs-5 fw-bold text-warning">{money(stats.montant_en_attente)}</div></div></div>
                </div>
              )}
              {!paiements && <div className="text-center text-muted py-4">Chargement…</div>}
              {paiements && paiements.length===0 && <p className="text-muted text-center py-4">Aucun paiement.</p>}
              {paiements && paiements.length>0 && (
                <div className="table-responsive"><table className="table align-middle">
                  <thead className="table-light"><tr><th>Colis</th><th>Montant</th><th>Méthode</th><th>Référence</th><th>Date</th><th>Statut</th><th></th></tr></thead>
                  <tbody>{paiements.map(p=><tr key={p.id}><td>{p.nom_colis || 'Colis #'+p.colis_id}</td><td>{money(p.montant)}</td><td>{p.methode_paiement==='carte_credit' ? 'Carte' : p.methode_paiement==='mobile_money' ? 'Mobile Money' : p.methode_paiement||'—'}</td><td className="small text-muted">{p.reference}<br/>{p.numero_transaction||''}</td><td className="small">{datetime(p.date_creation)}</td><td><Badge statut={p.statut} /></td><td>{p.statut==='en_attente' && <Link className="btn btn-sm btn-outline-success" to={`/paiement?colis_id=${p.colis_id}`}><i className="fa-solid fa-credit-card"></i> Payer</Link>}</td></tr>)}</tbody>
                </table></div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Modal édition colis — vanilla */}
      {editColis && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,.5)' }}>
          <div className="modal-dialog modal-lg">
            <div className="modal-content">
              <form onSubmit={submitEdit}>
                <div className="modal-header"><h5 className="modal-title">Modifier le colis</h5><button type="button" className="btn-close" onClick={()=>setEditColis(null)}></button></div>
                <div className="modal-body">
                  <div className="row g-3">
                    <div className="col-md-6"><label className="form-label">Nom du colis</label><input type="text" className="form-control" value={editForm.nom_colis||''} onChange={e=>setEditForm({...editForm, nom_colis:e.target.value})} required /></div>
                    <div className="col-md-3"><label className="form-label">Type</label><select className="form-select" value={editForm.type_produit||''} onChange={e=>setEditForm({...editForm, type_produit:e.target.value})} required><option value="alimentaire">Alimentaire</option><option value="electronique">Électronique</option><option value="vetements">Vêtements</option><option value="documents">Documents</option><option value="autre">Autre</option></select></div>
                    <div className="col-md-3"><label className="form-label">Poids (kg)</label><input type="number" step="0.01" className="form-control" value={editForm.poids||''} onChange={e=>setEditForm({...editForm, poids:e.target.value})} required /></div>
                    <div className="col-md-6"><label className="form-label">Pays</label><input type="text" className="form-control" value={editForm.pays||''} onChange={e=>setEditForm({...editForm, pays:e.target.value})} required /></div>
                    <div className="col-md-6"><label className="form-label">Ville</label><input type="text" className="form-control" value={editForm.ville||''} onChange={e=>setEditForm({...editForm, ville:e.target.value})} required /></div>
                    <div className="col-md-6"><label className="form-label">Adresse départ</label><input type="text" className="form-control" value={editForm.adresse_depart||''} onChange={e=>setEditForm({...editForm, adresse_depart:e.target.value})} /></div>
                    <div className="col-md-6"><label className="form-label">Adresse destination</label><input type="text" className="form-control" value={editForm.adresse_destination||''} onChange={e=>setEditForm({...editForm, adresse_destination:e.target.value})} /></div>
                    <div className="col-md-6"><label className="form-label">Date limite</label><input type="date" className="form-control" value={editForm.date_limite||''} onChange={e=>setEditForm({...editForm, date_limite:e.target.value})} required /></div>
                  </div>
                </div>
                <div className="modal-footer"><button type="button" className="btn btn-secondary" onClick={()=>setEditColis(null)}>Annuler</button><button type="submit" className="btn btn-primary">Enregistrer</button></div>
              </form>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
