import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime } from '../lib/format'
import '../styles/home-originale.css'

interface Conversation { id: number; prenom: string; nom: string; photo_url: string | null; dernier_message: string | null; derniere_date: string | null; non_lus: number; }

export default function ListeMessagerie() {
  const [list, setList] = useState<Conversation[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  useEffect(() => { (async () => { try { setList(await api.get<Conversation[]>('/api/conversations')) } catch (e) { setError(e instanceof ApiError ? e.message : 'Erreur inconnue') } })() }, [])
  return (
    <div className="home-originale">
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 960}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fa-solid fa-envelope" style={{color: 'var(--primary-color)'}}></i> Messagerie</h2>
          <div className="card w-100" style={{maxWidth: 860, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <h5 className="mb-0" style={{color: 'var(--primary-color)', fontWeight: 700}}>Mes conversations</h5>
              <Link to="/messagerie-admin" className="btn btn-outline-primary btn-sm"><i className="fa-solid fa-headset"></i> Contacter l'admin</Link>
            </div>
            {error && <div className="alert alert-danger text-center">{error}</div>}
            {!list && !error && <div className="text-center text-muted py-4">Chargement…</div>}
            {list && list.length === 0 && <p className="text-muted py-3 mb-0 text-center">Aucune conversation. Les conversations apparaissent après un premier message.</p>}
            {list && list.length > 0 && (
              <div className="list-group text-start mx-auto w-100" style={{maxWidth: 700}}>
                {list.map((c) => (
                  <Link key={c.id} to={'/messagerie?destinataire_id=' + c.id} className="list-group-item list-group-item-action d-flex gap-3 align-items-center justify-content-center">
                    {c.photo_url ? <img src={c.photo_url} className="rounded-circle" style={{ width: 44, height: 44, objectFit: 'cover' }} alt="" /> : <i className="fa-solid fa-user-circle fa-2x text-muted"></i>}
                    <div className="flex-grow-1"><div className="d-flex justify-content-between"><strong>{c.prenom} {c.nom}</strong><small className="text-muted">{datetime(c.derniere_date)}</small></div><div className="small text-muted text-truncate" style={{ maxWidth: 480 }}>{c.dernier_message || ''}</div></div>
                    {c.non_lus > 0 && <span className="badge bg-danger rounded-pill">{c.non_lus}</span>}
                  </Link>
                ))}
              </div>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
