import { useEffect, useState, useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import { datetime, money, StatusBadge } from '../lib/format'
import ResponsiveTable from "../components/ResponsiveTable";

/** Paiement — widget FedaPay par défaut, rétrocompatibilité Kkiapay conservée. */

interface ProviderConfig {
  public_key: string
  sandbox: boolean
  configured: boolean
  enabled?: boolean
  widget_js?: string
  provider?: string
  provider_name?: string
}

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
  provider?: string
  fedapay?: ProviderConfig
  kkiapay?: ProviderConfig
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
  provider?: ProviderConfig
  fedapay?: ProviderConfig
  kkiapay?: ProviderConfig
}

// FedaPay types
declare global {
  interface Window {
    FedaPay?: {
      Checkout: new (opts: FedaPayCheckoutOptions) => FedaPayCheckoutInstance
    }
    // Legacy Kkiapay
    openKkiapayWidget?: (opts: any) => void
    addSuccessListener?: (cb: (res: { transactionId: string }) => void) => void
    addFailedListener?: (cb: (err: any) => void) => void
  }
}

interface FedaPayCheckoutOptions {
  public_key: string
  transaction: {
    id?: number | string
    amount?: number
    description?: string
  }
  customer?: {
    email?: string
    firstname?: string
    lastname?: string
  }
  currency?: { iso: string }
  onComplete?: (result: FedaPayResult) => void
  button?: { text?: string; class?: string }
  container?: string
  trigger?: string
  locale?: string
}

interface FedaPayResult {
  reason: 'CHECKOUT COMPLETE' | 'DIALOG DISMISSED' | string
  transaction: {
    id: string | number
    status?: string
    reference?: string
    amount?: number
  }
}

interface FedaPayCheckoutInstance {
  open: () => void
  close: () => void
}

const FEDAPAY_SCRIPT = 'https://cdn.fedapay.com/checkout.js'

function loadScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) {
      resolve()
      return
    }
    const s = document.createElement('script')
    s.src = src
    s.async = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error(`Impossible de charger ${src}`))
    document.body.appendChild(s)
  })
}

type ActiveProvider = 'fedapay' | 'kkiapay'

export default function Paiement() {
  const [params] = useSearchParams()
  const colisId = params.get('colis_id')
  const reference = params.get('reference')

  const [p, setP] = useState<PaiementData | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [alert, setAlert] = useState<{ type: 'danger' | 'success' | 'warning' | 'info'; html: string } | null>(null)
  const [verifying, setVerifying] = useState(false)
  const [paidNow, setPaidNow] = useState<{ message: string; numero_transaction: string } | null>(null)
  const [scriptsReady, setScriptsReady] = useState<{ fedapay: boolean; kkiapay: boolean }>({ fedapay: false, kkiapay: false })
  const [lastTxId, setLastTxId] = useState<string | null>(null)
  const [creatingTx, setCreatingTx] = useState(false)
  const [fedapayTxId, setFedapayTxId] = useState<string | number | null>(null)

  // Liste
  const [liste, setListe] = useState<PaiementData[] | null>(null)
  const [stats, setStats] = useState<PaiementsMineResponse['stats'] | null>(null)
  const [listeError, setListeError] = useState<string | null>(null)
  const [providerCfg, setProviderCfg] = useState<ProviderConfig | null>(null)
  const [activeProvider, setActiveProvider] = useState<ActiveProvider>('fedapay')

  // Charger scripts
  useEffect(() => {
    loadScript(FEDAPAY_SCRIPT)
      .then(() => setScriptsReady((s) => ({ ...s, fedapay: true })))
      .catch((e) => console.warn('FedaPay script:', e.message))
    // Kkiapay en fallback
    loadScript('https://cdn.kkiapay.me/k.js')
      .then(() => setScriptsReady((s) => ({ ...s, kkiapay: true })))
      .catch(() => {})
  }, [])

  // Charger données
  useEffect(() => {
    if (!colisId) {
      ;(async () => {
        try {
          const data = await api.get<PaiementsMineResponse>('/api/paiements/mine')
          setListe(data.paiements || [])
          setStats(data.stats)
          const cfg = data.provider || data.fedapay || data.kkiapay || null
          setProviderCfg(cfg)
          if (cfg?.provider === 'fedapay' || data.fedapay?.configured) setActiveProvider('fedapay')
          else setActiveProvider('kkiapay')
        } catch (e) {
          setListeError(e instanceof ApiError ? e.message : 'Erreur chargement paiements')
        }
        try {
          const cfg = await api.get<ProviderConfig>('/api/fedapay/public-config')
          if (cfg.configured) {
            setProviderCfg(cfg)
            setActiveProvider('fedapay')
          } else {
            const kk = await api.get<ProviderConfig>('/api/kkiapay/public-config')
            setProviderCfg(kk)
            setActiveProvider('kkiapay')
          }
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
        const cfg = data.fedapay?.configured ? data.fedapay : data.kkiapay
        setProviderCfg(cfg || null)
        setActiveProvider(data.fedapay?.configured ? 'fedapay' : 'kkiapay')
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Erreur inconnue')
      }
    })()
  }, [colisId, reference])

  // ================= FedaPay flow =================
  const verifyFedapay = useCallback(async (transactionId: string | number) => {
    if (!p) return
    setLastTxId(String(transactionId))
    setVerifying(true)
    setAlert(null)
    try {
      const res = await api.post<{ message: string; numero_transaction: string; statut: string }>(
        `/api/paiements/${p.id}/verify-fedapay`,
        { transaction_id: String(transactionId) },
      )
      setPaidNow(res)
      try {
        const updated = await api.get<PaiementData>('/api/paiements/colis/' + encodeURIComponent(colisId!))
        setP(updated)
      } catch {}
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Vérification échouée'
      setAlert({
        type: 'danger',
        html: `${msg}<br/><small class="mt-2 d-block">Transaction FedaPay : <code>${transactionId}</code> — Conservez ce reçu. Si le paiement est bien débité, l'admin peut valider manuellement.</small>`,
      })
    } finally {
      setVerifying(false)
    }
  }, [p, colisId])

  const payWithFedapay = useCallback(async () => {
    if (!p) return
    if (!scriptsReady.fedapay || !window.FedaPay?.Checkout) {
      setAlert({ type: 'danger', html: 'Widget FedaPay non chargé, réessayez.' })
      return
    }
    setAlert(null)
    setCreatingTx(true)
    let txId = fedapayTxId
    try {
      if (!txId) {
        const created = await api.post<{
          transaction_id: number | string
          public_key: string
          payment_token: string
          amount: number
        }>(`/api/paiements/${p.id}/fedapay/create`, {})
        txId = created.transaction_id
        setFedapayTxId(txId)
      }

      const publicKey = providerCfg?.public_key || ''
      const amount = Math.round(Number(p.montant))

      const checkout = new window.FedaPay.Checkout({
        public_key: publicKey,
        transaction: {
          id: txId,
          amount: amount,
          description: `Paiement colis ${p.nom_colis || ''} (${p.reference})`,
        },
        currency: { iso: 'XOF' },
        onComplete: (result) => {
          if (result.reason === 'CHECKOUT COMPLETE' && result.transaction?.id) {
            verifyFedapay(result.transaction.id)
          } else {
            setAlert({ type: 'warning', html: 'Paiement annulé ou fenêtre fermée. Réessayez si nécessaire.' })
          }
        },
      })
      checkout.open()
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Erreur initialisation paiement'
      setAlert({ type: 'danger', html: msg })
    } finally {
      setCreatingTx(false)
    }
  }, [p, scriptsReady.fedapay, providerCfg, fedapayTxId, verifyFedapay])

  // ================= Kkiapay legacy flow =================
  const payWithKkiapay = useCallback(() => {
    if (!p) return
    if (!scriptsReady.kkiapay || typeof window.openKkiapayWidget !== 'function') {
      setAlert({ type: 'danger', html: 'Widget Kkiapay non chargé.' })
      return
    }
    const publicKey = providerCfg?.public_key || ''
    const amount = Math.round(Number(p.montant))
    window.addSuccessListener?.(async (response) => {
      const txId = response?.transactionId
      if (txId) {
        setLastTxId(txId)
        setVerifying(true)
        try {
          const res = await api.post<{ message: string; numero_transaction: string }>(
            `/api/paiements/${p.id}/verify-kkiapay`,
            { transactionId: txId },
          )
          setPaidNow(res)
          try {
            const updated = await api.get<PaiementData>('/api/paiements/colis/' + encodeURIComponent(colisId!))
            setP(updated)
          } catch {}
        } catch (err) {
          const msg = err instanceof ApiError ? err.message : 'Vérification échouée'
          setAlert({ type: 'danger', html: msg })
        } finally {
          setVerifying(false)
        }
      }
    })
    window.addFailedListener?.(() => {
      setAlert({ type: 'danger', html: 'Paiement annulé/échoué.' })
    })
    window.openKkiapayWidget({
      amount,
      key: publicKey,
      sandbox: providerCfg?.sandbox ?? true,
      reason: `Paiement ${p.nom_colis || ''} ${p.reference}`,
      name: '', email: '', phone: '',
      data: p.reference,
      partnerId: String(p.colis_id),
      paymentmethod: 'momo,card',
      countries: ['BJ', 'CI', 'TG', 'SN', 'NE'],
      theme: '#0d6efd',
      position: 'center',
    })
  }, [p, scriptsReady.kkiapay, providerCfg, colisId])

  const openWidget = useCallback(() => {
    if (activeProvider === 'fedapay') payWithFedapay()
    else payWithKkiapay()
  }, [activeProvider, payWithFedapay, payWithKkiapay])

  const providerLabel = activeProvider === 'fedapay' ? 'FedaPay' : 'Kkiapay'
  const isSandbox = providerCfg?.sandbox ?? true

  // ================= Liste mode =================
  if (!colisId) {
    return (
      <>
        <h2 className="mb-4"><i className="fa-solid fa-credit-card text-primary"></i> Mes paiements <span className="badge bg-primary ms-2">{providerLabel}</span></h2>
        {providerCfg && !providerCfg.configured && (
          <div className="alert alert-warning small">
            {providerLabel} non configuré côté serveur.
          </div>
        )}
        {listeError && <div className="alert alert-danger">{listeError}</div>}
        {stats && (
          <div className="row g-3 mb-4">
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold">{money(stats.montant_total)}</div><div className="small text-muted">Total XOF</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold text-success">{money(stats.montant_paye)}</div><div className="small text-muted">{stats.nb_payes} payés</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-5 fw-bold text-warning">{money(stats.montant_en_attente)}</div><div className="small text-muted">{stats.nb_en_attente} en attente</div></div></div>
            <div className="col-md-3"><div className="page-card text-center">
              <div className="small text-muted mb-1">Propulsé par</div>
              <div className="fw-bold text-primary">{providerLabel}</div>
              <div className="small mt-2">{isSandbox ? <span className="badge bg-warning text-dark">SANDBOX</span> : <span className="badge bg-success">LIVE</span>}</div>
            </div></div>
          </div>
        )}
        <div className="page-card">
          {!liste && !listeError && <div className="text-center text-muted py-4">Chargement…</div>}
          {liste && liste.length === 0 && (
            <div className="text-center text-muted py-4">
              Aucun paiement. <Link to="/poster-colis">Poster un colis</Link> pour générer un paiement.
            </div>
          )}
          {liste && liste.length > 0 && (
            <ResponsiveTable<PaiementData>
              columns={[
                { key: 'nom_colis', label: 'Colis', render: (pm) => pm.nom_colis || `Colis #${pm.colis_id}` },
                { key: 'montant', label: 'Montant XOF', render: (pm) => money(pm.montant), primaryOnMobile: true },
                { key: 'reference', label: 'Référence', render: (pm) => <code className="small">{pm.reference}</code> },
                { key: 'statut', label: 'Statut', render: (pm) => <StatusBadge statut={pm.statut} />, primaryOnMobile: true },
              ]}
              data={liste}
              rowKey={(pm) => pm.id}
              titleKey="nom_colis"
              subtitleKey="montant"
              emptyText="Aucun paiement."
              className="table-sm align-middle"
              actions={(pm) => (
                pm.statut === 'paye' ? (
                  <span className="small text-muted">
                    <i className="fa-solid fa-check text-success"></i> {pm.numero_transaction || ''}
                  </span>
                ) : (
                  <Link to={`/paiement?colis_id=${pm.colis_id}`} className="btn btn-success btn-sm">
                    <i className="fa-solid fa-credit-card"></i> Payer
                  </Link>
                )
              )}
            />
          )}
        </div>
      </>
    )
  }

  // ================= Mode paiement unique =================
  return (
    <>
      <h2 className="mb-4">
        <i className="fa-solid fa-credit-card text-primary"></i> Paiement du colis{' '}
        <span className="badge bg-primary ms-2">{providerLabel}</span>
      </h2>
      <div className="page-card" style={{ maxWidth: 640 }}>
        {alert && (
          <div className={`alert alert-${alert.type}`} dangerouslySetInnerHTML={{ __html: alert.html }}></div>
        )}
        {lastTxId && alert?.type === 'danger' && (
          <div className="mb-3">
            <button
              onClick={() => activeProvider === 'fedapay' ? verifyFedapay(lastTxId) : null}
              disabled={verifying}
              className="btn btn-warning btn-sm w-100"
            >
              <i className="fa-solid fa-rotate"></i> Re-vérifier transaction {lastTxId}
            </button>
            <div className="small text-muted mt-2">
              Reçu : {lastTxId} — Montant {p ? money(p.montant) : ''} XOF. L'admin peut valider manuellement si besoin.
            </div>
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
            <h4 className="mt-3">Paiement déjà effectué via {providerLabel}</h4>
            <p className="text-muted mb-1">{p.nom_colis || ''} — {money(p.montant)} XOF</p>
            <p className="small text-muted">
              Transaction : <code>{p.numero_transaction || ''}</code><br />
              Référence : <code>{p.reference}</code><br />
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
              <div className="small mt-2 d-flex gap-2 flex-wrap">
                <span className="badge bg-light text-dark border">
                  <i className="fa-solid fa-shield-halved"></i> {providerLabel} {isSandbox ? 'SANDBOX' : 'LIVE'}
                </span>
                {providerCfg && !providerCfg.configured && (
                  <span className="badge bg-warning text-dark">Non configuré</span>
                )}
                {/* Bascule manuelle provider si les deux existent */}
                {p.fedapay?.configured && p.kkiapay?.configured && (
                  <div className="btn-group btn-group-sm ms-auto" role="group">
                    <button
                      className={`btn btn-sm ${activeProvider === 'fedapay' ? 'btn-primary' : 'btn-outline-primary'}`}
                      onClick={() => setActiveProvider('fedapay')}
                    >FedaPay</button>
                    <button
                      className={`btn btn-sm ${activeProvider === 'kkiapay' ? 'btn-primary' : 'btn-outline-primary'}`}
                      onClick={() => setActiveProvider('kkiapay')}
                    >Kkiapay</button>
                  </div>
                )}
              </div>
            </div>

            <div className="alert alert-info small">
              <i className="fa-solid fa-info-circle"></i> Vous allez être redirigé vers le widget sécurisé <strong>{providerLabel}</strong>.
              {' '}Moyens acceptés : <strong>MTN Mobile Money, Moov Money, Celtiis Cash, Coris Money, Visa, Mastercard</strong>
              {activeProvider === 'fedapay' && <> (Wave/Orange Money bientôt disponible en CI/SN/Togo)</>}
              . Devise : <strong>XOF</strong>.
            </div>

            <button
              onClick={openWidget}
              disabled={!scriptsReady[activeProvider] || verifying || creatingTx}
              className="btn btn-primary btn-lg w-100 fw-bold"
            >
              {verifying || creatingTx ? (
                <><span className="spinner-border spinner-border-sm me-2"></span>Traitement…</>
              ) : (
                <><i className="fa-solid fa-lock"></i> Payer {money(p.montant)} XOF via {providerLabel}</>
              )}
            </button>

            <div className="text-center mt-3">
              <div className="small fw-bold text-primary">{providerLabel}</div>
              <p className="text-muted small mt-2 mb-0">
                <i className="fa-solid fa-shield-halved"></i> Paiement 100% sécurisé — PCI DSS, chiffrement TLS. Aucune donnée bancaire stockée sur nos serveurs.
              </p>
              <Link to="/paiement" className="small mt-2 d-inline-block">← Voir tous mes paiements</Link>
            </div>

            {!scriptsReady[activeProvider] && (
              <div className="text-center small text-muted mt-3">Chargement widget {providerLabel}…</div>
            )}
          </>
        )}
      </div>
    </>
  )
}
