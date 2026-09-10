import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { date, money, SmartImg, StatusBadge } from '../lib/format'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../components/Toasts'

/** Réserver un colis — port de reservation-colis.html (?colis_id=). */

interface ColisInfo {
  id: number
  nom_colis: string
  image_url: string | null
  statut: string
  ville: string
  pays: string
  poids: string | number
  prix_estime: string | number
}

interface VoyageMine {
  id: number
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
  poids_max: string | number
  statut: string
}

export default function ReservationColis() {
  const [params] = useSearchParams()
  const colisId = params.get('colis_id')
  const { me } = useAuth()
  const { toast } = useToast()
  const navigate = useNavigate()

  const [colis, setColis] = useState<ColisInfo | null>(null)
  const [compatibles, setCompatibles] = useState<VoyageMine[]>([])
  const [voyageSel, setVoyageSel] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [availableColis, setAvailableColis] = useState<ColisInfo[] | null>(null)

  useEffect(() => {
    if (!me) return
    if (!colisId) {
      // Mode liste : afficher colis disponibles à réserver
      ;(async () => {
        try {
          const data = await api.get<ColisInfo[]>('/api/colis/available')
          setAvailableColis(data)
        } catch (e) {
          setError(e instanceof ApiError ? e.message : 'Erreur chargement colis disponibles')
        }
      })()
      return
    }
    ;(async () => {
      try {
        const [c, voyages] = await Promise.all([
          api.get<ColisInfo>('/api/colis/' + encodeURIComponent(colisId)),
          api.get<VoyageMine[]>('/api/voyages/mine'),
        ])
        setColis(c)
        setCompatibles(
          voyages.filter(
            (v) =>
              v.statut === 'approuve' &&
              new Date(v.date_depart) >= new Date(new Date().toDateString()) &&
              v.pays_destination.toLowerCase() === (c.pays || '').toLowerCase() &&
              parseFloat(String(v.poids_max)) >= parseFloat(String(c.poids)),
          ),
        )
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [colisId, me])

  const reserver = async () => {
    if (voyageSel === null) {
      toast('Sélectionnez un voyage.', 'error')
      return
    }
    try {
      const data = await api.post<{ message?: string }>('/api/reservations', {
        colis_id: Number(colisId),
        voyage_id: voyageSel,
      })
      toast(data.message || 'Réservation envoyée.')
      setTimeout(() => navigate('/dashboard'), 1200)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-handshake text-primary"></i> Réserver un colis
      </h2>
      <div className="page-card" style={{ maxWidth: 860 }}>
        {error && <div className="alert alert-danger">{error}</div>}

        {!colisId ? (
          <>
            <h5 className="mb-3"><i className="fa-solid fa-boxes-stacked"></i> Colis disponibles à réserver</h5>
            {!availableColis && !error && <div className="text-center text-muted py-4">Chargement…</div>}
            {availableColis && availableColis.length === 0 && <div className="text-muted">Aucun colis disponible pour le moment. <Link to="/colis">Voir mes colis</Link></div>}
            {availableColis && availableColis.length > 0 && (
              <div className="list-group">
                {availableColis.map(c => (
                  <Link key={c.id} to={`/reservation-colis?colis_id=${c.id}`} className="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                    <SmartImg url={c.image_url} alt={c.nom_colis} className="img-colis" />
                    <div className="flex-grow-1">
                      <div className="fw-bold">{c.nom_colis} <StatusBadge statut={c.statut} /></div>
                      <div className="small text-muted">{c.ville}, {c.pays} · {c.poids} kg · {money(c.prix_estime)}</div>
                    </div>
                    <span className="btn btn-sm btn-primary"><i className="fa-solid fa-handshake"></i> Réserver</span>
                  </Link>
                ))}
              </div>
            )}
            <div className="mt-3 small text-muted">Astuce : vous devez être transporteur avec un voyage approuvé compatible (destination + poids).</div>
          </>
        ) : me && !me.is_transporteur ? (
          <div className="alert alert-warning mb-0">
            Vous devez être transporteur pour réserver un colis.{' '}
            <Link to="/devenir-transporteur" className="alert-link">
              Proposer un voyage
            </Link>
          </div>
        ) : !colis ? (
          !error && <div className="text-center text-muted py-4">Chargement…</div>
        ) : (
          <>
            <div className="border rounded-3 p-3 mb-4 d-flex gap-3 align-items-center">
              <SmartImg url={colis.image_url} alt={colis.nom_colis} className="img-colis" />
              <div>
                <div className="fw-bold">
                  {colis.nom_colis} <StatusBadge statut={colis.statut} />
                </div>
                <div className="small text-muted">
                  {colis.ville}, {colis.pays} · {colis.poids} kg · {money(colis.prix_estime)}
                </div>
              </div>
            </div>

            {colis.statut !== 'approuve' ? (
              <div className="alert alert-warning mb-0">
                Ce colis n'est pas encore approuvé : réservation impossible.
              </div>
            ) : compatibles.length ? (
              <>
                <h6>Choisissez l'un de vos voyages compatibles</h6>
                <div className="list-group mb-3">
                  {compatibles.map((v) => (
                    <label key={v.id} className="list-group-item d-flex gap-3 align-items-center">
                      <input
                        className="form-check-input"
                        type="radio"
                        name="voyage"
                        value={v.id}
                        checked={voyageSel === v.id}
                        onChange={() => setVoyageSel(v.id)}
                      />
                      <span>
                        <strong>
                          {v.pays_depart} → {v.pays_destination}
                        </strong>
                        <span className="text-muted small d-block">
                          Départ : {date(v.date_depart)} à {(v.heure_depart || '').substring(0, 5)}
                          · Poids max : {v.poids_max} kg
                        </span>
                      </span>
                    </label>
                  ))}
                </div>
                <button className="btn btn-success btn-lg w-100 fw-bold" onClick={reserver}>
                  <i className="fa-solid fa-handshake"></i> Envoyer la réservation au propriétaire
                </button>
              </>
            ) : (
              <>
                <div className="alert alert-info mb-2">
                  Aucun de vos voyages approuvés ne correspond à ce colis (destination :{' '}
                  <strong>{colis.pays}</strong>, poids : <strong>{colis.poids} kg</strong>).
                </div>
                <Link to="/devenir-transporteur" className="btn btn-primary">
                  Proposer un voyage compatible
                </Link>
              </>
            )}
          </>
        )}
      </div>
    </>
  )
}
