import { useEffect, useRef, useState } from 'react'
import { api, ApiError } from '../lib/api'
import { datetime } from '../lib/format'

/** Chat avec l'administrateur — port de messagerie-admin.html. */

interface AdminChatMessage {
  moi: boolean
  prenom?: string
  nom?: string
  contenu: string
  date_envoi: string
}

export default function MessagerieAdmin() {
  const [messages, setMessages] = useState<AdminChatMessage[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [contenu, setContenu] = useState('')
  const zoneRef = useRef<HTMLDivElement>(null)

  const loadMessages = async (scroll: boolean) => {
    try {
      const list = await api.get<AdminChatMessage[]>('/api/admin-chat')
      setMessages(list)
      if (scroll) {
        const zone = zoneRef.current
        if (zone) zone.scrollTop = zone.scrollHeight
      }
    } catch (e) {
      if (scroll) setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }

  useEffect(() => {
    loadMessages(true)
    const timer = setInterval(() => loadMessages(false), 8000)
    return () => clearInterval(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const onSend = async (e: React.FormEvent) => {
    e.preventDefault()
    const texte = contenu.trim()
    if (!texte) return
    setError(null)
    try {
      await api.post('/api/admin-chat', { contenu: texte })
      setContenu('')
      await loadMessages(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-headset text-primary"></i> Contacter l'administrateur
      </h2>

      <div className="page-card" style={{ maxWidth: 860 }}>
        {error && <div className="alert alert-danger">{error}</div>}
        <div className="chat-zone" ref={zoneRef}>
          {messages === null ? (
            <p className="text-muted text-center mb-0">Chargement…</p>
          ) : messages.length === 0 ? (
            <p className="text-muted text-center mb-0">Aucun message. Écrivez à l'agence !</p>
          ) : (
            messages.map((m, i) => (
              <div key={i} className={`chat-msg ${m.moi ? 'me' : 'other'}`}>
                {!m.moi && (
                  <strong className="d-block small">
                    {m.prenom} {m.nom} (admin)
                  </strong>
                )}
                <span style={{ whiteSpace: 'pre-line' }}>{m.contenu}</span>
                <small>{datetime(m.date_envoi)}</small>
              </div>
            ))
          )}
        </div>

        <form className="d-flex gap-2" onSubmit={onSend}>
          <textarea
            className="form-control"
            rows={2}
            maxLength={5000}
            placeholder="Votre message à l'agence…"
            value={contenu}
            onChange={(e) => setContenu(e.target.value)}
          />
          <button type="submit" className="btn btn-primary fw-bold">
            <i className="fa-solid fa-paper-plane"></i>
          </button>
        </form>
      </div>
    </>
  )
}
