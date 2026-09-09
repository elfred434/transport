import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, datetime, money, SmartImg, StatusBadge } from '../lib/format'
import { useAuth } from '../context/AuthContext'

/** Détail d'un colis — port de colis-detail.html (?id= → /colis/:id). */

interface EtapeSuivi {
  statut: string
  date_etape: string
}

interface ColisDetailData {
  id: number
  user_id: number | null
  nom_colis: string
  image_url: string | null
  statut: string
  prix_estime: string | number
  numero_suivi: string
  type_produit: string | null
  nombre_produits: number | null
  poids: string | number
  dimensions: string | null
  ville: string
  pays: string
  adresse_depart: string | null
  adresse_destination: string | null
  date_limite: string
  date_post: string
  nom: string | null
  prenom: string | null
  owner_email?: string | null
  owner_tel?: string | null
  etapes_suivi: EtapeSuivi[]
}

export default function ColisDetail() {
  const { id } = useParams<{ id: string }>()
  const { me } = useAuth()
  const [c, setC] = useState<ColisDetailData | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!id) return
    ;(async () => {
      try {
        setC(await api.get<ColisDetailData>('/api/colis/' + encodeURIComponent(id)))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [id])

  if (!id) return <div className="alert alert-danger">Identifiant de colis manquant.</div>
  if (error)
    return (
      <>
        <div className="alert alert-danger">{error}</div>
        <Link to="/dashboard" className="btn btn-outline-primary">
          Retour au tableau de bord
        </Link>
      </>
    )
  if (!c || !me) return <div className="text-center text-muted py-5">Chargement…</div>

  const isOwner = me.id === c.user_id || me.role === 'admin'

  return (
    <div className="row g-4" style={{ maxWidth: 1000 }}>
      <div className="col-md-5">
        <div className="page-card text-center">
          <SmartImg url={c.image_url} alt={c.nom_colis} className="img-fluid rounded" />
          <h4 className="mt-3">{c.nom_colis}</h4>
          <div>
            <StatusBadge statut={c.statut} />
          </div>
          <div className="fw-bold text-primary fs-4 mt-2">{money(c.prix_estime)}</div>
          <p className="text-muted small mb-3">
            Suivi : <code>{c.numero_suivi}</code>
          </p>
          <div className="d-grid gap-2">
            <Link
              to={'/suivi?numero_suivi=' + encodeURIComponent(c.numero_suivi)}
              className="btn btn-outline-info"
            >
              <i className="fa-solid fa-search-location"></i> Suivre la livraison
            </Link>
            {c.statut === 'approuve' && me.is_transporteur && !isOwner && (
              <Link to={`/reservation-colis?colis_id=${c.id}`} className="btn btn-success">
                <i className="fa-solid fa-handshake"></i> Réserver ce colis
              </Link>
            )}
            {isOwner && c.statut === 'en_attente' && (
              <Link to={`/paiement?colis_id=${c.id}`} className="btn btn-success">
                <i className="fa-solid fa-credit-card"></i> Payer
              </Link>
            )}
            {isOwner && (
              <Link to="/dashboard" className="btn btn-outline-primary">
                <i className="fa-solid fa-list"></i> Gérer les réservations
              </Link>
            )}
          </div>
        </div>
      </div>
      <div className="col-md-7">
        <div className="page-card">
          <h5 className="mb-3">
            <i className="fa-solid fa-circle-info text-primary"></i> Informations
          </h5>
          <table className="table table-sm">
            <tbody>
              <tr>
                <th style={{ width: '45%' }}>Type de produit</th>
                <td>{c.type_produit || '—'}</td>
              </tr>
              <tr>
                <th>Nombre de produits</th>
                <td>{c.nombre_produits || '—'}</td>
              </tr>
              <tr>
                <th>Poids</th>
                <td>{c.poids} kg</td>
              </tr>
              <tr>
                <th>Dimensions</th>
                <td>{c.dimensions || '—'}</td>
              </tr>
              <tr>
                <th>Destination</th>
                <td>
                  {c.ville}, {c.pays}
                </td>
              </tr>
              <tr>
                <th>Adresse de départ</th>
                <td>{c.adresse_depart || '—'}</td>
              </tr>
              <tr>
                <th>Adresse de destination</th>
                <td>{c.adresse_destination || '—'}</td>
              </tr>
              <tr>
                <th>Date limite</th>
                <td>{date(c.date_limite)}</td>
              </tr>
              <tr>
                <th>Posté le</th>
                <td>{datetime(c.date_post)}</td>
              </tr>
              <tr>
                <th>Propriétaire</th>
                <td>
                  {c.prenom || ''} {c.nom || ''}
                  {c.owner_email && (
                    <div className="small text-muted">
                      {c.owner_email} · {c.owner_tel || ''}
                    </div>
                  )}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div className="page-card">
          <h5 className="mb-3">
            <i className="fa-solid fa-route text-primary"></i> Étapes de suivi
          </h5>
          {c.etapes_suivi && c.etapes_suivi.length ? (
            <ul className="list-group">
              {c.etapes_suivi.map((et, i) => (
                <li key={i} className="list-group-item d-flex justify-content-between align-items-center">
                  <StatusBadge statut={et.statut} />
                  <span className="small text-muted">{datetime(et.date_etape)}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-muted mb-0">Aucune étape enregistrée.</p>
          )}
        </div>
      </div>
    </div>
  )
}
