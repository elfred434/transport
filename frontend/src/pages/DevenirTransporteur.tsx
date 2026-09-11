import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { CountrySelect, CitySelect } from '../components/LocationSelect'
import '../styles/home-originale.css'

export default function DevenirTransporteur() {
  const { me } = useAuth()
  const navigate = useNavigate()
  const fileRef = useRef<HTMLInputElement>(null)
  const [step, setStep] = useState(1)
  const totalSteps = 5
  const [form, setForm] = useState({
    numero_permis: '', vehicule: '', compagnie: '', adresse: '', ville: '', pays: '',
    pays_depart: '', pays_destination: '', date_depart: '', heure_depart: '', poids_max: '', email: '', telephone: '',
  })
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; msg: string } | null>(null)
  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [k]: e.target.value }))
  const progress = (step / totalSteps) * 100
  const next = () => { setAlert(null); if (step < totalSteps) setStep(s => s + 1) }
  const prev = () => { setAlert(null); if (step > 1) setStep(s => s - 1) }

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
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 900}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fas fa-id-badge" style={{color: 'var(--primary-color)'}}></i> Devenir transporteur</h2>
          
          {/* Progress jeu */}
          <div className="w-100 mb-4" style={{maxWidth: 700}}>
            <div className="d-flex justify-content-between mb-2">
              {[1,2,3,4,5].map(s => (
                <div key={s} className="d-flex flex-column align-items-center">
                  <div className="rounded-circle d-flex align-items-center justify-content-center fw-bold" style={{
                    width: 32, height: 32, fontSize: '0.8rem',
                    background: step >= s ? 'var(--primary-color)' : 'var(--gray-light)',
                    color: step >= s ? 'white' : 'var(--text-color)',
                    transition: 'all 0.3s'
                  }}>{s}</div>
                  <small style={{fontSize: '0.6rem', color: step >= s ? 'var(--primary-color)' : 'var(--text-color)', fontWeight: step===s ? 700 : 400, marginTop: 4, textAlign: 'center'}}>
                    {s===1 ? 'Permis' : s===2 ? 'Adresse' : s===3 ? 'Trajet' : s===4 ? 'Détails' : 'Contact'}
                  </small>
                </div>
              ))}
            </div>
            <div className="progress" style={{height: 8, borderRadius: 10, background: 'var(--gray-light)'}}>
              <div className="progress-bar" style={{width: `${progress}%`, background: 'linear-gradient(90deg, var(--primary-color), var(--primary-light))', transition: 'width 0.5s ease', borderRadius: 10}}></div>
            </div>
            <div className="text-center mt-2 small text-muted">Étape {step}/{totalSteps} — {Math.round(progress)}%</div>
          </div>

          <div className="card w-100" style={{maxWidth: 700, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            {alert && <div className={`alert alert-${alert.type} text-center`}>{alert.msg}</div>}
            <form onSubmit={onSubmit}>
              {step === 1 && (
                <div>
                  <div className="text-center mb-4">
                    <div style={{margin: '0 auto 1rem', background: 'rgba(37,99,235,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-id-card" style={{color: 'var(--primary-color)', fontSize: '1.5rem'}}></i></div>
                    <h5>Identité transporteur</h5><p className="text-muted small">3 infos pour commencer</p>
                  </div>
                  <div className="mb-3"><label className="form-label fw-bold">Numéro de permis *</label><input type="text" className="form-control form-control-lg" placeholder="Ex. PERM123456" required value={form.numero_permis} onChange={set('numero_permis')} /></div>
                  <div className="mb-3"><label className="form-label fw-bold">Véhicule *</label><input type="text" className="form-control form-control-lg" placeholder="Ex. Toyota HiAce" required value={form.vehicule} onChange={set('vehicule')} /></div>
                  <div className="mb-3"><label className="form-label fw-bold">Compagnie</label><input type="text" className="form-control form-control-lg" placeholder="Ex. SPIISTMOVE Express" value={form.compagnie} onChange={set('compagnie')} /></div>
                </div>
              )}
              {step === 2 && (
                <div>
                  <div className="text-center mb-4">
                    <div style={{margin: '0 auto 1rem', background: 'rgba(16,185,129,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-location-dot" style={{color: 'var(--secondary-color)', fontSize: '1.5rem'}}></i></div>
                    <h5>Adresse</h5><p className="text-muted small">Où êtes-vous basé ?</p>
                  </div>
                  <div className="mb-3"><label className="form-label fw-bold">Adresse</label><input type="text" className="form-control form-control-lg" placeholder="Ex. Quartier Hinkoudé" value={form.adresse} onChange={set('adresse')} /></div>
                  <div className="mb-3">
                    <CountrySelect
                      label="Pays"
                      value={form.pays}
                      onChange={(v) => setForm((f) => ({ ...f, pays: v, ville: f.pays === v ? f.ville : '' }))}
                      placeholder="Rechercher un pays…"
                    />
                  </div>
                  <div className="mb-3">
                    <CitySelect
                      label="Ville"
                      pays={form.pays}
                      value={form.ville}
                      onChange={(v) => setForm((f) => ({ ...f, ville: v }))}
                      placeholder="Choisir ou taper une ville…"
                    />
                  </div>
                </div>
              )}
              {step === 3 && (
                <div>
                  <div className="text-center mb-4">
                    <div style={{margin: '0 auto 1rem', background: 'rgba(245,158,11,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-route" style={{color: 'var(--accent-color)', fontSize: '1.5rem'}}></i></div>
                    <h5>Trajet proposé</h5><p className="text-muted small">Votre prochain voyage</p>
                  </div>
                  <div className="mb-3">
                    <CountrySelect
                      label="Pays départ *"
                      value={form.pays_depart}
                      onChange={(v) => setForm((f) => ({ ...f, pays_depart: v }))}
                      required
                      placeholder="Rechercher un pays…"
                    />
                  </div>
                  <div className="mb-3">
                    <CountrySelect
                      label="Pays destination *"
                      value={form.pays_destination}
                      onChange={(v) => setForm((f) => ({ ...f, pays_destination: v }))}
                      required
                      placeholder="Rechercher un pays…"
                    />
                  </div>
                  <div className="mb-3"><label className="form-label fw-bold">Date départ *</label><input type="date" className="form-control form-control-lg" required min={today} value={form.date_depart} onChange={set('date_depart')} /></div>
                </div>
              )}
              {step === 4 && (
                <div>
                  <div className="text-center mb-4">
                    <div style={{margin: '0 auto 1rem', background: 'rgba(37,99,235,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-clock" style={{color: 'var(--primary-color)', fontSize: '1.5rem'}}></i></div>
                    <h5>Détails voyage</h5><p className="text-muted small">3 dernières infos voyage</p>
                  </div>
                  <div className="mb-3"><label className="form-label fw-bold">Heure départ</label><input type="time" className="form-control form-control-lg" value={form.heure_depart} onChange={set('heure_depart')} /></div>
                  <div className="mb-3"><label className="form-label fw-bold">Poids max (kg) *</label><input type="number" step="0.01" min="0.01" className="form-control form-control-lg" required value={form.poids_max} onChange={set('poids_max')} /></div>
                  <div className="mb-3"><label className="form-label fw-bold">Email</label><input type="email" className="form-control form-control-lg" value={form.email} onChange={set('email')} /></div>
                </div>
              )}
              {step === 5 && (
                <div>
                  <div className="text-center mb-4">
                    <div style={{margin: '0 auto 1rem', background: 'rgba(16,185,129,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-phone" style={{color: 'var(--secondary-color)', fontSize: '1.5rem'}}></i></div>
                    <h5>Contact final</h5><p className="text-muted small">On y est presque !</p>
                  </div>
                  <div className="mb-3"><label className="form-label fw-bold">Téléphone</label><input type="tel" className="form-control form-control-lg" placeholder="Ex. 97000000" value={form.telephone} onChange={set('telephone')} /></div>
                  <div className="mb-3"><label className="form-label fw-bold">Photo véhicule</label><input type="file" ref={fileRef} className="form-control form-control-lg" accept="image/jpeg,image/png,image/gif,image/webp" /></div>
                  <div className="alert alert-info text-center small"><i className="fas fa-info-circle"></i> Récap : {form.vehicule} — {form.pays_depart} → {form.pays_destination} le {form.date_depart}</div>
                </div>
              )}

              <div className="d-flex gap-2 justify-content-between align-items-stretch flex-wrap mt-4 form-nav-btns">
                {step > 1 ? (
                  <button type="button" className="btn btn-outline-primary btn-lg flex-grow-1" onClick={prev}>
                    <i className="fas fa-arrow-left"></i> Précédent
                  </button>
                ) : <div className="flex-grow-1"></div>}
                {step < totalSteps ? (
                  <button type="button" className="btn btn-primary btn-lg flex-grow-1" onClick={next}>
                    Suivant <i className="fas fa-arrow-right"></i>
                  </button>
                ) : (
                  <button type="submit" className="btn btn-primary btn-lg flex-grow-1">
                    <i className="fas fa-check"></i> Devenir transporteur
                  </button>
                )}
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  )
}
