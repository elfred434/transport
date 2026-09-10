import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime, money, StatusBadge } from '../lib/format'

/** Paiement d'un colis — port de paiement.html (?colis_id=&reference=). 
 *  Si colis_id manquant, affiche la liste des paiements de l'utilisateur.
 */

interface PaiementData {
  id: number
  colis_id: number
  nom_colis: string | null
  montant: string | number
  reference: string
  statut: string
  numero_transaction: string | null
  date_paiement: string | null
  methode_paiement?: string | null
}

interface PaiementsMineResponse {
  paiements: PaiementData[]
  stats: {
    montant_total: number
    montant_paye: number
    montant_en_attente: number
    nb_payes: number
    nb_en_attente: number
  }
}

export default function Paiement() {
  const [params] = useSearchParams()
  const colisId = params.get('colis_id')
  const reference = params.get('reference')

  const [p, setP] = useState<PaiementData | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; html: string } | null>(null)

  const [methode, setMethode] = useState<'carte_credit' | 'mobile_money'>('carte_credit')
  const [numeroCarte, setNumeroCarte] = useState('')
  const [expiration, setExpiration] = useState('')
  const [cvv, setCvv] = useState('')
  const [operateur, setOperateur] = useState('')
  const [paidNow, setPaidNow] = useState<{ message: string; numero_transaction: string } | null>(null)

  // Mode liste quand colis_id manquant
  const [liste, setListe] = useState<PaiementData[] | null>(null)
  const [stats, setStats] = useState<PaiementsMineResponse['stats'] | null>(null)
  const [listeError, setListeError] = useState<string | null>(null)

  useEffect(() => {
    if (!colisId) {
      // Charger liste des paiements
      ;(async () => {
        try {
          const data = await api.get<PaiementsMineResponse>('/api/paiements/mine')
          setListe(data.paiements || [])
          setStats(data.stats)
        } catch (e) {
          setListeError(e instanceof ApiError ? e.message : 'Erreur chargement paiements')
        }
      })()
      return
    }
    ;(async () => {
      try {
        const data = await api.get<PaiementData>(
          '/api/paiements/colis/' + encodeURIComponent(colisId) +
            (reference ? '?reference=' + encodeURIComponent(reference) : ''),
        )
        setP(data)
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [colisId, reference])

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!p) return
    setAlert(null)

    const body: Record<string, string> = { methode_paiement: methode }
    if (methode === 'carte_credit') {
      body.numero_carte = numeroCarte
      body.expiration = expiration
      body.cvv = cvv
    } else {
      body.operateur = operateur
    }

    try {
      const data = await api.post<{ message: string; numero_transaction: string }>(
        `/api/paiements/${p.id}/payer`,
        body,
      )
      setPaidNow(data)
      setTimeout(async () => {
        try {
          setP(await api.get<PaiementData>('/api/paiements/colis/' + encodeURIComponent(colisId!)))
          setPaidNow(null)
        } catch {
          /* succès reste */
        }
      }, 2000)
    } catch (err) {
      setAlert({ type: 'danger', html: err instanceof ApiError ? err.message : 'Erreur inconnue' })
    }
  }

  // Mode liste
  if (!colisId) {
    return (
      <>
        <h2 className="mb-4"><i className="fa-solid fa-credit-card text-primary"></i> Mes paiements</h2>
        {listeError && <div className="alert alert-danger">{listeError}</div>}
        {stats && (
          <div className="row g-3 mb-4">
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold">{money(stats.montant_total)}</div><div className="small text-muted">Total</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold text-success">{money(stats.montant_paye)}</div><div className="small text-muted">{stats.nb_payes} payés</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold text-warning">{money(stats.montant_en_attente)}</div><div className="small text-muted">{stats.nb_en_attente} en attente</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><Link to="/poster-colis" className="btn btn-primary btn-sm w-100"><i className="fa-solid fa-box"></i> Poster un colis</Link></div></div>
          </div>
        )}
        <div className="page-card">
          {!liste && !listeError && <div className="text-center text-muted py-4">Chargement…</div>}
          {liste && liste.length === 0 && <div className="text-center text-muted py-4">Aucun paiement. <Link to="/poster-colis">Poster un colis</Link> pour générer un paiement.</div>}
          {liste && liste.length > 0 && (
            <div className="table-responsive">
              <table className="table table-sm align-middle">
                <thead><tr><th>Colis</th><th>Montant</th><th>Réf</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                  {liste.map(pm => (
                    <tr key={pm.id}>
                      <td>{pm.nom_colis || `Colis #${pm.colis_id}`}</td>
                      <td>{money(pm.montant)}</td>
                      <td><code className="small">{pm.reference}</code></td>
                      <td><StatusBadge statut={pm.statut} /></td>
                      <td>
                        {pm.statut === 'paye' ? (
                          <span className="small text-muted">{pm.numero_transaction || ''}</span>
                        ) : (
                          <Link to={`/paiement?colis_id=${pm.colis_id}`} className="btn btn-success btn-sm"><i className="fa-solid fa-credit-card"></i> Payer</Link>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </>
    )
  }

  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-credit-card text-primary"></i> Paiement du colis
      </h2>
      <div className="page-card" style={{ maxWidth: 640 }}>
        {alert && <div className={`alert alert-${alert.type}`}>{alert.html}</div>}
        {paidNow && (
          <div className="alert alert-success">
            {paidNow.message} — Transaction : <code>{paidNow.numero_transaction}</code>
          </div>
        )}

        {error && <div className="alert alert-danger">{error}</div>}
        {!p && !error && <div className="text-center text-muted py-4">Chargement…</div>}

        {p && p.statut === 'paye' && (
          <div className="text-center py-3">
            <i className="fa-solid fa-circle-check text-success" style={{ fontSize: '3.5rem' }}></i>
            <h4 className="mt-3">Paiement déjà effectué</h4>
            <p className="text-muted mb-1">
              {p.nom_colis || ''} — {money(p.montant)}
            </p>
            <p className="small text-muted">
              Transaction : <code>{p.numero_transaction || ''}</code>
              <br />
              Référence : <code>{p.reference}</code>
              <br />
              Payé le : {datetime(p.date_paiement)}
            </p>
            <div className="d-flex gap-2 justify-content-center">
              <Link to="/dashboard" className="btn btn-primary">Retour tableau de bord</Link>
              <Link to="/paiement" className="btn btn-outline-secondary">Mes paiements</Link>
            </div>
          </div>
        )}

        {p && p.statut !== 'paye' && (
          <>
            <div className="border rounded-3 p-3 mb-4">
              <div className="d-flex justify-content-between">
                <span>{p.nom_colis || 'Colis'}</span>
                <strong className="text-primary fs-5">{money(p.montant)}</strong>
              </div>
              <div className="small text-muted mt-1">
                Référence : <code>{p.reference}</code> · Statut : <StatusBadge statut={p.statut} />
              </div>
            </div>

            <form onSubmit={onSubmit}>
              <div className="mb-3">
                <label className="form-label fw-bold">Méthode de paiement</label>
                <div className="d-flex gap-3">
                  <div className="form-check">
                    <input className="form-check-input" type="radio" name="methode" id="m-carte" value="carte_credit" checked={methode === 'carte_credit'} onChange={() => setMethode('carte_credit')} />
                    <label className="form-check-label" htmlFor="m-carte"><i className="fa-solid fa-credit-card"></i> Carte bancaire</label>
                  </div>
                  <div className="form-check">
                    <input className="form-check-input" type="radio" name="methode" id="m-mobile" value="mobile_money" checked={methode === 'mobile_money'} onChange={() => setMethode('mobile_money')} />
                    <label className="form-check-label" htmlFor="m-mobile"><i className="fa-solid fa-mobile-screen"></i> Mobile Money</label>
                  </div>
                </div>
              </div>

              {methode === 'carte_credit' ? (
                <div>
                  <div className="mb-3">
                    <label className="form-label">Numéro de carte</label>
                    <input type="text" className="form-control" inputMode="numeric" placeholder="12 à 19 chiffres" maxLength={23} value={numeroCarte} onChange={(e) => setNumeroCarte(e.target.value)} />
                  </div>
                  <div className="row g-3">
                    <div className="col-6">
                      <label className="form-label">Expiration (MM/AA)</label>
                      <input type="text" className="form-control" placeholder="12/28" maxLength={5} value={expiration} onChange={(e) => setExpiration(e.target.value)} />
                    </div>
                    <div className="col-6">
                      <label className="form-label">CVV</label>
                      <input type="text" className="form-control" inputMode="numeric" placeholder="123" maxLength={4} value={cvv} onChange={(e) => setCvv(e.target.value)} />
                    </div>
                  </div>
                </div>
              ) : (
                <div className="mb-3">
                  <label className="form-label">Opérateur</label>
                  <select className="form-select" value={operateur} onChange={(e) => setOperateur(e.target.value)}>
                    <option value="">— Choisir —</option>
                    <option value="mtn">MTN MoMo</option>
                    <option value="moov">Moov Money</option>
                    <option value="wave">Wave</option>
                    <option value="orange">Orange Money</option>
                  </select>
                </div>
              )}

              <button type="submit" className="btn btn-success btn-lg w-100 fw-bold mt-4"><i className="fa-solid fa-lock"></i> Payer {money(p.montant)}</button>
              <p className="text-muted small text-center mt-2 mb-0"><i className="fa-solid fa-shield-halved"></i> Paiement simulé : aucune donnée sensible n'est stockée.</p>
              <div className="text-center mt-3"><Link to="/paiement" className="small">← Voir tous mes paiements</Link></div>
            </form>
          </>
        )}
      </div>
    </>
  )
}
