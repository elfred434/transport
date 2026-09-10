import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, money, SmartImg, StatusBadge } from '../lib/format'
import { CountrySelect } from '../components/LocationSelect'

/** Recherche (colis / voyages + voyages compatibles) — port de recherche.html. */

interface ColisRow {
  id: number
  nom_colis: string
  image_url: string | null
  ville: string
  pays: string
  poids: string | number
  prix_estime: string | number
  proprietaire: string
  statut?: string
}

interface VoyageRow {
  transporteur_id: number | null
  user_id?: number | null
  prenom: string
  nom: string
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
  poids_max: string | number
}

interface CompatiblesData {
  colis: ColisRow
  voyages: VoyageRow[]
}

export default function Recherche() {
  const [params] = useSearchParams()
  const colisId = params.get('colis_id')

  const [tab, setTab] = useState<'colis' | 'voyages'>('colis')

  // Colis
  const [cSearch, setCSearch] = useState('')
  const [cPays, setCPays] = useState('')
  const [cVille, setCVille] = useState('')
  const [colisList, setColisList] = useState<ColisRow[] | null>(null)
  const [colisError, setColisError] = useState<string | null>(null)

  // Voyages
  const [vSearch, setVSearch] = useState('')
  const [vDepart, setVDepart] = useState('')
  const [vDestination, setVDestination] = useState('')
  const [voyagesList, setVoyagesList] = useState<VoyageRow[] | null>(null)
  const [voyagesError, setVoyagesError] = useState<string | null>(null)

  // Voyages compatibles
  const [compat, setCompat] = useState<CompatiblesData | null>(null)
  const [compatError, setCompatError] = useState<string | null>(null)

  const searchColis = useCallback(async () => {
    const p = new URLSearchParams()
    if (cSearch.trim()) p.set('search', cSearch.trim())
    if (cPays.trim()) p.set('pays', cPays.trim())
    if (cVille.trim()) p.set('ville', cVille.trim())
    setColisList(null)
    setColisError(null)
    try {
      setColisList(await api.get<ColisRow[]>('/api/colis/available' + (p.toString() ? '?' + p : '')))
    } catch (e) {
      setColisError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [cSearch, cPays, cVille])

  const searchVoyages = useCallback(async () => {
    const p = new URLSearchParams()
    if (vSearch.trim()) p.set('search', vSearch.trim())
    if (vDepart.trim()) p.set('pays_depart', vDepart.trim())
    if (vDestination.trim()) p.set('pays_destination', vDestination.trim())
    setVoyagesList(null)
    setVoyagesError(null)
    try {
      setVoyagesList(await api.get<VoyageRow[]>('/api/voyages/available' + (p.toString() ? '?' + p : '')))
    } catch (e) {
      setVoyagesError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [vSearch, vDepart, vDestination])

  useEffect(() => {
    searchColis()
    searchVoyages()
    // Recherche initiale uniquement (comme le vanilla).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!colisId) return
    ;(async () => {
      try {
        setCompat(
          await api.get<CompatiblesData>(
            '/api/colis/' + encodeURIComponent(colisId) + '/voyages-compatibles',
          ),
        )
      } catch (e) {
        setCompatError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [colisId])

  const transporteurLink = (v: VoyageRow) => {
    const tid = v.transporteur_id || v.user_id
    return tid ? (
      <Link to={`/profil-transporteur?id=${tid}`}>
        {v.prenom} {v.nom}
      </Link>
    ) : (
      <>
        {v.prenom} {v.nom}
      </>
    )
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-magnifying-glass text-primary"></i> Recherche
      </h2>

      {colisId && (
        <div className="page-card">
          <h5 className="mb-3">Voyages compatibles avec votre colis</h5>
          {compatError && <div className="alert alert-danger">{compatError}</div>}
          {!compat && !compatError && <p className="text-muted">Chargement…</p>}
          {compat && (
            <>
              <div className="border rounded-3 p-3 mb-3 d-flex gap-3 align-items-center">
                <SmartImg url={compat.colis.image_url} alt={compat.colis.nom_colis} className="img-colis" />
                <div>
                  <strong>{compat.colis.nom_colis}</strong>{' '}
                  {compat.colis.statut && <StatusBadge statut={compat.colis.statut} />}
                  <div className="small text-muted">
                    {compat.colis.ville}, {compat.colis.pays} · {compat.colis.poids} kg
                  </div>
                </div>
              </div>
              {compat.voyages.length === 0 ? (
                <div className="alert alert-info mb-0">
                  Aucun voyage approuvé ne correspond actuellement à ce colis.
                </div>
              ) : (
                <>
                  <div className="table-responsive">
                    <table className="table align-middle">
                      <thead className="table-light">
                        <tr>
                          <th>Transporteur</th>
                          <th>Trajet</th>
                          <th>Départ</th>
                          <th>Poids max</th>
                        </tr>
                      </thead>
                      <tbody>
                        {compat.voyages.map((v, i) => (
                          <tr key={i}>
                            <td>{transporteurLink(v)}</td>
                            <td>
                              {v.pays_depart} → {v.pays_destination}
                            </td>
                            <td>
                              {date(v.date_depart)}{' '}
                              <span className="small text-muted">
                                {(v.heure_depart || '').substring(0, 5)}
                              </span>
                            </td>
                            <td>{v.poids_max} kg</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  <p className="text-muted small mb-0">
                    <i className="fa-solid fa-circle-info"></i> Les transporteurs vous envoient des
                    demandes de réservation ; vous les acceptez depuis votre tableau de bord.
                  </p>
                </>
              )}
            </>
          )}
        </div>
      )}

      <div className="page-card">
        <ul className="nav nav-pills mb-4" role="tablist">
          <li className="nav-item">
            <button
              className={`nav-link ${tab === 'colis' ? 'active' : ''}`}
              type="button"
              onClick={() => setTab('colis')}
            >
              <i className="fa-solid fa-box"></i> Colis disponibles
            </button>
          </li>
          <li className="nav-item">
            <button
              className={`nav-link ${tab === 'voyages' ? 'active' : ''}`}
              type="button"
              onClick={() => setTab('voyages')}
            >
              <i className="fa-solid fa-plane"></i> Voyages disponibles
            </button>
          </li>
        </ul>

        <div className="tab-content">
          {tab === 'colis' && (
            <div className="tab-pane fade show active">
              <form
                className="row g-2 align-items-end mb-3"
                onSubmit={(e) => {
                  e.preventDefault()
                  searchColis()
                }}
              >
                <div className="col-md-4">
                  <label className="form-label">Recherche</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Nom, type, ville…"
                    value={cSearch}
                    onChange={(e) => setCSearch(e.target.value)}
                  />
                </div>
                <div className="col-md-3">
                  <CountrySelect
                    label="Pays"
                    value={cPays}
                    onChange={(v) => { setCPays(v); if (cPays !== v) setCVille('') }}
                    placeholder="Tous les pays…"
                    className="mb-0"
                  />
                </div>
                <div className="col-md-3">
                  <CitySelect
                    label="Ville"
                    pays={cPays}
                    value={cVille}
                    onChange={setCVille}
                    placeholder="Toutes les villes…"
                    className="mb-0"
                  />
                </div>
                <div className="col-md-2">
                  <button className="btn btn-primary w-100" type="submit">
                    <i className="fa-solid fa-magnifying-glass"></i> Chercher
                  </button>
                </div>
              </form>
              {colisError && <div className="alert alert-danger">{colisError}</div>}
              {!colisList && !colisError && <div className="text-muted small">Chargement…</div>}
              {colisList && colisList.length === 0 && <p className="text-muted">Aucun résultat.</p>}
              {colisList && colisList.length > 0 && (
                <div className="table-responsive">
                  <table className="table align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>Colis</th>
                        <th>Destination</th>
                        <th>Poids</th>
                        <th>Prix</th>
                        <th>Propriétaire</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {colisList.map((c) => (
                        <tr key={c.id}>
                          <td>
                            <SmartImg url={c.image_url} alt={c.nom_colis} className="img-colis" />{' '}
                            <span className="ms-2">{c.nom_colis}</span>
                          </td>
                          <td>
                            {c.ville}, {c.pays}
                          </td>
                          <td>{c.poids} kg</td>
                          <td>{money(c.prix_estime)}</td>
                          <td>{c.proprietaire || ''}</td>
                          <td>
                            <Link to={`/colis/${c.id}`} className="btn btn-sm btn-outline-primary">
                              Détail
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

          {tab === 'voyages' && (
            <div className="tab-pane fade show active">
              <form
                className="row g-2 align-items-end mb-3"
                onSubmit={(e) => {
                  e.preventDefault()
                  searchVoyages()
                }}
              >
                <div className="col-md-4">
                  <label className="form-label">Recherche</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Pays…"
                    value={vSearch}
                    onChange={(e) => setVSearch(e.target.value)}
                  />
                </div>
                <div className="col-md-3">
                  <CountrySelect
                    label="Pays de départ"
                    value={vDepart}
                    onChange={setVDepart}
                    placeholder="Tous les départs…"
                    className="mb-0"
                  />
                </div>
                <div className="col-md-3">
                  <CountrySelect
                    label="Pays de destination"
                    value={vDestination}
                    onChange={setVDestination}
                    placeholder="Toutes les destinations…"
                    className="mb-0"
                  />
                </div>
                <div className="col-md-2">
                  <button className="btn btn-primary w-100" type="submit">
                    <i className="fa-solid fa-magnifying-glass"></i> Chercher
                  </button>
                </div>
              </form>
              {voyagesError && <div className="alert alert-danger">{voyagesError}</div>}
              {!voyagesList && !voyagesError && <div className="text-muted small">Chargement…</div>}
              {voyagesList && voyagesList.length === 0 && <p className="text-muted">Aucun résultat.</p>}
              {voyagesList && voyagesList.length > 0 && (
                <div className="table-responsive">
                  <table className="table align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>Transporteur</th>
                        <th>Trajet</th>
                        <th>Départ</th>
                        <th>Poids max</th>
                      </tr>
                    </thead>
                    <tbody>
                      {voyagesList.map((v, i) => (
                        <tr key={i}>
                          <td>{transporteurLink(v)}</td>
                          <td>
                            {v.pays_depart} → {v.pays_destination}
                          </td>
                          <td>
                            {date(v.date_depart)}{' '}
                            <span className="small text-muted">
                              {(v.heure_depart || '').substring(0, 5)}
                            </span>
                          </td>
                          <td>{v.poids_max} kg</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </>
  )
}
