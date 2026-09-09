import { useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime } from '../lib/format'
import { useAuth } from '../context/AuthContext'

/** Conversation 1-à-1 — port de messagerie.html (?destinataire_id=). */

interface Message {
  expediteur_id: number
  contenu: string
  fichier_url: string | null
  date_envoi: string
  prenom?: string
  nom?: string
}

export default function Messagerie() {
  const [params] = useSearchParams()
  const peerId = Number(params.get('destinataire_id')) || null
  const { me } = useAuth()

  const [messages, setMessages] = useState<Message[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [peerName, setPeerName] = useState('Conversation')
  const [contenu, setContenu] = useState('')
  const zoneRef = useRef<HTMLDivElement>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  const loadMessages = async (scroll: boolean) => {
    try {
      const list = await api.get<Message[]>('/api/messages?destinataire_id=' + peerId)
      if (list.length && list[0].prenom !== undefined) {
        const peer = list.find((m) => m.expediteur_id === peerId) || list[0]
        setPeerName(peer.prenom + ' ' + peer.nom)
      }
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
    if (!peerId) return
    loadMessages(true)
    // Rafraîchissement périodique (comme le vanilla : 8 s)
    const timer = setInterval(() => loadMessages(false), 8000)
    return () => clearInterval(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [peerId])

  const onSend = async (e: React.FormEvent) => {
    e.preventDefault()
    const fichier = fileRef.current?.files?.[0]
    const texte = contenu.trim()
    if (!texte && !fichier) return
    setError(null)
    try {
      await api.upload('/api/messages', { destinataire_id: peerId, contenu: texte }, { fichier })
      setContenu('')
      if (fileRef.current) fileRef.current.value = ''
      await loadMessages(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="mb-0">
          <i className="fa-solid fa-envelope text-primary"></i> {peerName}
        </h2>
        <Link to="/liste-messagerie" className="btn btn-outline-secondary btn-sm">
          ← Toutes les conversations
        </Link>
      </div>

      <div className="page-card" style={{ maxWidth: 860 }}>
        {error && <div className="alert alert-danger">{error}</div>}

        {!peerId ? (
          <div className="alert alert-danger mb-0">destinataire_id manquant.</div>
        ) : (
          <>
            <div className="chat-zone" ref={zoneRef}>
              {messages === null ? (
                <p className="text-muted text-center mb-0">Chargement…</p>
              ) : messages.length === 0 ? (
                <p className="text-muted text-center mb-0">Aucun message. Écrivez le premier !</p>
              ) : (
                messages.map((m, i) => {
                  const mine = Number(m.expediteur_id) === me?.id
                  return (
                    <div key={i} className={`chat-msg ${mine ? 'me' : 'other'}`}>
                      {m.fichier_url && (
                        <a href={m.fichier_url} target="_blank" rel="noopener">
                          <img
                            src={m.fichier_url}
                            alt="pièce jointe"
                            style={{ maxWidth: 220, borderRadius: 8 }}
                            className="mb-1 d-block"
                          />
                        </a>
                      )}
                      <span style={{ whiteSpace: 'pre-line' }}>{m.contenu}</span>
                      <small>{datetime(m.date_envoi)}</small>
                    </div>
                  )
                })
              )}
            </div>

            <form className="d-flex gap-2 align-items-end" onSubmit={onSend}>
              <div className="flex-grow-1">
                <textarea
                  className="form-control"
                  rows={2}
                  maxLength={5000}
                  placeholder="Votre message…"
                  value={contenu}
                  onChange={(e) => setContenu(e.target.value)}
                />
              </div>
              <div>
                <label className="form-label small text-muted d-block">Image (optionnel)</label>
                <input
                  type="file"
                  ref={fileRef}
                  className="form-control form-control-sm"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                />
              </div>
              <button type="submit" className="btn btn-primary fw-bold">
                <i className="fa-solid fa-paper-plane"></i>
              </button>
            </form>
          </>
        )}
      </div>
    </>
  )
}
