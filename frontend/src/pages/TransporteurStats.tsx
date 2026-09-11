import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { money } from '../lib/format'
import { useAuth } from '../context/AuthContext'
import ResponsiveTable, { type Column } from '../components/ResponsiveTable'

/** Statistiques transporteur — 95% transporteur / 5% admin + retraits auto */

interface Stats {
  solde: string | number
  nb_voyages: number
  nb_reservations_acceptees: number
  note_moyenne: number | null
  nb_avis: number
}

interface SoldeDetail {
  solde: number
  total_paye: number
  en_attente: number
}

interface Retrait {
  id: number
  montant: number
  statut: string
  methode: string
  numero: string
  reference: string
  details: string
  colis_id: number | null
  date_demande: string
  date_traitement: string | null
}

function StatCard({
  icon,
  label,
  value,
  color = 'primary',
}: {
  icon: string
  label: string
  value: React.ReactNode
  color?: string
}) {
  return (
    <div className="col-md-3 col-6">
      <div className="page-card text-center h-100">
        <i className={`fa-solid ${icon} text-${color}`} style={{ fontSize: '2rem' }}></i>
        <div className="fs-4 fw-bold mt-2">{value}</div>
        <div className="text-muted small">{label}</div>
      </div>
    </div>
  )
}

export default function TransporteurStats() {
  const { me } = useAuth()
  const [s, setS] = useState<Stats | null>(null)
  const [soldeDetail, setSoldeDetail] = useState<SoldeDetail | null>(null)
  const [retraits, setRetraits] = useState<Retrait[]>([])
  const [error, setError] = useState<string | null>(null)
  const [retraitForm, setRetraitForm] = useState({ montant: '', numero: '' })
  const [retraitMsg, setRetraitMsg] = useState<string | null>(null)

  const load = async () => {
    try {
      const [stats, solde, ret] = await Promise.all([
        api.get<Stats>('/api/transporteur-stats'),
        api.get<SoldeDetail>('/api/transporteur/solde'),
        api.get<Retrait[]>('/api/transporteur/retraits'),
      ])
      setS(stats)
      setSoldeDetail(solde)
      setRetraits(ret)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }

  useEffect(() => {
    if (!me?.is_transporteur) return
    load()
  }, [me])

  const onDemandeRetrait = async (e: React.FormEvent) => {
    e.preventDefault()
    setRetraitMsg(null)
    try {
      const res = await api.post<{ message: string }>('/api/transporteur/retraits', {
        montant: parseFloat(retraitForm.montant),
        numero: retraitForm.numero,
        methode: 'mobile_money',
      })
      setRetraitMsg(res.message)
      setRetraitForm({ montant: '', numero: '' })
      load()
    } catch (err) {
      setRetraitMsg(err instanceof Error ? err.message : 'Erreur')
    }
  }

  if (me && !me.is_transporteur)
    return (
      <>
        <h2 className="mb-4">
          <i className="fa-solid fa-chart-line text-primary"></i> Mes statistiques transporteur
        </h2>
        <div className="alert alert-info">
          Vous n'êtes pas encore transporteur.{' '}
          <Link to="/devenir-transporteur" className="alert-link">
            Proposer un voyage
          </Link>
        </div>
      </>
    )

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-chart-line text-primary"></i> Mes statistiques transporteur
      </h2>

      {error && <div className="alert alert-danger">{error}</div>}
      {!s && !error && <div className="text-center text-muted py-4">Chargement…</div>}

      {s && (
        <>
          <div className="row g-3">
            <StatCard icon="fa-wallet" label="Solde disponible (95%)" value={money(soldeDetail?.solde ?? s.solde)} color="success" />
            <StatCard icon="fa-plane" label="Voyages proposés" value={s.nb_voyages} />
            <StatCard icon="fa-handshake" label="Réservations acceptées" value={s.nb_reservations_acceptees} color="info" />
            <StatCard
              icon="fa-star"
              label="Note moyenne"
              value={s.note_moyenne !== null ? `${s.note_moyenne}/5 (${s.nb_avis} avis)` : '—'}
              color="warning"
            />
          </div>

          <div className="row g-3 mt-1">
            <div className="col-md-4">
              <div className="page-card">
                <h6><i className="fa-solid fa-money-bill-transfer text-success"></i> Total déjà payé</h6>
                <div className="fs-5 fw-bold text-success">{money(soldeDetail?.total_paye ?? 0)}</div>
                <small className="text-muted">Paiements automatiques après livraison confirmée</small>
              </div>
            </div>
            <div className="col-md-4">
              <div className="page-card">
                <h6><i className="fa-solid fa-clock text-warning"></i> En attente</h6>
                <div className="fs-5 fw-bold text-warning">{money(soldeDetail?.en_attente ?? 0)}</div>
                <small className="text-muted">Retraits en attente admin</small>
              </div>
            </div>
            <div className="col-md-4">
              <div className="page-card">
                <h6><i className="fa-solid fa-percent text-primary"></i> Répartition</h6>
                <div className="small">Transporteur <strong>95%</strong> du prix estimé</div>
                <div className="small">Plateforme <strong>5%</strong></div>
                <small className="text-muted">Après vérification client, envoi auto sur votre numéro</small>
              </div>
            </div>
          </div>

          <div className="page-card mt-4">
            <h5><i className="fa-solid fa-hand-holding-dollar"></i> Demander un retrait manuel</h5>
            <p className="small text-muted">Si un solde reste (ex: numéro manquant lors du paiement auto), vous pouvez demander un retrait. L'admin validera et enverra sur votre numéro.</p>
            <form onSubmit={onDemandeRetrait} className="row g-2 align-items-end">
              <div className="col-md-3">
                <label className="form-label">Montant (XOF)</label>
                <input type="number" className="form-control" min={1000} step={100} value={retraitForm.montant} onChange={e=>setRetraitForm({...retraitForm, montant:e.target.value})} required />
              </div>
              <div className="col-md-4">
                <label className="form-label">Numéro Mobile Money</label>
                <input type="text" className="form-control" placeholder="22997000000" value={retraitForm.numero} onChange={e=>setRetraitForm({...retraitForm, numero:e.target.value})} />
                <small className="text-muted">Laissez vide pour utiliser votre tel profil</small>
              </div>
              <div className="col-md-3">
                <button className="btn btn-primary w-100" type="submit"><i className="fa-solid fa-paper-plane"></i> Demander</button>
              </div>
            </form>
            {retraitMsg && <div className="alert alert-info mt-2 py-2">{retraitMsg}</div>}
          </div>

          <div className="page-card mt-4">
            <h5><i className="fa-solid fa-list"></i> Historique retraits / paiements automatiques</h5>
            {retraits.length===0 ? <p className="text-muted">Aucun retrait</p> : (
              <ResponsiveTable<Retrait>
                columns={[
                  { key: 'date_demande', label: 'Date',
                    render: (r) => <span className="small">{new Date(r.date_demande).toLocaleString('fr-FR')}</span> },
                  { key: 'montant', label: 'Montant',
                    render: (r) => <span className="fw-bold">{money(r.montant)}</span>, primaryOnMobile: true },
                  { key: 'statut', label: 'Statut',
                    render: (r) => (
                      <span className={`badge ${r.statut==='paye'?'bg-success':r.statut==='en_attente'?'bg-warning text-dark':r.statut==='echec'?'bg-danger':'bg-secondary'}`}>{r.statut}</span>
                    ), primaryOnMobile: true },
                  { key: 'numero', label: 'Numéro', render: (r) => <span className="small">{r.numero||'—'}</span> },
                  { key: 'reference', label: 'Référence', render: (r) => <span className="small">{r.reference}</span> },
                  { key: 'colis_id', label: 'Colis', render: (r) => <span className="small">{r.colis_id ? `#${r.colis_id}` : '—'}</span> },
                ]}
                data={retraits}
                rowKey={(r) => r.id}
                titleKey="reference"
                subtitleKey="montant"
                emptyText="Aucun retrait"
                className="table-sm"
              />
            )}
          </div>

          <p className="text-muted small mt-3">
            <i className="fa-solid fa-circle-info"></i> Le solde est crédité de <strong>95% du prix estimé</strong> de chaque colis dont la livraison est confirmée par l'agence (après vérification client). 5% reste pour la plateforme. Paiement automatique via Kkiapay Mobile Money sur le numéro enregistré.
          </p>
        </>
      )}
    </>
  )
}
