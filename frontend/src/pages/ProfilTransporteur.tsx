import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date } from '../lib/format'
import { useAuth } from '../context/AuthContext'
import ResponsiveTable, { type Column } from '../components/ResponsiveTable'

/** Fiche publique d'un transporteur — port de profil-transporteur.html (?id=). */

interface TransporteurUser {
  id: number
  nom: string
  prenom: string
  photo_url: string | null
  date_inscription: string
}

interface TransporteurFiche {
  vehicule: string | null
  compagnie: string | null
  ville: string | null
  pays: string | null
  photo_vehicule_url: string | null
}

interface TransporteurStats {
  note_moyenne: number | null
  nb_avis: number
  nb_colis_transportes: number
}

interface TransporteurVoyage {
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
  poids_max: string | number
}

interface TransporteurData {
  user: TransporteurUser
  transporteur: TransporteurFiche
  stats: TransporteurStats
  voyages: TransporteurVoyage[]
}

interface Avis {
  prenom: string
  nom: string
  note: number
  commentaire: string
  date_avis: string
}

function stars(note: number | null) {
  const n = Math.round(Number(note || 0))
  return (
    <>
      {[1, 2, 3, 4, 5].map((i) => (
        <i
          key={i}
          className={i <= n ? 'fa-solid fa-star text-warning' : 'fa-solid fa-star text-muted opacity-25'}
        />
      ))}
    </>
  )
}

export default function ProfilTransporteur() {
  const [params] = useSearchParams()
  const { me } = useAuth()
  const id = params.get('id') || (me?.is_transporteur ? String(me.id) : null)

  const [data, setData] = useState<TransporteurData | null>(null)
  const [avis, setAvis] = useState<Avis[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [avisAlert, setAvisAlert] = useState<{ type: 'danger' | 'success'; msg: string } | null>(null)
  const [note, setNote] = useState('')
  const [commentaire, setCommentaire] = useState('')

  useEffect(() => {
    if (!id) return
    ;(async () => {
      try {
        const [d, a] = await Promise.all([
          api.get<TransporteurData>('/api/transporteurs/' + encodeURIComponent(id)),
          api.get<Avis[]>('/api/transporteurs/' + encodeURIComponent(id) + '/avis'),
        ])
        setData(d)
        setAvis(a)
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [id])

  const publierAvis = async (e: React.FormEvent) => {
    e.preventDefault()
    setAvisAlert(null)
    try {
      const res = await api.post<{ message: string }>('/api/avis', {
        transporteur_id: Number(id),
        note: Number(note),
        commentaire,
      })
      setAvisAlert({ type: 'success', msg: res.message })
      setNote('')
      setCommentaire('')
    } catch (err) {
      setAvisAlert({ type: 'danger', msg: err instanceof ApiError ? err.message : 'Erreur inconnue' })
    }
  }

  if (!id) return <div className="alert alert-danger">Identifiant manquant.</div>
  if (error) return <div className="alert alert-danger">{error}</div>
  if (!data || !avis || !me) return <div className="text-center text-muted py-5">Chargement…</div>

  const u = data.user
  const t = data.transporteur
  const s = data.stats
  const isSelf = me.id === u.id

  return (
    <div className="row g-4" style={{ maxWidth: 1000 }}>
      <div className="col-md-4">
        <div className="page-card text-center">
          {u.photo_url ? (
            <img src={u.photo_url} className="avatar-lg" alt="" />
          ) : (
            <i className="fa-solid fa-user avatar-lg text-muted"></i>
          )}
          <h5 className="mt-3">
            {u.prenom} {u.nom}
          </h5>
          <div className="mb-2">
            {stars(s.note_moyenne)}
            <span className="small text-muted d-block">
              {s.note_moyenne !== null ? s.note_moyenne + '/5' : 'Pas encore de note'} · {s.nb_avis}{' '}
              avis
            </span>
          </div>
          <p className="text-muted small mb-2">
            <i className="fa-solid fa-box"></i> {s.nb_colis_transportes} colis transportés
            <br />
            Membre depuis {date(u.date_inscription)}
          </p>
          {!isSelf && (
            <Link
              to={'/messagerie?destinataire_id=' + u.id}
              className="btn btn-outline-primary btn-sm w-100"
            >
              <i className="fa-solid fa-envelope"></i> Contacter
            </Link>
          )}
        </div>
      </div>
      <div className="col-md-8">
        <div className="page-card">
          <h5 className="mb-3">
            <i className="fa-solid fa-truck text-primary"></i> Informations
          </h5>
          <table className="table table-sm">
            <tbody>
              <tr>
                <th style={{ width: '40%' }}>Véhicule</th>
                <td>{t.vehicule || '—'}</td>
              </tr>
              <tr>
                <th>Compagnie</th>
                <td>{t.compagnie || '—'}</td>
              </tr>
              <tr>
                <th>Zone</th>
                <td>
                  {t.ville || ''} {t.pays || ''}
                </td>
              </tr>
            </tbody>
          </table>
          {t.photo_vehicule_url && (
            <img
              src={t.photo_vehicule_url}
              className="img-fluid rounded"
              alt="Véhicule"
              style={{ maxHeight: 220 }}
            />
          )}
        </div>

        <div className="page-card">
          <h5 className="mb-3">
            <i className="fa-solid fa-plane text-primary"></i> Prochains voyages
          </h5>
          {data.voyages.length ? (
            <ResponsiveTable<TransporteurVoyage>
              columns={[
                { key: 'trajet', label: 'Trajet',
                  render: (v) => <>{v.pays_depart} → {v.pays_destination}</> },
                { key: 'date_depart', label: 'Départ',
                  render: (v) => <>
                    {date(v.date_depart)}{' '}
                    <span className="small text-muted">{(v.heure_depart || '').substring(0, 5)}</span>
                  </>, primaryOnMobile: true },
                { key: 'poids_max', label: 'Poids max',
                  render: (v) => <>{v.poids_max} kg</>, primaryOnMobile: true },
              ]}
              data={data.voyages}
              rowKey={(_, i) => i}
              titleKey="trajet"
              subtitleKey="date_depart"
              emptyText="Aucun voyage à venir."
              className="table-sm align-middle"
            />
          ) : (
            <p className="text-muted mb-0">Aucun voyage à venir.</p>
          )}
        </div>

        <div className="page-card">
          <h5 className="mb-3">
            <i className="fa-solid fa-star text-warning"></i> Avis ({avis.length})
          </h5>
          {!isSelf && (
            <form className="border rounded-3 p-3 mb-4" onSubmit={publierAvis}>
              {avisAlert && <div className={`alert alert-${avisAlert.type}`}>{avisAlert.msg}</div>}
              <div className="mb-2">
                <label className="form-label">Votre note *</label>
                <select
                  className="form-select"
                  style={{ maxWidth: 140 }}
                  required
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                >
                  <option value="">—</option>
                  <option value="5">5 - Excellent</option>
                  <option value="4">4 - Très bien</option>
                  <option value="3">3 - Bien</option>
                  <option value="2">2 - Moyen</option>
                  <option value="1">1 - Mauvais</option>
                </select>
              </div>
              <div className="mb-2">
                <label className="form-label">Commentaire *</label>
                <textarea
                  className="form-control"
                  rows={2}
                  maxLength={2000}
                  required
                  value={commentaire}
                  onChange={(e) => setCommentaire(e.target.value)}
                />
              </div>
              <button type="submit" className="btn btn-warning fw-bold">
                <i className="fa-solid fa-star"></i> Publier un avis
              </button>
              <span className="small text-muted ms-2">
                Un seul avis par transporteur, publié après modération.
              </span>
            </form>
          )}
          <div>
            {avis.length ? (
              avis.map((a, i) => (
                <div key={i} className="border-bottom py-3">
                  <div className="d-flex justify-content-between">
                    <strong>
                      {a.prenom} {a.nom}
                    </strong>
                    <span>{stars(a.note)}</span>
                  </div>
                  <p className="mb-1 mt-1" style={{ whiteSpace: 'pre-line' }}>
                    {a.commentaire}
                  </p>
                  <small className="text-muted">{date(a.date_avis)}</small>
                </div>
              ))
            ) : (
              <p className="text-muted mb-0">Aucun avis publié pour le moment.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
