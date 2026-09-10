import { useEffect, useState, useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime, money, StatusBadge } from '../lib/format'

/** Paiement 100% Kkiapay — port de paiement.html (?colis_id=&reference=). 
 *  Si colis_id manquant, affiche la liste des paiements.
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
  prix_estime?: string | number
  kkiapay?: {
    public_key: string
    sandbox: boolean
    configured: boolean
  }
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
  kkiapay?: {
    public_key: string
    sandbox: boolean
    configured: boolean
  }
}

declare global {
  interface Window {
    openKkiapayWidget: (opts: any) => void
    addSuccessListener: (cb: (res: { transactionId: string }) => void) => void
    addFailedListener: (cb: (err: any) => void) => void
    removeKkiapayListener?: (event: string, cb: any) => void
  }
}

const KKIAPAY_SCRIPT = 'https://cdn.kkiapay.me/k.js'
const PUBLIC_KEY_FALLBACK = import.meta.env.VITE_KKIAPAY_PUBLIC_KEY || '95ab811a8947a69d7d57d3a9ffdc8f9ae3abb4d'
const SANDBOX = (import.meta.env.VITE_KKIAPAY_SANDBOX || 'true') === 'true'

function loadKkiapayScript(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${KKIAPAY_SCRIPT}"]`)) {
      resolve()
      return
    }
    const s = document.createElement('script')
    s.src = KKIAPAY_SCRIPT
    s.async = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('Impossible de charger Kkiapay'))
    document.body.appendChild(s)
  })
}

export default function Paiement() {
  const [params] = useSearchParams()
  const colisId = params.get('colis_id')
  const reference = params.get('reference')

  const [p, setP] = useState<PaiementData | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [alert, setAlert] = useState<{ type: 'danger' | 'success'; html: string } | null>(null)
  const [verifying, setVerifying] = useState(false)
  const [paidNow, setPaidNow] = useState<{ message: string; numero_transaction: string } | null>(null)
  const [scriptReady, setScriptReady] = useState(false)
  const [lastTxId, setLastTxId] = useState<string | null>(null)

  // Liste mode
  const [liste, setListe] = useState<PaiementData[] | null>(null)
  const [stats, setStats] = useState<PaiementsMineResponse['stats'] | null>(null)
  const [listeError, setListeError] = useState<string | null>(null)
  const [kkiapayCfg, setKkiapayCfg] = useState<{ public_key: string; sandbox: boolean; configured: boolean } | null>(null)

  // Charger script Kkiapay une fois
  useEffect(() => {
    loadKkiapayScript()
      .then(() => setScriptReady(true))
      .catch((e) => setAlert({ type: 'danger', html: e.message }))
  }, [])

  // Mode liste ou single
  useEffect(() => {
    if (!colisId) {
      ;(async () => {
        try {
          const data = await api.get<PaiementsMineResponse>('/api/paiements/mine')
          setListe(data.paiements || [])
          setStats(data.stats)
          if (data.kkiapay) setKkiapayCfg(data.kkiapay)
        } catch (e) {
          setListeError(e instanceof ApiError ? e.message : 'Erreur chargement paiements')
        }
        // aussi essayer config publique
        try {
          const cfg = await api.get<{ public_key: string; sandbox: boolean; configured: boolean }>('/api/kkiapay/public-config')
          setKkiapayCfg(cfg)
        } catch {}
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
        if (data.kkiapay) setKkiapayCfg(data.kkiapay)
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [colisId, reference])

  const verifyWithBackend = useCallback(async (transactionId: string) => {
    if (!p) return
    setLastTxId(transactionId)
    setVerifying(true)
    setAlert(null)
    try {
      const res = await api.post<{ message: string; numero_transaction: string }>(
        `/api/paiements/${p.id}/verify-kkiapay`,
        { transactionId }
      )
      setPaidNow(res)
      // recharger paiement
      try {
        const updated = await api.get<PaiementData>('/api/paiements/colis/' + encodeURIComponent(colisId!))
        setP(updated)
      } catch {}
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Vérification Kkiapay échouée'
      setAlert({ type: 'danger', html: `${msg}<br/><small class="mt-2 d-block">TransactionId: <code>${transactionId}</code> - Conservez ce reçu. Le backend est en SANDBOX, la vérification peut nécessiter activation des clés. Essayez de re-cliquer ou contactez admin pour validation manuelle.</small>` })
    } finally {
      setVerifying(false)
    }
  }, [p, colisId])

  const openWidget = useCallback(() => {
    if (!p) return
    if (!scriptReady || typeof window.openKkiapayWidget !== 'function') {
      setAlert({ type: 'danger', html: 'Widget Kkiapay non chargé, réessayez' })
      return
    }
    const publicKey = kkiapayCfg?.public_key || PUBLIC_KEY_FALLBACK
    const sandbox = kkiapayCfg?.sandbox ?? SANDBOX
    const amount = Math.round(Number(p.montant)) // XOF entier

    // Nettoyer anciens listeners
    try {
      // @ts-ignore
      if (window.removeKkiapayListener) {
        // noop
      }
    } catch {}

    window.addSuccessListener(async (response) => {
      console.log('Kkiapay success', response)
      const txId = response?.transactionId
      if (txId) {
        await verifyWithBackend(txId)
      } else {
        setAlert({ type: 'danger', html: 'TransactionId Kkiapay manquant' })
      }
    })

    window.addFailedListener((err) => {
      console.log('Kkiapay failed', err)
      setAlert({ type: 'danger', html: 'Paiement Kkiapay échoué ou annulé' })
    })

    window.openKkiapayWidget({
      amount,
      key: publicKey,
      sandbox,
      reason: `Paiement colis ${p.nom_colis || p.colis_id} - ${p.reference}`,
      name: '', // peut être rempli via user
      email: '',
      phone: '',
      data: p.reference, // on passe la référence
      partnerId: String(p.colis_id),
      paymentmethod: 'momo,card', // autorise mobile money + carte
      countries: ['BJ', 'CI', 'TG', 'SN', 'NE'],
      theme: '#0d6efd',
      position: 'center',
    })
  }, [p, scriptReady, kkiapayCfg, verifyWithBackend])

  // Mode liste
  if (!colisId) {
    return (
      <>
        <h2 className="mb-4"><i className="fa-solid fa-credit-card text-primary"></i> Mes paiements <span className="badge bg-primary ms-2">Kkiapay 100%</span></h2>
        {kkiapayCfg && !kkiapayCfg.configured && (
          <div className="alert alert-warning small">Kkiapay non configuré côté serveur – vérifiez les clés dans .env</div>
        )}
        {listeError && <div className="alert alert-danger">{listeError}</div>}
        {stats && (
          <div className="row g-3 mb-4">
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold">{money(stats.montant_total)}</div><div className="small text-muted">Total XOF</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold text-success">{money(stats.montant_paye)}</div><div className="small text-muted">{stats.nb_payes} payés</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold text-warning">{money(stats.montant_en_attente)}</div><div className="small text-muted">{stats.nb_en_attente} en attente</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="small text-muted mb-1">Propulsé par</div><img src="https://kkiapay.me/wp-content/uploads/2021/02/logo-kkiapay.png" alt="Kkiapay" style={{height:22}} /><div className="small mt-2">{kkiapayCfg?.sandbox ? <span className="badge bg-warning text-dark">SANDBOX</span> : <span className="badge bg-success">LIVE</span>}</div></div></div>
          </div>
        )}
        <div className="page-card">
          {!liste && !listeError && <div className="text-center text-muted py-4">Chargement…</div>}
          {liste && liste.length === 0 && <div className="text-center text-muted py-4">Aucun paiement. <Link to="/poster-colis">Poster un colis</Link> pour générer un paiement Kkiapay.</div>}
          {liste && liste.length > 0 && (
            <div className="table-responsive">
              <table className="table table-sm align-middle">
                <thead><tr><th>Colis</th><th>Montant XOF</th><th>Réf</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                  {liste.map(pm => (
                    <tr key={pm.id}>
                      <td>{pm.nom_colis || `Colis #${pm.colis_id}`}</td>
                      <td>{money(pm.montant)}</td>
                      <td><code className="small">{pm.reference}</code></td>
                      <td><StatusBadge statut={pm.statut} /></td>
                      <td>
                        {pm.statut === 'paye' ? (
                          <span className="small text-muted"><i className="fa-solid fa-check text-success"></i> {pm.numero_transaction || ''}</span>
                        ) : (
                          <Link to={`/paiement?colis_id=${pm.colis_id}`} className="btn btn-success btn-sm"><i className="fa-solid fa-credit-card"></i> Payer via Kkiapay</Link>
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
        <i className="fa-solid fa-credit-card text-primary"></i> Paiement du colis <span className="badge bg-primary ms-2">Kkiapay</span>
      </h2>
      <div className="page-card" style={{ maxWidth: 640 }}>
        {alert && <div className={`alert alert-${alert.type}`} dangerouslySetInnerHTML={{__html: alert.html}}></div>}
        {lastTxId && alert && (
          <div className="mb-3">
            <button onClick={() => lastTxId && verifyWithBackend(lastTxId)} disabled={verifying} className="btn btn-warning btn-sm w-100">
              <i className="fa-solid fa-rotate"></i> Re-vérifier transaction {lastTxId}
            </button>
            <div className="small text-muted mt-2">Reçu Kkiapay : {lastTxId} — Montant {p ? money(p.montant) : ''} XOF — Si le paiement est bien débité chez vous, l'admin peut valider manuellement dans /admin → Paiements</div>
          </div>
        )}
        {paidNow && (
          <div className="alert alert-success">
            <i className="fa-solid fa-circle-check"></i> {paidNow.message} — Transaction : <code>{paidNow.numero_transaction}</code>
          </div>
        )}

        {error && <div className="alert alert-danger">{error}</div>}
        {!p && !error && <div className="text-center text-muted py-4">Chargement…</div>}

        {p && p.statut === 'paye' && (
          <div className="text-center py-3">
            <i className="fa-solid fa-circle-check text-success" style={{ fontSize: '3.5rem' }}></i>
            <h4 className="mt-3">Paiement déjà effectué via Kkiapay</h4>
            <p className="text-muted mb-1">
              {p.nom_colis || ''} — {money(p.montant)} XOF
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
              <div className="d-flex justify-content-between align-items-center">
                <span className="fw-bold">{p.nom_colis || 'Colis'}</span>
                <strong className="text-primary fs-5">{money(p.montant)} XOF</strong>
              </div>
              <div className="small text-muted mt-2">
                Référence : <code>{p.reference}</code> · Statut : <StatusBadge statut={p.statut} /> · Prix estimé : {p.prix_estime ? money(p.prix_estime) : money(p.montant)} XOF
              </div>
              {kkiapayCfg && (
                <div className="small mt-2">
                  <span className="badge bg-light text-dark border"><i className="fa-solid fa-shield-halved"></i> Kkiapay {kkiapayCfg.sandbox ? 'SANDBOX' : 'LIVE'}</span>
                  {!kkiapayCfg.configured && <span className="badge bg-warning text-dark ms-2">Non configuré</span>}
                </div>
              )}
            </div>

            <div className="alert alert-info small">
              <i className="fa-solid fa-info-circle"></i> Vous serez redirigé vers le widget sécurisé Kkiapay. Moyens acceptés : <strong>MTN Mobile Money, Moov Money, Celtiis Cash, Orange Money, Wave, Visa, Mastercard</strong> (Bénin, CI, Togo, Sénégal, Niger). Devise : <strong>XOF</strong>.
            </div>

            <button
              onClick={openWidget}
              disabled={!scriptReady || verifying}
              className="btn btn-primary btn-lg w-100 fw-bold"
              style={{ background: '#0d6efd' }}
            >
              {verifying ? (
                <><span className="spinner-border spinner-border-sm me-2"></span>Vérification…</>
              ) : (
                <><i className="fa-solid fa-lock"></i> Payer {money(p.montant)} XOF via Kkiapay</>
              )}
            </button>

            <div className="text-center mt-3">
              <img src="https://kkiapay.me/wp-content/uploads/2021/02/logo-kkiapay.png" alt="Kkiapay" style={{height:20, opacity:0.8}} />
              <p className="text-muted small mt-2 mb-0">
                <i className="fa-solid fa-shield-halved"></i> Paiement 100% sécurisé par Kkiapay – PCI DSS, chiffrement TLS. Aucune donnée carte stockée sur nos serveurs.
              </p>
              <Link to="/paiement" className="small mt-2 d-inline-block">← Voir tous mes paiements</Link>
            </div>

            {!scriptReady && <div className="text-center small text-muted mt-3">Chargement widget Kkiapay…</div>}
          </>
        )}
      </div>
    </>
  )
}
