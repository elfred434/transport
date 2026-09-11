import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, datetime, money, SmartImg } from '../lib/format'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../components/Toasts'
import ResponsiveTable from '../components/ResponsiveTable'
import type { Column } from '../components/ResponsiveTable'
import '../styles/dashboard-originale.css'

/**
 * Tableau de bord — port React de dashboard.php (design d'origine conservé) :
 * carte de recherche transporteur, table « Mes colis postés » avec modale à
 * onglets (Détails / Statut / Transporteurs), « Mes voyages proposés »,
 * « Mes réservations » (transporteur), « Mes paiements » (cartes + table +
 * modale), carte solde et menu inférieur mobile.
 * Les modales et onglets Bootstrap sont gérés en état React.
 */

/* ================================ TYPES ================================== */

interface ReservationAffectee {
  transporteur_id: number
  nom: string
  prenom: string
  email: string | null
  telephone: string | null
  photo_url: string | null
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
  telephone: string | null
  nb_reservations: number
  statut: string
}

interface ReservationRecue {
  colis_id: number
  nom_colis: string
  image_url: string | null
  numero_suivi: string
  poids: string | number
  ville: string
  pays: string
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
  nb_payes: number
  nb_en_attente: number
}

interface TransporteurStats {
  note_moyenne: number | null
  nb_avis: number
  nb_colis_transportes: number
}

interface TransporteurData {
  user: { id: number; nom: string; prenom: string }
  stats: TransporteurStats
}

interface Avis {
  prenom: string
  nom: string
  note: number
  commentaire: string
  date_avis: string
}

/* =============================== HELPERS ================================= */

const TYPE_LABELS: Record<string, string> = {
  documents: 'Documents',
  vetements: 'Vêtements',
  electronique: 'Électronique',
  alimentaire: 'Alimentaire',
  autre: 'Autre',
}

function BadgeColis({ statut }: { statut: string }) {
  if (statut === 'en_attente') return <span className="badge bg-warning text-dark">En attente</span>
  if (statut === 'approuve') return <span className="badge bg-success">Approuvé</span>
  if (statut === 'refuse') return <span className="badge bg-danger">Refusé</span>
  return <span className="badge bg-light text-dark">{statut}</span>
}

function BadgeReservation({ statut }: { statut: string }) {
  if (statut === 'en_attente') return <span className="badge bg-warning text-dark">En attente</span>
  if (statut === 'accepte') return <span className="badge bg-success">Accepté</span>
  if (statut === 'refuse') return <span className="badge bg-danger">Refusé</span>
  if (statut === 'annule') return <span className="badge bg-secondary">Annulé</span>
  return <span className="badge bg-light text-dark">{statut}</span>
}

function BadgePaiement({ statut }: { statut: string }) {
  if (statut === 'paye') return <span className="badge bg-success">Payé</span>
  if (statut === 'en_attente') return <span className="badge bg-warning text-dark">En attente</span>
  if (statut === 'echec') return <span className="badge bg-danger">Échec</span>
  if (statut === 'annule') return <span className="badge bg-secondary">Annulé</span>
  return <span className="badge bg-light text-dark">{statut}</span>
}

function Stars({ note }: { note: number | null }) {
  const n = Math.round(Number(note || 0))
  return (
    <div className="text-warning">
      {[1, 2, 3, 4, 5].map((i) => (
        <i
          key={i}
          className={i <= n ? 'fas fa-star' : 'fa-solid fa-star text-muted opacity-25'}
        />
      ))}
    </div>
  )
}

/* ============================== DASHBOARD ================================ */

export default function Dashboard() {
  const { me, logout } = useAuth()
  const { toast } = useToast()
  const navigate = useNavigate()

  const [colis, setColis] = useState<ColisMine[] | null>(null)
  const [voyages, setVoyages] = useState<VoyageMine[] | null>(null)
  const [reservations, setReservations] = useState<ReservationRecue[] | null>(null)
  const [paiements, setPaiements] = useState<PaiementMine[] | null>(null)
  const [stats, setStats] = useState<PaiementsStats | null>(null)
  const [error, setError] = useState<string | null>(null)

  const [detailsColis, setDetailsColis] = useState<ColisMine | null>(null)
  const [detailsVoyage, setDetailsVoyage] = useState<VoyageMine | null>(null)
  const [detailsPaiement, setDetailsPaiement] = useState<PaiementMine | null>(null)

  const [searchColisId, setSearchColisId] = useState('')

  const loadColis = useCallback(async () => {
    try {
      setError(null)
      setColis(await api.get<ColisMine[]>('/api/colis/mine'))
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [])

  const loadVoyages = useCallback(async () => {
    try {
      setVoyages(await api.get<VoyageMine[]>('/api/voyages/mine'))
    } catch {
      setVoyages([])
    }
  }, [])

  const loadReservations = useCallback(async () => {
    try {
      setReservations(await api.get<ReservationRecue[]>('/api/reservations/recues'))
    } catch {
      setReservations([])
    }
  }, [])

  const loadPaiements = useCallback(async () => {
    try {
      const data = await api.get<{ paiements: PaiementMine[]; stats: PaiementsStats }>(
        '/api/paiements/mine',
      )
      setPaiements(data.paiements || [])
      setStats(data.stats || null)
    } catch {
      setPaiements([])
    }
  }, [])

  useEffect(() => {
    loadColis()
    loadVoyages()
    loadPaiements()
  }, [loadColis, loadVoyages, loadPaiements])

  useEffect(() => {
    if (me?.is_transporteur) loadReservations()
  }, [me, loadReservations])

  const onLogout = async () => {
    await logout()
    navigate('/')
  }

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (searchColisId) navigate('/recherche?colis_id=' + encodeURIComponent(searchColisId))
  }

  const onSuivi = async (colisId: number, statut: string) => {
    if (!statut) return
    if (statut === 'Livré' && !window.confirm("Marquer comme livré ? L'agence devra confirmer.")) {
      loadReservations()
      return
    }
    try {
      const data = await api.post<{ message?: string }>('/api/suivi', { colis_id: colisId, statut })
      toast(data.message || 'Statut mis à jour.')
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Erreur inconnue', 'error')
    }
    loadReservations()
  }

  return (
    <div className="dashboard-originale">
      <div className="container py-3">
        {error && <div className="alert alert-danger">{error}</div>}

        {/* Déconnexion (comme l'original, en haut à droite) */}
        <div className="d-flex justify-content-end mb-3">
          <button type="button" onClick={onLogout} className="btn btn-danger btn-sm">
            <i className="fa-solid fa-right-from-bracket"></i> Déconnexion
          </button>
        </div>

        {/* Recherche d'un transporteur pour un colis */}
        <div className="card shadow-sm mb-4">
          <div className="card-body">
            <form onSubmit={onSearch} className="row g-2 align-items-end">
              <div className="col-md-8">
                <label htmlFor="colis_id" className="form-label fw-bold">
                  <i className="fa-solid fa-search"></i> Rechercher un transporteur :
                </label>
                <select
                  name="colis_id"
                  id="colis_id"
                  className="form-select"
                  required
                  value={searchColisId}
                  onChange={(e) => setSearchColisId(e.target.value)}
                >
                  <option value="">-- Sélectionnez un colis --</option>
                  {(colis || []).map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.nom_colis} ({c.pays})
                    </option>
                  ))}
                </select>
              </div>
              <div className="col-md-4">
                <button type="submit" className="btn btn-primary w-100">
                  <i className="fa-solid fa-truck"></i> Rechercher
                </button>
              </div>
            </form>
          </div>
        </div>

        {/* ========================= MES COLIS POSTÉS ========================= */}
        <h2 className="text-primary text-center mb-4">
          <i className="fa-solid fa-box"></i> Mes colis postés
        </h2>
        <div className="mb-5">
          <ResponsiveTable<ColisMine>
            columns={[
              { key: 'nom_colis', label: 'Nom' },
              { key: 'image_url', label: 'Image',
                render: (c) => <SmartImg url={c.image_url} alt={c.nom_colis} className="rounded" />,
                hideOnCard: true },
              { key: 'type_produit', label: 'Type',
                render: (c) => TYPE_LABELS[c.type_produit || ''] || c.type_produit || '—' },
              { key: 'poids', label: 'Poids',
                render: (c) => <>{c.poids} kg</>, primaryOnMobile: true },
              { key: 'destination', label: 'Destination',
                render: (c) => <>{c.ville}, {c.pays}</>, primaryOnMobile: true },
              { key: 'date_limite', label: 'Date limite', render: (c) => date(c.date_limite) },
              { key: 'statut', label: 'Statut',
                render: (c) => <BadgeColis statut={c.statut} />, primaryOnMobile: true },
            ]}
            data={colis || []}
            loading={!colis}
            rowKey={(c) => c.id}
            titleKey="nom_colis"
            subtitleKey="destination"
            emptyText={<>Aucun colis posté. <Link to="/poster-colis">Poster un colis</Link></>}
            className="table-bordered shadow-sm"
            actions={(c) => (
              <>
                <button type="button" className="btn btn-sm btn-info" onClick={() => setDetailsColis(c)}>
                  <i className="fas fa-info-circle"></i>
                  <span className="d-none d-md-inline"> Détails</span>
                </button>
                <Link to={`/reservation-colis?id=${c.id}`} className="btn btn-sm btn-primary">
                  <i className="fa-solid fa-eye"></i>
                  <span className="d-none d-md-inline"> Réservations</span>
                </Link>
              </>
            )}
          />
        </div>

        {/* ====================== MES VOYAGES PROPOSÉS ======================= */}
        <h2 className="text-primary text-center mb-4">
          <i className="fa-solid fa-truck"></i> Mes voyages proposés
        </h2>
        <div className="mb-5">
          <ResponsiveTable<VoyageMine>
            columns={[
              { key: 'pays_depart', label: 'Départ', primaryOnMobile: true },
              { key: 'pays_destination', label: 'Destination', primaryOnMobile: true },
              { key: 'date_depart', label: 'Date', render: (v) => date(v.date_depart) },
              { key: 'heure_depart', label: 'Heure',
                render: (v) => (v.heure_depart || '').substring(0, 5) || '—' },
              { key: 'poids_max', label: 'Poids max',
                render: (v) => <>{v.poids_max} kg</>, primaryOnMobile: true },
            ]}
            data={voyages || []}
            loading={!voyages}
            rowKey={(v) => v.id}
            titleKey="pays_depart"
            subtitleKey="pays_destination"
            emptyText={<>Aucun voyage proposé.{' '}<Link to="/devenir-transporteur">Proposer un voyage</Link></>}
            className="table-bordered shadow-sm"
            actions={(v) => (
              <button type="button" className="btn btn-sm btn-info" onClick={() => setDetailsVoyage(v)}>
                <i className="fas fa-info-circle"></i>
                <span className="d-none d-md-inline"> Détails</span>
              </button>
            )}
          />
        </div>

        {/* ==================== MES RÉSERVATIONS (TRANSPORTEUR) ==================== */}
        {me?.is_transporteur && (
          <>
            <h2 className="text-success text-center mb-4 mt-5">
              <i className="fa-solid fa-handshake"></i> Mes réservations
            </h2>
            <div className="mb-5">
              <ResponsiveTable<ReservationRecue>
                columns={[
                  { key: 'nom_colis', label: 'Colis',
                    render: (r) => (
                      <>
                        <Link to={`/colis/${r.colis_id}`}>{r.nom_colis}</Link>
                        <div className="small text-muted"><code>{r.numero_suivi}</code></div>
                      </>
                    ) },
                  { key: 'image_url', label: 'Image',
                    render: (r) => <SmartImg url={r.image_url} alt={r.nom_colis} className="rounded" />,
                    hideOnCard: true },
                  { key: 'poids', label: 'Poids',
                    render: (r) => <>{r.poids} kg</>, primaryOnMobile: true },
                  { key: 'destination', label: 'Destination',
                    render: (r) => <>{r.ville}, {r.pays}</>, primaryOnMobile: true },
                  { key: 'statut', label: 'Statut',
                    render: (r) => <BadgeReservation statut={r.statut} />,
                    primaryOnMobile: true },
                ]}
                data={reservations || []}
                loading={!reservations}
                rowKey={(r, i) => `${r.colis_id}-${i}`}
                titleKey="nom_colis"
                subtitleKey="destination"
                emptyText="Aucune réservation sur vos voyages."
                className="table-bordered shadow-sm"
                actions={(r) => (
                  r.statut_suivi !== 'Livré' ? (
                    <SuiviRow colisId={r.colis_id} initial={r.statut_suivi} onSubmit={onSuivi} />
                  ) : (
                    <span className="badge bg-success">Livré</span>
                  )
                )}
              />
            </div>
          </>
        )}

        {/* ========================== MES PAIEMENTS ========================== */}
        <h2 className="text-primary text-center mb-4">
          <i className="fas fa-money-bill-wave"></i> Mes paiements
        </h2>

        <div className="row mb-4">
          <div className="col-md-4 mb-3">
            <div className="card bg-primary text-white">
              <div className="card-body text-center">
                <h3>{money(stats?.montant_paye ?? 0)}</h3>
                <p className="mb-0">Total payé</p>
              </div>
            </div>
          </div>
          <div className="col-md-4 mb-3">
            <div className="card bg-success text-white">
              <div className="card-body text-center">
                <h3>{stats?.nb_payes ?? 0}</h3>
                <p className="mb-0">Paiements réussis</p>
              </div>
            </div>
          </div>
          <div className="col-md-4 mb-3">
            <div className="card bg-warning text-dark">
              <div className="card-body text-center">
                <h3>{stats?.nb_en_attente ?? 0}</h3>
                <p className="mb-0">En attente</p>
              </div>
            </div>
          </div>
        </div>

        <div className="mb-5">
          <ResponsiveTable<PaiementMine>
            columns={[
              { key: 'reference', label: 'Référence' },
              { key: 'nom_colis', label: 'Colis',
                render: (p) => p.nom_colis ? (
                  <>{p.nom_colis}<small className="text-muted d-block">ID: {p.colis_id}</small></>
                ) : 'N/A' },
              { key: 'montant', label: 'Montant',
                render: (p) => money(p.montant), primaryOnMobile: true },
              { key: 'methode_paiement', label: 'Méthode',
                render: (p) => p.methode_paiement || '—' },
              { key: 'operateur', label: 'Opérateur',
                render: (p) => (p.details_paiement && p.details_paiement.operateur) || '—' },
              { key: 'date_creation', label: 'Date', render: (p) => datetime(p.date_creation) },
              { key: 'statut', label: 'Statut',
                render: (p) => <BadgePaiement statut={p.statut} />, primaryOnMobile: true },
            ]}
            data={paiements || []}
            loading={!paiements}
            rowKey={(p) => p.id}
            titleKey="reference"
            subtitleKey="nom_colis"
            emptyText="Aucun paiement enregistré"
            className="table-bordered shadow-sm"
            actions={(p) => (
              <>
                <button type="button" className="btn btn-sm btn-info"
                  onClick={() => setDetailsPaiement(p)}>
                  <i className="fas fa-info-circle"></i>
                  <span className="d-none d-md-inline"> Détails</span>
                </button>
                {p.statut === 'en_attente' && (
                  <Link to={`/paiement?colis_id=${p.colis_id}`} className="btn btn-sm btn-success">
                    <i className="fas fa-money-bill-wave"></i>
                    <span className="d-none d-md-inline"> Payer</span>
                  </Link>
                )}
              </>
            )}
          />
        </div>

        {/* ============================= SOLDE =============================== */}
        <div className="card bg-info text-white">
          <div className="card-body text-center">
            <h3>{money(me?.solde ?? 0)}</h3>
            <p className="mb-0">Votre solde</p>
          </div>
        </div>
      </div>

      {/* ============================= MODALES ============================== */}
      {detailsColis && (
        <ColisDetailsModal
          c={detailsColis}
          onClose={() => setDetailsColis(null)}
          onSaved={(prix) => {
            setDetailsColis(null)
            toast('Colis mis à jour. Nouveau prix estimé : ' + money(prix))
            loadColis()
          }}
          toast={toast}
        />
      )}
      {detailsVoyage && (
        <VoyageDetailsModal v={detailsVoyage} onClose={() => setDetailsVoyage(null)} />
      )}
      {detailsPaiement && (
        <PaiementDetailsModal p={detailsPaiement} onClose={() => setDetailsPaiement(null)} />
      )}
    </div>
  )
}

/* ================== SUIVI (ligne « Mes réservations ») ==================== */

function SuiviRow({
  colisId,
  initial,
  onSubmit,
}: {
  colisId: number
  initial: string
  onSubmit: (colisId: number, statut: string) => void
}) {
  const [statut, setStatut] = useState(initial || 'En attente')
  return (
    <span className="d-inline-flex gap-1 align-items-center">
      <select
        name="statut"
        className="form-select form-select-sm d-inline-block"
        style={{ width: 'auto' }}
        value={statut}
        onChange={(e) => setStatut(e.target.value)}
      >
        <option value="En attente">En attente</option>
        <option value="En cours">En cours</option>
        <option value="Livré">Livré</option>
      </select>
      <button
        type="button"
        className="btn btn-sm btn-outline-success"
        onClick={() => onSubmit(colisId, statut)}
      >
        <i className="fas fa-sync-alt"></i>
      </button>
    </span>
  )
}

/* ==================== MODALE DÉTAILS / ÉDITION COLIS ====================== */

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

type ColisTab = 'details' | 'statut' | 'transporteurs'

function ColisDetailsModal({
  c,
  onClose,
  onSaved,
  toast,
}: {
  c: ColisMine
  onClose: () => void
  onSaved: (prixEstime: number) => void
  toast: (message: string, type?: 'success' | 'error' | 'info') => void
}) {
  const [fields, setFields] = useState<ColisEditFields | null>(null)
  const [prixEstime, setPrixEstime] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [tab, setTab] = useState<ColisTab>('details')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    ;(async () => {
      try {
        const d = await api.get<Record<string, unknown>>(`/api/colis/${c.id}`)
        setFields({
          nom_colis: String(d.nom_colis ?? ''),
          type_produit: String(d.type_produit ?? 'autre'),
          nombre_produits: String(d.nombre_produits ?? 1),
          poids: String(d.poids ?? ''),
          dimensions: String(d.dimensions ?? ''),
          date_limite: String(d.date_limite ?? '').substring(0, 10),
          pays: String(d.pays ?? ''),
          ville: String(d.ville ?? ''),
          adresse_depart: String(d.adresse_depart ?? ''),
          adresse_destination: String(d.adresse_destination ?? ''),
        })
        setPrixEstime(String(d.prix_estime ?? ''))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      } finally {
        setLoading(false)
      }
    })()
  }, [c.id])

  const set =
    (key: keyof ColisEditFields) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setFields((f) => (f ? { ...f, [key]: e.target.value } : f))

  const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0] || null
    setFile(f)
    if (f) {
      const reader = new FileReader()
      reader.onload = (ev) => setPreview(String(ev.target?.result || ''))
      reader.readAsDataURL(f)
    } else {
      setPreview(null)
    }
  }

  const copySuivi = async () => {
    try {
      await navigator.clipboard.writeText(c.numero_suivi)
      toast('Numéro de suivi copié !')
    } catch {
      toast('Erreur lors de la copie', 'error')
    }
  }

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!fields) return
    setError(null)
    try {
      const data = await api.upload<{ prix_estime: number }>(
        `/api/colis/${c.id}`,
        { ...fields },
        { image_colis: file },
      )
      onSaved(data.prix_estime)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  const hasTransporteurs = (c.reservations || []).length > 0

  return (
    <div
      className="modal fade show d-block"
      tabIndex={-1}
      style={{ backgroundColor: 'rgba(0,0,0,.5)' }}
    >
      <div className="modal-dialog modal-dialog-centered modal-lg">
        <div className="modal-content">
          <div className="modal-header bg-light">
            <h5 className="modal-title">Détails du colis: {c.nom_colis}</h5>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Close"></button>
          </div>
          <form onSubmit={onSubmit}>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {loading && <p className="text-muted text-center py-3">Chargement…</p>}
              {fields && (
                <div className="row">
                  {/* Colonne de gauche — image et suivi */}
                  <div className="col-md-4 mb-3 mb-md-0">
                    <div className="card border-0 shadow-sm h-100">
                      <div className="card-body text-center">
                        <SmartImg
                          url={preview || c.image_url}
                          alt={c.nom_colis}
                          className="img-fluid rounded mb-3 preview-image"
                        />

                        {/* Numéro de suivi */}
                        <div className="tracking-info bg-light p-3 rounded border">
                          <h6 className="text-muted mb-2">
                            <i className="fas fa-barcode"></i> Numéro de suivi
                          </h6>
                          <div className="d-flex justify-content-between align-items-center">
                            <code className="text-primary fs-6">{c.numero_suivi}</code>
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-primary copy-btn"
                              title="Copier dans le presse-papier"
                              onClick={copySuivi}
                            >
                              <i className="fas fa-copy"></i>
                            </button>
                          </div>
                        </div>

                        {/* Upload image */}
                        <div className="mt-3">
                          <label htmlFor="image_colis" className="form-label small text-muted">
                            Changer l'image
                          </label>
                          <input
                            type="file"
                            className="form-control form-control-sm"
                            id="image_colis"
                            name="image_colis"
                            accept="image/*"
                            onChange={onFile}
                          />
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Colonne de droite — formulaire à onglets */}
                  <div className="col-md-8">
                    <div className="card border-0 shadow-sm h-100">
                      <div className="card-body">
                        <ul className="nav nav-tabs mb-4" role="tablist">
                          <li className="nav-item" role="presentation">
                            <button
                              type="button"
                              className={'nav-link' + (tab === 'details' ? ' active' : '')}
                              onClick={() => setTab('details')}
                            >
                              <i className="fas fa-info-circle me-1"></i> Détails
                            </button>
                          </li>
                          <li className="nav-item" role="presentation">
                            <button
                              type="button"
                              className={'nav-link' + (tab === 'statut' ? ' active' : '')}
                              onClick={() => setTab('statut')}
                            >
                              <i className="fas fa-truck me-1"></i> Statut
                            </button>
                          </li>
                          {hasTransporteurs && (
                            <li className="nav-item" role="presentation">
                              <button
                                type="button"
                                className={'nav-link' + (tab === 'transporteurs' ? ' active' : '')}
                                onClick={() => setTab('transporteurs')}
                              >
                                <i className="fas fa-users me-1"></i> Transporteurs
                              </button>
                            </li>
                          )}
                        </ul>

                        <div className="tab-content">
                          {/* Onglet Détails */}
                          {tab === 'details' && (
                            <div className="tab-pane fade show active" role="tabpanel">
                              <div className="row g-3">
                                <div className="col-md-6">
                                  <label htmlFor="nom_colis" className="form-label">
                                    Nom du colis*
                                  </label>
                                  <input
                                    type="text"
                                    className="form-control"
                                    id="nom_colis"
                                    name="nom_colis"
                                    maxLength={100}
                                    value={fields.nom_colis}
                                    onChange={set('nom_colis')}
                                    required
                                  />
                                </div>
                                <div className="col-md-6">
                                  <label htmlFor="type_produit" className="form-label">
                                    Type de produit*
                                  </label>
                                  <select
                                    className="form-select"
                                    id="type_produit"
                                    name="type_produit"
                                    value={fields.type_produit}
                                    onChange={set('type_produit')}
                                    required
                                  >
                                    <option value="documents">Documents</option>
                                    <option value="vetements">Vêtements</option>
                                    <option value="electronique">Électronique</option>
                                    <option value="alimentaire">Alimentaire</option>
                                    <option value="autre">Autre</option>
                                  </select>
                                </div>
                                <div className="col-md-4">
                                  <label htmlFor="nombre_produits" className="form-label">
                                    Quantité*
                                  </label>
                                  <input
                                    type="number"
                                    min={1}
                                    className="form-control"
                                    id="nombre_produits"
                                    name="nombre_produits"
                                    value={fields.nombre_produits}
                                    onChange={set('nombre_produits')}
                                    required
                                  />
                                </div>
                                <div className="col-md-4">
                                  <label htmlFor="poids" className="form-label">
                                    Poids (kg)*
                                  </label>
                                  <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    className="form-control"
                                    id="poids"
                                    name="poids"
                                    value={fields.poids}
                                    onChange={set('poids')}
                                    required
                                  />
                                </div>
                                <div className="col-md-4">
                                  <label htmlFor="prix_estime" className="form-label">
                                    Prix estimé (F CFA)
                                  </label>
                                  <input
                                    type="number"
                                    step="0.01"
                                    className="form-control"
                                    id="prix_estime"
                                    name="prix_estime"
                                    value={prixEstime}
                                    readOnly
                                  />
                                </div>
                                <div className="col-md-6">
                                  <label htmlFor="pays" className="form-label">
                                    Pays de destination*
                                  </label>
                                  <input
                                    type="text"
                                    className="form-control"
                                    id="pays"
                                    name="pays"
                                    value={fields.pays}
                                    onChange={set('pays')}
                                    required
                                  />
                                </div>
                                <div className="col-md-6">
                                  <label htmlFor="ville" className="form-label">
                                    Ville de destination*
                                  </label>
                                  <input
                                    type="text"
                                    className="form-control"
                                    id="ville"
                                    name="ville"
                                    value={fields.ville}
                                    onChange={set('ville')}
                                    required
                                  />
                                </div>
                                <div className="col-md-6">
                                  <label htmlFor="date_limite" className="form-label">
                                    Date limite*
                                  </label>
                                  <input
                                    type="date"
                                    className="form-control"
                                    id="date_limite"
                                    name="date_limite"
                                    value={fields.date_limite}
                                    onChange={set('date_limite')}
                                    required
                                  />
                                </div>
                                <div className="col-12">
                                  <label htmlFor="adresse_depart" className="form-label">
                                    Adresse de départ*
                                  </label>
                                  <input
                                    type="text"
                                    className="form-control"
                                    id="adresse_depart"
                                    name="adresse_depart"
                                    value={fields.adresse_depart}
                                    onChange={set('adresse_depart')}
                                    required
                                  />
                                </div>
                                <div className="col-12">
                                  <label htmlFor="adresse_destination" className="form-label">
                                    Adresse de destination*
                                  </label>
                                  <input
                                    type="text"
                                    className="form-control"
                                    id="adresse_destination"
                                    name="adresse_destination"
                                    value={fields.adresse_destination}
                                    onChange={set('adresse_destination')}
                                    required
                                  />
                                </div>
                              </div>
                            </div>
                          )}

                          {/* Onglet Statut */}
                          {tab === 'statut' && (
                            <div className="tab-pane fade show active" role="tabpanel">
                              <div className="status-section mb-4">
                                <h5 className="d-flex align-items-center mb-3">
                                  <i className="fas fa-info-circle text-primary me-2"></i>
                                  <span>Statut du colis</span>
                                </h5>
                                <div className="d-flex align-items-center p-3 bg-light rounded border-start border-primary border-4">
                                  {c.statut === 'en_attente' && (
                                    <>
                                      <span className="badge bg-warning text-dark me-3">
                                        En attente
                                      </span>
                                      <p className="mb-0">
                                        En attente d'approbation par l'administrateur
                                      </p>
                                    </>
                                  )}
                                  {c.statut === 'approuve' && (
                                    <>
                                      <span className="badge bg-success me-3">Approuvé</span>
                                      <p className="mb-0">
                                        Votre colis a été approuvé et est visible par les
                                        transporteurs
                                      </p>
                                    </>
                                  )}
                                  {c.statut === 'refuse' && (
                                    <>
                                      <span className="badge bg-danger me-3">Refusé</span>
                                      <p className="mb-0">Votre colis a été refusé</p>
                                    </>
                                  )}
                                </div>
                              </div>

                              {c.statut === 'approuve' && (
                                <div className="status-section">
                                  <h5 className="d-flex align-items-center mb-3">
                                    <i className="fas fa-truck text-primary me-2"></i>
                                    <span>Statut de livraison</span>
                                  </h5>
                                  <div className="d-flex align-items-center p-3 bg-light rounded border-start border-primary border-4">
                                    {(!c.statut_livraison || c.statut_livraison === 'En attente') && (
                                      <>
                                        <span className="badge bg-secondary me-3">En attente</span>
                                        <p className="mb-0">
                                          En attente de prise en charge par un transporteur
                                        </p>
                                      </>
                                    )}
                                    {c.statut_livraison === 'En cours' && (
                                      <>
                                        <span className="badge bg-info me-3">En cours</span>
                                        <p className="mb-0">Votre colis est en cours de livraison</p>
                                      </>
                                    )}
                                    {c.statut_livraison === 'Livré' &&
                                      (c.suivi_confirme_par_admin ? (
                                        <>
                                          <span className="badge bg-success me-3">Livré</span>
                                          <p className="mb-0">
                                            Votre colis a été livré avec succès
                                          </p>
                                        </>
                                      ) : (
                                        <>
                                          <span className="badge bg-warning me-3">
                                            En attente de confirmation
                                          </span>
                                          <p className="mb-0">
                                            Le transporteur a marqué le colis comme « Livré », en
                                            attente de confirmation par l'admin
                                          </p>
                                        </>
                                      ))}
                                  </div>
                                </div>
                              )}
                            </div>
                          )}

                          {/* Onglet Transporteurs */}
                          {tab === 'transporteurs' && hasTransporteurs && (
                            <div className="tab-pane fade show active" role="tabpanel">
                              {c.reservations.map((r) => (
                                <TransporteurCard
                                  key={r.transporteur_id}
                                  res={r}
                                  colisId={c.id}
                                />
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>
                Fermer
              </button>
              <button type="submit" className="btn btn-primary" disabled={!fields}>
                Enregistrer les modifications
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

/* =============== CARTE TRANSPORTEUR (onglet Transporteurs) ================ */

function TransporteurCard({ res, colisId }: { res: ReservationAffectee; colisId: number }) {
  const [data, setData] = useState<TransporteurData | null>(null)
  const [avis, setAvis] = useState<Avis[] | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        const [d, a] = await Promise.all([
          api.get<TransporteurData>(`/api/transporteurs/${res.transporteur_id}`),
          api.get<Avis[]>(`/api/transporteurs/${res.transporteur_id}/avis`),
        ])
        setData(d)
        setAvis(a)
      } catch {
        setData(null)
        setAvis([])
      }
    })()
  }, [res.transporteur_id])

  const note = data?.stats?.note_moyenne ?? res.note_moyenne ?? null
  const nbAvis = data?.stats?.nb_avis ?? 0
  const nbColis = data?.stats?.nb_colis_transportes ?? 0
  const derniers = (avis || []).slice(0, 3)

  return (
    <div className="card mb-3">
      <div className="card-body">
        <div className="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 className="card-title mb-1">
              {res.prenom} {res.nom}
            </h5>
            {res.email && <small className="text-muted d-block">{res.email}</small>}
            {res.telephone && <small className="text-muted">Tél: {res.telephone}</small>}
          </div>
          <div className="d-flex gap-2">
            <Link
              to={`/messagerie?destinataire_id=${res.transporteur_id}&colis_id=${colisId}`}
              className="btn btn-sm btn-primary"
            >
              <i className="fas fa-envelope"></i> Contacter
            </Link>
            <Link
              to={`/profil-transporteur?id=${res.transporteur_id}`}
              className="btn btn-sm btn-info"
            >
              <i className="fas fa-user"></i> Profil
            </Link>
          </div>
        </div>

        {/* Statistiques du transporteur */}
        <div className="row mb-3">
          <div className="col-md-4">
            <div className="d-flex align-items-center">
              <div className="me-3">
                <span className="display-6 fw-bold">{Number(note || 0).toFixed(1)}</span>
                <span className="text-muted">/5</span>
              </div>
              <div>
                <Stars note={note} />
                <small className="text-muted">{nbAvis} avis</small>
              </div>
            </div>
          </div>
          <div className="col-md-4">
            <div className="d-flex align-items-center h-100">
              <i className="fas fa-box-open fa-2x text-primary me-3"></i>
              <div>
                <div className="fw-bold">{nbColis}</div>
                <small className="text-muted">Colis transportés</small>
              </div>
            </div>
          </div>
          <div className="col-md-4">
            <div className="d-flex align-items-center h-100">
              <i className="fas fa-check-circle fa-2x text-success me-3"></i>
              <div>
                <div className="fw-bold">100%</div>
                <small className="text-muted">Livraisons réussies</small>
              </div>
            </div>
          </div>
        </div>

        {/* Avis récents */}
        {derniers.length > 0 ? (
          <>
            <h6 className="mt-3 mb-2">Avis récents :</h6>
            <div className="border-top pt-2">
              {derniers.map((a, i) => (
                <div key={i} className="mb-3 pb-2 border-bottom">
                  <div className="d-flex justify-content-between">
                    <div className="fw-bold">
                      {a.prenom} {a.nom}
                    </div>
                    <Stars note={a.note} />
                  </div>
                  <div className="small text-muted">{date(a.date_avis)}</div>
                  <div className="mt-1">{a.commentaire}</div>
                </div>
              ))}
              <Link
                to={`/profil-transporteur?id=${res.transporteur_id}#avis`}
                className="btn btn-sm btn-outline-primary"
              >
                Voir tous les avis
              </Link>
            </div>
          </>
        ) : (
          <div className="alert alert-info mb-0">Ce transporteur n'a pas encore reçu d'avis.</div>
        )}
      </div>
    </div>
  )
}

/* ======================= MODALE DÉTAILS VOYAGE ============================ */

function VoyageDetailsModal({ v, onClose }: { v: VoyageMine; onClose: () => void }) {
  return (
    <div
      className="modal fade show d-block"
      tabIndex={-1}
      style={{ backgroundColor: 'rgba(0,0,0,.5)' }}
    >
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Détails du voyage</h5>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Close"></button>
          </div>
          <div className="modal-body">
            <ul className="list-group list-group-flush">
              <li className="list-group-item">
                <strong>De:</strong> {v.pays_depart}
              </li>
              <li className="list-group-item">
                <strong>À:</strong> {v.pays_destination}
              </li>
              <li className="list-group-item">
                <strong>Date:</strong> {date(v.date_depart)}
              </li>
              <li className="list-group-item">
                <strong>Heure:</strong> {(v.heure_depart || '').substring(0, 5) || '—'}
              </li>
              <li className="list-group-item">
                <strong>Poids max:</strong> {v.poids_max} kg
              </li>
              <li className="list-group-item">
                <strong>Contact:</strong> {v.telephone || '—'}
              </li>
            </ul>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Fermer
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

/* ====================== MODALE DÉTAILS PAIEMENT =========================== */

function PaiementDetailsModal({ p, onClose }: { p: PaiementMine; onClose: () => void }) {
  const details = p.details_paiement
    ? Object.entries(p.details_paiement).filter(([, v]) => v !== undefined && v !== null && v !== '')
    : []

  return (
    <div
      className="modal fade show d-block"
      tabIndex={-1}
      style={{ backgroundColor: 'rgba(0,0,0,.5)' }}
    >
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Détails du paiement #{p.reference}</h5>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Close"></button>
          </div>
          <div className="modal-body">
            <ul className="list-group list-group-flush">
              <li className="list-group-item">
                <strong>Référence:</strong> {p.reference}
              </li>
              <li className="list-group-item">
                <strong>Montant:</strong> {money(p.montant)}
              </li>
              <li className="list-group-item">
                <strong>Méthode:</strong> {p.methode_paiement || '—'}
              </li>
              <li className="list-group-item">
                <strong>Opérateur:</strong> {(p.details_paiement && p.details_paiement.operateur) || 'N/A'}
              </li>
              <li className="list-group-item">
                <strong>Numéro transaction:</strong> {p.numero_transaction || 'N/A'}
              </li>
              <li className="list-group-item">
                <strong>Date création:</strong> {datetime(p.date_creation)}
              </li>
              <li className="list-group-item">
                <strong>Statut:</strong> <BadgePaiement statut={p.statut} />
              </li>
              {details.length > 0 && (
                <li className="list-group-item">
                  <strong>Détails:</strong>
                  <div className="mt-2 p-2 bg-light rounded">
                    <ul className="list-unstyled mb-0">
                      {details.map(([key, value]) => (
                        <li key={key}>
                          <strong>
                            {key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ')}:
                          </strong>{' '}
                          {String(value)}
                        </li>
                      ))}
                    </ul>
                  </div>
                </li>
              )}
            </ul>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Fermer
            </button>
            {p.statut === 'en_attente' && (
              <Link to={`/paiement?colis_id=${p.colis_id}`} className="btn btn-success">
                <i className="fas fa-money-bill-wave"></i> Payer maintenant
              </Link>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
