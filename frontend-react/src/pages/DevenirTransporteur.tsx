import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { useAuth } from '../context/AuthContext'

/** Devenir transporteur (création fiche + premier voyage) — port de devenir-transporteur.html. */

export default function DevenirTransporteur() {
  const { me } = useAuth()
  const navigate = useNavigate()
  const fileRef = useRef<HTMLInputElement>(null)

  const [form, setForm] = useState({
    numero_permis: '',
    vehicule: '',
    compagnie: '',
    adresse: '',
    ville: '',
    pays: '',
    pays_depart: '',
    pays_destination: '',
    date_depart: '',
    heure_depart: '',
    poids_max: '',
    email: '',
    telephone: '',
  })
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; msg: string } | null>(null)

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }))

  useEffect(() => {
    // Pré-remplir email/téléphone depuis la session.
    setForm((f) => ({
      ...f,
      email: me?.email || '',
      telephone: me?.telephone || '',
    }))
    // Pré-remplissage optionnel avec la fiche transporteur existante.
    ;(async () => {
      try {
        const data = await api.get<{ transporteur: Record<string, string | null> | null }>(
          '/api/profile',
        )
        const t = data.transporteur
        if (t)
          setForm((f) => ({
            ...f,
            numero_permis: t.numero_permis || '',
            vehicule: t.vehicule || '',
            compagnie: t.compagnie || '',
            adresse: t.adresse || '',
            ville: t.ville || '',
            pays: t.pays || '',
          }))
      } catch {
        /* pré-remplissage optionnel */
      }
    })()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlert(null)
    // Soumission multipart (comme le vanilla api.upload).
    try {
      const data = await api.upload(
        '/api/voyages',
        { ...form },
        { photo_vehicule: fileRef.current?.files?.[0] },
      )
      setAlert({ type: 'success', msg: (data as { message?: string }).message || 'Voyage proposé.' })
      setTimeout(() => navigate('/dashboard'), 1500)
    } catch (err) {
      setAlert({ type: 'danger', msg: err instanceof ApiError ? err.message : 'Erreur inconnue' })
    }
  }

  const today = new Date().toISOString().split('T')[0]

  return (
    <>
      <h2 className="mb-2">
        <i className="fa-solid fa-id-badge text-primary"></i> Devenir transporteur
      </h2>
      <p className="text-muted">
        Proposez un voyage : votre fiche transporteur est créée automatiquement et votre voyage
        sera visible après validation par l'agence.
      </p>

      <div className="page-card" style={{ maxWidth: 860 }}>
        {alert && <div className={`alert alert-${alert.type}`}>{alert.msg}</div>}

        <form onSubmit={onSubmit}>
          <h6 className="text-primary mb-3">
            <i className="fa-solid fa-id-card"></i> Informations transporteur
          </h6>
          <div className="row g-3 mb-4">
            <div className="col-md-6">
              <label className="form-label">Numéro de permis *</label>
              <input type="text" className="form-control" required value={form.numero_permis} onChange={set('numero_permis')} />
            </div>
            <div className="col-md-6">
              <label className="form-label">Véhicule *</label>
              <input
                type="text"
                className="form-control"
                placeholder="Ex. Toyota HiAce, Immatriculation…"
                required
                value={form.vehicule}
                onChange={set('vehicule')}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label">Compagnie</label>
              <input type="text" className="form-control" value={form.compagnie} onChange={set('compagnie')} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Adresse</label>
              <input type="text" className="form-control" value={form.adresse} onChange={set('adresse')} />
            </div>
            <div className="col-md-2">
              <label className="form-label">Ville</label>
              <input type="text" className="form-control" value={form.ville} onChange={set('ville')} />
            </div>
            <div className="col-md-2">
              <label className="form-label">Pays</label>
              <input type="text" className="form-control" value={form.pays} onChange={set('pays')} />
            </div>
            <div className="col-12">
              <label className="form-label">Photo du véhicule</label>
              <input
                type="file"
                ref={fileRef}
                className="form-control"
                accept="image/jpeg,image/png,image/gif,image/webp"
              />
            </div>
          </div>

          <h6 className="text-primary mb-3">
            <i className="fa-solid fa-plane-departure"></i> Premier voyage proposé
          </h6>
          <div className="row g-3">
            <div className="col-md-3">
              <label className="form-label">Pays de départ *</label>
              <input type="text" className="form-control" required value={form.pays_depart} onChange={set('pays_depart')} />
            </div>
            <div className="col-md-3">
              <label className="form-label">Pays de destination *</label>
              <input
                type="text"
                className="form-control"
                required
                value={form.pays_destination}
                onChange={set('pays_destination')}
              />
            </div>
            <div className="col-md-3">
              <label className="form-label">Date de départ *</label>
              <input type="date" className="form-control" required min={today} value={form.date_depart} onChange={set('date_depart')} />
            </div>
            <div className="col-md-3">
              <label className="form-label">Heure de départ *</label>
              <input type="time" className="form-control" required value={form.heure_depart} onChange={set('heure_depart')} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Poids maximum (kg) *</label>
              <input
                type="number"
                step="0.01"
                min="0.01"
                max="100000"
                className="form-control"
                required
                value={form.poids_max}
                onChange={set('poids_max')}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label">Email de contact *</label>
              <input type="email" className="form-control" required value={form.email} onChange={set('email')} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Téléphone de contact *</label>
              <input type="tel" className="form-control" required value={form.telephone} onChange={set('telephone')} />
            </div>
          </div>

          <button type="submit" className="btn btn-primary btn-lg w-100 fw-bold mt-4">
            <i className="fa-solid fa-paper-plane"></i> Proposer le voyage
          </button>
        </form>
      </div>
    </>
  )
}
