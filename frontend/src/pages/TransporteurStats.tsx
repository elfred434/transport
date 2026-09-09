import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { money } from '../lib/format'
import { useAuth } from '../context/AuthContext'

/** Statistiques transporteur — port de transporteur-stats.html. */

interface Stats {
  solde: string | number
  nb_voyages: number
  nb_reservations_acceptees: number
  note_moyenne: number | null
  nb_avis: number
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
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!me?.is_transporteur) return
    ;(async () => {
      try {
        setS(await api.get<Stats>('/api/transporteur-stats'))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [me])

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
            <StatCard icon="fa-wallet" label="Solde (commissions 5 %)" value={money(s.solde)} color="success" />
            <StatCard icon="fa-plane" label="Voyages proposés" value={s.nb_voyages} />
            <StatCard icon="fa-handshake" label="Réservations acceptées" value={s.nb_reservations_acceptees} color="info" />
            <StatCard
              icon="fa-star"
              label="Note moyenne"
              value={s.note_moyenne !== null ? `${s.note_moyenne}/5 (${s.nb_avis} avis)` : '—'}
              color="warning"
            />
          </div>
          <p className="text-muted small mt-3">
            <i className="fa-solid fa-circle-info"></i> Le solde est crédité de 5 % du prix estimé
            de chaque colis dont la livraison est confirmée par l'agence.
          </p>
        </>
      )}
    </>
  )
}
