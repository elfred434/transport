import { useEffect, useState } from 'react'
import { api, ApiError } from '../lib/api'
import { datetime } from '../lib/format'

/** Réponses de l'agence aux messages de contact — port de reponses.html. */

interface Reponse {
  nom: string | null
  message: string
  reponse: string
  date_envoi: string
  date_reponse: string
}

export default function Reponses() {
  const [list, setList] = useState<Reponse[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        setList(await api.get<Reponse[]>('/api/contact/reponses'))
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [])

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-reply text-primary"></i> Réponses de l'agence
      </h2>

      <div className="page-card" style={{ maxWidth: 860 }}>
        <p className="text-muted small">
          Réponses aux messages envoyés via le formulaire de contact avec votre email.
        </p>

        {error && <div className="alert alert-danger">{error}</div>}
        {!list && !error && <div className="text-center text-muted py-4">Chargement…</div>}
        {list && list.length === 0 && (
          <p className="text-muted py-3 mb-0">Aucune réponse pour le moment.</p>
        )}

        {list &&
          list.map((r, i) => (
            <div key={i} className="border rounded-3 p-3 mb-3 text-start">
              <div className="d-flex justify-content-between flex-wrap">
                <strong>
                  <i className="fa-solid fa-comment-dots text-primary"></i> {r.nom || ''}
                </strong>
                <small className="text-muted">
                  Envoyé le {datetime(r.date_envoi)} · Répondu le {datetime(r.date_reponse)}
                </small>
              </div>
              <p className="mb-2 mt-2" style={{ whiteSpace: 'pre-line' }}>
                {r.message}
              </p>
              <div className="alert alert-success mb-0">
                <strong>
                  <i className="fa-solid fa-headset"></i> Réponse de l'agence :
                </strong>
                <br />
                <span style={{ whiteSpace: 'pre-line' }}>{r.reponse}</span>
              </div>
            </div>
          ))}
      </div>
    </>
  )
}
