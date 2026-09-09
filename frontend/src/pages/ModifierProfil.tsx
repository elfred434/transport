import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ApiError } from '../lib/api'

/** Modifier mon profil — port de modifier-profil.html. */

interface ProfileUser {
  nom: string
  prenom: string
  email: string
  telephone: string | null
  photo_url: string | null
}

export default function ModifierProfil() {
  const navigate = useNavigate()
  const [nom, setNom] = useState('')
  const [prenom, setPrenom] = useState('')
  const [email, setEmail] = useState('')
  const [telephone, setTelephone] = useState('')
  const [photoUrl, setPhotoUrl] = useState<string | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; msg: string } | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        const data = await api.get<{ user: ProfileUser }>('/api/profile')
        const u = data.user
        setNom(u.nom || '')
        setPrenom(u.prenom || '')
        setEmail(u.email || '')
        setTelephone(u.telephone || '')
        setPhotoUrl(u.photo_url)
      } catch (e) {
        setAlert({ type: 'danger', msg: e instanceof ApiError ? e.message : 'Erreur inconnue' })
      }
    })()
  }, [])

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlert(null)
    try {
      await api.upload(
        '/api/profile',
        { nom, prenom, email, telephone },
        { photo_profil: fileRef.current?.files?.[0] },
      )
      setAlert({ type: 'success', msg: 'Profil mis à jour.' })
      setTimeout(() => navigate('/profil'), 1200)
    } catch (err) {
      setAlert({ type: 'danger', msg: err instanceof ApiError ? err.message : 'Erreur inconnue' })
    }
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-user-pen text-primary"></i> Modifier mon profil
      </h2>

      <div className="page-card" style={{ maxWidth: 640 }}>
        {alert && <div className={`alert alert-${alert.type}`}>{alert.msg}</div>}
        <form onSubmit={onSubmit}>
          <div className="text-center mb-3">
            {photoUrl && <img src={photoUrl} className="avatar-lg" alt="" />}
          </div>
          <div className="mb-3">
            <label className="form-label">Nom *</label>
            <input type="text" className="form-control" required value={nom} onChange={(e) => setNom(e.target.value)} />
          </div>
          <div className="mb-3">
            <label className="form-label">Prénom *</label>
            <input type="text" className="form-control" required value={prenom} onChange={(e) => setPrenom(e.target.value)} />
          </div>
          <div className="mb-3">
            <label className="form-label">Email *</label>
            <input type="email" className="form-control" required value={email} onChange={(e) => setEmail(e.target.value)} />
          </div>
          <div className="mb-3">
            <label className="form-label">Téléphone</label>
            <input type="tel" className="form-control" value={telephone} onChange={(e) => setTelephone(e.target.value)} />
          </div>
          <div className="mb-3">
            <label className="form-label">Photo de profil</label>
            <input
              type="file"
              ref={fileRef}
              className="form-control"
              accept="image/jpeg,image/png,image/gif,image/webp"
            />
          </div>
          <div className="d-flex gap-2">
            <button type="submit" className="btn btn-primary flex-grow-1 fw-bold">
              <i className="fa-solid fa-floppy-disk"></i> Enregistrer
            </button>
            <Link to="/profil" className="btn btn-outline-secondary">
              Annuler
            </Link>
          </div>
        </form>
      </div>
    </>
  )
}
