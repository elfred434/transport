import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { money } from '../lib/format'

/** Poster un colis — port de poster-colis.html. */

interface CreateResponse {
  colis_id: number
  numero_suivi: string
  paiement: { id: number; reference: string; montant: number }
}

/** Même formule que le serveur (affichage indicatif). */
function calcPrix(poids: string): number | null {
  const p = parseFloat(poids)
  if (!p || p <= 0) return null
  return Math.round(Math.max(1000, 1000 + 1000 * p) * 1.2 * 100) / 100
}

export default function PosterColis() {
  const formRef = useRef<HTMLFormElement>(null)
  const [poids, setPoids] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [created, setCreated] = useState<CreateResponse | null>(null)

  const prix = calcPrix(poids)
  const today = new Date().toISOString().split('T')[0]

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
          nom_colis: String(fd.get('nom_colis') || ''),
          type_produit: String(fd.get('type_produit') || ''),
          nombre_produits: String(fd.get('nombre_produits') || ''),
          poids: String(fd.get('poids') || ''),
          dimensions: String(fd.get('dimensions') || ''),
          date_limite: String(fd.get('date_limite') || ''),
          pays: String(fd.get('pays') || ''),
          ville: String(fd.get('ville') || ''),
          adresse_depart: String(fd.get('adresse_depart') || ''),
          adresse_destination: String(fd.get('adresse_destination') || ''),
        },
        { image_colis: file && file.size > 0 ? file : null },
      )
      setCreated(data)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur inconnue')
    }
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-box text-primary"></i> Poster un colis
      </h2>

      <div className="page-card" style={{ maxWidth: 860 }}>
        {error && <div className="alert alert-danger">{error}</div>}

        <form ref={formRef} onSubmit={onSubmit} style={{ display: created ? 'none' : undefined }}>
          <div className="row g-3">
            <div className="col-md-6">
              <label className="form-label">Nom du colis *</label>
              <input type="text" name="nom_colis" className="form-control" maxLength={100} required />
            </div>
            <div className="col-md-3">
              <label className="form-label">Type de produit *</label>
              <select name="type_produit" className="form-select" required defaultValue="">
                <option value="">— Choisir —</option>
                <option value="alimentaire">Alimentaire</option>
                <option value="electronique">Électronique</option>
                <option value="vetements">Vêtements</option>
                <option value="documents">Documents</option>
                <option value="autre">Autre</option>
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Nombre de produits *</label>
              <input type="number" name="nombre_produits" min={1} max={10000} defaultValue={1} className="form-control" required />
            </div>
            <div className="col-md-4">
              <label className="form-label">Poids (kg) *</label>
              <input
                type="number"
                name="poids"
                step="0.01"
                min="0.01"
                max="1000"
                className="form-control"
                value={poids}
                onChange={(e) => setPoids(e.target.value)}
                required
              />
            </div>
            <div className="col-md-4">
              <label className="form-label">Dimensions (LxlxH cm)</label>
              <input type="text" name="dimensions" className="form-control" maxLength={50} placeholder="30x20x15" />
            </div>
            <div className="col-md-4">
              <label className="form-label">Date limite *</label>
              <input type="date" name="date_limite" className="form-control" min={today} required />
            </div>
            <div className="col-md-6">
              <label className="form-label">Pays de destination *</label>
              <input type="text" name="pays" className="form-control" required />
            </div>
            <div className="col-md-6">
              <label className="form-label">Ville de destination *</label>
              <input type="text" name="ville" className="form-control" required />
            </div>
            <div className="col-md-6">
              <label className="form-label">Adresse de départ *</label>
              <input type="text" name="adresse_depart" className="form-control" required />
            </div>
            <div className="col-md-6">
              <label className="form-label">Adresse de destination *</label>
              <input type="text" name="adresse_destination" className="form-control" required />
            </div>
            <div className="col-12">
              <label className="form-label">Photo du colis</label>
              <input type="file" name="image_colis" className="form-control" accept="image/jpeg,image/png,image/gif,image/webp" />
            </div>
          </div>

          <div className="alert alert-secondary mt-3">
            <i className="fa-solid fa-calculator"></i> Prix estimé :{' '}
            <strong>{prix !== null ? money(prix) : '—'}</strong>{' '}
            <span className="small text-muted">
              (1 000 F de base + 1 000 F par kg, majoré de 20 %, minimum 1 200 F)
            </span>
          </div>

          <button type="submit" className="btn btn-primary btn-lg w-100 fw-bold">
            <i className="fa-solid fa-paper-plane"></i> Poster le colis
          </button>
        </form>

        {created && (
          <div className="text-center py-4">
            <i className="fa-solid fa-circle-check text-success" style={{ fontSize: '3.5rem' }}></i>
            <h4 className="mt-3">Colis posté avec succès !</h4>
            <p>
              Numéro de suivi : <code className="fs-5">{created.numero_suivi}</code>
            </p>
            <p className="text-muted">
              Une demande de paiement a été créée. Payez pour accélérer la validation.
            </p>
            <div className="d-flex gap-2 justify-content-center flex-wrap">
              <Link to={`/paiement?colis_id=${created.colis_id}`} className="btn btn-success">
                <i className="fa-solid fa-credit-card"></i> Payer maintenant
              </Link>
              <Link to="/dashboard" className="btn btn-outline-primary">
                <i className="fa-solid fa-gauge"></i> Tableau de bord
              </Link>
            </div>
          </div>
        )}
      </div>
    </>
  )
}
