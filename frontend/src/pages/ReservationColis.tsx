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

  useEffect(() => {
    if (!colisId || !me) return
    ;(async () => {
      try {
        const [c, voyages] = await Promise.all([
          api.get<ColisInfo>('/api/colis/' + encodeURIComponent(colisId)),
          api.get<VoyageMine[]>('/api/voyages/mine'),
        ])
        setColis(c)
        // Voyages compatibles : approuvés, à venir, destination = pays du colis, poids max suffisant
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
          <div className="alert alert-danger">colis_id manquant.</div>
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
