import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, Auth } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import GoogleOneTap from '../components/GoogleOneTap'

interface RegisterResponse {
  token: string
  refresh?: string
  user: { id: number }
}

/** Inscription — port de register.html (multipart avec photo optionnelle). */
export default function Register() {
  const formRef = useRef<HTMLFormElement>(null)
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()
  const { setToken } = useAuth()

  useEffect(() => {
    if (Auth.isLoggedIn()) navigate('/dashboard', { replace: true })
  }, [navigate])

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    const form = formRef.current
    if (!form) return

    const fd = new FormData(form)
    const file = (fd.get('photo_profil') as File | null) || null

    try {
      const data = await api.upload<RegisterResponse>(
        '/api/auth/register',
        {
          nom: String(fd.get('nom') || ''),
          prenom: String(fd.get('prenom') || ''),
          email: String(fd.get('email') || ''),
          password: String(fd.get('password') || ''),
          tel: String(fd.get('tel') || ''),
        },
        { photo_profil: file && file.size > 0 ? file : null },
      )
      await setToken({ token: data.token, refresh: data.refresh })
      navigate('/dashboard', { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <div className="bg-light">
      <div className="container d-flex align-items-center justify-content-center min-vh-100">
        <div className="card shadow-lg border-0 rounded-4 p-4" style={{ maxWidth: '420px', width: '100%' }}>
          <h2 className="text-center text-primary mb-4">
            <i className="fa-solid fa-user-plus"></i> Inscription
          </h2>

          {error && <div className="alert alert-danger">{error}</div>}

          <form ref={formRef} onSubmit={onSubmit} autoComplete="off">
            <div className="mb-3">
              <label className="form-label">Nom</label>
              <input type="text" name="nom" className="form-control" required />
            </div>
            <div className="mb-3">
              <label className="form-label">Prénom</label>
              <input type="text" name="prenom" className="form-control" required />
            </div>
            <div className="mb-3">
              <label className="form-label">Email</label>
              <input type="email" name="email" className="form-control" required />
            </div>
            <div className="mb-3">
              <label className="form-label">
                Mot de passe <small className="text-muted">(8 caractères min.)</small>
              </label>
              <input type="password" name="password" className="form-control" minLength={8} required />
            </div>
            <div className="mb-3">
              <label className="form-label">Téléphone</label>
              <input type="tel" name="tel" className="form-control" />
            </div>
            <div className="mb-3">
              <label className="form-label">Photo de profil</label>
              <input
                type="file"
                name="photo_profil"
                className="form-control"
                accept="image/*"
              />
            </div>
            <button type="submit" className="btn btn-primary w-100 fw-bold">
              <i className="fa-solid fa-user-plus"></i> S'inscrire
            </button>
            <div className="text-center mt-3">
              Déjà un compte ?{' '}
              <Link to="/login" className="text-primary text-decoration-underline">
                Connectez-vous
              </Link>
            </div>
          </form>
          <GoogleOneTap mode="register" />
        </div>
      </div>
    </div>
  )
}
