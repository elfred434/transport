/**
 * Test d'intégration frontend↔backend.
 *
 * Charge le VRAI client API du SPA React (frontend/src/lib/api.ts, bundlé à la
 * volée avec rolldown — fourni par Vite) dans un environnement navigateur
 * simulé, puis rejoue le parcours complet et vérifie les noms de champs que
 * les pages React lisent.
 *
 * Le client émet des URLs relatives ('/api/...') — comme dans le navigateur,
 * où le proxy Vite (dev) ou le domaine unique (prod) les rebase vers l'API.
 * Ici, le fetch simulé les rebase vers API_BASE.
 *
 * Usage :
 *   API_BASE=http://localhost:8002 node tests/test_frontend_integration.js
 * Prérequis : backend démarré, `cd frontend && npm install` (fournit rolldown).
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const API_BASE = process.env.API_BASE || 'http://localhost:8002';
const ADMIN_EMAIL = 'admin@transport.bj';
const ADMIN_PASS = 'Admin@12345';
const stamp = Date.now();

let pass = 0, fail = 0;
function check(label, cond, extra = '') {
    if (cond) { pass++; console.log('  OK  ' + label); }
    else { fail++; console.log('  FAIL ' + label + (extra ? ' — ' + extra : '')); }
}

// --- Bundle du vrai client React (frontend/src/lib/api.ts) ---
const ROOT = path.join(__dirname, '..');
const ROLLDOWN = path.join(ROOT, 'frontend', 'node_modules', '.bin', 'rolldown');
const ENTRY = path.join(ROOT, 'frontend', 'src', 'lib', 'api.ts');
if (!fs.existsSync(ROLLDOWN)) {
    console.error('rolldown introuvable : exécutez `cd frontend && npm install` puis relancez.');
    process.exit(2);
}
const outfile = path.join(os.tmpdir(), `transport-api-client-${process.pid}.cjs`);
execFileSync(ROLLDOWN, [ENTRY, '--format', 'cjs', '--platform', 'node',
    '--file', outfile], { stdio: ['ignore', 'ignore', 'inherit'] });

// --- Environnement navigateur minimal ---
const store = {};
let lastRequestedPath = null;
const sandbox = {
    console,
    // Relie les URLs relatives du client vers API_BASE (rôle du proxy Vite/Caddy).
    fetch: (url, opts) => {
        lastRequestedPath = String(url);
        return fetch(String(url).startsWith('/') ? API_BASE + url : url, opts);
    },
    FormData,
    File,
    Blob,
    URLSearchParams,
    TextEncoder,
    TextDecoder,
    localStorage: {
        getItem: k => (k in store ? store[k] : null),
        setItem: (k, v) => { store[k] = String(v); },
        removeItem: k => { delete store[k]; },
    },
    window: { location: { href: '' } },
    module: { exports: {} },
    process: { env: {} },
};
sandbox.exports = sandbox.module.exports;
sandbox.globalThis = sandbox;
sandbox.window.localStorage = sandbox.localStorage;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(outfile, 'utf8'), sandbox, { filename: 'api.bundle.cjs' });
fs.rmSync(outfile, { force: true });

const { api, Auth } = sandbox.module.exports;

const adminFetch = (method, p, body, token) => fetch(API_BASE + p, {
    method,
    headers: {
        'Content-Type': 'application/json',
        ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
}).then(r => r.json());

(async () => {
    // ---- 0. Le client émet des URLs relatives et désenveloppe {success,data} ----
    const root = await api.get('/api');
    check('client React : URLs relatives + enveloppe désenveloppée (GET /api)',
        lastRequestedPath === '/api' && typeof root.endpoints === 'number' && root.endpoints >= 55,
        `path=${lastRequestedPath} endpoints=${root && root.endpoints}`);

    // ---- 1. Inscription utilisateur A (propriétaire colis) via api.upload ----
    const emailA = `own${stamp}@test.bj`;
    const regA = await api.upload('/api/auth/register', {
        nom: 'Proprio', prenom: 'Colis', email: emailA, password: 'Test@12345', tel: '97000001',
    }, {});
    check('api.upload register renvoie un token', !!regA.token);
    Auth.save(regA.token);
    check('Auth.save/isLoggedIn', Auth.isLoggedIn());
    const idA = regA.user.id;

    // ---- 2. Création d'un colis ----
    const colis = await api.upload('/api/colis', {
        nom_colis: 'Carton test', type_produit: 'documents', nombre_produits: 2, poids: 3,
        dimensions: '30x20x10', pays: 'France', ville: 'Paris',
        date_limite: new Date(Date.now() + 86400000 * 5).toISOString().split('T')[0],
        adresse_depart: 'Rue 1 Cotonou', adresse_destination: 'Rue 2 Paris',
    }, {});
    check('colis créé (colis_id + numero_suivi + paiement)', colis.colis_id && colis.numero_suivi && colis.paiement, JSON.stringify(colis));

    // ---- 3. Paiement du colis ----
    const pay = await api.post(`/api/paiements/${colis.paiement.id}/payer`, {
        methode_paiement: 'carte_credit', numero_carte: '4111111111111111', expiration: '12/28', cvv: '123',
    });
    check('paiement effectué', pay.numero_transaction, JSON.stringify(pay));

    // ---- 4. Admin : approuver le colis ----
    Auth.save(regA.token);
    const adminLogin = await adminFetch('POST', '/api/auth/login', { email: ADMIN_EMAIL, password: ADMIN_PASS });
    const adminToken = adminLogin.data.token;
    const adminApi = (method, p, body) => adminFetch(method, p, body, adminToken);

    const appr = await adminApi('POST', `/api/admin/colis/${colis.colis_id}/statut`, { statut: 'approuve' });
    check('admin approuve le colis', appr.success, JSON.stringify(appr));

    // ---- 5. Utilisateur B devient transporteur (POST /api/voyages) ----
    const emailB = `tra${stamp}@test.bj`;
    const regB = await api.upload('/api/auth/register', {
        nom: 'Transpo', prenom: 'Routeur', email: emailB, password: 'Test@12345', tel: '97000002',
    }, {});
    const tokenB = regB.token;
    const idB = regB.user.id;
    Auth.save(tokenB);
    const voy = await api.upload('/api/voyages', {
        numero_permis: 'P123', vehicule: 'Toyota HiAce', compagnie: 'FastCo', adresse: 'Rue 3',
        ville: 'Cotonou', pays: 'Bénin', pays_depart: 'Bénin', pays_destination: 'France',
        date_depart: new Date(Date.now() + 86400000 * 3).toISOString().split('T')[0],
        heure_depart: '08:00', poids_max: 50, email: emailB, telephone: '97000002',
    }, {});
    check('voyage proposé (voyage_id)', voy.voyage_id, JSON.stringify(voy));

    // Admin approuve le voyage
    const apprV = await adminApi('POST', `/api/admin/voyages/${voy.voyage_id}/statut`, { statut: 'approuve' });
    check('admin approuve le voyage', apprV.success, JSON.stringify(apprV));

    // ---- 6. B réserve le colis de A ----
    const res = await api.post('/api/reservations', { colis_id: colis.colis_id, voyage_id: voy.voyage_id });
    check('réservation créée', res.message !== undefined, JSON.stringify(res));

    // ---- 7. A accepte la réservation ----
    Auth.save(regA.token);
    const resList = await api.get(`/api/colis/${colis.colis_id}/reservations`);
    const resId = resList[0].id;
    const accept = await api.post(`/api/reservations/${resId}/action`, { action: 'accepte' });
    check('propriétaire accepte la réservation', accept.statut === 'accepte', JSON.stringify(accept));

    // ---- 8. B lit /api/reservations/recues : vérifie les champs lus par Dashboard.tsx ----
    Auth.save(tokenB);
    const recues = await api.get('/api/reservations/recues');
    const r0 = recues.find(r => r.colis_id === colis.colis_id) || recues[0];
    const champsRecues = ['nom_colis', 'numero_suivi', 'image_url', 'client_id', 'client_prenom', 'client_nom',
        'client_tel', 'pays_depart', 'pays_destination', 'date_depart', 'prix_estime', 'statut', 'statut_suivi'];
    const manquants = champsRecues.filter(c => !(c in r0));
    check('reservations/recues expose tous les champs attendus', manquants.length === 0, 'manquants: ' + manquants.join(','));
    check('client_prenom = "Colis" (propriétaire A)', r0.client_prenom === 'Colis', r0.client_prenom);
    check('pays_depart = "Bénin"', r0.pays_depart === 'Bénin', r0.pays_depart);

    // ---- 9. A lit /api/colis/mine : vérifie les champs + reservations embarquées ----
    Auth.save(regA.token);
    const mine = await api.get('/api/colis/mine');
    const c0 = mine.find(c => c.id === colis.colis_id);
    const champsMine = ['nom_colis', 'image_url', 'poids', 'ville', 'pays', 'prix_estime', 'statut',
        'numero_suivi', 'type_produit', 'statut_livraison', 'reservations'];
    const manqMine = champsMine.filter(c => !(c in c0));
    check('colis/mine expose tous les champs attendus', manqMine.length === 0, 'manquants: ' + manqMine.join(','));
    check('colis/mine reservations[0] a transporteur_id + note_moyenne', c0.reservations[0] && ('transporteur_id' in c0.reservations[0]) && ('note_moyenne' in c0.reservations[0]), JSON.stringify(c0.reservations[0]));

    // ---- 10. /api/paiements/mine : structure {paiements, stats} ----
    const paiements = await api.get('/api/paiements/mine');
    check('paiements/mine renvoie {paiements, stats}', Array.isArray(paiements.paiements) && paiements.stats, JSON.stringify(Object.keys(paiements)));
    const p0 = paiements.paiements.find(p => p.colis_id === colis.colis_id);
    const champsPay = ['nom_colis', 'montant', 'methode_paiement', 'reference', 'statut', 'date_creation', 'details_paiement'];
    const manqPay = champsPay.filter(c => !(c in p0));
    check('paiement expose les champs attendus', manqPay.length === 0, 'manquants: ' + manqPay.join(','));
    check('details_paiement.numero_masque présent (carte)', p0.details_paiement && p0.details_paiement.numero_masque, JSON.stringify(p0.details_paiement));

    // ---- 11. /api/auth/me renvoie is_transporteur + solde (B est transporteur) ----
    Auth.save(tokenB);
    const meB = await api.get('/api/auth/me');
    check('auth/me: is_transporteur=true pour B', meB.is_transporteur === true);
    check('auth/me: solde présent', 'solde' in meB);

    // ---- 12. Livraison + commission 5% (B marque Livré, admin confirme) ----
    const suivi = await api.post('/api/suivi', { colis_id: colis.colis_id, statut: 'Livré' });
    check('transporteur marque Livré (demande admin)', suivi.message && suivi.message.includes('confirmation'), JSON.stringify(suivi));
    // admin récupère la demande_livraison_id via /api/admin/colis
    const adminColis = await adminApi('GET', '/api/admin/colis?search=' + encodeURIComponent(colis.numero_suivi));
    const ac = adminColis.data.find(c => c.id === colis.colis_id);
    check('admin/colis expose demande_livraison_id', ac && ac.demande_livraison_id, JSON.stringify(ac && { d: ac.demande_livraison_id }));
    const dec = await adminApi('POST', `/api/admin/suivi/${ac.demande_livraison_id}/livraison`, { decision: 'confirmer' });
    check('admin confirme livraison + commission 5%', dec.success && Math.abs(dec.data.commission_transporteur - Math.round(c0.prix_estime * 0.05 * 100) / 100) < 0.01, JSON.stringify(dec.data));

    // ---- 13. Messagerie A↔B ----
    Auth.save(regA.token);
    await api.upload('/api/messages', { destinataire_id: idB, contenu: 'Bonjour, mon colis est prêt.' }, {});
    Auth.save(tokenB);
    const msgs = await api.get('/api/messages?destinataire_id=' + idA);
    check('messagerie A→B reçue', msgs.some(m => m.contenu.includes('colis est prêt')), JSON.stringify(msgs.length));
    const champsMsg = ['contenu', 'date_envoi', 'expediteur_id', 'fichier_url'];
    const manqMsg = champsMsg.filter(c => !(c in msgs[0]));
    check('message expose les champs attendus', manqMsg.length === 0, 'manquants: ' + manqMsg.join(','));

    console.log(`\n${pass}/${pass + fail} vérifications frontend OK` + (fail ? ` — ${fail} ÉCHECS` : ' ✅'));
    process.exit(fail ? 1 : 0);
})().catch(e => {
    console.error('\nERREUR FATALE:', e && e.message ? e.message : e);
    if (e && e.stack) console.error(e.stack);
    process.exit(2);
});
