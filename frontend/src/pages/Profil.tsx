import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, money } from '../lib/format'

/** Mon profil — port de profil.html. */

interface ProfileUser {
  id: number
  nom: string
  prenom: string
  email: string
  telephone: string | null
  photo_url: string | null
  role: string
  date_inscription: string
}

interface ProfileTransporteur {
  numero_permis: string | null
  vehicule: string | null
  compagnie: string | null
  adresse: string | null
  ville: string | null
  pays: string | null
  solde: string | number
  date_creation: string
}

interface ProfileData {
  user: ProfileUser
  transporteur: ProfileTransporteur | null
}

export default function Profil() {
  const [data, setData] = useState<ProfileData | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        setData(await api.get<ProfileData>('/api/profile'))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [])

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-user text-primary"></i> Mon profil
      </h2>

      {error && <div className="alert alert-danger">{error}</div>}

      {!data && !error && <div className="text-muted">Chargement…</div>}

      {data && (
        <div className="row g-4" style={{ maxWidth: 960 }}>
          <div className="col-md-4">
            <div className="page-card text-center">
              {data.user.photo_url ? (
                <img src={data.user.photo_url} className="avatar-lg" alt="" />
              ) : (
                <i className="fa-solid fa-user avatar-lg text-muted"></i>
              )}
              <h5 className="mt-3">
                {data.user.prenom} {data.user.nom}
              </h5>
              <span className="badge bg-primary">{data.user.role}</span>
              <div className="d-grid mt-3">
                <Link to="/modifier-profil" className="btn btn-outline-primary">
                  <i className="fa-solid fa-pen"></i> Modifier mon profil
                </Link>
              </div>
            </div>
          </div>
          <div className="col-md-8">
            <div className="page-card">
              <h5 className="mb-3">Informations</h5>
              <table className="table table-sm">
                <tbody>
                  <tr>
                    <th style={{ width: '40%' }}>Nom</th>
                    <td>{data.user.nom}</td>
                  </tr>
                  <tr>
                    <th>Prénom</th>
                    <td>{data.user.prenom}</td>
                  </tr>
                  <tr>
                    <th>Email</th>
                    <td>{data.user.email}</td>
                  </tr>
                  <tr>
                    <th>Téléphone</th>
                    <td>{data.user.telephone || '—'}</td>
                  </tr>
                  <tr>
                    <th>Inscrit le</th>
                    <td>{date(data.user.date_inscription)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            {data.transporteur && (
              <div className="page-card">
                <h5 className="mb-3">
                  <i className="fa-solid fa-truck text-primary"></i> Fiche transporteur
                </h5>
                <table className="table table-sm">
                  <tbody>
                    <tr>
                      <th style={{ width: '40%' }}>N° permis</th>
                      <td>{data.transporteur.numero_permis || '—'}</td>
                    </tr>
                    <tr>
                      <th>Véhicule</th>
                      <td>{data.transporteur.vehicule || '—'}</td>
                    </tr>
                    <tr>
                      <th>Compagnie</th>
                      <td>{data.transporteur.compagnie || '—'}</td>
                    </tr>
                    <tr>
                      <th>Adresse</th>
                      <td>
                        {data.transporteur.adresse || '—'}, {data.transporteur.ville || ''} (
                        {data.transporteur.pays || ''})
                      </td>
                    </tr>
                    <tr>
                      <th>Solde</th>
                      <td>
                        <strong className="text-success">{money(data.transporteur.solde)}</strong>
                      </td>
                    </tr>
                    <tr>
                      <th>Transporteur depuis</th>
                      <td>{date(data.transporteur.date_creation)}</td>
                    </tr>
                  </tbody>
                </table>
                <Link to="/transporteur-stats" className="btn btn-outline-primary btn-sm">
                  <i className="fa-solid fa-chart-line"></i> Mes statistiques
                </Link>{' '}
                <Link
                  to={'/profil-transporteur?id=' + data.user.id}
                  className="btn btn-outline-secondary btn-sm"
                >
                  <i className="fa-solid fa-eye"></i> Voir ma fiche publique
                </Link>
              </div>
            )}
          </div>
        </div>
      )}
    </>
  )
}
