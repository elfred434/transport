import { useCallback, useEffect, useState } from 'react'

import { api, ApiError } from '../../lib/api'
import { date, datetime, money } from '../../lib/format'
import { useToast } from '../../components/Toasts'
import { useAuth } from '../../context/AuthContext'

type Section = 'stats' | 'users' | 'colis' | 'voyages' | 'transporteurs' | 'paiements' | 'avis' | 'contact' | 'admins' | 'wallet' | 'retraits'

interface PendingLivraison {
  suivi_id: number
  date_etape: string
  colis_id: number
  nom_colis: string
  numero_suivi: string
  prix_estime: string | number
  ville: string
  pays: string
  client_nom: string | null
  client_prenom: string | null
  client_tel: string | null
  transporteur_id: number | null
  transporteur_nom: string | null
  transporteur_prenom: string | null
  transporteur_tel: string | null
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
interface AdminUser {
  id: number
  nom: string
  prenom: string
  email: string
  telephone: string | null
  photo_url: string | null
  role: string
  date_inscription: string
}
interface AdminColis {
  id: number
  nom_colis: string
  image_url: string | null
  numero_suivi: string
  prenom: string | null
  nom: string | null
  email: string | null
  ville: string
  pays: string
  poids: string | number
  prix_estime: string | number
  statut: string
  statut_livraison: string | null
  demande_livraison_id: number | null
}
interface AdminVoyage {
  id: number
  prenom: string | null
  nom: string | null
  email: string | null
  pays_depart: string
  pays_destination: string
  date_depart: string
  heure_depart: string | null
  poids_max: string | number
  nb_reservations: number
  statut: string
}
interface AdminTransporteur {
  id: number
  user_id: number
  prenom: string
  nom: string
  email: string
  telephone: string | null
  vehicule: string | null
  numero_permis: string | null
  compagnie: string | null
  ville: string | null
  pays: string | null
  nb_colis_transportes: number
  solde: string | number
}
interface AdminPaiement {
  id: number
  reference: string
  numero_transaction: string | null
  prenom: string | null
  nom: string | null
  email: string | null
  nom_colis: string | null
  numero_suivi: string | null
  montant: string | number
  methode_paiement: string | null
  operateur: string | null
  date_creation: string
  statut: string
}
interface AdminAvis {
  id: number
  user_prenom: string
  user_nom: string
  transporteur_id: number
  transporteur_prenom: string
  transporteur_nom: string
  note: number
  commentaire: string
  date_avis: string
  statut: string
}
interface AdminContact {
  id: number
  nom: string
  email: string
  message: string
  reponse: string | null
  date_envoi: string
  date_reponse: string | null
}
interface AdminWallet {
  solde: number
  total_commission_generee: number
  total_paye_transporteurs: number
  total_retraits_admin: number
}
interface AdminRetrait {
  id: number
  user_id: number
  type: string
  montant: number
  statut: string
  methode: string
  numero: string | null
  reference: string
  details: string | null
  colis_id: number | null
  nom: string | null
  prenom: string | null
  email: string | null
  nom_colis: string | null
  numero_suivi: string | null
  date_demande: string
  date_traitement: string | null
}

const errMsg = (e: unknown) => (e instanceof ApiError ? e.message : 'Erreur inconnue')

const ROLE_LABELS: Record<string, {label:string, color:string, icon:string}> = {
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

export default function AdminIndex() {
  const { toast } = useToast()
  const { me, isSuperAdmin } = useAuth()
  const [section, setSection] = useState<Section>('stats')

  const [stats, setStats] = useState<AdminStats | null>(null)
  const [users, setUsers] = useState<AdminUser[] | null>(null)
  const [colisList, setColisList] = useState<AdminColis[] | null>(null)
  const [voyages, setVoyages] = useState<AdminVoyage[] | null>(null)
  const [transporteurs, setTransporteurs] = useState<AdminTransporteur[] | null>(null)
  const [paiements, setPaiements] = useState<AdminPaiement[] | null>(null)
  const [avis, setAvis] = useState<AdminAvis[] | null>(null)
  const [contacts, setContacts] = useState<AdminContact[] | null>(null)
  const [admins, setAdmins] = useState<AdminUser[] | null>(null)
  const [wallet, setWallet] = useState<AdminWallet | null>(null)
  const [retraits, setRetraits] = useState<AdminRetrait[] | null>(null)
  const [sectionError, setSectionError] = useState<string | null>(null)

  const [uSearch, setUSearch] = useState('')
  const [uRole, setURole] = useState('')
  const [cSearch, setCSearch] = useState('')
  const [cStatut, setCStatut] = useState('')
  const [vSearch, setVSearch] = useState('')
  const [vStatut, setVStatut] = useState('')
  const [tSearch, setTSearch] = useState('')
  const [pSearch, setPSearch] = useState('')
  const [pStatut, setPStatut] = useState('')
  const [aStatut, setAStatut] = useState('')
  const [ctRepondu, setCtRepondu] = useState('')
  const [rSearch, setRSearch] = useState('')
  const [rStatut, setRStatut] = useState('')
  const [rType, setRType] = useState('')
  const [adminRetraitForm, setAdminRetraitForm] = useState({ montant: '', numero: '' })

  const [userModal, setUserModal] = useState(false)
  const [userForm, setUserForm] = useState({ nom: '', prenom: '', email: '', password: '', role: 'client' })
  const [userModalError, setUserModalError] = useState<string | null>(null)
  const [replyModal, setReplyModal] = useState<{ id: number; nom: string; message: string } | null>(null)
  const [replyText, setReplyText] = useState('')
  const [prevDemandesLivraison, setPrevDemandesLivraison] = useState<number>(-1)
  const [soundEnabled, setSoundEnabled] = useState(true)

  // Générateur de beep via Web Audio API (pas de fichier mp3 nécessaire)
  const playBeep = useCallback(() => {
    try {
      const AC = (window as any).AudioContext || (window as any).webkitAudioContext
      if (!AC) return
      const ctx = new AC()
      const beep = (freq: number, start: number, dur: number, vol = 0.25) => {
        const o = ctx.createOscillator()
        const g = ctx.createGain()
        o.type = 'sine'
        o.frequency.value = freq
        o.connect(g); g.connect(ctx.destination)
        g.gain.setValueAtTime(vol, ctx.currentTime + start)
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur)
        o.start(ctx.currentTime + start)
        o.stop(ctx.currentTime + start + dur)
      }
      beep(880, 0, 0.15)
      beep(1100, 0.2, 0.15)
      beep(1320, 0.4, 0.25)
      setTimeout(() => ctx.close().catch(()=>{}), 800)
    } catch { /* ignore */ }
  }, [])

  const loadStats = useCallback(async (opts?: { silent?: boolean }) => {
    if (!opts?.silent) setSectionError(null)
    try {
      const data = await api.get<AdminStats>('/api/admin/stats')
      setStats(prev => {
        // Détecter nouvelles livraisons pour alerte sonore + toast
        if (prev && data.demandes_livraison > prevDemandesLivraison && prevDemandesLivraison >= 0) {
          const nouveaux = data.demandes_livraison - prevDemandesLivraison
          toast(`🔔 ${nouveaux} nouvelle${nouveaux>1?'s':''} livraison${nouveaux>1?'s':''} à confirmer !`, 'error')
          if (soundEnabled) playBeep()
        }
        return data
      })
      setPrevDemandesLivraison(data.demandes_livraison)
    } catch (e) { if (!opts?.silent) setSectionError(errMsg(e)) }
  }, [toast, soundEnabled, playBeep, prevDemandesLivraison])

  const loadUsers = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (uSearch.trim()) p.set('search', uSearch.trim())
    if (uRole) p.set('role', uRole)
    try { setUsers(await api.get<AdminUser[]>('/api/admin/users' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [uSearch, uRole])

  const loadAdmins = useCallback(async () => {
    if (!isSuperAdmin) return
    setSectionError(null)
    try { setAdmins(await api.get<AdminUser[]>('/api/super-admin/admins')) } catch (e) { setSectionError(errMsg(e)) }
  }, [isSuperAdmin])

  const loadColis = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (cSearch.trim()) p.set('search', cSearch.trim())
    if (cStatut) p.set('statut', cStatut)
    try { setColisList(await api.get<AdminColis[]>('/api/admin/colis' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [cSearch, cStatut])

  const loadVoyages = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (vSearch.trim()) p.set('search', vSearch.trim())
    if (vStatut) p.set('statut', vStatut)
    try { setVoyages(await api.get<AdminVoyage[]>('/api/admin/voyages' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [vSearch, vStatut])

  const loadTransporteurs = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (tSearch.trim()) p.set('search', tSearch.trim())
    try { setTransporteurs(await api.get<AdminTransporteur[]>('/api/admin/transporteurs' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [tSearch])

  const loadPaiements = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (pSearch.trim()) p.set('search', pSearch.trim())
    if (pStatut) p.set('statut', pStatut)
    try { setPaiements(await api.get<AdminPaiement[]>('/api/admin/paiements' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [pSearch, pStatut])

  const loadAvis = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (aStatut) p.set('statut', aStatut)
    try { setAvis(await api.get<AdminAvis[]>('/api/admin/avis' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [aStatut])

  const loadContact = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (ctRepondu) p.set('repondu', ctRepondu)
    try { setContacts(await api.get<AdminContact[]>('/api/admin/contact-messages' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [ctRepondu])

  const loadWallet = useCallback(async () => {
    setSectionError(null)
    try { setWallet(await api.get<AdminWallet>('/api/admin/wallet')) } catch (e) { setSectionError(errMsg(e)) }
  }, [])

  const loadRetraits = useCallback(async () => {
    setSectionError(null)
    const p = new URLSearchParams()
    if (rSearch.trim()) p.set('search', rSearch.trim())
    if (rStatut) p.set('statut', rStatut)
    if (rType) p.set('type', rType)
    try { setRetraits(await api.get<AdminRetrait[]>('/api/admin/retraits' + (p.toString() ? '?' + p : ''))) } catch (e) { setSectionError(errMsg(e)) }
  }, [rSearch, rStatut, rType])

  useEffect(() => {
    const loaders: Record<Section, () => void> = {
      stats: loadStats,
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
    }
    loaders[section]()
  }, [section, loadStats, loadUsers, loadColis, loadVoyages, loadTransporteurs, loadPaiements, loadAvis, loadContact, loadAdmins, loadWallet, loadRetraits])

  // Polling stats toutes les 30s pour détecter les nouvelles livraisons
  useEffect(() => {
    loadStats({ silent: true }) // chargement initial silencieux
    const id = setInterval(() => loadStats({ silent: true }), 30000)
    return () => clearInterval(id)
  }, [loadStats])

  const onCreateUser = async (e: React.FormEvent) => {
    e.preventDefault()
    setUserModalError(null)
    try {
      await api.post('/api/admin/users', userForm)
      toast('Utilisateur créé')
      setUserModal(false)
      setUserForm({ nom: '', prenom: '', email: '', password: '', role: 'client' })
      loadUsers()
      if (isSuperAdmin) loadAdmins()
    } catch (err) { setUserModalError(errMsg(err)) }
  }

  const onDeleteUser = async (id: number) => {
    if (!window.confirm('Supprimer cet utilisateur ?')) return
    try {
      await api.del(`/api/admin/users/${id}`)
      toast('Utilisateur supprimé')
      loadUsers()
      if (isSuperAdmin) loadAdmins()
    } catch (err) { toast(errMsg(err), 'error') }
  }

  const onChangeRole = async (id: number, newRole: string) => {
    if (!window.confirm(`Changer le rôle en ${newRole} ?`)) return
    try {
      await api.post(`/api/admin/users/${id}/role`, { role: newRole })
      toast(`Rôle changé en ${newRole}`)
      loadUsers()
      if (isSuperAdmin) loadAdmins()
    } catch (err) { toast(errMsg(err), 'error') }
  }

  const onColisStatut = async (id: number, statut: string) => {
    try { await api.post(`/api/admin/colis/${id}/statut`, { statut }); toast(`Colis ${statut}`); loadColis() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onColisDelete = async (id: number) => {
    if (!window.confirm('Supprimer ce colis ?')) return
    try { await api.del(`/api/admin/colis/${id}`); toast('Colis supprimé'); loadColis() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onVoyageStatut = async (id: number, statut: string) => {
    try { await api.post(`/api/admin/voyages/${id}/statut`, { statut }); toast(`Voyage ${statut}`); loadVoyages() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onVoyageDelete = async (id: number) => {
    if (!window.confirm('Supprimer ce voyage ?')) return
    try { await api.del(`/api/admin/voyages/${id}`); toast('Voyage supprimé'); loadVoyages() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onTransporteurDelete = async (id: number) => {
    if (!window.confirm('Supprimer cette fiche transporteur ?')) return
    try { await api.del(`/api/admin/transporteurs/${id}`); toast('Fiche supprimée'); loadTransporteurs() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onPaiementStatut = async (id: number, statut: string) => {
    try { await api.post(`/api/admin/paiements/${id}/statut`, { statut }); toast(`Paiement ${statut}`); loadPaiements() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onAvisStatut = async (id: number, statut: string) => {
    try { await api.post(`/api/admin/avis/${id}/statut`, { statut }); toast(`Avis ${statut}`); loadAvis() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onAvisDelete = async (id: number) => {
    if (!window.confirm('Supprimer cet avis ?')) return
    try { await api.del(`/api/admin/avis/${id}`); toast('Avis supprimé'); loadAvis() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onContactDelete = async (id: number) => {
    if (!window.confirm('Supprimer ce message ?')) return
    try { await api.del(`/api/admin/contact-messages/${id}`); toast('Message supprimé'); loadContact() } catch (err) { toast(errMsg(err), 'error') }
  }
  const onReplyContact = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!replyModal) return
    try {
      await api.post(`/api/admin/contact-messages/${replyModal.id}/repondre`, { reponse: replyText })
      toast('Réponse envoyée')
      setReplyModal(null)
      setReplyText('')
      loadContact()
    } catch (err) { toast(errMsg(err), 'error') }
  }
  const onLivraisonDecision = async (id: number, decision: 'confirmer' | 'refuser') => {
    if (!window.confirm(decision === 'confirmer' ? 'Confirmer la livraison ? Transporteur 95% + payout auto sur son numéro, admin 5%.' : 'Refuser la livraison ?')) return
    try {
      const res = await api.post<{ commission_transporteur?: number; commission_admin?: number; payout?: any }>(`/api/admin/suivi/${id}/livraison`, { decision })
      if (decision === 'confirmer') {
        const payoutStatus = res.payout?.status === 'SUCCESS' ? ' - Payout auto SUCCESS' : res.payout ? ` - Payout ${res.payout.status}` : ''
        toast(`Livraison confirmée, transporteur ${money(res.commission_transporteur || 0)} (95%) + admin ${money(res.commission_admin || 0)} (5%)${payoutStatus}`)
      } else toast('Livraison refusée')
      loadColis()
      loadStats()
    } catch (err) { toast(errMsg(err), 'error') }
  }

  const onRetraitDecision = async (id: number, decision: 'approuve' | 'refuse' | 'paye' | 'echec') => {
    const note = window.prompt(`Note pour ${decision} ?`) || ''
    if (decision !== 'approuve' && !window.confirm(`${decision} ce retrait ?`)) return
    try {
      const res = await api.post<{ message: string; payout?: any }>(`/api/admin/retraits/${id}/decision`, { decision, note })
      toast(res.message + (res.payout ? ` - Payout ${res.payout.status}` : ''))
      loadRetraits()
      loadWallet()
    } catch (err) { toast(errMsg(err), 'error') }
  }

  const onRetraitRetry = async (id: number) => {
    if (!window.confirm('Retenter payout automatique Kkiapay ?')) return
    try {
      const res = await api.post<{ message: string; payout: any; statut: string }>(`/api/admin/retraits/${id}/retry`, {})
      toast(`Retry: ${res.statut} - ${res.payout?.status || ''}`)
      loadRetraits()
    } catch (err) { toast(errMsg(err), 'error') }
  }

  const onAdminRetrait = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      const res = await api.post<{ message: string }>(`/api/admin/retraits`, { montant: parseFloat(adminRetraitForm.montant), numero: adminRetraitForm.numero })
      toast(res.message)
      setAdminRetraitForm({ montant: '', numero: '' })
      loadWallet()
      loadRetraits()
    } catch (err) { toast(errMsg(err), 'error') }
  }

  return (
    <>
      <h2 className="mb-4"><i className="fa-solid fa-shield-halved text-primary"></i> Administration {isSuperAdmin && <span className="badge bg-warning text-dark ms-2"><i className="fa-solid fa-crown"></i> Super Admin</span>}</h2>

      <div className="mb-4">
        <div className="d-flex flex-wrap gap-2 align-items-center">
          {(['stats','users','colis','voyages','transporteurs','paiements','avis','contact','wallet','retraits'] as Section[]).map(s=>{
            const label = s==='wallet' ? '💰 Wallet' : s==='retraits' ? '💸 Retraits' : s
            const badge = s==='colis' && stats && stats.demandes_livraison>0
              ? <span className="badge bg-danger ms-1 animate-pulse" style={{animation:'pulse 1s infinite'}}><i className="fa-solid fa-bell"></i> {stats.demandes_livraison}</span>
              : null
            return (
              <button key={s} className={`btn btn-sm ${section===s?'btn-primary':'btn-outline-primary'}`} onClick={()=>setSection(s)}>
                {label}{badge}
              </button>
            )
          })}
          {isSuperAdmin && <button className={`btn btn-sm ${section==='admins'?'btn-warning':'btn-outline-warning'}`} onClick={()=>setSection('admins')}><i className="fa-solid fa-crown"></i> Admins</button>}
          <button
            className={`btn btn-sm ${soundEnabled?'btn-outline-secondary':'btn-secondary'}`}
            title={soundEnabled?'Son activé (clic pour couper)':'Son coupé'}
            onClick={()=>setSoundEnabled(v=>!v)}
          >
            <i className={`fa-solid ${soundEnabled?'fa-volume-high':'fa-volume-xmark'}`}></i>
          </button>
        </div>
        {isSuperAdmin ? <div className="alert alert-warning mt-3 small"><i className="fa-solid fa-crown"></i> Super Admin : vous gérez tous les rôles + wallet 5% + retraits transporteurs (payout auto Kkiapay Mobile Money sur numéro transporteur après vérification client).</div> : <div className="alert alert-info mt-3 small"><i className="fa-solid fa-shield-halved"></i> Admin : gérez clients, transporteurs, colis, voyages, paiements, avis, contacts, wallet (5% plateforme) et retraits auto 95% transporteur.</div>}
      </div>

      {sectionError && <div className="alert alert-danger">{sectionError}</div>}

      {/* BANNER ALERTE LIVRAISON — visible sur TOUTES les sections si livraisons en attente */}
      {stats && stats.demandes_livraison>0 && (
        <div
          className="alert alert-danger alert-dismissible d-flex align-items-start justify-content-between"
          role="alert"
          style={{
            animation: 'adminBlink 1.2s ease-in-out infinite',
            border: '2px solid #dc3545',
            boxShadow: '0 0 12px rgba(220,53,69,.35)',
          }}
        >
          <div>
            <h5 className="alert-heading mb-1"><i className="fa-solid fa-triangle-exclamation"></i> 🔴 {stats.demandes_livraison} LIVRAISON{stats.demandes_livraison>1?'S':''} À CONFIRMER</h5>
            <p className="mb-2 small">Un transporteur a signalé une livraison. Cliquez sur un colis pour confirmer (95% transporteur + payout auto Kkiapay, 5% admin).</p>
            {(stats.pending_livraisons||[]).length>0 && (
              <div className="list-group list-group-flush small">
                {stats.pending_livraisons!.slice(0,5).map(p=>(
                  <div key={p.suivi_id} className="list-group-item bg-transparent border-light px-0 py-1 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i className="fa-solid fa-box"></i> <strong>{p.nom_colis}</strong> <code className="small">{p.numero_suivi}</code> — {p.ville}, {p.pays}</span>
                    <span className="small text-light">Client: {p.client_prenom} {p.client_nom} | Transporteur: {p.transporteur_prenom} {p.transporteur_nom}</span>
                    <div className="d-flex gap-1">
                      <button className="btn btn-sm btn-success" onClick={()=>{ onLivraisonDecision(p.suivi_id,'confirmer'); }}>
                        <i className="fa-solid fa-check"></i> Confirmer
                      </button>
                      <button className="btn btn-sm btn-outline-light" onClick={()=>{ onLivraisonDecision(p.suivi_id,'refuser'); }}>
                        <i className="fa-solid fa-xmark"></i> Refuser
                      </button>
                    </div>
                  </div>
                ))}
                {stats.pending_livraisons!.length>5 && (
                  <button className="btn btn-sm btn-light mt-1" onClick={()=>setSection('colis')}>Voir {stats.pending_livraisons!.length-5} de plus →</button>
                )}
              </div>
            )}
          </div>
          <button type="button" className="btn-close btn-close-white" onClick={()=>setSection('colis')} aria-label="Voir colis"></button>
          <style>{`
            @keyframes adminBlink {
              0%, 100% { background-color: #dc3545; color: #fff; }
              50% { background-color: #b02a37; color: #fff; }
            }
          `}</style>
        </div>
      )}

      {section==='stats' && stats && (
        <>
          <div className="row g-3">
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_users}</div><div className="text-muted small">Utilisateurs totaux</div><div className="small mt-2"><RoleBadge role="client" /> {stats.nb_clients} | <RoleBadge role="transporteur" /> {stats.nb_transporteurs_users}<br/><RoleBadge role="admin" /> {stats.nb_admins} | <RoleBadge role="super_admin" /> {stats.nb_super_admins}</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_colis}</div><div className="text-muted small">Colis</div><div className="small text-warning">{stats.colis_en_attente} en attente</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_voyages}</div><div className="text-muted small">Voyages</div><div className="small text-warning">{stats.voyages_en_attente} en attente</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_transporteurs}</div><div className="text-muted small">Fiches transporteurs</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_paiements}</div><div className="text-muted small">Paiements</div><div className="small text-success">{money(stats.montant_total_paye)} payés</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.nb_avis}</div><div className="text-muted small">Avis</div><div className="small text-warning">{stats.avis_en_attente} en attente</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.demandes_livraison}</div><div className="text-muted small">Livraisons à confirmer</div></div></div>
            <div className="col-md-3"><div className="page-card text-center"><div className="fs-4 fw-bold">{stats.messages_contact_non_lus}</div><div className="text-muted small">Messages contact non lus</div></div></div>
          </div>
          <div className="row g-3 mt-2">
            <div className="col-md-3"><div className="card bg-success text-white"><div className="card-body text-center"><h4>{money(stats.admin_wallet_solde||0)}</h4><small>Wallet Admin (5%)</small></div></div></div>
            <div className="col-md-3"><div className="card bg-primary text-white"><div className="card-body text-center"><h4>{money(stats.admin_commission_total||0)}</h4><small>Commission totale 5%</small></div></div></div>
            <div className="col-md-3"><div className="card bg-info text-dark"><div className="card-body text-center"><h4>{money(stats.total_paye_transporteurs||0)}</h4><small>Payé transporteurs 95%</small></div></div></div>
            <div className="col-md-3"><div className="card bg-warning text-dark"><div className="card-body text-center"><h4>{stats.retraits_en_attente||0} / {stats.retraits_payes||0}</h4><small>Retraits en attente / payés</small></div></div></div>
          </div>
        </>
      )}

      {section==='users' && (
        <div className="page-card">
          <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 className="mb-0">Utilisateurs {isSuperAdmin ? '(tous rôles)' : '(clients & transporteurs)'}</h5>
            <button className="btn btn-primary btn-sm" onClick={()=>setUserModal(true)}><i className="fa-solid fa-user-plus"></i> Créer utilisateur</button>
          </div>
          <div className="row g-2 mb-3">
            <div className="col-md-4"><input className="form-control form-control-sm" placeholder="Recherche nom/email" value={uSearch} onChange={e=>setUSearch(e.target.value)} /></div>
            <div className="col-md-3">
              <select className="form-select form-select-sm" value={uRole} onChange={e=>setURole(e.target.value)}>
                <option value="">Tous rôles {isSuperAdmin ? '' : '(clients/transp.)'}</option>
                <option value="client">Client</option>
                <option value="transporteur">Transporteur</option>
                {isSuperAdmin && <><option value="admin">Admin</option><option value="super_admin">Super Admin</option></>}
              </select>
            </div>
            <div className="col-md-2"><button className="btn btn-outline-primary btn-sm w-100" onClick={loadUsers}>Filtrer</button></div>
          </div>
          {!users && <div className="text-muted">Chargement…</div>}
          {users && (
            <div className="table-responsive"><table className="table table-sm align-middle">
              <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscrit</th><th>Actions</th></tr></thead>
              <tbody>{users.map(u=>(
                <tr key={u.id}>
                  <td>{u.id}</td>
                  <td>{u.prenom} {u.nom}</td>
                  <td className="small">{u.email}</td>
                  <td><RoleBadge role={u.role} /></td>
                  <td className="small">{date(u.date_inscription)}</td>
                  <td>
                    <div className="d-flex gap-1 flex-wrap">
                      {isSuperAdmin ? (
                        <select className="form-select form-select-sm" style={{width:'auto'}} value={u.role} onChange={e=>onChangeRole(u.id, e.target.value)}>
                          <option value="client">Client</option>
                          <option value="transporteur">Transporteur</option>
                          <option value="admin">Admin</option>
                          <option value="super_admin">Super Admin</option>
                        </select>
                      ) : (
                        <select className="form-select form-select-sm" style={{width:'auto'}} value={u.role} onChange={e=>onChangeRole(u.id, e.target.value)}>
                          <option value="client">Client</option>
                          <option value="transporteur">Transporteur</option>
                        </select>
                      )}
                      <button className="btn btn-sm btn-outline-danger" onClick={()=>onDeleteUser(u.id)} disabled={u.id===me?.id}><i className="fa-solid fa-trash"></i></button>
                    </div>
                  </td>
                </tr>
              ))}</tbody>
            </table></div>
          )}
        </div>
      )}

      {section==='admins' && isSuperAdmin && (
        <div className="page-card">
          <h5 className="mb-3"><i className="fa-solid fa-crown text-warning"></i> Gestion des Administrateurs (Super Admin uniquement)</h5>
          {!admins && <div className="text-muted">Chargement…</div>}
          {admins && (
            <div className="table-responsive"><table className="table align-middle">
              <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscrit</th><th>Actions</th></tr></thead>
              <tbody>{admins.map(a=>(
                <tr key={a.id}>
                  <td>{a.id}</td>
                  <td>{a.prenom} {a.nom}</td>
                  <td>{a.email}</td>
                  <td><RoleBadge role={a.role} /></td>
                  <td className="small">{date(a.date_inscription)}</td>
                  <td>
                    <div className="d-flex gap-1">
                      {a.role==='admin' && <button className="btn btn-sm btn-warning" onClick={()=>onChangeRole(a.id,'super_admin')}><i className="fa-solid fa-crown"></i> Promouvoir Super</button>}
                      {a.role==='super_admin' && a.id!==me?.id && <button className="btn btn-sm btn-outline-info" onClick={()=>onChangeRole(a.id,'admin')}><i className="fa-solid fa-arrow-down"></i> Rétrograder Admin</button>}
                      <button className="btn btn-sm btn-outline-danger" onClick={()=>onDeleteUser(a.id)} disabled={a.id===me?.id}><i className="fa-solid fa-trash"></i></button>
                    </div>
                  </td>
                </tr>
              ))}</tbody>
            </table></div>
          )}
        </div>
      )}

      {section==='colis' && (
        <div className="page-card">
          <h5>Colis</h5>
          <div className="row g-2 mb-3">
            <div className="col-md-4"><input className="form-control form-control-sm" placeholder="Recherche" value={cSearch} onChange={e=>setCSearch(e.target.value)} /></div>
            <div className="col-md-3"><select className="form-select form-select-sm" value={cStatut} onChange={e=>setCStatut(e.target.value)}><option value="">Tous statuts</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select></div>
            <div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadColis}>Filtrer</button></div>
          </div>
          {!colisList && <div className="text-muted">Chargement…</div>}
          {colisList && (
            <div className="table-responsive"><table className="table table-sm align-middle">
              <thead><tr><th>ID</th><th>Colis</th><th>Propriétaire</th><th>Poids/Prix</th><th>Statut</th><th>Livraison</th><th>Actions</th></tr></thead>
              <tbody>{colisList.map(c=>(
                <tr key={c.id} className={c.demande_livraison_id ? 'table-warning' : ''} style={c.demande_livraison_id ? {boxShadow:'inset 3px 0 0 #dc3545'} : undefined}>
                  <td>{c.id}{c.demande_livraison_id && <span className="badge bg-danger ms-1" style={{animation:'pulse 1s infinite'}}>!</span>}</td>
                  <td><div className="d-flex gap-2 align-items-center">{c.image_url && <img src={c.image_url} alt="" className="img-colis" />}<div>{c.nom_colis}<div className="small text-muted"><code>{c.numero_suivi}</code></div>{c.demande_livraison_id && <span className="badge bg-danger mt-1"><i className="fa-solid fa-bell"></i> Livraison à confirmer</span>}</div></div></td>
                  <td className="small">{c.prenom} {c.nom}<br/>{c.email}</td>
                  <td className="small">{c.poids} kg<br/>{money(c.prix_estime)}</td>
                  <td><span className="badge bg-light text-dark">{c.statut}</span></td>
                  <td>{c.statut_livraison && <span className="badge bg-info text-dark">{c.statut_livraison}</span>}{c.demande_livraison_id && <div className="mt-1 d-flex gap-1"><button className="btn btn-sm btn-success" onClick={()=>onLivraisonDecision(c.demande_livraison_id!,'confirmer')}><i className="fa-solid fa-check"></i> Confirmer (95/5)</button><button className="btn btn-sm btn-danger" onClick={()=>onLivraisonDecision(c.demande_livraison_id!,'refuser')}><i className="fa-solid fa-xmark"></i> Refuser</button></div>}</td>
                  <td><div className="d-flex gap-1"><select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{ if(e.target.value) onColisStatut(c.id,e.target.value)}}><option value="">Changer…</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select><button className="btn btn-sm btn-outline-danger" onClick={()=>onColisDelete(c.id)}><i className="fa-solid fa-trash"></i></button></div></td>
                </tr>
              ))}</tbody>
            </table></div>
          )}
        </div>
      )}

      {section==='voyages' && (
        <div className="page-card">
          <h5>Voyages</h5>
          <div className="row g-2 mb-3">
            <div className="col-md-4"><input className="form-control form-control-sm" placeholder="Recherche" value={vSearch} onChange={e=>setVSearch(e.target.value)} /></div>
            <div className="col-md-3"><select className="form-select form-select-sm" value={vStatut} onChange={e=>setVStatut(e.target.value)}><option value="">Tous</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select></div>
            <div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadVoyages}>Filtrer</button></div>
          </div>
          {!voyages && <div className="text-muted">Chargement…</div>}
          {voyages && <div className="table-responsive"><table className="table table-sm"><thead><tr><th>ID</th><th>Transporteur</th><th>Trajet</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead><tbody>{voyages.map(v=><tr key={v.id}><td>{v.id}</td><td className="small">{v.prenom} {v.nom}<br/>{v.email}</td><td>{v.pays_depart} → {v.pays_destination}<br/><span className="small">{v.poids_max} kg, {v.nb_reservations} rés.</span></td><td className="small">{date(v.date_depart)} {(v.heure_depart||'').substring(0,5)}</td><td>{v.statut}</td><td><div className="d-flex gap-1"><select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{ if(e.target.value) onVoyageStatut(v.id,e.target.value)}}><option value="">Changer…</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select><button className="btn btn-sm btn-outline-danger" onClick={()=>onVoyageDelete(v.id)}><i className="fa-solid fa-trash"></i></button></div></td></tr>)}</tbody></table></div>}
        </div>
      )}

      {section==='transporteurs' && (
        <div className="page-card">
          <h5>Transporteurs</h5>
          <div className="row g-2 mb-3"><div className="col-md-4"><input className="form-control form-control-sm" placeholder="Recherche" value={tSearch} onChange={e=>setTSearch(e.target.value)} /></div><div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadTransporteurs}>Filtrer</button></div></div>
          {!transporteurs && <div className="text-muted">Chargement…</div>}
          {transporteurs && <div className="table-responsive"><table className="table table-sm"><thead><tr><th>ID</th><th>Transporteur</th><th>Véhicule</th><th>Solde</th><th>Actions</th></tr></thead><tbody>{transporteurs.map(t=><tr key={t.id}><td>{t.id}</td><td>{t.prenom} {t.nom}<br/><span className="small">{t.email} {t.telephone||''}</span></td><td className="small">{t.vehicule||'—'}<br/>{t.compagnie||''} {t.ville||''}</td><td>{money(t.solde)}</td><td><button className="btn btn-sm btn-outline-danger" onClick={()=>onTransporteurDelete(t.id)}><i className="fa-solid fa-trash"></i></button></td></tr>)}</tbody></table></div>}
        </div>
      )}

      {section==='paiements' && (
        <div className="page-card">
          <h5>Paiements</h5>
          <div className="row g-2 mb-3"><div className="col-md-3"><input className="form-control form-control-sm" placeholder="Recherche" value={pSearch} onChange={e=>setPSearch(e.target.value)} /></div><div className="col-md-3"><select className="form-select form-select-sm" value={pStatut} onChange={e=>setPStatut(e.target.value)}><option value="">Tous</option><option value="en_attente">En attente</option><option value="paye">Payé</option><option value="echec">Échec</option></select></div><div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadPaiements}>Filtrer</button></div></div>
          {!paiements && <div className="text-muted">Chargement…</div>}
          {paiements && <div className="table-responsive"><table className="table table-sm"><thead><tr><th>ID</th><th>Colis</th><th>User</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead><tbody>{paiements.map(p=><tr key={p.id}><td>{p.id}</td><td className="small">{p.nom_colis||'—'}<br/><code>{p.numero_suivi||''}</code></td><td className="small">{p.prenom} {p.nom}<br/>{p.email}</td><td>{money(p.montant)}<br/><span className="small">{p.methode_paiement||''}</span></td><td>{p.statut}</td><td><select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{ if(e.target.value) onPaiementStatut(p.id,e.target.value)}}><option value="">Changer…</option><option value="en_attente">En attente</option><option value="paye">Payé</option><option value="echec">Échec</option></select></td></tr>)}</tbody></table></div>}
        </div>
      )}

      {section==='avis' && (
        <div className="page-card">
          <h5>Avis</h5>
          <div className="row g-2 mb-3"><div className="col-md-3"><select className="form-select form-select-sm" value={aStatut} onChange={e=>setAStatut(e.target.value)}><option value="">Tous</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select></div><div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadAvis}>Filtrer</button></div></div>
          {!avis && <div className="text-muted">Chargement…</div>}
          {avis && <div className="table-responsive"><table className="table table-sm"><thead><tr><th>ID</th><th>Client</th><th>Transporteur</th><th>Note</th><th>Commentaire</th><th>Statut</th><th>Actions</th></tr></thead><tbody>{avis.map(a=><tr key={a.id}><td>{a.id}</td><td>{a.user_prenom} {a.user_nom}</td><td>{a.transporteur_prenom} {a.transporteur_nom} (#{a.transporteur_id})</td><td>{a.note}★</td><td className="small">{a.commentaire?.substring(0,80)}</td><td>{a.statut}</td><td><div className="d-flex gap-1"><select className="form-select form-select-sm" style={{width:'auto'}} defaultValue="" onChange={e=>{ if(e.target.value) onAvisStatut(a.id,e.target.value)}}><option value="">Changer…</option><option value="en_attente">En attente</option><option value="approuve">Approuvé</option><option value="refuse">Refusé</option></select><button className="btn btn-sm btn-outline-danger" onClick={()=>onAvisDelete(a.id)}><i className="fa-solid fa-trash"></i></button></div></td></tr>)}</tbody></table></div>}
        </div>
      )}

      {section==='contact' && (
        <div className="page-card">
          <h5>Messages Contact</h5>
          <div className="row g-2 mb-3"><div className="col-md-3"><select className="form-select form-select-sm" value={ctRepondu} onChange={e=>setCtRepondu(e.target.value)}><option value="">Tous</option><option value="non">Non répondus</option><option value="oui">Répondus</option></select></div><div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadContact}>Filtrer</button></div></div>
          {!contacts && <div className="text-muted">Chargement…</div>}
          {contacts && <div className="table-responsive"><table className="table table-sm"><thead><tr><th>ID</th><th>Nom</th><th>Message</th><th>Réponse</th><th>Date</th><th>Actions</th></tr></thead><tbody>{contacts.map(c=><tr key={c.id}><td>{c.id}</td><td>{c.nom}<br/><span className="small">{c.email}</span></td><td className="small">{c.message.substring(0,100)}</td><td className="small">{c.reponse?.substring(0,100)||'—'}</td><td className="small">{datetime(c.date_envoi)}</td><td><div className="d-flex gap-1"><button className="btn btn-sm btn-outline-primary" onClick={()=>{ setReplyModal({id:c.id, nom:c.nom, message:c.message}); setReplyText(c.reponse||'')}}>Répondre</button><button className="btn btn-sm btn-outline-danger" onClick={()=>onContactDelete(c.id)}><i className="fa-solid fa-trash"></i></button></div></td></tr>)}</tbody></table></div>}
        </div>
      )}

      {section==='wallet' && (
        <div className="page-card">
          <h5><i className="fa-solid fa-wallet"></i> Portefeuille Admin (5% plateforme)</h5>
          {!wallet && <div className="text-muted">Chargement…</div>}
          {wallet && (
            <>
              <div className="row g-3 mb-4">
                <div className="col-md-3"><div className="card bg-success text-white"><div className="card-body text-center"><h3>{money(wallet.solde)}</h3><p className="mb-0">Solde disponible</p></div></div></div>
                <div className="col-md-3"><div className="card bg-primary text-white"><div className="card-body text-center"><h3>{money(wallet.total_commission_generee)}</h3><p className="mb-0">Commission totale générée (5%)</p></div></div></div>
                <div className="col-md-3"><div className="card bg-info text-dark"><div className="card-body text-center"><h3>{money(wallet.total_paye_transporteurs)}</h3><p className="mb-0">Total payé transporteurs (95%)</p></div></div></div>
                <div className="col-md-3"><div className="card bg-warning text-dark"><div className="card-body text-center"><h3>{money(wallet.total_retraits_admin)}</h3><p className="mb-0">Retraits admin déjà effectués</p></div></div></div>
              </div>
              <div className="alert alert-info small">
                <i className="fa-solid fa-circle-info"></i> Répartition : Transporteur <strong>95%</strong> du prix estimé, Admin <strong>5%</strong>. Après confirmation livraison (vérification client), le système tente un payout automatique Kkiapay Mobile Money sur le numéro du transporteur (colonne téléphone). Si échec (numéro manquant ou API), le retrait reste en attente pour validation manuelle dans l'onglet Retraits.
              </div>
              <h6 className="mt-4"><i className="fa-solid fa-hand-holding-dollar"></i> Retirer mon solde admin</h6>
              <form onSubmit={onAdminRetrait} className="row g-2 align-items-end">
                <div className="col-md-3"><label className="form-label">Montant XOF</label><input type="number" className="form-control" min={1000} value={adminRetraitForm.montant} onChange={e=>setAdminRetraitForm({...adminRetraitForm, montant:e.target.value})} required /></div>
                <div className="col-md-4"><label className="form-label">Numéro / Compte</label><input type="text" className="form-control" placeholder="Mobile Money ou IBAN" value={adminRetraitForm.numero} onChange={e=>setAdminRetraitForm({...adminRetraitForm, numero:e.target.value})} /></div>
                <div className="col-md-2"><button className="btn btn-primary w-100" type="submit">Retirer</button></div>
              </form>
            </>
          )}
        </div>
      )}

      {section==='retraits' && (
        <div className="page-card">
          <h5><i className="fa-solid fa-money-bill-transfer"></i> Retraits & Paiements automatiques</h5>
          <div className="row g-2 mb-3">
            <div className="col-md-3"><input className="form-control form-control-sm" placeholder="Recherche ref/num/nom" value={rSearch} onChange={e=>setRSearch(e.target.value)} /></div>
            <div className="col-md-2"><select className="form-select form-select-sm" value={rType} onChange={e=>setRType(e.target.value)}><option value="">Tous types</option><option value="transporteur">Transporteur 95%</option><option value="admin">Admin 5%</option></select></div>
            <div className="col-md-2"><select className="form-select form-select-sm" value={rStatut} onChange={e=>setRStatut(e.target.value)}><option value="">Tous statuts</option><option value="en_attente">En attente</option><option value="paye">Payé</option><option value="echec">Échec</option><option value="refuse">Refusé</option></select></div>
            <div className="col-md-2"><button className="btn btn-sm btn-outline-primary w-100" onClick={loadRetraits}>Filtrer</button></div>
            <div className="col-md-3 text-end"><span className="badge bg-warning text-dark">{retraits?.filter(r=>r.statut==='en_attente').length||0} en attente</span> <span className="badge bg-success ms-1">{retraits?.filter(r=>r.statut==='paye').length||0} payés</span></div>
          </div>
          {!retraits && <div className="text-muted">Chargement…</div>}
          {retraits && (
            <div className="table-responsive">
              <table className="table table-sm align-middle">
                <thead><tr><th>ID</th><th>Type</th><th>Transporteur</th><th>Montant</th><th>Statut</th><th>Numéro</th><th>Réf</th><th>Colis</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>{retraits.map(r=>(
                  <tr key={r.id}>
                    <td>{r.id}</td>
                    <td><span className={`badge ${r.type==='transporteur'?'bg-success':'bg-primary'}`}>{r.type}</span></td>
                    <td className="small">{r.prenom} {r.nom}<br/>{r.email}</td>
                    <td className="fw-bold">{money(r.montant)}</td>
                    <td><span className={`badge ${r.statut==='paye'?'bg-success':r.statut==='en_attente'?'bg-warning text-dark':r.statut==='echec'?'bg-danger':'bg-secondary'}`}>{r.statut}</span></td>
                    <td className="small">{r.numero||'—'}</td>
                    <td className="small"><code>{r.reference}</code></td>
                    <td className="small">{r.colis_id ? `#${r.colis_id} ${r.nom_colis||''}` : '—'}</td>
                    <td className="small">{datetime(r.date_demande)}</td>
                    <td>
                      <div className="d-flex gap-1 flex-wrap">
                        {r.statut==='en_attente' && <><button className="btn btn-sm btn-success" onClick={()=>onRetraitDecision(r.id,'paye')}>Payer</button><button className="btn btn-sm btn-outline-success" onClick={()=>onRetraitDecision(r.id,'approuve')}>Approuver</button><button className="btn btn-sm btn-outline-danger" onClick={()=>onRetraitDecision(r.id,'refuse')}>Refuser</button></>}
                        {r.statut==='echec' && <button className="btn btn-sm btn-warning" onClick={()=>onRetraitRetry(r.id)}><i className="fa-solid fa-rotate"></i> Retry payout</button>}
                        {r.statut==='paye' && <span className="small text-success"><i className="fa-solid fa-check"></i> OK</span>}
                      </div>
                    </td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {userModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{backgroundColor:'rgba(0,0,0,.5)'}}>
          <div className="modal-dialog">
            <div className="modal-content">
              <form onSubmit={onCreateUser}>
                <div className="modal-header"><h5 className="modal-title">Créer utilisateur</h5><button type="button" className="btn-close" onClick={()=>setUserModal(false)}></button></div>
                <div className="modal-body">
                  {userModalError && <div className="alert alert-danger">{userModalError}</div>}
                  <div className="mb-2"><label className="form-label">Nom</label><input className="form-control" value={userForm.nom} onChange={e=>setUserForm({...userForm, nom:e.target.value})} required /></div>
                  <div className="mb-2"><label className="form-label">Prénom</label><input className="form-control" value={userForm.prenom} onChange={e=>setUserForm({...userForm, prenom:e.target.value})} required /></div>
                  <div className="mb-2"><label className="form-label">Email</label><input type="email" className="form-control" value={userForm.email} onChange={e=>setUserForm({...userForm, email:e.target.value})} required /></div>
                  <div className="mb-2"><label className="form-label">Mot de passe</label><input type="password" className="form-control" value={userForm.password} onChange={e=>setUserForm({...userForm, password:e.target.value})} required /></div>
                  <div className="mb-2"><label className="form-label">Rôle</label>
                    <select className="form-select" value={userForm.role} onChange={e=>setUserForm({...userForm, role:e.target.value})}>
                      <option value="client">Client</option>
                      <option value="transporteur">Transporteur</option>
                      {isSuperAdmin && <><option value="admin">Admin simple</option><option value="super_admin">Super Admin</option></>}
                    </select>
                    {!isSuperAdmin && <div className="form-text">Admin simple ne peut créer que Client/Transporteur</div>}
                  </div>
                </div>
                <div className="modal-footer"><button type="button" className="btn btn-secondary" onClick={()=>setUserModal(false)}>Annuler</button><button type="submit" className="btn btn-primary">Créer</button></div>
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
                  <div className="alert alert-light small">{replyModal.message}</div>
                  <div className="mb-2"><label className="form-label">Réponse</label><textarea className="form-control" rows={4} value={replyText} onChange={e=>setReplyText(e.target.value)} required></textarea></div>
                </div>
                <div className="modal-footer"><button type="button" className="btn btn-secondary" onClick={()=>setReplyModal(null)}>Annuler</button><button type="submit" className="btn btn-primary">Envoyer</button></div>
              </form>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
