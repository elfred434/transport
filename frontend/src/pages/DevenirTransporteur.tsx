import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import '../styles/home-originale.css'

export default function DevenirTransporteur() {
  const { me } = useAuth()
  const navigate = useNavigate()
  const fileRef = useRef<HTMLInputElement>(null)
  const [form, setForm] = useState({
    numero_permis: '', vehicule: '', compagnie: '', adresse: '', ville: '', pays: '',
    pays_depart: '', pays_destination: '', date_depart: '', heure_depart: '', poids_max: '', email: '', telephone: '',
  })
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; msg: string } | null>(null)
  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [k]: e.target.value }))
  useEffect(() => {
    setForm((f) => ({ ...f, email: me?.email || '', telephone: me?.telephone || '' }))
    ;(async () => {
      try {
        const data = await api.get<{ transporteur: Record<string, string | null> | null }>('/api/profile')
        const t = data.transporteur
        if (t) setForm((f) => ({ ...f, numero_permis: t.numero_permis || '', vehicule: t.vehicule || '', compagnie: t.compagnie || '', adresse: t.adresse || '', ville: t.ville || '', pays: t.pays || '' }))
      } catch {}
    })()
  }, [me])
  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlert(null)
    try {
      const data = await api.upload('/api/voyages', { ...form }, { photo_vehicule: fileRef.current?.files?.[0] })
      setAlert({ type: 'success', msg: (data as { message?: string }).message || 'Voyage proposé.' })
      setTimeout(() => navigate('/dashboard'), 1500)
    } catch (err) {
      setAlert({ type: 'danger', msg: err instanceof ApiError ? err.message : 'Erreur inconnue' })
    }
  }
  const today = new Date().toISOString().split('T')[0]
  return (
    <div className="home-originale">
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 960}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fas fa-id-badge" style={{color: 'var(--primary-color)'}}></i> Devenir transporteur</h2>
          <p className="text-muted text-center" style={{maxWidth: 700}}>Proposez un voyage : votre fiche transporteur est créée automatiquement et votre voyage sera visible après validation par l'agence.</p>
          <div className="card w-100" style={{maxWidth: 860, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            {alert && <div className={`alert alert-${alert.type}`}>{alert.msg}</div>}
            <form onSubmit={onSubmit}>
              <h6 className="mb-3" style={{color: 'var(--primary-color)', fontWeight: 700}}><i className="fa-solid fa-id-card"></i> Informations transporteur</h6>
              <div className="row g-3 mb-4">
                <div className="col-md-6"><label className="form-label fw-bold">Numéro de permis *</label><input type="text" className="form-control" required value={form.numero_permis} onChange={set('numero_permis')} /></div>
                <div className="col-md-6"><label className="form-label fw-bold">Véhicule *</label><input type="text" className="form-control" placeholder="Ex. Toyota HiAce, Immatriculation…" required value={form.vehicule} onChange={set('vehicule')} /></div>
                <div className="col-md-4"><label className="form-label fw-bold">Compagnie</label><input type="text" className="form-control" value={form.compagnie} onChange={set('compagnie')} /></div>
                <div className="col-md-4"><label className="form-label fw-bold">Adresse</label><input type="text" className="form-control" value={form.adresse} onChange={set('adresse')} /></div>
                <div className="col-md-4"><label className="form-label fw-bold">Ville</label><input type="text" className="form-control" value={form.ville} onChange={set('ville')} /></div>
                <div className="col-md-6"><label className="form-label fw-bold">Pays</label><input type="text" className="form-control" value={form.pays} onChange={set('pays')} /></div>
                <div className="col-md-6"><label className="form-label fw-bold">Photo véhicule</label><input type="file" ref={fileRef} className="form-control" accept="image/jpeg,image/png,image/gif,image/webp" /></div>
              </div>
              <h6 className="mb-3" style={{color: 'var(--primary-color)', fontWeight: 700}}><i className="fa-solid fa-plane-departure"></i> Voyage proposé</h6>
              <div className="row g-3">
                <div className="col-md-6"><label className="form-label fw-bold">Pays départ *</label><input type="text" className="form-control" required value={form.pays_depart} onChange={set('pays_depart')} /></div>
                <div className="col-md-6"><label className="form-label fw-bold">Pays destination *</label><input type="text" className="form-control" required value={form.pays_destination} onChange={set('pays_destination')} /></div>
                <div className="col-md-4"><label className="form-label fw-bold">Date départ *</label><input type="date" className="form-control" required min={today} value={form.date_depart} onChange={set('date_depart')} /></div>
                <div className="col-md-4"><label className="form-label fw-bold">Heure départ</label><input type="time" className="form-control" value={form.heure_depart} onChange={set('heure_depart')} /></div>
                <div className="col-md-4"><label className="form-label fw-bold">Poids max (kg) *</label><input type="number" step="0.01" min="0.01" className="form-control" required value={form.poids_max} onChange={set('poids_max')} /></div>
                <div className="col-md-6"><label className="form-label fw-bold">Email</label><input type="email" className="form-control" value={form.email} onChange={set('email')} /></div>
                <div className="col-md-6"><label className="form-label fw-bold">Téléphone</label><input type="tel" className="form-control" value={form.telephone} onChange={set('telephone')} /></div>
              </div>
              <button type="submit" className="btn btn-primary btn-lg w-100 fw-bold mt-4"><i className="fas fa-paper-plane"></i> Proposer le voyage</button>
            </form>
          </div>
        </section>
      </div>
    </div>
  )
}
