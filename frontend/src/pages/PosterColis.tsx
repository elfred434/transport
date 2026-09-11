import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { money } from '../lib/format'
import { CountrySelect, CitySelect } from '../components/LocationSelect'
import '../styles/home-originale.css'

interface CreateResponse {
  colis_id: number
  numero_suivi: string
  paiement: { id: number; reference: string; montant: number }
}

function calcPrix(poids: string): number | null {
  const p = parseFloat(poids)
  if (!p || p <= 0) return null
  return Math.round(Math.max(1000, 1000 + 1000 * p) * 1.2 * 100) / 100
}

export default function PosterColis() {
  const formRef = useRef<HTMLFormElement>(null)
  const [step, setStep] = useState(1)
  const totalSteps = 4
  const [poids, setPoids] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [created, setCreated] = useState<CreateResponse | null>(null)
  const [formData, setFormData] = useState({
    nom_colis: '',
    type_produit: '',
    nombre_produits: '1',
    dimensions: '',
    date_limite: '',
    pays: '',
    ville: '',
    adresse_depart: '',
    adresse_destination: '',
  })

  const prix = calcPrix(poids)
  const today = new Date().toISOString().split('T')[0]
  const progress = (step / totalSteps) * 100

  const next = () => { setError(null); if (step < totalSteps) setStep(s => s + 1) }
  const prev = () => { setError(null); if (step > 1) setStep(s => s - 1) }

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    const form = formRef.current
    if (!form) return
    const fd = new FormData(form)
    const file = (fd.get('image_colis') as File | null) || null
    try {
      const data = await api.upload<CreateResponse>(
        '/api/colis',
        {
          nom_colis: formData.nom_colis,
          type_produit: formData.type_produit,
          nombre_produits: formData.nombre_produits,
          poids: poids,
          dimensions: formData.dimensions,
          date_limite: formData.date_limite,
          pays: formData.pays,
          ville: formData.ville,
          adresse_depart: formData.adresse_depart,
          adresse_destination: formData.adresse_destination,
        },
        { image_colis: file && file.size > 0 ? file : null },
      )
      setCreated(data)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  const update = (k: keyof typeof formData) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setFormData(f => ({ ...f, [k]: e.target.value }))

  return (
    <div className="home-originale">
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 900}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fas fa-box" style={{color: 'var(--primary-color)'}}></i> Poster un colis</h2>
          
          {/* Barre de progression jeu */}
          <div className="w-100 mb-4" style={{maxWidth: 700}}>
            <div className="d-flex justify-content-between mb-2">
              {[1,2,3,4].map(s => (
                <div key={s} className="d-flex flex-column align-items-center">
                  <div className="rounded-circle d-flex align-items-center justify-content-center fw-bold" style={{
                    width: 36, height: 36,
                    background: step >= s ? 'var(--primary-color)' : 'var(--gray-light)',
                    color: step >= s ? 'white' : 'var(--text-color)',
                    transition: 'all 0.3s'
                  }}>{s}</div>
                  <small className="mt-1" style={{fontSize: '0.7rem', color: step >= s ? 'var(--primary-color)' : 'var(--text-color)', fontWeight: step===s ? 700 : 400}}>
                    {s===1 ? 'Colis' : s===2 ? 'Détails' : s===3 ? 'Destination' : 'Photo'}
                  </small>
                </div>
              ))}
            </div>
            <div className="progress" style={{height: 8, borderRadius: 10, background: 'var(--gray-light)'}}>
              <div className="progress-bar" style={{width: `${progress}%`, background: 'linear-gradient(90deg, var(--primary-color), var(--primary-light))', transition: 'width 0.5s ease', borderRadius: 10}}></div>
            </div>
            <div className="text-center mt-2 small text-muted">Étape {step} sur {totalSteps} — {Math.round(progress)}% complété</div>
          </div>

          <div className="card w-100" style={{maxWidth: 700, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            {error && <div className="alert alert-danger text-center">{error}</div>}
            
            {created ? (
              <div className="text-center py-4">
                <div className="card-icon" style={{margin: '0 auto 1rem'}}><i className="fa-solid fa-circle-check" style={{color: 'var(--secondary-color)', fontSize: '3rem'}}></i></div>
                <h4>Colis posté avec succès !</h4>
                <p>Numéro de suivi : <code className="fs-5">{created.numero_suivi}</code></p>
                <p className="text-muted">Une demande de paiement a été créée.</p>
                <div className="d-flex gap-2 justify-content-center flex-wrap">
                  <Link to={`/paiement?colis_id=${created.colis_id}`} className="btn btn-primary"><i className="fa-solid fa-credit-card"></i> Payer maintenant</Link>
                  <Link to="/dashboard" className="btn btn-outline-primary"><i className="fas fa-gauge"></i> Tableau de bord</Link>
                </div>
              </div>
            ) : (
              <form ref={formRef} onSubmit={onSubmit}>
                {/* ÉTAPE 1 : Infos colis (3 champs) */}
                {step === 1 && (
                  <div className="animate-step">
                    <div className="text-center mb-4">
                      <div className="card-icon" style={{margin: '0 auto 1rem', background: 'rgba(37,99,235,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-box" style={{color: 'var(--primary-color)', fontSize: '1.5rem'}}></i></div>
                      <h5 style={{color: 'var(--dark-color)'}}>Informations du colis</h5>
                      <p className="text-muted small">Décrivez votre colis en 3 infos simples</p>
                    </div>
                    <div className="mb-3"><label className="form-label fw-bold">Nom du colis *</label><input type="text" className="form-control form-control-lg" placeholder="Ex. Documents importants" value={formData.nom_colis} onChange={update('nom_colis')} maxLength={100} required /></div>
                    <div className="mb-3"><label className="form-label fw-bold">Type de produit *</label><select className="form-select form-select-lg" required value={formData.type_produit} onChange={update('type_produit')}><option value="">— Choisir —</option><option value="alimentaire">Alimentaire</option><option value="electronique">Électronique</option><option value="vetements">Vêtements</option><option value="documents">Documents</option><option value="autre">Autre</option></select></div>
                    <div className="mb-3"><label className="form-label fw-bold">Nombre de produits *</label><input type="number" min={1} max={10000} className="form-control form-control-lg" value={formData.nombre_produits} onChange={update('nombre_produits')} required /></div>
                  </div>
                )}

                {/* ÉTAPE 2 : Détails (3 champs) */}
                {step === 2 && (
                  <div className="animate-step">
                    <div className="text-center mb-4">
                      <div className="card-icon" style={{margin: '0 auto 1rem', background: 'rgba(16,185,129,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-weight-hanging" style={{color: 'var(--secondary-color)', fontSize: '1.5rem'}}></i></div>
                      <h5 style={{color: 'var(--dark-color)'}}>Poids & Dimensions</h5>
                      <p className="text-muted small">3 infos pour calculer le prix</p>
                    </div>
                    <div className="mb-3"><label className="form-label fw-bold">Poids (kg) *</label><input type="number" step="0.01" min="0.01" max="1000" className="form-control form-control-lg" value={poids} onChange={(e) => setPoids(e.target.value)} placeholder="Ex. 2.5" required /></div>
                    <div className="mb-3"><label className="form-label fw-bold">Dimensions (LxlxH cm)</label><input type="text" className="form-control form-control-lg" placeholder="Ex. 30x20x15" value={formData.dimensions} onChange={update('dimensions')} maxLength={50} /></div>
                    <div className="mb-3"><label className="form-label fw-bold">Date limite *</label><input type="date" className="form-control form-control-lg" min={today} value={formData.date_limite} onChange={update('date_limite')} required /></div>
                    <div className="prix-estime mt-3 p-3 text-center" style={{background: '#f1f8ff', borderRadius: '10px', border: '2px dashed var(--primary-color)'}}>
                      <i className="fa-solid fa-calculator" style={{color: 'var(--primary-color)'}}></i> Prix estimé : <strong style={{fontSize: '1.2rem', color: 'var(--primary-color)'}}>{prix !== null ? money(prix) : '—'}</strong>
                    </div>
                  </div>
                )}

                {/* ÉTAPE 3 : Destination (3 champs) */}
                {step === 3 && (
                  <div className="animate-step">
                    <div className="text-center mb-4">
                      <div className="card-icon" style={{margin: '0 auto 1rem', background: 'rgba(245,158,11,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-map-location-dot" style={{color: 'var(--accent-color)', fontSize: '1.5rem'}}></i></div>
                      <h5 style={{color: 'var(--dark-color)'}}>Destination</h5>
                      <p className="text-muted small">Où va votre colis ?</p>
                    </div>
                    <div className="mb-3">
                      <CountrySelect
                        label="Pays destination *"
                        value={formData.pays}
                        onChange={(v) => setFormData({ ...formData, pays: v, ville: formData.pays === v ? formData.ville : '' })}
                        required
                        placeholder="Rechercher un pays…"
                      />
                    </div>
                    <div className="mb-3">
                      <CitySelect
                        label="Ville destination *"
                        pays={formData.pays}
                        value={formData.ville}
                        onChange={(v) => setFormData({ ...formData, ville: v })}
                        required
                        placeholder="Choisir ou taper une ville…"
                      />
                    </div>
                    <div className="mb-3"><label className="form-label fw-bold">Adresse départ *</label><input type="text" className="form-control form-control-lg" placeholder="Ex. Porto-Novo, Hinkoudé" value={formData.adresse_depart} onChange={update('adresse_depart')} required /></div>
                  </div>
                )}

                {/* ÉTAPE 4 : Final (2 champs) */}
                {step === 4 && (
                  <div className="animate-step">
                    <div className="text-center mb-4">
                      <div className="card-icon" style={{margin: '0 auto 1rem', background: 'rgba(37,99,235,0.1)', width: 60, height: 60, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center'}}><i className="fas fa-camera" style={{color: 'var(--primary-color)', fontSize: '1.5rem'}}></i></div>
                      <h5 style={{color: 'var(--dark-color)'}}>Finalisation</h5>
                      <p className="text-muted small">Dernière étape !</p>
                    </div>
                    <div className="mb-3"><label className="form-label fw-bold">Adresse destination *</label><input type="text" className="form-control form-control-lg" placeholder="Ex. Cotonou, Akpakpa" value={formData.adresse_destination} onChange={update('adresse_destination')} required /></div>
                    <div className="mb-3"><label className="form-label fw-bold">Photo du colis (optionnel)</label><input type="file" name="image_colis" className="form-control form-control-lg" accept="image/jpeg,image/png,image/gif,image/webp" /></div>
                    <div className="alert alert-info text-center"><i className="fas fa-info-circle"></i> Vérifiez vos infos : <strong>{formData.nom_colis}</strong> — {poids}kg — {formData.pays} → {formData.ville}<br/>Prix : <strong>{prix !== null ? money(prix) : '—'}</strong></div>
                  </div>
                )}

                {/* Boutons navigation */}
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
                      <i className="fas fa-paper-plane"></i> Poster le colis
                    </button>
                  )}
                </div>
              </form>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
