import { useCallback, useEffect, useMemo, useState } from 'react'

import { api, ApiError } from '../../lib/api'
import { date, datetime, money } from '../../lib/format'
import { useToast } from '../../components/Toasts'
import { useAuth } from '../../context/AuthContext'

/* ============================================================
   Types
   ============================================================ */

type Section = 'stats' | 'livraisons' | 'users' | 'colis' | 'voyages' | 'transporteurs' | 'paiements' | 'avis' | 'contact' | 'admins' | 'wallet' | 'retraits' | 'kkiapay'

interface PaginationInfo {
  page: number
  per_page: number
  total: number
  last_page: number
  from: number
  to: number
}
interface Paginated<T> {
  data: T[]
  pagination: PaginationInfo
}

interface PendingLivraison {
  suivi_id: number
  date_etape: string
  colis_id: number
  nom_colis: string
  numero_suivi: string
  prix_estime: string | number
  poids: string | number
  ville: string
  pays: string
  adresse_depart: string | null
  adresse_destination: string | null
  image_url: string | null
  client_nom: string | null
  client_prenom: string | null
  client_tel: string | null
  client_email: string | null
  transporteur_id: number | null
  transporteur_nom: string | null
  transporteur_prenom: string | null
  transporteur_tel: string | null
  transporteur_email: string | null
  vehicule: string | null
  compagnie: string | null
  transporteur_ville: string | null
  commission_transporteur: number
  commission_admin: number
}

interface AdminStats {
  nb_users: number
  nb_clients: number
  nb_transporteurs_users: number
  nb_admins: number
  nb_super_admins: number
  nb_colis: number
  nb_voyages: number
  nb_transporteurs: number
  colis_en_attente: number
  voyages_en_attente: number
  avis_en_attente: number
  demandes_livraison: number
  nb_paiements: number
  montant_total_paye: string | number
  messages_contact_non_lus: number
  nb_avis: number
  admin_wallet_solde?: number
  admin_commission_total?: number
  retraits_en_attente?: number
  retraits_payes?: number
  total_paye_transporteurs?: number
  total_frais_admin?: number
  pending_livraisons?: PendingLivraison[]
  notifications_non_lues?: number
}

interface AdminUser { id:number; nom:string; prenom:string; email:string; telephone:string|null; photo_url:string|null; role:string; date_inscription:string }
interface AdminColis { id:number; nom_colis:string; image_url:string|null; numero_suivi:string; prenom:string|null; nom:string|null; email:string|null; ville:string; pays:string; poids:string|number; prix_estime:string|number; statut:string; statut_livraison:string|null; demande_livraison_id:number|null }
interface AdminVoyage { id:number; prenom:string|null; nom:string|null; email:string|null; pays_depart:string; pays_destination:string; date_depart:string; heure_depart:string|null; poids_max:string|number; nb_reservations:number; statut:string }
interface AdminTransporteur { id:number; user_id:number; prenom:string; nom:string; email:string; telephone:string|null; vehicule:string|null; numero_permis:string|null; compagnie:string|null; ville:string|null; pays:string|null; nb_colis_transportes:number; solde:string|number }
interface AdminPaiement { id:number; reference:string; numero_transaction:string|null; prenom:string|null; nom:string|null; email:string|null; nom_colis:string|null; numero_suivi:string|null; montant:string|number; methode_paiement:string|null; operateur:string|null; date_creation:string; statut:string }
interface AdminAvis { id:number; user_prenom:string; user_nom:string; transporteur_id:number; transporteur_prenom:string; transporteur_nom:string; note:number; commentaire:string; date_avis:string; statut:string }
interface AdminContact { id:number; nom:string; email:string; message:string; reponse:string|null; date_envoi:string; date_reponse:string|null }
interface AdminWallet { solde:number; total_commission_generee:number; total_paye_transporteurs:number; total_retraits_admin:number }
interface AdminRetrait { id:number; user_id:number; type:string; montant:number; statut:string; methode:string; numero:string|null; reference:string; details:string|null; colis_id:number|null; nom:string|null; prenom:string|null; email:string|null; nom_colis:string|null; numero_suivi:string|null; date_demande:string; date_traitement:string|null; kkiapay_response?:string|null }
interface KkiapayStatus { configured:boolean; sandbox:boolean; public_key_masked:string|null; base_url:string; simulate_fallback:boolean; admin_phone:string|null }
interface KkiapayBalance { status:string; [k:string]:any }


/* ============================================================
   Utilitaires
   ============================================================ */

const errMsg = (e: unknown) => (e instanceof ApiError ? e.message : 'Erreur inconnue')

const ROLE_LABELS: Record<string, {label:string; color:string; icon:string}> = {
  client: {label:'Client', color:'bg-primary', icon:'fa-user'},
  utilisateur: {label:'Client', color:'bg-primary', icon:'fa-user'},
  transporteur: {label:'Transporteur', color:'bg-success', icon:'fa-truck'},
  admin: {label:'Admin', color:'bg-info text-dark', icon:'fa-shield-halved'},
  super_admin: {label:'Super Admin', color:'bg-warning text-dark', icon:'fa-crown'},
}

function RoleBadge({ role }: { role: string }) {
  const r = ROLE_LABELS[role] || {label:role, color:'bg-light text-dark', icon:'fa-user'}
  return <span className={`badge ${r.color}`}><i className={`fa-solid ${r.icon}`}></i> {r.label}</span>
}

/* ============================================================
   Composant Pagination réutilisable
   ============================================================ */

function Pagination({ info, onChange }: { info: PaginationInfo; onChange: (p: number) => void }) {
  const { page, last_page, total, from, to } = info
  if (last_page <= 1) return <div className="text-muted small mt-3">{total} élément{total>1?'s':''}</div>

  const pages: (number | '...')[] = []
  const add = (p: number | '...') => pages.push(p)
  const range = 2
  for (let i = 1; i <= last_page; i++) {
    if (i === 1 || i === last_page || (i >= page - range && i <= page + range)) add(i)
    else if (pages[pages.length-1] !== '...') add('...')
  }

  return (
    <div className="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
      <div className="small text-muted">
        Affichage <strong>{from}-{to}</strong> sur <strong>{total}</strong> élément{total>1?'s':''}
      </div>
      <nav>
        <ul className="pagination pagination-sm mb-0">
          <li className={`page-item ${page<=1?'disabled':''}`}>
            <button className="page-link" onClick={()=>onChange(page-1)} disabled={page<=1}><i className="fa-solid fa-chevron-left"></i></button>
          </li>
          {pages.map((p,i) => p === '...' ? (
            <li key={`e${i}`} className="page-item disabled"><span className="page-link">…</span></li>
          ) : (
            <li key={p} className={`page-item ${p===page?'active':''}`}>
              <button className="page-link" onClick={()=>onChange(p)}>{p}</button>
            </li>
          ))}
          <li className={`page-item ${page>=last_page?'disabled':''}`}>
            <button className="page-link" onClick={()=>onChange(page+1)} disabled={page>=last_page}><i className="fa-solid fa-chevron-right"></i></button>
          </li>
        </ul>
      </nav>
    </div>
  )
}

/* ============================================================
   Section stat card
   ============================================================ */
function StatCard({ value, label, sub, subColor='text-warning' }: { value: React.ReactNode; label: string; sub?: React.ReactNode; subColor?: string }) {
  return (
    <div className="page-card text-center p-3 h-100">
      <div className="fs-4 fw-bold">{value}</div>
      <div className="text-muted small">{label}</div>
      {sub && <div className={`small mt-2 ${subColor}`}>{sub}</div>}
    </div>
  )
}

/* ============================================================
   Composant principal
   ============================================================ */

export default function AdminIndex() {
  const { toast } = useToast()
  const { me, isSuperAdmin } = useAuth()
  const [section, setSection] = useState<Section>('stats')

  const [stats, setStats] = useState<AdminStats | null>(null)
  const [users, setUsers] = useState<Paginated<AdminUser> | null>(null)
  const [colisList, setColisList] = useState<Paginated<AdminColis> | null>(null)
  const [voyages, setVoyages] = useState<Paginated<AdminVoyage> | null>(null)
  const [transporteurs, setTransporteurs] = useState<Paginated<AdminTransporteur> | null>(null)
  const [paiements, setPaiements] = useState<Paginated<AdminPaiement> | null>(null)
  const [avis, setAvis] = useState<Paginated<AdminAvis> | null>(null)
  const [contacts, setContacts] = useState<Paginated<AdminContact> | null>(null)
  const [admins, setAdmins] = useState<AdminUser[] | null>(null)
  const [wallet, setWallet] = useState<AdminWallet | null>(null)
  const [retraits, setRetraits] = useState<Paginated<AdminRetrait> | null>(null)
  const [livraisons, setLivraisons] = useState<Paginated<PendingLivraison> | null>(null)
  const [selectedLivs, setSelectedLivs] = useState<Set<number>>(new Set())
  const [sectionError, setSectionError] = useState<string | null>(null)
  const [kkStatus, setKkStatus] = useState<KkiapayStatus | null>(null)
  const [kkBalance, setKkBalance] = useState<KkiapayBalance | null>(null)
  const [kkSetupLoading, setKkSetupLoading] = useState(false)
  const [kkPayoutLoading, setKkPayoutLoading] = useState(false)
  const [kkPayoutForm, setKkPayoutForm] = useState({ phone:'', amount:'', name:'' })
  const [kkSetupForm, setKkSetupForm] = useState({ destination:'', roof_amount:'50000' })

  // Filtres
  const [uSearch, setUSearch] = useState(''); const [uRole, setURole] = useState(''); const [uPage, setUPage] = useState(1)
  const [cSearch, setCSearch] = useState(''); const [cStatut, setCStatut] = useState(''); const [cPage, setCPage] = useState(1)
  const [vSearch, setVSearch] = useState(''); const [vStatut, setVStatut] = useState(''); const [vPage, setVPage] = useState(1)
  const [tSearch, setTSearch] = useState(''); const [tPage, setTPage] = useState(1)
  const [pSearch, setPSearch] = useState(''); const [pStatut, setPStatut] = useState(''); const [pPage, setPPage] = useState(1)
  const [aStatut, setAStatut] = useState(''); const [aPage, setAPage] = useState(1)
  const [ctRepondu, setCtRepondu] = useState(''); const [ctPage, setCtPage] = useState(1)
  const [rSearch, setRSearch] = useState(''); const [rStatut, setRStatut] = useState(''); const [rType, setRType] = useState(''); const [rPage, setRPage] = useState(1)
  const [lPage, setLPage] = useState(1)
  const [perPage, setPerPage] = useState(15)

  const [adminRetraitForm, setAdminRetraitForm] = useState({ montant: '', numero: '' })
  const [userModal, setUserModal] = useState(false)
  const [userForm, setUserForm] = useState({ nom: '', prenom: '', email: '', password: '', role: 'client' })
  const [userModalError, setUserModalError] = useState<string | null>(null)
  const [replyModal, setReplyModal] = useState<{ id: number; nom: string; message: string } | null>(null)
  const [replyText, setReplyText] = useState('')
  const [prevDemandesLivraison, setPrevDemandesLivraison] = useState<number>(-1)
  const [soundEnabled, setSoundEnabled] = useState(true)

  // Beep via Web Audio
  const playBeep = useCallback(() => {
    try {
      const AC = (window as any).AudioContext || (window as any).webkitAudioContext
      if (!AC) return
      const ctx = new AC()
      const beep = (freq: number, start: number, dur: number, vol = 0.25) => {
        const o = ctx.createOscillator(); const g = ctx.createGain()
        o.type = 'sine'; o.frequency.value = freq
        o.connect(g); g.connect(ctx.destination)
        g.gain.setValueAtTime(vol, ctx.currentTime + start)
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur)
        o.start(ctx.currentTime + start); o.stop(ctx.currentTime + start + dur)
      }
      beep(880,0,0.15); beep(1100,0.2,0.15); beep(1320,0.4,0.25)
      setTimeout(() => ctx.close().catch(()=>{}), 800)
    } catch { /* ignore */ }
  }, [])

  /* ======================= Stats & polling ======================= */

  const loadStats = useCallback(async (opts?: { silent?: boolean }) => {
    if (!opts?.silent) setSectionError(null)
    try {
      const data = await api.get<AdminStats>('/api/admin/stats')
      setStats(prev => {
        if (prev && data.demandes_livraison > prevDemandesLivraison && prevDemandesLivraison >= 0) {
          const n = data.demandes_livraison - prevDemandesLivraison
          toast(<><i className="fa-solid fa-bell text-danger me-2"></i>{`${n} nouvelle${n>1?'s':''} livraison${n>1?'s':''} à confirmer !`}</>, 'error')
          if (soundEnabled) playBeep()
        }
        return data
      })
      setPrevDemandesLivraison(data.demandes_livraison)
    } catch (e) { if (!opts?.silent) setSectionError(errMsg(e)) }
  }, [toast, soundEnabled, playBeep, prevDemandesLivraison])

  useEffect(() => {
    loadStats({ silent: true })
    const id = setInterval(() => loadStats({ silent: true }), 30000)
    return () => clearInterval(id)
  }, [loadStats])

  /* ======================= Loaders paginés ======================= */

  const buildLoader = useCallback(<T,>(
    path: string,
    filters: Record<string,string>,
    page: number,
    setter: (v: Paginated<T> | null) => void,
    resetPage: boolean = false,
  ) => {
    return async () => {
      if (!resetPage) setSectionError(null)
      const p = new URLSearchParams()
      Object.entries(filters).forEach(([k,v]) => { if (v) p.set(k, v) })
      p.set('page', String(page)); p.set('per_page', String(perPage))
      try { setter(await api.get<Paginated<T>>(`${path}?${p}`)) }
      catch (e) {
        if (!resetPage) setSectionError(errMsg(e))
        else console.error(errMsg(e))
      }
    }
  }, [perPage])

  const loadUsers = useMemo(() => buildLoader<AdminUser>(
    '/api/admin/users',
    { search: uSearch.trim(), role: uRole },
    uPage, setUsers
  ), [buildLoader, uSearch, uRole, uPage])

  const loadColis = useMemo(() => buildLoader<AdminColis>(
    '/api/admin/colis',
    { search: cSearch.trim(), statut: cStatut },
    cPage, setColisList
  ), [buildLoader, cSearch, cStatut, cPage])

  const loadVoyages = useMemo(() => buildLoader<AdminVoyage>(
    '/api/admin/voyages',
    { search: vSearch.trim(), statut: vStatut },
    vPage, setVoyages
  ), [buildLoader, vSearch, vStatut, vPage])

  const loadTransporteurs = useMemo(() => buildLoader<AdminTransporteur>(
    '/api/admin/transporteurs',
    { search: tSearch.trim() },
    tPage, setTransporteurs
  ), [buildLoader, tSearch, tPage])

  const loadPaiements = useMemo(() => buildLoader<AdminPaiement>(
    '/api/admin/paiements',
    { search: pSearch.trim(), statut: pStatut },
    pPage, setPaiements
  ), [buildLoader, pSearch, pStatut, pPage])

  const loadAvis = useMemo(() => buildLoader<AdminAvis>(
    '/api/admin/avis',
    { statut: aStatut },
    aPage, setAvis
  ), [buildLoader, aStatut, aPage])

  const loadContact = useMemo(() => buildLoader<AdminContact>(
    '/api/admin/contact-messages',
    { repondu: ctRepondu },
    ctPage, setContacts
  ), [buildLoader, ctRepondu, ctPage])

  const loadRetraits = useMemo(() => buildLoader<AdminRetrait>(
    '/api/admin/retraits',
    { search: rSearch.trim(), statut: rStatut, type: rType },
    rPage, setRetraits
  ), [buildLoader, rSearch, rStatut, rType, rPage])

  const loadLivraisons = useMemo(() => buildLoader<PendingLivraison>(
    '/api/admin/livraisons',
    {}, lPage, setLivraisons
  ), [buildLoader, lPage])

  const loadAdmins = useCallback(async () => {
    if (!isSuperAdmin) return
    setSectionError(null)
    try { setAdmins(await api.get<AdminUser[]>('/api/super-admin/admins')) } catch (e) { setSectionError(errMsg(e)) }
  }, [isSuperAdmin])

  const loadWallet = useCallback(async () => {
    setSectionError(null)
    try { setWallet(await api.get<AdminWallet>('/api/admin/wallet')) } catch (e) { setSectionError(errMsg(e)) }
  }, [])

  const loadKkiapayStatus = useCallback(async () => {
    setSectionError(null)
    try {
      const s = await api.get<KkiapayStatus>('/api/admin/kkiapay/status')
      setKkStatus(s)
      if (s.admin_phone) setKkSetupForm(f => ({ ...f, destination: f.destination || s.admin_phone || '' }))
    } catch (e) { setSectionError(errMsg(e)) }
  }, [])

  const loadKkiapayBalance = useCallback(async () => {
    try { setKkBalance(await api.get<KkiapayBalance>('/api/admin/kkiapay/balance')) } catch (e) { toast(errMsg(e),'error') }
  }, [toast])

  // Chargement selon section
  useEffect(() => {
    const loaders: Record<Section, () => void> = {
      stats: () => loadStats(),
      livraisons: loadLivraisons,
      users: loadUsers,
      colis: loadColis,
      voyages: loadVoyages,
      transporteurs: loadTransporteurs,
      paiements: loadPaiements,
      avis: loadAvis,
      contact: loadContact,
      admins: loadAdmins,
      wallet: loadWallet,
      retraits: loadRetraits,
      kkiapay: () => { loadKkiapayStatus(); loadKkiapayBalance() },
    }
    setSelectedLivs(new Set())
    loaders[section]()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [section, perPage, uPage, cPage, vPage, tPage, pPage, aPage, ctPage, rPage, lPage])

  // Recharger quand les filtres changent (reset page à 1)
  useEffect(() => { if (section==='users') { setUPage(1); loadUsers() } /* eslint-disable-next-line */ }, [uSearch, uRole])
  useEffect(() => { if (section==='colis') { setCPage(1); loadColis() } /* eslint-disable-next-line */ }, [cSearch, cStatut])
  useEffect(() => { if (section==='voyages') { setVPage(1); loadVoyages() } /* eslint-disable-next-line */ }, [vSearch, vStatut])
  useEffect(() => { if (section==='transporteurs') { setTPage(1); loadTransporteurs() } /* eslint-disable-next-line */ }, [tSearch])
  useEffect(() => { if (section==='paiements') { setPPage(1); loadPaiements() } /* eslint-disable-next-line */ }, [pSearch, pStatut])
  useEffect(() => { if (section==='avis') { setAPage(1); loadAvis() } /* eslint-disable-next-line */ }, [aStatut])
  useEffect(() => { if (section==='contact') { setCtPage(1); loadContact() } /* eslint-disable-next-line */ }, [ctRepondu])
  useEffect(() => { if (section==='retraits') { setRPage(1); loadRetraits() } /* eslint-disable-next-line */ }, [rSearch, rStatut, rType])

  /* ======================= Actions ======================= */

  const onCreateUser = async (e: React.FormEvent) => {
    e.preventDefault(); setUserModalError(null)
    try {
      await api.post('/api/admin/users', userForm)
      toast('Utilisateur créé'); setUserModal(false)
      setUserForm({ nom:'', prenom:'', email:'', password:'', role:'client' })
      loadUsers(); if (isSuperAdmin) loadAdmins()
    } catch (err) { setUserModalError(errMsg(err)) }
  }
  const onDeleteUser = async (id:number) => {
    if (!window.confirm('Supprimer cet utilisateur ?')) return
    try { await api.del(`/api/admin/users/${id}`); toast('Utilisateur supprimé'); loadUsers(); if (isSuperAdmin) loadAdmins() }
    catch (err) { toast(errMsg(err),'error') }
  }
  const onChangeRole = async (id:number, role:string) => {
    if (!window.confirm(`Changer le rôle en ${role} ?`)) return
    try { await api.post(`/api/admin/users/${id}/role`,{role}); toast(`Rôle changé en ${role}`); loadUsers(); if (isSuperAdmin) loadAdmins() }
    catch (err) { toast(errMsg(err),'error') }
  }
  const onColisStatut = async (id:number, statut:string) => {
    try { await api.post(`/api/admin/colis/${id}/statut`,{statut}); toast(`Colis ${statut}`); loadColis(); loadStats() } catch (err) { toast(errMsg(err),'error') }
  }
  const onColisDelete = async (id:number) => {
    if (!window.confirm('Supprimer ce colis ?')) return
    try { await api.del(`/api/admin/colis/${id}`); toast('Colis supprimé'); loadColis(); loadStats() } catch (err) { toast(errMsg(err),'error') }
  }
  const onVoyageStatut = async (id:number, statut:string) => {
    try { await api.post(`/api/admin/voyages/${id}/statut`,{statut}); toast(`Voyage ${statut}`); loadVoyages() } catch (err) { toast(errMsg(err),'error') }
  }
  const onVoyageDelete = async (id:number) => {
    if (!window.confirm('Supprimer ce voyage ?')) return
    try { await api.del(`/api/admin/voyages/${id}`); toast('Voyage supprimé'); loadVoyages() } catch (err) { toast(errMsg(err),'error') }
  }
  const onTransporteurDelete = async (id:number) => {
    if (!window.confirm('Supprimer cette fiche transporteur ?')) return
    try { await api.del(`/api/admin/transporteurs/${id}`); toast('Fiche supprimée'); loadTransporteurs() } catch (err) { toast(errMsg(err),'error') }
  }
  const onPaiementStatut = async (id:number, statut:string) => {
    try { await api.post(`/api/admin/paiements/${id}/statut`,{statut}); toast(`Paiement ${statut}`); loadPaiements() } catch (err) { toast(errMsg(err),'error') }
  }
  const onAvisStatut = async (id:number, statut:string) => {
    try { await api.post(`/api/admin/avis/${id}/statut`,{statut}); toast(`Avis ${statut}`); loadAvis() } catch (err) { toast(errMsg(err),'error') }
  }
  const onAvisDelete = async (id:number) => {
    if (!window.confirm('Supprimer cet avis ?')) return
    try { await api.del(`/api/admin/avis/${id}`); toast('Avis supprimé'); loadAvis() } catch (err) { toast(errMsg(err),'error') }
  }
  const onContactDelete = async (id:number) => {
    if (!window.confirm('Supprimer ce message ?')) return
    try { await api.del(`/api/admin/contact-messages/${id}`); toast('Message supprimé'); loadContact() } catch (err) { toast(errMsg(err),'error') }
  }
  const onReplyContact = async (e:React.FormEvent) => {
    e.preventDefault(); if (!replyModal) return
    try { await api.post(`/api/admin/contact-messages/${replyModal.id}/repondre`,{reponse:replyText}); toast('Réponse envoyée'); setReplyModal(null); setReplyText(''); loadContact() }
    catch (err) { toast(errMsg(err),'error') }
  }

  const onLivraisonDecision = async (id:number, decision:'confirmer'|'refuser', silent=false) => {
    if (!silent && !window.confirm(decision==='confirmer'
      ? 'Confirmer la livraison ? 5% seront crédités au solde transporteur (il pourra demander un retrait), 95% à ton wallet admin.'
      : 'Refuser la livraison ?')) return
    try {
      const res = await api.post<{commission_transporteur?:number;commission_admin?:number;payout?:any;message?:string;note_transporteur?:string}>(`/api/admin/suivi/${id}/livraison`,{decision})
      if (decision==='confirmer') {
        toast(`Livraison confirmée — transporteur ${money(res.commission_transporteur||0)} (5%) + admin ${money(res.commission_admin||0)} (95%)`)
      } else toast('Livraison refusée')
      loadLivraisons(); loadColis(); loadStats(); loadRetraits(); loadWallet()
      setSelectedLivs(prev => { const n = new Set(prev); n.delete(id); return n })
    } catch (err) { toast(errMsg(err),'error') }
  }

  const onBulkDecision = async (decision:'confirmer'|'refuser') => {
    const ids = Array.from(selectedLivs)
    if (ids.length === 0) { toast('Sélectionnez au moins une livraison','error'); return }
    if (!window.confirm(`${decision==='confirmer'?'Confirmer':'Refuser'} ${ids.length} livraison(s) ?`)) return
    try {
      const res = await api.post<{traitees:number;total_commission_transporteur:number;total_commission_admin:number;message:string}>(`/api/admin/livraisons/bulk`,{ids,decision})
      toast(`${res.message} — T ${money(res.total_commission_transporteur)} / A ${money(res.total_commission_admin)}`)
      setSelectedLivs(new Set())
      loadLivraisons(); loadStats(); loadWallet(); loadRetraits()
    } catch (err) { toast(errMsg(err),'error') }
  }

  const toggleSelectLiv = (id:number) => setSelectedLivs(prev => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n })
  const toggleSelectAll = () => {
    if (!livraisons) return
    const all = livraisons.data.every(l => selectedLivs.has(l.suivi_id))
    setSelectedLivs(all ? new Set() : new Set(livraisons.data.map(l=>l.suivi_id)))
  }

  const onRetraitDecision = async (id:number, decision:'approuve'|'refuse'|'paye'|'echec', forceManuel = false) => {
    const note = window.prompt(`Note pour ${decision} ?`) || ''
    if (decision !== 'approuve' && !window.confirm(forceManuel
      ? 'Marquer ce retrait comme PAYÉ MANUELLEMENT (espèces/virement) ? Le payout Kkiapay ne sera PAS tenté.'
      : `${decision} ce retrait ?${decision==='paye'?' Un payout Kkiapay sera tenté vers le numéro du transporteur.':''}`
    )) return
    try {
      const res = await api.post<{message:string;payout?:any;manuel?:boolean}>(`/api/admin/retraits/${id}/decision`,{decision,note,force_manuel:forceManuel})
      toast(res.message + (res.payout && !res.manuel ? ` - Payout ${res.payout.status||''}` : ''))
      loadRetraits(); loadWallet()
    } catch (err) {
      // Payout échoué : on affiche l'erreur avec option "forcer manuel"
      if (decision === 'paye' && !forceManuel && window.confirm(`${errMsg(err)}\n\nVoulez-vous marquer comme PAYÉ MANUELLEMENT ?`)) {
        return onRetraitDecision(id,'paye',true)
      }
      toast(errMsg(err),'error')
    }
  }

  const onKkiapaySetupRoof = async (e:React.FormEvent) => {
    e.preventDefault()
    setKkSetupLoading(true)
    try {
      const res = await api.post<{message:string;result:any}>('/api/admin/kkiapay/setup-payout',{
        algorithm:'roof',
        destination:kkSetupForm.destination,
        roof_amount:parseFloat(kkSetupForm.roof_amount)||50000,
      })
      toast(res.message); loadKkiapayStatus()
    } catch (err) { toast(errMsg(err),'error') }
    finally { setKkSetupLoading(false) }
  }

  const onKkiapayDirectPayout = async (e:React.FormEvent) => {
    e.preventDefault()
    if (!kkPayoutForm.phone || !kkPayoutForm.amount) { toast('Numéro et montant requis','error'); return }
    if (!window.confirm(`Envoyer ${money(parseFloat(kkPayoutForm.amount))} à ${kkPayoutForm.phone} ?`)) return
    setKkPayoutLoading(true)
    try {
      const res = await api.post<{status:string;result:any}>('/api/admin/kkiapay/payout-direct',{
        phone:kkPayoutForm.phone,
        amount:parseFloat(kkPayoutForm.amount),
        beneficiary_name:kkPayoutForm.name,
      })
      toast(`Payout ${res.status}`)
      setKkPayoutForm({phone:'',amount:'',name:''})
      loadKkiapayBalance()
    } catch (err) { toast(errMsg(err),'error') }
    finally { setKkPayoutLoading(false) }
  }
  const onRetraitRetry = async (id:number) => {
    if (!window.confirm('Retenter payout automatique Kkiapay ?')) return
    try {
      const res = await api.post<{message:string;payout:any;statut:string}>(`/api/admin/retraits/${id}/retry`,{})
      toast(`Retry: ${res.statut} - ${res.payout?.status||''}`); loadRetraits()
    } catch (err) { toast(errMsg(err),'error') }
  }
  const onAdminRetrait = async (e:React.FormEvent) => {
    e.preventDefault()
    try {
      await api.post('/api/admin/retraits',{montant:parseFloat(adminRetraitForm.montant),numero:adminRetraitForm.numero})
      toast('Retrait effectué'); setAdminRetraitForm({montant:'',numero:''}); loadWallet(); loadRetraits()
    } catch (err) { toast(errMsg(err),'error') }
  }

  /* ======================= Rendu ======================= */

  // Liste des sections de navigation admin (pour référence future / menu latéral)
  const _sectionsNav: { id: Section; label: string; icon?: string; badge?: React.ReactNode; color?: string }[] = [
    { id:'stats', label:'Tableau de bord', icon:'fa-gauge-high' },
    { id:'livraisons', label:'Livraisons', icon:'fa-truck-ramp-box', color:'danger',
      badge: stats && stats.demandes_livraison>0 ? <span className="badge bg-danger ms-1" style={{animation:'pulse 1s infinite'}}>{stats.demandes_livraison}</span> : null },
    { id:'colis', label:'Colis', icon:'fa-box',
      badge: stats && stats.demandes_livraison>0 ? <span className="badge bg-danger ms-1" style={{animation:'pulse 1s infinite'}}>{stats.demandes_livraison}</span> : null },
    { id:'voyages', label:'Voyages', icon:'fa-route' },
    { id:'transporteurs', label:'Transporteurs', icon:'fa-truck' },
  ]
  void _sectionsNav

  return (
    <>
      <div className="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h2 className="mb-0">
          <i className="fa-solid fa-shield-halved text-primary"></i> Administration
          {isSuperAdmin && <span className="badge bg-warning text-dark ms-2"><i className="fa-solid fa-crown"></i> Super Admin</span>}
        </h2>
        <div className="d-flex align-items-center gap-2">
          <button
            className={`btn btn-sm ${soundEnabled?'btn-outline-secondary':'btn-secondary'}`}
            title={soundEnabled?'Son activé (clic pour couper)':'Son coupé'}
            onClick={()=>setSoundEnabled(v=>!v)}
          >
            <i className={`fa-solid ${soundEnabled?'fa-volume-high':'fa-volume-xmark'}`}></i>
          </button>
          <select className="form-select form-select-sm" style={{width:'auto'}} value={perPage} onChange={e=>setPerPage(Number(e.target.value))}>
            <option value={10}>10/page</option>
            <option value={15}>15/page</option>
            <option value={25}>25/page</option>
            <option value={50}>50/page</option>
          </select>
        </div>
      </div>

      {/* NAV */}
      <div className="mb-4">
        <div className="d-flex flex-wrap gap-2">
          <NavBtn icon="fa-gauge-high" label="Tableau de bord" active={section==='stats'} onClick={()=>setSection('stats')} />
          <NavBtn icon="fa-truck-ramp-box" label="Livraisons à confirmer" active={section==='livraisons'} onClick={()=>setSection('livraisons')} variant="danger" badge={stats?.demandes_livraison || 0} pulse={!!stats?.demandes_livraison} />
          <NavBtn icon="fa-box" label="Colis" active={section==='colis'} onClick={()=>setSection('colis')} badge={stats?.colis_en_attente || 0} />
          <NavBtn icon="fa-route" label="Voyages" active={section==='voyages'} onClick={()=>setSection('voyages')} badge={stats?.voyages_en_attente || 0} />
          <NavBtn icon="fa-truck" label="Transporteurs" active={section==='transporteurs'} onClick={()=>setSection('transporteurs')} />
          <NavBtn icon="fa-credit-card" label="Paiements" active={section==='paiements'} onClick={()=>setSection('paiements')} />
          <NavBtn icon="fa-star" label="Avis" active={section==='avis'} onClick={()=>setSection('avis')} badge={stats?.avis_en_attente || 0} />
          <NavBtn icon="fa-envelope" label="Contact" active={section==='contact'} onClick={()=>setSection('contact')} badge={stats?.messages_contact_non_lus || 0} />
          <NavBtn icon="fa-wallet" label="Wallet" active={section==='wallet'} onClick={()=>setSection('wallet')} />
          <NavBtn icon="fa-money-bill-transfer" label="Retraits" active={section==='retraits'} onClick={()=>setSection('retraits')} badge={stats?.retraits_en_attente || 0} />
          <NavBtn icon="fa-credit-card" label="Kkiapay" active={section==='kkiapay'} onClick={()=>setSection('kkiapay')} variant="primary" />
          <NavBtn icon="fa-users" label="Utilisateurs" active={section==='users'} onClick={()=>setSection('users')} />
          {isSuperAdmin && <NavBtn icon="fa-crown" label="Admins" active={section==='admins'} onClick={()=>setSection('admins')} variant="warning" />}
        </div>
      </div>

      {sectionError && <div className="alert alert-danger">{sectionError}</div>}

      {/* Bannière discrète en haut si livraisons (sur toutes pages sauf livraisons) */}
      {stats && stats.demandes_livraison>0 && section!=='livraisons' && (
        <div
          className="alert alert-danger d-flex align-items-center justify-content-between py-2 mb-4"
          role="alert"
          style={{ animation:'adminBlink 1.4s ease-in-out infinite', border:'2px solid #dc3545' }}
        >
          <span>
            <i className="fa-solid fa-bell me-2"></i>
            <strong>{stats.demandes_livraison}</strong> livraison{stats.demandes_livraison>1?'s':''} à confirmer
          </span>
          <button className="btn btn-sm btn-light" onClick={()=>setSection('livraisons')}>
            Voir <i className="fa-solid fa-arrow-right ms-1"></i>
          </button>
          <style>{`
            @keyframes adminBlink {
              0%,100%{background-color:#dc3545;color:#fff} 50%{background-color:#b02a37;color:#fff}
            }
          `}</style>
        </div>
      )}

      {/* ==================== STATS ==================== */}
      {section==='stats' && stats && (
        <>
          <div className="row g-3 mb-3">
            <div className="col-6 col-md-3"><StatCard value={stats.nb_users} label="Utilisateurs" sub={<><RoleBadge role="client"/> {stats.nb_clients} · <RoleBadge role="transporteur"/> {stats.nb_transporteurs_users}<br/><RoleBadge role="admin"/> {stats.nb_admins} · <RoleBadge role="super_admin"/> {stats.nb_super_admins}</>} subColor=""/></div>
            <div className="col-6 col-md-3"><StatCard value={stats.nb_colis} label="Colis" sub={`${stats.colis_en_attente} en attente`}/></div>
            <div className="col-6 col-md-3"><StatCard value={stats.nb_voyages} label="Voyages" sub={`${stats.voyages_en_attente} en attente`}/></div>
            <div className="col-6 col-md-3"><StatCard value={stats.nb_transporteurs} label="Fiches transporteurs" /></div>
            <div className="col-6 col-md-3"><StatCard value={stats.nb_paiements} label="Paiements" sub={`${money(stats.montant_total_paye)} payés`} subColor="text-success"/></div>
            <div className="col-6 col-md-3"><StatCard value={stats.nb_avis} label="Avis" sub={`${stats.avis_en_attente} en attente`}/></div>
            <div className="col-6 col-md-3"><StatCard value={stats.demandes_livraison} label="Livraisons à confirmer" subColor="text-danger" sub={stats.demandes_livraison>0?<button className="btn btn-sm btn-outline-danger mt-1" onClick={()=>setSection('livraisons')}>Traiter →</button>:undefined}/></div>
            <div className="col-6 col-md-3"><StatCard value={stats.messages_contact_non_lus} label="Messages non lus"/></div>
          </div>
          <div className="row g-3">
            <div className="col-6 col-md-3"><div className="card bg-success text-white"><div className="card-body text-center p-3"><h4 className="mb-0">{money(stats.admin_wallet_solde||0)}</h4><small>Wallet Admin (95%)</small></div></div></div>
            <div className="col-6 col-md-3"><div className="card bg-primary text-white"><div className="card-body text-center p-3"><h4 className="mb-0">{money(stats.admin_commission_total||0)}</h4><small>Commission totale 95%</small></div></div></div>
            <div className="col-6 col-md-3"><div className="card bg-info text-dark"><div className="card-body text-center p-3"><h4 className="mb-0">{money(stats.total_paye_transporteurs||0)}</h4><small>Payé transporteurs 5%</small></div></div></div>
            <div className="col-6 col-md-3"><div className="card bg-warning text-dark"><div className="card-body text-center p-3"><h4 className="mb-0">{stats.retraits_en_attente||0} / {stats.retraits_payes||0}</h4><small>Retraits attente / payés</small></div></div></div>
          </div>
        </>
      )}

      {/* ==================== PAGE LIVRAISONS (dédiée) ==================== */}
      {section==='livraisons' && (
        <div className="page-card">
          <div className="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h5 className="mb-0"><i className="fa-solid fa-truck-ramp-box text-danger"></i> Livraisons à confirmer</h5>
            <span className="badge bg-danger">{livraisons?.pagination.total ?? stats?.demandes_livraison ?? 0} en attente</span>
          </div>

          {livraisons === null && <div className="text-muted py-4">Chargement…</div>}

          {livraisons && livraisons.data.length === 0 && (
            <div className="text-center py-5 text-success">
              <i className="fa-solid fa-circle-check fa-3x mb-3"></i>
              <h5>Aucune livraison en attente</h5>
              <p className="text-muted mb-0">Toutes les livraisons signalées ont été traitées.</p>
            </div>
          )}

          {livraisons && livraisons.data.length > 0 && (
            <>
              <div className="d-flex gap-2 mb-3 flex-wrap">
                <button className="btn btn-sm btn-outline-secondary" onClick={toggleSelectAll}>
                  <i className="fa-solid fa-check-double"></i> Tout sélectionner
                </button>
                <button
                  className="btn btn-sm btn-success"
                  disabled={selectedLivs.size===0}
                  onClick={()=>onBulkDecision('confirmer')}
                >
                  <i className="fa-solid fa-check"></i> Confirmer {selectedLivs.size>0?`(${selectedLivs.size})`:''}
                </button>
                <button
                  className="btn btn-sm btn-danger"
                  disabled={selectedLivs.size===0}
                  onClick={()=>onBulkDecision('refuser')}
                >
                  <i className="fa-solid fa-xmark"></i> Refuser {selectedLivs.size>0?`(${selectedLivs.size})`:''}
                </button>
                <button className="btn btn-sm btn-outline-primary ms-auto" onClick={loadLivraisons} title="Rafraîchir">
                  <i className="fa-solid fa-rotate"></i>
                </button>
              </div>

              <div className="row g-3">
                {livraisons.data.map(p => (
                  <div key={p.suivi_id} className="col-12 col-lg-6">
                    <div className={`card border-danger ${selectedLivs.has(p.suivi_id)?'border-3 shadow':''}`} style={{borderWidth:selectedLivs.has(p.suivi_id)?'3px':'1px'}}>
                      <div className="card-header bg-danger-subtle d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                        <div className="form-check mb-0">
                          <input
                            className="form-check-input"
                            type="checkbox"
                            checked={selectedLivs.has(p.suivi_id)}
                            onChange={()=>toggleSelectLiv(p.suivi_id)}
                            id={`chk-${p.suivi_id}`}
                          />
                          <label className="form-check-label small" htmlFor={`chk-${p.suivi_id}`}>
                            <strong>{p.nom_colis}</strong> <code className="ms-1">{p.numero_suivi}</code>
                          </label>
                        </div>
                        <span className="badge bg-danger"><i className="fa-solid fa-clock"></i> {datetime(p.date_etape)}</span>
                      </div>
                      <div className="card-body p-3">
                        <div className="row g-3">
                          <div className="col-4 col-md-3 text-center">
                            {p.image_url
                              ? <img src={p.image_url} alt={p.nom_colis} className="img-fluid rounded" style={{maxHeight:120,objectFit:'cover'}}/>
                              : <div className="bg-light rounded d-flex align-items-center justify-content-center text-muted" style={{height:100}}><i className="fa-solid fa-box fa-2x"></i></div>}
                          </div>
                          <div className="col-8 col-md-9">
                            <div className="row g-2 small">
                              <div className="col-12 col-sm-6">
                                <div className="text-muted mb-1"><i className="fa-solid fa-map-location-dot"></i> Trajet</div>
                                <div>{p.adresse_depart}</div>
                                <div>→ {p.adresse_destination}</div>
                                <div className="text-muted">{p.ville}, {p.pays} · {p.poids}kg</div>
                              </div>
                              <div className="col-12 col-sm-6">
                                <div className="text-muted mb-1"><i className="fa-solid fa-sack-dollar"></i> Prix / Commissions</div>
                                <div>Prix : <strong>{money(p.prix_estime)}</strong></div>
                                <div className="text-success">Transporteur (5%) : <strong>{money(p.commission_transporteur)}</strong></div>
                                <div className="text-primary">Admin (95%) : <strong>{money(p.commission_admin)}</strong></div>
                              </div>
                              <div className="col-12 col-sm-6">
                                <div className="text-muted mb-1"><i className="fa-solid fa-user"></i> Client</div>
                                <div>{p.client_prenom} {p.client_nom}</div>
                                <div className="text-muted">{p.client_email}</div>
                                <a href={`tel:${p.client_tel}`} className="small"><i className="fa-solid fa-phone"></i> {p.client_tel}</a>
                              </div>
                              <div className="col-12 col-sm-6">
                                <div className="text-muted mb-1"><i className="fa-solid fa-truck"></i> Transporteur</div>
                                <div>{p.transporteur_prenom} {p.transporteur_nom}</div>
                                <div className="small text-muted">{p.compagnie} · {p.vehicule}</div>
                                <a href={`tel:${p.transporteur_tel}`} className="small"><i className="fa-solid fa-phone"></i> {p.transporteur_tel}</a>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div className="card-footer bg-white d-flex gap-2 flex-wrap py-2">
                        <button className="btn btn-sm btn-success" onClick={()=>onLivraisonDecision(p.suivi_id,'confirmer')}>
                          <i className="fa-solid fa-check"></i> Confirmer + 95/5
                        </button>
                        <button className="btn btn-sm btn-outline-danger" onClick={()=>onLivraisonDecision(p.suivi_id,'refuser')}>
                          <i className="fa-solid fa-xmark"></i> Refuser
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              <Pagination info={livraisons.pagination} onChange={setLPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== COLIS ==================== */}
      {section==='colis' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-box"></i> Colis</h5>
          <div className="row g-2 mb-3">
            <div className="col-12 col-sm-6 col-md-4"><input className="form-control form-control-sm" placeholder="Recherche (nom, n° suivi, ville…)" value={cSearch} onChange={e=>setCSearch(e.target.value)}/></div>
            <div className="col-6 col-sm-4 col-md-3"><select className="form-select form-select-sm" value={cStatut} onChange={e=>setCStatut(e.target.value)}><option value="">Tous statuts</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadColis}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!colisList && <div className="text-muted py-4">Chargement…</div>}
          {colisList && (
            <>
              <div className="table-responsive">
                <table className="table table-sm align-middle table-hover">
                  <thead className="table-light"><tr><th style={{width:50}}>ID</th><th>Colis</th><th>Propriétaire</th><th>Poids/Prix</th><th>Statut</th><th>Livraison</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {colisList.data.length===0 && <tr><td colSpan={7} className="text-center text-muted py-4">Aucun colis</td></tr>}
                    {colisList.data.map(c=>(
                      <tr key={c.id} className={c.demande_livraison_id?'table-warning':''} style={c.demande_livraison_id?{boxShadow:'inset 3px 0 0 #dc3545'}:undefined}>
                        <td><strong>{c.id}</strong>{c.demande_livraison_id && <span className="badge bg-danger ms-1" style={{animation:'pulse 1s infinite'}}>!</span>}</td>
                        <td><div className="d-flex gap-2 align-items-center">
                          {c.image_url && <img src={c.image_url} alt="" className="img-colis"/>}
                          <div>
                            {c.nom_colis}
                            <div className="small text-muted"><code>{c.numero_suivi}</code> · {c.ville}, {c.pays}</div>
                            {c.demande_livraison_id && <span className="badge bg-danger mt-1"><i className="fa-solid fa-bell"></i> Livraison à confirmer</span>}
                          </div>
                        </div></td>
                        <td className="small">{c.prenom} {c.nom}<br/><span className="text-muted">{c.email}</span></td>
                        <td className="small">{c.poids} kg<br/><strong>{money(c.prix_estime)}</strong></td>
                        <td><span className={`badge ${c.statut==='approuve'?'bg-success':c.statut==='refuse'?'bg-danger':'bg-secondary'}`}>{c.statut}</span></td>
                        <td>{c.statut_livraison && <span className="badge bg-info text-dark">{c.statut_livraison}</span>}
                          {c.demande_livraison_id && <div className="mt-1 d-flex gap-1">
                            <button className="btn btn-sm btn-success" onClick={()=>onLivraisonDecision(c.demande_livraison_id!,'confirmer')}><i className="fa-solid fa-check"></i> Confirmer</button>
                            <button className="btn btn-sm btn-danger" onClick={()=>onLivraisonDecision(c.demande_livraison_id!,'refuser')}><i className="fa-solid fa-xmark"></i></button>
                          </div>}
                        </td>
                        <td><div className="d-flex gap-1 justify-content-end flex-wrap">
                          <select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{if(e.target.value) onColisStatut(c.id,e.target.value)}}>
                            <option value="">Statut…</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option>
                          </select>
                          <button className="btn btn-sm btn-outline-danger" onClick={()=>onColisDelete(c.id)} title="Supprimer"><i className="fa-solid fa-trash"></i></button>
                        </div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={colisList.pagination} onChange={setCPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== VOYAGES ==================== */}
      {section==='voyages' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-route"></i> Voyages</h5>
          <div className="row g-2 mb-3">
            <div className="col-12 col-sm-6 col-md-4"><input className="form-control form-control-sm" placeholder="Recherche" value={vSearch} onChange={e=>setVSearch(e.target.value)}/></div>
            <div className="col-6 col-sm-4 col-md-3"><select className="form-select form-select-sm" value={vStatut} onChange={e=>setVStatut(e.target.value)}><option value="">Tous</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadVoyages}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!voyages && <div className="text-muted py-4">Chargement…</div>}
          {voyages && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Transporteur</th><th>Trajet</th><th>Date</th><th>Capacité</th><th>Statut</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {voyages.data.length===0 && <tr><td colSpan={7} className="text-center text-muted py-4">Aucun voyage</td></tr>}
                    {voyages.data.map(v=>(
                      <tr key={v.id}>
                        <td><strong>{v.id}</strong></td>
                        <td className="small">{v.prenom} {v.nom}<br/><span className="text-muted">{v.email}</span></td>
                        <td><strong>{v.pays_depart} → {v.pays_destination}</strong></td>
                        <td className="small">{date(v.date_depart)} {(v.heure_depart||'').substring(0,5)}</td>
                        <td className="small">{v.poids_max} kg<br/><span className="text-muted">{v.nb_reservations} rés.</span></td>
                        <td><span className={`badge ${v.statut==='approuve'?'bg-success':v.statut==='refuse'?'bg-danger':'bg-secondary'}`}>{v.statut}</span></td>
                        <td><div className="d-flex gap-1 justify-content-end">
                          <select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{if(e.target.value) onVoyageStatut(v.id,e.target.value)}}>
                            <option value="">Statut…</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option>
                          </select>
                          <button className="btn btn-sm btn-outline-danger" onClick={()=>onVoyageDelete(v.id)}><i className="fa-solid fa-trash"></i></button>
                        </div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={voyages.pagination} onChange={setVPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== TRANSPORTEURS ==================== */}
      {section==='transporteurs' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-truck"></i> Transporteurs</h5>
          <div className="row g-2 mb-3">
            <div className="col-12 col-sm-6 col-md-4"><input className="form-control form-control-sm" placeholder="Recherche (nom, email, compagnie, ville)" value={tSearch} onChange={e=>setTSearch(e.target.value)}/></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadTransporteurs}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!transporteurs && <div className="text-muted py-4">Chargement…</div>}
          {transporteurs && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Transporteur</th><th>Véhicule</th><th>Colis livrés</th><th>Solde</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {transporteurs.data.length===0 && <tr><td colSpan={6} className="text-center text-muted py-4">Aucun transporteur</td></tr>}
                    {transporteurs.data.map(t=>(
                      <tr key={t.id}>
                        <td><strong>{t.id}</strong></td>
                        <td>{t.prenom} {t.nom}<br/><small className="text-muted">{t.email} · {t.telephone||'—'}</small></td>
                        <td className="small">{t.vehicule||'—'}<br/><span className="text-muted">{t.compagnie||''} {t.ville||''}</span></td>
                        <td><span className="badge bg-info text-dark">{t.nb_colis_transportes}</span></td>
                        <td className="fw-bold text-success">{money(t.solde)}</td>
                        <td className="text-end"><button className="btn btn-sm btn-outline-danger" onClick={()=>onTransporteurDelete(t.id)}><i className="fa-solid fa-trash"></i></button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={transporteurs.pagination} onChange={setTPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== PAIEMENTS ==================== */}
      {section==='paiements' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-credit-card"></i> Paiements</h5>
          <div className="row g-2 mb-3">
            <div className="col-12 col-sm-6 col-md-3"><input className="form-control form-control-sm" placeholder="Recherche" value={pSearch} onChange={e=>setPSearch(e.target.value)}/></div>
            <div className="col-6 col-sm-4 col-md-3"><select className="form-select form-select-sm" value={pStatut} onChange={e=>setPStatut(e.target.value)}><option value="">Tous</option><option value="en_attente">En attente</option><option value="paye">Payé</option><option value="echec">Échec</option></select></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadPaiements}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!paiements && <div className="text-muted py-4">Chargement…</div>}
          {paiements && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Colis</th><th>Client</th><th>Montant</th><th>Statut</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {paiements.data.length===0 && <tr><td colSpan={6} className="text-center text-muted py-4">Aucun paiement</td></tr>}
                    {paiements.data.map(p=>(
                      <tr key={p.id}>
                        <td><strong>{p.id}</strong></td>
                        <td className="small">{p.nom_colis||'—'}<br/><code className="text-muted">{p.numero_suivi||''}</code></td>
                        <td className="small">{p.prenom} {p.nom}<br/><span className="text-muted">{p.email}</span></td>
                        <td><strong>{money(p.montant)}</strong><br/><small className="text-muted">{p.methode_paiement||'kkiapay'}</small></td>
                        <td><span className={`badge ${p.statut==='paye'?'bg-success':p.statut==='echec'?'bg-danger':'bg-warning text-dark'}`}>{p.statut}</span></td>
                        <td className="text-end"><select className="form-select form-select-sm" style={{width:'auto',marginLeft:'auto'}} defaultValue="" onChange={e=>{if(e.target.value) onPaiementStatut(p.id,e.target.value)}}>
                          <option value="">Statut…</option><option value="en_attente">En attente</option><option value="paye">Payé</option><option value="echec">Échec</option>
                        </select></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={paiements.pagination} onChange={setPPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== AVIS ==================== */}
      {section==='avis' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-star"></i> Avis</h5>
          <div className="row g-2 mb-3">
            <div className="col-6 col-sm-4 col-md-3"><select className="form-select form-select-sm" value={aStatut} onChange={e=>setAStatut(e.target.value)}><option value="">Tous</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadAvis}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!avis && <div className="text-muted py-4">Chargement…</div>}
          {avis && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Client</th><th>Transporteur</th><th>Note</th><th>Commentaire</th><th>Statut</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {avis.data.length===0 && <tr><td colSpan={7} className="text-center text-muted py-4">Aucun avis</td></tr>}
                    {avis.data.map(a=>(
                      <tr key={a.id}>
                        <td><strong>{a.id}</strong></td>
                        <td>{a.user_prenom} {a.user_nom}</td>
                        <td>{a.transporteur_prenom} {a.transporteur_nom} (#{a.transporteur_id})</td>
                        <td>
                          {Array.from({length:5}).map((_,i) => (
                            <i key={i} className={`fa-${i<a.note?'solid':'regular'} fa-star ${i<a.note?'text-warning':'text-muted'}`}></i>
                          ))}
                        </td>
                        <td className="small" style={{maxWidth:280}}>{(a.commentaire||'').substring(0,100)}</td>
                        <td><span className={`badge ${a.statut==='approuve'?'bg-success':a.statut==='refuse'?'bg-danger':'bg-warning text-dark'}`}>{a.statut}</span></td>
                        <td className="text-end"><div className="d-flex gap-1 justify-content-end">
                          <select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{if(e.target.value) onAvisStatut(a.id,e.target.value)}}>
                            <option value="">Statut…</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option>
                          </select>
                          <button className="btn btn-sm btn-outline-danger" onClick={()=>onAvisDelete(a.id)}><i className="fa-solid fa-trash"></i></button>
                        </div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={avis.pagination} onChange={setAPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== CONTACT ==================== */}
      {section==='contact' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-envelope"></i> Messages Contact</h5>
          <div className="row g-2 mb-3">
            <div className="col-6 col-sm-4 col-md-3"><select className="form-select form-select-sm" value={ctRepondu} onChange={e=>setCtRepondu(e.target.value)}><option value="">Tous</option><option value="non">Non répondus</option><option value="oui">Répondus</option></select></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadContact}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!contacts && <div className="text-muted py-4">Chargement…</div>}
          {contacts && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Expéditeur</th><th>Message</th><th>Réponse</th><th>Date</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {contacts.data.length===0 && <tr><td colSpan={6} className="text-center text-muted py-4">Aucun message</td></tr>}
                    {contacts.data.map(c=>(
                      <tr key={c.id}>
                        <td><strong>{c.id}</strong></td>
                        <td>{c.nom}<br/><small className="text-muted">{c.email}</small></td>
                        <td className="small" style={{maxWidth:300}}>{c.message.substring(0,120)}{c.message.length>120?'…':''}</td>
                        <td className="small">{c.reponse?c.reponse.substring(0,80)+(c.reponse.length>80?'…':''):<span className="text-muted">—</span>}</td>
                        <td className="small">{datetime(c.date_envoi)}</td>
                        <td className="text-end"><div className="d-flex gap-1 justify-content-end">
                          <button className="btn btn-sm btn-outline-primary" onClick={()=>{setReplyModal({id:c.id,nom:c.nom,message:c.message});setReplyText(c.reponse||'')}}><i className="fa-solid fa-reply"></i> Répondre</button>
                          <button className="btn btn-sm btn-outline-danger" onClick={()=>onContactDelete(c.id)}><i className="fa-solid fa-trash"></i></button>
                        </div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={contacts.pagination} onChange={setCtPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== USERS ==================== */}
      {section==='users' && (
        <div className="page-card">
          <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 className="mb-0"><i className="fa-solid fa-users"></i> Utilisateurs {isSuperAdmin?'(tous rôles)':'(clients & transporteurs)'}</h5>
            <button className="btn btn-primary btn-sm" onClick={()=>setUserModal(true)}><i className="fa-solid fa-user-plus"></i> Créer utilisateur</button>
          </div>
          <div className="row g-2 mb-3">
            <div className="col-12 col-sm-6 col-md-4"><input className="form-control form-control-sm" placeholder="Recherche nom/email" value={uSearch} onChange={e=>setUSearch(e.target.value)}/></div>
            <div className="col-6 col-sm-4 col-md-3">
              <select className="form-select form-select-sm" value={uRole} onChange={e=>setURole(e.target.value)}>
                <option value="">Tous rôles {isSuperAdmin?'':'(clients/transp.)'}</option>
                <option value="client">Client</option><option value="transporteur">Transporteur</option>
                {isSuperAdmin && <><option value="admin">Admin</option><option value="super_admin">Super Admin</option></>}
              </select>
            </div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadUsers}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
          </div>
          {!users && <div className="text-muted py-4">Chargement…</div>}
          {users && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Rôle</th><th>Inscrit</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {users.data.length===0 && <tr><td colSpan={7} className="text-center text-muted py-4">Aucun utilisateur</td></tr>}
                    {users.data.map(u=>(
                      <tr key={u.id}>
                        <td><strong>{u.id}</strong></td>
                        <td>{u.prenom} {u.nom}</td>
                        <td className="small">{u.email}</td>
                        <td className="small">{u.telephone||'—'}</td>
                        <td><RoleBadge role={u.role}/></td>
                        <td className="small">{date(u.date_inscription)}</td>
                        <td className="text-end"><div className="d-flex gap-1 justify-content-end flex-wrap">
                          {isSuperAdmin ? (
                            <select className="form-select form-select-sm" style={{width:'auto'}} value={u.role} onChange={e=>onChangeRole(u.id,e.target.value)}>
                              <option value="client">Client</option><option value="transporteur">Transporteur</option><option value="admin">Admin</option><option value="super_admin">Super Admin</option>
                            </select>
                          ) : (
                            <select className="form-select form-select-sm" style={{width:'auto'}} value={u.role} onChange={e=>onChangeRole(u.id,e.target.value)}>
                              <option value="client">Client</option><option value="transporteur">Transporteur</option>
                            </select>
                          )}
                          <button className="btn btn-sm btn-outline-danger" onClick={()=>onDeleteUser(u.id)} disabled={u.id===me?.id}><i className="fa-solid fa-trash"></i></button>
                        </div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={users.pagination} onChange={setUPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== ADMINS (super) ==================== */}
      {section==='admins' && isSuperAdmin && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-crown text-warning"></i> Administrateurs</h5>
          {!admins && <div className="text-muted py-4">Chargement…</div>}
          {admins && (
            <div className="table-responsive">
              <table className="table table-hover align-middle">
                <thead className="table-light"><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscrit</th><th className="text-end">Actions</th></tr></thead>
                <tbody>
                  {admins.map(a=>(
                    <tr key={a.id}>
                      <td><strong>{a.id}</strong></td>
                      <td>{a.prenom} {a.nom}</td>
                      <td>{a.email}</td>
                      <td><RoleBadge role={a.role}/></td>
                      <td className="small">{date(a.date_inscription)}</td>
                      <td className="text-end"><div className="d-flex gap-1 justify-content-end">
                        {a.role==='admin' && <button className="btn btn-sm btn-warning" onClick={()=>onChangeRole(a.id,'super_admin')}><i className="fa-solid fa-crown"></i> Promouvoir</button>}
                        {a.role==='super_admin' && a.id!==me?.id && <button className="btn btn-sm btn-outline-info" onClick={()=>onChangeRole(a.id,'admin')}><i className="fa-solid fa-arrow-down"></i> Rétrograder</button>}
                        <button className="btn btn-sm btn-outline-danger" onClick={()=>onDeleteUser(a.id)} disabled={a.id===me?.id}><i className="fa-solid fa-trash"></i></button>
                      </div></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ==================== WALLET ==================== */}
      {section==='wallet' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-wallet"></i> Portefeuille Admin (95% plateforme)</h5>
          {!wallet && <div className="text-muted py-4">Chargement…</div>}
          {wallet && (
            <>
              <div className="row g-3 mb-4">
                <div className="col-6 col-md-3"><div className="card bg-success text-white"><div className="card-body text-center p-3"><h3 className="mb-0">{money(wallet.solde)}</h3><small>Solde disponible</small></div></div></div>
                <div className="col-6 col-md-3"><div className="card bg-primary text-white"><div className="card-body text-center p-3"><h3 className="mb-0">{money(wallet.total_commission_generee)}</h3><small>Commission totale (95%)</small></div></div></div>
                <div className="col-6 col-md-3"><div className="card bg-info text-dark"><div className="card-body text-center p-3"><h3 className="mb-0">{money(wallet.total_paye_transporteurs)}</h3><small>Payé transporteurs (5%)</small></div></div></div>
                <div className="col-6 col-md-3"><div className="card bg-warning text-dark"><div className="card-body text-center p-3"><h3 className="mb-0">{money(wallet.total_retraits_admin)}</h3><small>Retraits admin effectués</small></div></div></div>
              </div>
              <div className="alert alert-info small">
                <i className="fa-solid fa-circle-info"></i> Répartition : <strong>5%</strong> transporteur, <strong>95%</strong> admin. Payout automatique Kkiapay Mobile Money sur le numéro du transporteur après confirmation. Si échec, retrait en attente dans l'onglet Retraits.
              </div>
              <h6 className="mt-4"><i className="fa-solid fa-hand-holding-dollar"></i> Retirer mon solde admin</h6>
              <form onSubmit={onAdminRetrait} className="row g-2 align-items-end">
                <div className="col-12 col-sm-4"><label className="form-label small">Montant XOF</label><input type="number" className="form-control" min={1000} value={adminRetraitForm.montant} onChange={e=>setAdminRetraitForm({...adminRetraitForm,montant:e.target.value})} required/></div>
                <div className="col-12 col-sm-5"><label className="form-label small">Numéro / Compte</label><input type="text" className="form-control" placeholder="Mobile Money ou IBAN" value={adminRetraitForm.numero} onChange={e=>setAdminRetraitForm({...adminRetraitForm,numero:e.target.value})}/></div>
                <div className="col-12 col-sm-3"><button className="btn btn-primary w-100" type="submit"><i className="fa-solid fa-money-bill-transfer"></i> Retirer</button></div>
              </form>
            </>
          )}
        </div>
      )}

      {/* ==================== RETRAITS ==================== */}
      {section==='retraits' && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-money-bill-transfer"></i> Retraits & Paiements</h5>
          <div className="row g-2 mb-3">
            <div className="col-12 col-sm-6 col-md-3"><input className="form-control form-control-sm" placeholder="Recherche réf/num/nom" value={rSearch} onChange={e=>setRSearch(e.target.value)}/></div>
            <div className="col-6 col-sm-4 col-md-2"><select className="form-select form-select-sm" value={rType} onChange={e=>setRType(e.target.value)}><option value="">Tous types</option><option value="transporteur">Transporteur 5%</option><option value="admin">Admin 95%</option></select></div>
            <div className="col-6 col-sm-4 col-md-2"><select className="form-select form-select-sm" value={rStatut} onChange={e=>setRStatut(e.target.value)}><option value="">Tous statuts</option><option value="en_attente">En attente</option><option value="paye">Payé</option><option value="echec">Échec</option><option value="refuse">Refusé</option></select></div>
            <div className="col-6 col-sm-2 col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadRetraits}><i className="fa-solid fa-magnifying-glass"></i> Filtrer</button></div>
            <div className="col-12 col-md-3 text-md-end">
              <span className="badge bg-warning text-dark me-1">{retraits?.data.filter(r=>r.statut==='en_attente').length||0} en attente</span>
              <span className="badge bg-success">{retraits?.data.filter(r=>r.statut==='paye').length||0} payés</span>
            </div>
          </div>
          {!retraits && <div className="text-muted py-4">Chargement…</div>}
          {retraits && (
            <>
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle">
                  <thead className="table-light"><tr><th>ID</th><th>Type</th><th>Bénéficiaire</th><th>Montant</th><th>Statut</th><th>Numéro</th><th>Réf</th><th>Colis</th><th>Date</th><th className="text-end">Actions</th></tr></thead>
                  <tbody>
                    {retraits.data.length===0 && <tr><td colSpan={10} className="text-center text-muted py-4">Aucun retrait</td></tr>}
                    {retraits.data.map(r=>(
                      <tr key={r.id}>
                        <td><strong>{r.id}</strong></td>
                        <td><span className={`badge ${r.type==='transporteur'?'bg-success':'bg-primary'}`}>{r.type}</span></td>
                        <td className="small">{r.prenom} {r.nom}<br/><span className="text-muted">{r.email}</span></td>
                        <td className="fw-bold text-nowrap">{money(r.montant)}</td>
                        <td><span className={`badge ${r.statut==='paye'?'bg-success':r.statut==='en_attente'?'bg-warning text-dark':r.statut==='echec'?'bg-danger':'bg-secondary'}`}>{r.statut}</span></td>
                        <td className="small">{r.numero||'—'}</td>
                        <td className="small"><code>{r.reference}</code></td>
                        <td className="small">{r.colis_id?`#${r.colis_id} ${r.nom_colis||''}`:'—'}</td>
                        <td className="small text-nowrap">{datetime(r.date_demande)}</td>
                        <td className="text-end"><div className="d-flex gap-1 justify-content-end flex-wrap">
                          {r.statut==='en_attente' && <>
                            <button className="btn btn-sm btn-success" title="Tente un payout Kkiapay direct vers ce numéro (compte Pro requis)" onClick={()=>onRetraitDecision(r.id,'paye')}><i className="fa-solid fa-money-bill-wave"></i> Payer</button>
                            <button className="btn btn-sm btn-outline-secondary" title="Marquer payé manuellement (espèces/virement/transfert depuis ton téléphone). NE tente PAS de payout Kkiapay." onClick={()=>onRetraitDecision(r.id,'paye',true)}><i className="fa-solid fa-hand-holding-dollar"></i> Manuel</button>
                            <button className="btn btn-sm btn-outline-success" onClick={()=>onRetraitDecision(r.id,'approuve')}>Approuver</button>
                            <button className="btn btn-sm btn-outline-danger" onClick={()=>onRetraitDecision(r.id,'refuse')}>Refuser</button>
                          </>}
                          {r.statut==='echec' && <button className="btn btn-sm btn-warning" onClick={()=>onRetraitRetry(r.id)}><i className="fa-solid fa-rotate"></i> Retry</button>}
                          {r.statut==='paye' && <span className="small text-success"><i className="fa-solid fa-check"></i> OK</span>}
                        </div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination info={retraits.pagination} onChange={setRPage}/>
            </>
          )}
        </div>
      )}

      {/* ==================== KKIAPAY ==================== */}
      {section==='kkiapay' && (
        <div className="page-card">
          <h5 className="mb-3">
            <i className="fa-solid fa-credit-card text-primary"></i> Kkiapay — Paiements & Retraits Mobile Money
          </h5>

          {!kkStatus && <div className="text-muted py-4">Chargement…</div>}

          {kkStatus && (
            <>
              {/* État config */}
              <div className={`alert ${kkStatus.configured?'alert-success':'alert-warning'}`}>
                <div className="d-flex flex-wrap gap-3 align-items-center">
                  <span>
                    <i className={`fa-solid ${kkStatus.configured?'fa-circle-check':'fa-triangle-exclamation'} me-2`}></i>
                    <strong>Statut :</strong>{' '}
                    {kkStatus.configured ? 'Clés API configurées' : 'Clés API MANQUANTES (.env)'}
                  </span>
                  {kkStatus.configured && <span className="badge bg-dark">Clé : {kkStatus.public_key_masked}</span>}
                  <span className={`badge ${kkStatus.sandbox?'bg-warning text-dark':'bg-success'}`}>
                    <i className={`fa-solid ${kkStatus.sandbox?'fa-flask':'fa-rocket'} me-1`}></i>
                    {kkStatus.sandbox ? 'SANDBOX (test)' : 'PRODUCTION'}
                  </span>
                  {kkStatus.simulate_fallback && <span className="badge bg-danger">Simulation activée</span>}
                  <span className="badge bg-info text-dark ms-auto">{kkStatus.base_url}</span>
                </div>
                {!kkStatus.configured && (
                  <div className="mt-2 mb-0 small">
                    Ajoutez dans votre fichier <code>.env</code> de Laravel :
                    <pre className="mt-2 mb-0 bg-light p-2 rounded small">{`KKIAPAY_PUBLIC_KEY=...
KKIAPAY_PRIVATE_KEY=...
KKIAPAY_SECRET=...
KKIAPAY_SANDBOX=true
KKIAPAY_FALLBACK_SIMULATE=false`}</pre>
                    <p className="mt-2 mb-0"><i className="fa-solid fa-hand-point-right me-1"></i>Les clés sont sur <a href="https://kkiapay.me/merchant/settings" target="_blank" rel="noreferrer">dashboard.kkiapay.me → Paramètres → API</a></p>
                  </div>
                )}
              </div>

              <div className="row g-3">
                {/* Solde Kkiapay */}
                <div className="col-12 col-md-6">
                  <div className="card h-100">
                    <div className="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
                      <strong><i className="fa-solid fa-sack-dollar"></i> Solde compte Kkiapay</strong>
                      <button className="btn btn-sm btn-light" onClick={loadKkiapayBalance}><i className="fa-solid fa-rotate"></i></button>
                    </div>
                    <div className="card-body">
                      {!kkBalance && <div className="text-muted small">Cliquez sur l'icône ↻ pour charger…</div>}
                      {kkBalance && kkBalance.status !== 'SUCCESS' && (
                        <div className="alert alert-warning small mb-0">
                          <i className="fa-solid fa-xmark"></i> Impossible de récupérer le solde :
                          <pre className="mb-0 mt-2 small">{JSON.stringify(kkBalance, null, 2)}</pre>
                        </div>
                      )}
                      {kkBalance && kkBalance.status === 'SUCCESS' && (
                        <div className="row g-2 small">
                          {[
                            ['Solde disponible','available_balance','text-success'],
                            ['Solde en opération','operation_balance','text-warning'],
                            ['Solde total','total_balance','text-primary'],
                          ].map(([label,key,col]) => {
                            const val = kkBalance[key] ?? kkBalance[key.replace('_','')] ?? kkBalance.data?.[key] ?? null
                            return val !== null ? (
                              <div key={key} className="col-12">
                                <div className={`d-flex justify-content-between p-2 rounded bg-light ${col} fw-bold`}>
                                  <span>{label}</span><span>{money(Number(val) || val)}</span>
                                </div>
                              </div>
                            ) : null
                          })}
                          <details className="col-12 mt-2">
                            <summary className="small text-muted cursor-pointer">Réponse brute API</summary>
                            <pre className="small mt-2 bg-light p-2" style={{maxHeight:200,overflow:'auto'}}>{JSON.stringify(kkBalance,null,2)}</pre>
                          </details>
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {/* Payout automatique (roof) */}
                <div className="col-12 col-md-6">
                  <div className="card h-100">
                    <div className="card-header bg-success text-white py-2">
                      <strong><i className="fa-solid fa-rotate"></i> Payout automatique (par plafond)</strong>
                    </div>
                    <div className="card-body">
                      <p className="small text-muted mb-3">
                        Configure Kkiapay pour qu'il <strong>verse automatiquement</strong> l'argent collecté
                        vers <strong>ton propre numéro Mobile Money</strong> dès qu'un certain montant est atteint.
                        Minimum Kkiapay : <strong>50 000 FCFA</strong>.
                      </p>
                      <form onSubmit={onKkiapaySetupRoof} className="row g-2">
                        <div className="col-12">
                          <label className="form-label small">Numéro Mobile Money admin (Bénin)</label>
                          <input type="tel" className="form-control form-control-sm" placeholder="22997000000"
                            value={kkSetupForm.destination}
                            onChange={e=>setKkSetupForm({...kkSetupForm,destination:e.target.value})} required/>
                          <div className="form-text">Kkiapay enverra un code de vérification à ce numéro.</div>
                        </div>
                        <div className="col-12">
                          <label className="form-label small">Plafond de virement (roof_amount) — FCFA</label>
                          <input type="number" className="form-control form-control-sm" min={50000} step={5000}
                            value={kkSetupForm.roof_amount}
                            onChange={e=>setKkSetupForm({...kkSetupForm,roof_amount:e.target.value})} required/>
                        </div>
                        <div className="col-12 d-grid">
                          <button className="btn btn-sm btn-success" disabled={kkSetupLoading || !kkStatus.configured}>
                            {kkSetupLoading ? <><i className="fa-solid fa-spinner fa-spin"></i> Configuration…</> : <><i className="fa-solid fa-gears"></i> Activer payout automatique</>}
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                {/* Payout direct (Merchant Pro) */}
                <div className="col-12 col-md-6">
                  <div className="card h-100">
                    <div className="card-header bg-warning text-dark py-2">
                      <strong><i className="fa-solid fa-paper-plane"></i> Payout direct (Merchant Pro)</strong>
                    </div>
                    <div className="card-body">
                      <div className="alert alert-warning small py-2 mb-2">
                        <i className="fa-solid fa-circle-info"></i> Cette fonctionnalité est réservée aux comptes
                        <strong> Kkiapay Merchant Pro</strong>. Sans compte Pro, cette requête échouera (c'est normal).
                      </div>
                      <p className="small text-muted mb-2">
                        Envoie directement de l'argent depuis ton solde Kkiapay vers un numéro Mobile Money.
                      </p>
                      <form onSubmit={onKkiapayDirectPayout} className="row g-2">
                        <div className="col-12 col-sm-8">
                          <label className="form-label small">Numéro bénéficiaire</label>
                          <input type="tel" className="form-control form-control-sm" placeholder="22997000000"
                            value={kkPayoutForm.phone} onChange={e=>setKkPayoutForm({...kkPayoutForm,phone:e.target.value})} required/>
                        </div>
                        <div className="col-12 col-sm-4">
                          <label className="form-label small">Montant FCFA</label>
                          <input type="number" className="form-control form-control-sm" min={100}
                            value={kkPayoutForm.amount} onChange={e=>setKkPayoutForm({...kkPayoutForm,amount:e.target.value})} required/>
                        </div>
                        <div className="col-12">
                          <label className="form-label small">Nom bénéficiaire (optionnel)</label>
                          <input type="text" className="form-control form-control-sm"
                            value={kkPayoutForm.name} onChange={e=>setKkPayoutForm({...kkPayoutForm,name:e.target.value})}/>
                        </div>
                        <div className="col-12 d-grid">
                          <button className="btn btn-sm btn-warning" disabled={kkPayoutLoading || !kkStatus.configured}>
                            {kkPayoutLoading ? <><i className="fa-solid fa-spinner fa-spin"></i> Envoi…</> : <><i className="fa-solid fa-paper-plane"></i> Envoyer</>}
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                {/* Guide */}
                <div className="col-12 col-md-6">
                  <div className="card h-100">
                    <div className="card-header bg-info text-dark py-2">
                      <strong><i className="fa-solid fa-book"></i> Comment ça marche ?</strong>
                    </div>
                    <div className="card-body small">
                      <ol className="mb-0 ps-3">
                        <li>Les clients payent leurs colis via <strong>le widget Kkiapay</strong> (déjà en place) → l'argent arrive sur ton compte Kkiapay.</li>
                        <li>Quand tu confirmes une livraison, la base calcule :
                          <ul>
                            <li><span className="text-success">5%</span> crédités sur le solde du transporteur (DANS L'APP)</li>
                            <li><span className="text-primary">95%</span> crédités sur ton wallet admin (DANS L'APP)</li>
                          </ul>
                        </li>
                        <li>Le transporteur voit son solde dans son dashboard et fait une <strong>demande de retrait</strong>.</li>
                        <li>Tu vas dans l'onglet <strong>Retraits</strong>, tu cliques <strong>« Payer »</strong> :
                          <ul>
                            <li>Si payout direct fonctionne (compte Pro), l'argent est envoyé immédiatement.</li>
                            <li>Sinon, tu as un bouton <strong>« Marquer payé manuellement »</strong> (espèces / virement / Orange Money depuis ton téléphone).</li>
                          </ul>
                        </li>
                        <li>Pour TES commissions (95%), active <strong>Payout automatique</strong> ci-dessus : Kkiapay te reverse tout seul sur ton numéro Mobile Money dès que le seuil est atteint.</li>
                      </ol>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      )}

      {/* ==================== Modals ==================== */}
      {userModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{backgroundColor:'rgba(0,0,0,.5)'}}>
          <div className="modal-dialog">
            <div className="modal-content">
              <form onSubmit={onCreateUser}>
                <div className="modal-header"><h5 className="modal-title">Créer utilisateur</h5><button type="button" className="btn-close" onClick={()=>setUserModal(false)}></button></div>
                <div className="modal-body">
                  {userModalError && <div className="alert alert-danger small">{userModalError}</div>}
                  <div className="mb-2"><label className="form-label small">Nom</label><input className="form-control form-control-sm" value={userForm.nom} onChange={e=>setUserForm({...userForm,nom:e.target.value})} required/></div>
                  <div className="mb-2"><label className="form-label small">Prénom</label><input className="form-control form-control-sm" value={userForm.prenom} onChange={e=>setUserForm({...userForm,prenom:e.target.value})} required/></div>
                  <div className="mb-2"><label className="form-label small">Email</label><input type="email" className="form-control form-control-sm" value={userForm.email} onChange={e=>setUserForm({...userForm,email:e.target.value})} required/></div>
                  <div className="mb-2"><label className="form-label small">Mot de passe (8 caractères min)</label><input type="password" className="form-control form-control-sm" value={userForm.password} onChange={e=>setUserForm({...userForm,password:e.target.value})} required minLength={8}/></div>
                  <div className="mb-2"><label className="form-label small">Rôle</label>
                    <select className="form-select form-select-sm" value={userForm.role} onChange={e=>setUserForm({...userForm,role:e.target.value})}>
                      <option value="client">Client</option><option value="transporteur">Transporteur</option>
                      {isSuperAdmin && <><option value="admin">Admin simple</option><option value="super_admin">Super Admin</option></>}
                    </select>
                    {!isSuperAdmin && <div className="form-text">Admin simple ne peut créer que Client/Transporteur</div>}
                  </div>
                </div>
                <div className="modal-footer"><button type="button" className="btn btn-secondary btn-sm" onClick={()=>setUserModal(false)}>Annuler</button><button type="submit" className="btn btn-primary btn-sm">Créer</button></div>
              </form>
            </div>
          </div>
        </div>
      )}

      {replyModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{backgroundColor:'rgba(0,0,0,.5)'}}>
          <div className="modal-dialog">
            <div className="modal-content">
              <form onSubmit={onReplyContact}>
                <div className="modal-header"><h5 className="modal-title">Répondre à {replyModal.nom}</h5><button type="button" className="btn-close" onClick={()=>setReplyModal(null)}></button></div>
                <div className="modal-body">
                  <div className="alert alert-light small mb-3">{replyModal.message}</div>
                  <label className="form-label small">Réponse</label>
                  <textarea className="form-control" rows={5} value={replyText} onChange={e=>setReplyText(e.target.value)} required></textarea>
                </div>
                <div className="modal-footer"><button type="button" className="btn btn-secondary btn-sm" onClick={()=>setReplyModal(null)}>Annuler</button><button type="submit" className="btn btn-primary btn-sm">Envoyer</button></div>
              </form>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

/* ============================================================
   Bouton de navigation
   ============================================================ */

function NavBtn({
  icon, label, active, onClick, variant, badge, pulse,
}: {
  icon: string; label: string; active: boolean; onClick: ()=>void;
  variant?: 'danger'|'warning'|'primary'; badge?: number|React.ReactNode; pulse?: boolean;
}) {
  const cls = active
    ? (variant==='danger'?'btn-danger':variant==='warning'?'btn-warning':variant==='primary'?'btn-primary':'btn-primary')
    : (variant==='danger'?'btn-outline-danger':variant==='warning'?'btn-outline-warning':'btn-outline-primary')
  return (
    <button className={`btn btn-sm ${cls}`} onClick={onClick}>
      <i className={`fa-solid ${icon} me-1`}></i>{label}
      {badge !== undefined && badge !== null && badge !== 0 && (
        <span className={`badge bg-light text-dark ms-1${pulse?' animate-pulse':''}`} style={pulse?{animation:'pulse 1s infinite'}:undefined}>
          {typeof badge === 'number' ? badge : badge}
        </span>
      )}
    </button>
  )
}
