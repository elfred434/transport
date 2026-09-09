import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../../lib/api'
import { datetime } from '../../lib/format'

/** Messagerie admin ↔ utilisateurs — port de admin/messagerie.html. */

interface AdminConversation {
  user_id: number
  prenom: string
  nom: string
  photo_url: string | null
  dernier_message: string | null
  non_lus: number
}

interface AdminChatMessage {
  moi: boolean
  prenom?: string
  nom?: string
  contenu: string
  date_envoi: string
}

export default function AdminMessagerie() {
  const [params] = useSearchParams()
  const preselect = Number(params.get('user_id')) || null

  const [convs, setConvs] = useState<AdminConversation[] | null>(null)
  const [convError, setConvError] = useState<string | null>(null)
  const [peerId, setPeerId] = useState<number | null>(preselect)
  const [peerName, setPeerName] = useState<string | null>(null)
  const [messages, setMessages] = useState<AdminChatMessage[] | null>(null)
  const [chatError, setChatError] = useState<string | null>(null)
  const [contenu, setContenu] = useState('')
  const zoneRef = useRef<HTMLDivElement>(null)

  const loadConversations = useCallback(async () => {
    try {
      setConvs(await api.get<AdminConversation[]>('/api/admin-chat/conversations'))
    } catch (e) {
      setConvError(e instanceof ApiError ? e.message : 'Erreur inconnue')
    }
  }, [])

  const loadChat = useCallback(
    async (scroll: boolean, id: number) => {
      try {
        const list = await api.get<AdminChatMessage[]>('/api/admin-chat?user_id=' + id)
        setMessages(list)
        if (scroll) {
          const zone = zoneRef.current
          if (zone) zone.scrollTop = zone.scrollHeight
        }
      } catch (e) {
        if (scroll) setChatError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    },
    [],
  )

  useEffect(() => {
    loadConversations()
  }, [loadConversations])

  // Ouverture directe si ?user_id= ; sinon conversation sélectionnée.
  useEffect(() => {
    if (!peerId) return
    loadChat(true, peerId)
    const timer = setInterval(() => loadChat(false, peerId), 8000)
    return () => clearInterval(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [peerId])

  // Titre de la conversation : nom depuis la liste, sinon "Utilisateur #id".
  useEffect(() => {
    if (peerId === null) {
      setPeerName(null)
      return
    }
    const item = convs?.find((c) => c.user_id === peerId)
    setPeerName(item ? item.prenom + ' ' + item.nom : 'Utilisateur #' + peerId)
  }, [peerId, convs])

  const selectPeer = (id: number) => {
    setPeerId(id)
    setMessages(null)
    setChatError(null)
  }

  const onSend = async (e: React.FormEvent) => {
    e.preventDefault()
    const texte = contenu.trim()
    if (!texte || peerId === null) return
    setChatError(null)
    try {
      await api.post('/api/admin-chat', { destinataire_id: peerId, contenu: texte })
      setContenu('')
      await loadChat(true, peerId)
      loadConversations()
    } catch (err) {
      setChatError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 className="mb-0">
          <i className="fa-solid fa-headset text-primary"></i> Messagerie avec les utilisateurs
        </h2>
        <Link to="/admin" className="btn btn-outline-secondary btn-sm">
          <i className="fas fa-shield-halved"></i> Panneau d'administration
        </Link>
      </div>

      <div className="row g-4">
        <div className="col-md-4">
          <div className="page-card">
            <h6 className="mb-3">Conversations</h6>
            {convError && <div className="alert alert-danger">{convError}</div>}
            {!convs && !convError ? (
              <div className="list-group text-muted small">Chargement…</div>
            ) : convs && convs.length === 0 ? (
              <p className="text-muted small mb-0">Aucune conversation.</p>
            ) : (
              <div className="list-group">
                {convs?.map((c) => (
                  <a
                    key={c.user_id}
                    href="#"
                    className={`list-group-item list-group-item-action d-flex gap-2 align-items-center ${
                      peerId === c.user_id ? 'active' : ''
                    }`}
                    onClick={(e) => {
                      e.preventDefault()
                      selectPeer(c.user_id)
                    }}
                  >
                    {c.photo_url ? (
                      <img
                        src={c.photo_url}
                        className="rounded-circle"
                        style={{ width: 36, height: 36, objectFit: 'cover' }}
                        alt=""
                      />
                    ) : (
                      <i className="fa-solid fa-user-circle fa-xl text-muted"></i>
                    )}
                    <div className="flex-grow-1 overflow-hidden">
                      <strong className="small">
                        {c.prenom} {c.nom}
                      </strong>
                      <div className="text-truncate" style={{ maxWidth: 180, fontSize: '.78rem' }}>
                        {c.dernier_message || ''}
                      </div>
                    </div>
                    {c.non_lus > 0 && (
                      <span className="badge bg-danger rounded-pill">{c.non_lus}</span>
                    )}
                  </a>
                ))}
              </div>
            )}
          </div>
        </div>
        <div className="col-md-8">
          <div className="page-card">
            <h6 className="mb-3">{peerName || 'Sélectionnez une conversation'}</h6>
            {chatError && <div className="alert alert-danger">{chatError}</div>}
            <div className="chat-zone" ref={zoneRef}>
              {peerId === null ? (
                <p className="text-muted text-center mb-0">—</p>
              ) : messages === null ? (
                <p className="text-muted text-center mb-0">Chargement…</p>
              ) : messages.length === 0 ? (
                <p className="text-muted text-center mb-0">Aucun message.</p>
              ) : (
                messages.map((m, i) => (
                  <div key={i} className={`chat-msg ${m.moi ? 'me' : 'other'}`}>
                    {!m.moi && (
                      <strong className="d-block small">
                        {m.prenom} {m.nom}
                      </strong>
                    )}
                    <span style={{ whiteSpace: 'pre-line' }}>{m.contenu}</span>
                    <small>{datetime(m.date_envoi)}</small>
                  </div>
                ))
              )}
            </div>
            {peerId !== null && (
              <form className="d-flex gap-2" onSubmit={onSend}>
                <textarea
                  className="form-control"
                  rows={2}
                  maxLength={5000}
                  placeholder="Votre réponse…"
                  value={contenu}
                  onChange={(e) => setContenu(e.target.value)}
                />
                <button type="submit" className="btn btn-primary fw-bold">
                  <i className="fa-solid fa-paper-plane"></i>
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </>
  )
}
