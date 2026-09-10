import { useEffect, useRef, useState } from 'react'
import { api, ApiError } from '../lib/api'
import { datetime } from '../lib/format'
import '../styles/home-originale.css'

interface AdminChatMessage { moi: boolean; prenom?: string; nom?: string; contenu: string; date_envoi: string; }

export default function MessagerieAdmin() {
  const [messages, setMessages] = useState<AdminChatMessage[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [contenu, setContenu] = useState('')
  const zoneRef = useRef<HTMLDivElement>(null)
  const loadMessages = async (scroll: boolean) => {
    try {
      const list = await api.get<AdminChatMessage[]>('/api/admin-chat')
      setMessages(list)
      if (scroll) { const zone = zoneRef.current; if (zone) zone.scrollTop = zone.scrollHeight }
    } catch (e) { if (scroll) setError(e instanceof ApiError ? e.message : 'Erreur inconnue') }
  }
  useEffect(() => { loadMessages(true); const timer = setInterval(() => loadMessages(false), 8000); return () => clearInterval(timer) }, [])
  const onSend = async (e: React.FormEvent) => {
    e.preventDefault(); const texte = contenu.trim(); if (!texte) return; setError(null)
    try { await api.post('/api/admin-chat', { contenu: texte }); setContenu(''); await loadMessages(true) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Erreur inconnue') }
  }
  return (
    <div className="home-originale">
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 960}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fa-solid fa-headset" style={{color: 'var(--primary-color)'}}></i> Contacter l'administrateur</h2>
          <div className="card w-100" style={{maxWidth: 860, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            {error && <div className="alert alert-danger text-center">{error}</div>}
            <div className="chat-zone mx-auto w-100" ref={zoneRef} style={{maxWidth: 700, maxHeight: 420, overflowY: 'auto', background: '#f9f9f9', borderRadius: '8px', padding: '16px', marginBottom: '16px'}}>
              {messages === null ? <p className="text-muted text-center mb-0">Chargement…</p> : messages.length === 0 ? <p className="text-muted text-center mb-0">Aucun message. Écrivez à l'agence !</p> : messages.map((m, i) => (
                <div key={i} className={`chat-msg ${m.moi ? 'me' : 'other'}`} style={{margin: '0 auto 10px', maxWidth: '80%', padding: '10px 14px', borderRadius: '10px', background: m.moi ? 'var(--primary-color)' : '#ececec', color: m.moi ? '#fff' : '#000', marginLeft: m.moi ? 'auto' : '0'}}>
                  {!m.moi && <strong className="d-block small">{m.prenom} {m.nom} (admin)</strong>}
                  <span style={{ whiteSpace: 'pre-line' }}>{m.contenu}</span><small style={{display: 'block', marginTop: '4px', opacity: 0.75}}>{datetime(m.date_envoi)}</small>
                </div>
              ))}
            </div>
            <form className="d-flex gap-2 mx-auto w-100" style={{maxWidth: 700}} onSubmit={onSend}>
              <textarea className="form-control" rows={2} maxLength={5000} placeholder="Votre message à l'agence…" value={contenu} onChange={(e) => setContenu(e.target.value)} />
              <button type="submit" className="btn btn-primary fw-bold"><i className="fa-solid fa-paper-plane"></i></button>
            </form>
          </div>
        </section>
      </div>
    </div>
  )
}
