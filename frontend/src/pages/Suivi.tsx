import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime, SmartImg, StatusBadge } from '../lib/format'
import '../styles/home-originale.css'

interface Etape { statut: string; date_etape: string; }
interface SuiviResponse { colis: { id: number; nom_colis: string; image_url: string | null; statut: string; ville: string; pays: string; poids: string | number; numero_suivi: string; }; etapes: Etape[]; statut_actuel: string; }

export default function Suivi() {
  const [params] = useSearchParams()
  const [numero, setNumero] = useState(params.get('numero_suivi') || '')
  const [result, setResult] = useState<SuiviResponse | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const track = async (num: string) => {
    if (!num) return
    setLoading(true); setError(null); setResult(null)
    try { const data = await api.get<SuiviResponse>('/api/suivi/' + encodeURIComponent(num)); setResult(data) }
    catch (e) { setError(e instanceof ApiError ? e.message : 'Erreur inconnue') }
    finally { setLoading(false) }
  }
  useEffect(() => { const prefill = params.get('numero_suivi'); if (prefill) track(prefill) }, [params])
  const onSubmit = (e: React.FormEvent) => { e.preventDefault(); track(numero.trim()) }
  const c = result?.colis
  return (
    <div className="home-originale">
      <div className="container d-flex flex-column align-items-center" style={{maxWidth: 960}}>
        <section className="section w-100 d-flex flex-column align-items-center">
          <h2 className="section-title text-center"><i className="fa-solid fa-search-location" style={{color: 'var(--primary-color)'}}></i> Suivi de colis</h2>
          <div className="card w-100" style={{maxWidth: 760, margin: '0 auto', textAlign: 'left', alignItems: 'stretch'}}>
            <form onSubmit={onSubmit} className="d-flex gap-2 mb-4 justify-content-center">
              <input type="text" className="form-control" style={{maxWidth: 400}} placeholder="Numéro de suivi (ex. COLIS1A2B3C4D5E)" value={numero} onChange={(e) => setNumero(e.target.value)} required />
              <button className="btn btn-primary fw-bold" type="submit"><i className="fa-solid fa-magnifying-glass"></i> Suivre</button>
            </form>
            {loading && <p className="text-muted text-center py-3">Recherche…</p>}
            {error && <div className="alert alert-danger text-center">{error}</div>}
            {result && c && (
              <>
                <div className="border rounded-3 p-3 mb-4" style={{background: 'white'}}>
                  <div className="d-flex gap-3 align-items-center flex-wrap justify-content-center">
                    <SmartImg url={c.image_url} alt={c.nom_colis} className="img-colis" />
                    <div className="text-center text-md-start"><div className="fw-bold fs-5">{c.nom_colis} <StatusBadge statut={c.statut} /></div><div className="small text-muted">{c.ville}, {c.pays} · {c.poids} kg · Suivi : <code>{c.numero_suivi}</code></div></div>
                    <div className="ms-md-auto text-center"><div className="text-muted small">Statut actuel</div><StatusBadge statut={result.statut_actuel} /></div>
                  </div>
                </div>
                <h6 className="text-center" style={{color: 'var(--primary-color)', fontWeight: 700}}>Étapes de livraison</h6>
                {result.etapes.length ? (
                  <ul className="timeline mx-auto" style={{maxWidth: 500}}>
                    {result.etapes.map((et, i) => (<li key={i} className={et.statut === 'Livré' ? 'livre' : ''}><strong>{et.statut}</strong><br /><small className="text-muted">{datetime(et.date_etape)}</small></li>))}
                  </ul>
                ) : <p className="text-muted text-center">Aucune étape pour le moment.</p>}
                <div className="text-center mt-3"><Link to="/dashboard" className="btn btn-outline-primary"><i className="fas fa-gauge"></i> Tableau de bord</Link></div>
              </>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
