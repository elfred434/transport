import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, money, SmartImg, StatusBadge } from '../lib/format'
import { useAuth } from '../context/AuthContext'

/** Colis disponibles (transporteurs) — port de colis.html. */

interface ColisAvailable {
  id: number
  nom_colis: string
  image_url: string | null
  ville: string
  pays: string
  poids: string | number
  type_produit: string | null
  proprietaire: string
  date_limite: string
  prix_estime: string | number
  statut: string
  numero_suivi: string
}

export default function ColisDisponibles() {
  const { me } = useAuth()
  const [search, setSearch] = useState('')
  const [pays, setPays] = useState('')
  const [ville, setVille] = useState('')
  const [type, setType] = useState('')
  const [list, setList] = useState<ColisAvailable[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    const params = new URLSearchParams()
    if (search.trim()) params.set('search', search.trim())
    if (pays.trim()) params.set('pays', pays.trim())
    if (ville.trim()) params.set('ville', ville.trim())
    if (type) params.set('type', type)

    setList(null)
    setError(null)
    try {
      setList(await api.get<ColisAvailable[]>('/api/colis/available' + (params.toString() ? '?' + params : '')))
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [search, pays, ville, type])

  useEffect(() => {
    load()
    // Chargement initial uniquement ; les filtres s'appliquent au clic.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const reset = () => {
    setSearch('')
    setPays('')
    setVille('')
    setType('')
    // Recharger avec les filtres vidés (le state n'est pas encore à jour).
    setTimeout(async () => {
      setList(null)
      try {
        setList(await api.get<ColisAvailable[]>('/api/colis/available'))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    }, 0)
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-box-open text-primary"></i> Colis disponibles
      </h2>

      <div className="page-card">
        <form
          className="row g-2 align-items-end mb-4"
          onSubmit={(e) => {
            e.preventDefault()
            load()
          }}
        >
          <div className="col-md-3">
            <label className="form-label">Recherche</label>
            <input type="text" className="form-control" placeholder="Nom, type, ville…" value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
          <div className="col-md-2">
            <label className="form-label">Pays</label>
            <input type="text" className="form-control" value={pays} onChange={(e) => setPays(e.target.value)} />
          </div>
          <div className="col-md-2">
            <label className="form-label">Ville</label>
            <input type="text" className="form-control" value={ville} onChange={(e) => setVille(e.target.value)} />
          </div>
          <div className="col-md-2">
            <label className="form-label">Type</label>
            <select className="form-select" value={type} onChange={(e) => setType(e.target.value)}>
              <option value="">Tous</option>
              <option value="alimentaire">Alimentaire</option>
              <option value="electronique">Électronique</option>
              <option value="vetements">Vêtements</option>
              <option value="documents">Documents</option>
              <option value="autre">Autre</option>
            </select>
          </div>
          <div className="col-md-3 d-flex gap-2">
            <button type="submit" className="btn btn-primary flex-grow-1">
              <i className="fa-solid fa-filter"></i> Filtrer
            </button>
            <button type="button" className="btn btn-outline-secondary" onClick={reset}>
              <i className="fa-solid fa-eraser"></i>
            </button>
          </div>
        </form>

        {list && list.length > 0 && !me?.is_transporteur && (
          <div className="alert alert-warning">
            Pour réserver un colis, proposez d'abord un voyage.{' '}
            <Link to="/devenir-transporteur" className="alert-link">
              Devenir transporteur
            </Link>
          </div>
        )}

        {error && <div className="alert alert-danger">{error}</div>}
        {!list && !error && <p className="text-muted text-center py-4">Chargement…</p>}
        {list && list.length === 0 && (
          <p className="text-muted text-center py-4">Aucun colis disponible.</p>
        )}

        {list && list.length > 0 && (
          <div className="row g-3">
            {list.map((c) => (
              <div className="col-md-6 col-lg-4" key={c.id}>
                <div className="card h-100 shadow-sm border-0">
                  <SmartImg url={c.image_url} alt={c.nom_colis} className="card-img-top" />
                  <div className="card-body">
                    <h6 className="card-title">
                      {c.nom_colis} <StatusBadge statut={c.statut} />
                    </h6>
                    <p className="card-text small text-muted mb-2">
                      <i className="fa-solid fa-location-dot"></i> {c.ville}, {c.pays}
                      <br />
                      <i className="fa-solid fa-weight-hanging"></i> {c.poids} kg ·
                      <i className="fa-solid fa-tag"></i> {c.type_produit || 'autre'}
                      <br />
                      <i className="fa-solid fa-user"></i> {c.proprietaire || ''}
                      <br />
                      <i className="fa-solid fa-calendar"></i> Limite : {date(c.date_limite)}
                    </p>
                    <div className="fw-bold text-primary">{money(c.prix_estime)}</div>
                  </div>
                  <div className="card-footer bg-white border-0 d-flex gap-2">
                    <Link to={`/colis/${c.id}`} className="btn btn-sm btn-outline-primary flex-grow-1">
                      Détail
                    </Link>
                    {me?.is_transporteur && (
                      <Link to={`/reservation-colis?colis_id=${c.id}`} className="btn btn-sm btn-success flex-grow-1">
                        <i className="fa-solid fa-handshake"></i> Réserver
                      </Link>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  )
}
