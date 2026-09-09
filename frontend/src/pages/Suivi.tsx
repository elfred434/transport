import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime, SmartImg, StatusBadge } from '../lib/format'

interface Etape {
  statut: string
  date_etape: string
}

interface SuiviResponse {
  colis: {
    id: number
    nom_colis: string
    image_url: string | null
    statut: string
    ville: string
    pays: string
    poids: string | number
    numero_suivi: string
  }
  etapes: Etape[]
  statut_actuel: string
}

/** Suivi de colis — port de suivi.html (pré-remplissage via ?numero_suivi=). */
export default function Suivi() {
  const [params] = useSearchParams()
  const [numero, setNumero] = useState(params.get('numero_suivi') || '')
  const [result, setResult] = useState<SuiviResponse | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const track = async (num: string) => {
    if (!num) return
    setLoading(true)
    setError(null)
    setResult(null)
    try {
      const data = await api.get<SuiviResponse>('/api/suivi/' + encodeURIComponent(num))
      setResult(data)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    } finally {
      setLoading(false)
    }
  }

  // Pré-remplissage depuis la query string (accueil → suivre).
  useEffect(() => {
    const prefill = params.get('numero_suivi')
    if (prefill) track(prefill)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [params])

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    track(numero.trim())
  }

  const c = result?.colis

  return (
    <>
      <style>{`
        .timeline { list-style: none; padding: 0; margin: 0; }
        .timeline li { position: relative; padding: 0 0 24px 36px; border-left: 2px solid #d7e4ef; }
        .timeline li:last-child { border-left-color: transparent; padding-bottom: 0; }
        .timeline li::before {
          content: ''; position: absolute; left: -9px; top: 2px;
          width: 16px; height: 16px; border-radius: 50%;
          background: #3498db; border: 3px solid #fff; box-shadow: 0 0 0 2px #3498db;
        }
        .timeline li.livre::before { background: #198754; box-shadow: 0 0 0 2px #198754; }
      `}</style>

      <h2 className="mb-4">
        <i className="fa-solid fa-search-location text-primary"></i> Suivi de colis
      </h2>

      <div className="page-card" style={{ maxWidth: 760 }}>
        <form onSubmit={onSubmit} className="d-flex gap-2 mb-4">
          <input
            type="text"
            className="form-control"
            placeholder="Numéro de suivi (ex. COLIS1A2B3C4D5E)"
            value={numero}
            onChange={(e) => setNumero(e.target.value)}
            required
          />
          <button className="btn btn-primary fw-bold" type="submit">
            <i className="fa-solid fa-magnifying-glass"></i> Suivre
          </button>
        </form>

        {loading && <p className="text-muted text-center py-3">Recherche…</p>}
        {error && <div className="alert alert-danger">{error}</div>}

        {result && c && (
          <>
            <div className="border rounded-3 p-3 mb-4">
              <div className="d-flex gap-3 align-items-center flex-wrap">
                <SmartImg url={c.image_url} alt={c.nom_colis} className="img-colis" />
                <div>
                  <div className="fw-bold fs-5">
                    {c.nom_colis} <StatusBadge statut={c.statut} />
                  </div>
                  <div className="small text-muted">
                    {c.ville}, {c.pays} · {c.poids} kg · Suivi : <code>{c.numero_suivi}</code>
                  </div>
                </div>
                <div className="ms-auto text-end">
                  <div className="text-muted small">Statut actuel</div>
                  <StatusBadge statut={result.statut_actuel} />
                </div>
              </div>
            </div>

            <h6>Étapes de livraison</h6>
            {result.etapes.length ? (
              <ul className="timeline mt-3">
                {result.etapes.map((et, i) => (
                  <li key={i} className={et.statut === 'Livré' ? 'livre' : ''}>
                    <strong>
                      <StatusBadge statut={et.statut} />
                    </strong>
                    <div className="small text-muted">{datetime(et.date_etape)}</div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted">
                Aucune étape enregistrée pour le moment.{' '}
                {c.statut === 'en_attente'
                  ? 'Le colis est en attente de validation et de paiement.'
                  : ''}
              </p>
            )}

            <div className="mt-4">
              <Link to={`/colis/${c.id}`} className="btn btn-outline-primary btn-sm">
                <i className="fa-solid fa-box-open"></i> Voir le détail du colis
              </Link>
            </div>
          </>
        )}
      </div>
    </>
  )
}
