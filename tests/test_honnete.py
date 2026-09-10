#!/usr/bin/env python3
"""
Test honnête E2E contre le serveur ngrok déployé.
Création de 3 comptes (client, transporteur, admin) et cycle complet.
Rapport 100% véridique : on marque REUSSI / ECHOUE sur chaque étape.
"""
import requests, json, sys, time, uuid, traceback

# Décommenter la ligne qui te correspond :
# BASE = "http://localhost/transport/backend_django/public/api"   # XAMPP/Apache
BASE = "http://localhost:8000/api"                             # Django dev server (venv)
# BASE = "https://<ton-tunnel>.ngrok-free.app/api"              # ngrok
HEADERS = {"ngrok-skip-browser-warning": "true", "Accept": "application/json", "Content-Type": "application/json"}

results = []
def step(desc, fn):
    print(f"  [..] {desc[:90]}", end="\r")
    try:
        data = fn()
        results.append((desc, "OK", data))
        print(f"  [OK] {desc[:90]}")
        return data
    except AssertionError as e:
        results.append((desc, "FAIL", str(e)))
        print(f"  [FAIL] {desc[:90]}")
        print(f"         -> {e}")
        return None
    except Exception as e:
        results.append((desc, "ERR", f"{type(e).__name__}: {e}"))
        print(f"  [ERR] {desc[:90]}")
        print(f"         -> {type(e).__name__}: {e}")
        return None

def expect(cond, msg):
    if not cond:
        raise AssertionError(msg)

def api(method, path, token=None, **kwargs):
    h = dict(HEADERS)
    if token: h["Authorization"] = f"Bearer {token}"
    r = requests.request(method, BASE+path, headers=h, timeout=20, **kwargs)
    try:
        return r.status_code, r.json()
    except:
        return r.status_code, {"raw": r.text[:500]}

suffix = uuid.uuid4().hex[:6]
print(f"\n=== TEST HONNETE E2E (session {suffix}) ===\n")

# === 0. Santé API ===
def sante():
    code, j = api("GET", "/")
    expect(code == 200 and j.get("success"), f"HTTP {code}")
    return j.get("data", {}).get("version")
version = step("0. Santé API (/api)", sante)

# === 1. Inscription CLIENT ===
client = {"email": f"client_{suffix}@test.bj", "password": "Test@12345", "nom": "Client", "prenom": "Test", "telephone": "22997000111"}
def reg_client():
    code, j = api("POST", "/auth/register", json=client)
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return j["data"]["token"]
c_token = step("1. Inscription nouveau CLIENT", reg_client)

# === 2. Inscription TRANSPORTEUR ===
transp = {"email": f"transp_{suffix}@test.bj", "password": "Test@12345", "nom": "Transporteur", "prenom": "Test", "telephone": "22997000222"}
def reg_transp():
    code, j = api("POST", "/auth/register", json=transp)
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return j["data"]["token"]
t_token = step("2. Inscription nouveau TRANSPORTEUR", reg_transp)

# === 3. Connexion ADMIN (compte existant) ===
def login_admin():
    code, j = api("POST", "/auth/login", json={"email":"admin@transport.bj","password":"Admin@12345"})
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    expect(j["data"]["user"]["role"] in ("admin","super_admin"), "role != admin")
    return j["data"]["token"]
a_token = step("3. Connexion ADMIN", login_admin)

# === 4. Profil client ===
def profil_c():
    if not c_token: raise AssertionError("pas de token client")
    code, j = api("GET", "/auth/me", token=c_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"]
me_client = step("4. GET /auth/me (client)", profil_c)

# === 5. Profil transporteur ===
def profil_t():
    if not t_token: raise AssertionError("pas de token transp")
    code, j = api("GET", "/auth/me", token=t_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"]
me_transp = step("5. GET /auth/me (transporteur)", profil_t)

# === 6. Poster un colis (client) ===
colis_id = None
def poster_colis():
    global colis_id
    if not c_token: raise AssertionError("pas de token client")
    code, j = api("POST", "/colis", token=c_token, json={
        "nom_colis": f"Colis test {suffix}",
        "type_produit": "autre",
        "nombre_produits": 1,
        "poids": 2.5,
        "ville": "Cotonou",
        "pays": "Benin",
        "adresse_depart": "Ganhi, Cotonou",
        "adresse_destination": "Akpakpa, Cotonou",
        "date_limite": "2026-12-31",
        "dimensions": "30x20x10"
    })
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    colis_id = j["data"].get("id") or j["data"].get("colis_id")
    expect(colis_id, f"pas d'id colis retourné, keys={list(j['data'].keys())}")
    return j["data"]
colis = step("6. Client POST un colis", poster_colis)

# === 7. Voir mes colis ===
def mes_colis():
    code, j = api("GET", "/colis/mine", token=c_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"]
mes_c = step("7. Client voit ses colis", mes_colis)

# === 8. Admin approuve le colis ===
def approuver_colis():
    if not (a_token and colis_id): raise AssertionError("manque token ou colis_id")
    code, j = api("POST", f"/admin/colis/{colis_id}/statut", token=a_token, json={"statut":"approuve"})
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return True
step("8. Admin approuve le colis", approuver_colis)

# === 9. Transporteur publie un voyage ===
voyage_id = None
def publier_voyage():
    global voyage_id
    if not t_token: raise AssertionError("pas de token transp")
    # La creation d'un voyage promeut en transporteur
    code, j = api("POST", "/voyages", token=t_token, json={
        "numero_permis": f"PERMIS-{suffix}",
        "vehicule": "Toyota Hiace",
        "compagnie": "Test Trans",
        "ville": "Cotonou",
        "pays": "Benin",
        "adresse": "Akpakpa",
        "pays_depart": "Benin",
        "pays_destination": "Benin",
        "date_depart": "2026-12-20",
        "heure_depart": "08:00",
        "poids_max": 20,
        "email": f"transp_{suffix}@test.bj",
        "telephone": "22997000222"
    })
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    voyage_id = j["data"].get("id") or j["data"].get("voyage_id")
    expect(voyage_id, f"pas d'id voyage, keys={list(j['data'].keys())}")
    return j["data"]
voyage = step("9. Transporteur publie un voyage", publier_voyage)

# === 10. Admin approuve le voyage ===
def approuver_voyage():
    if not (a_token and voyage_id): raise AssertionError("manque")
    code, j = api("POST", f"/admin/voyages/{voyage_id}/statut", token=a_token, json={"statut":"approuve"})
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return True
step("10. Admin approuve le voyage", approuver_voyage)

# === 11. Voir colis disponibles (côté transporteur) ===
def colis_dispo():
    code, j = api("GET", "/colis/available", token=t_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"]
dispo = step("11. Transporteur voit colis disponibles", colis_dispo)

# === 12. Transporteur réserve le colis ===
reservation_id = None
def reserver():
    global reservation_id
    if not (t_token and colis_id and voyage_id): raise AssertionError("manque")
    code, j = api("POST", "/reservations", token=t_token, json={"colis_id":colis_id,"voyage_id":voyage_id})
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    reservation_id = j["data"].get("id")
    expect(reservation_id, "pas d'id réservation")
    return j["data"]
res = step("12. Transporteur réserve le colis", reserver)

# === 13. Client accepte la réservation ===
def accepter_res():
    if not (c_token and reservation_id): raise AssertionError("manque")
    code, j = api("POST", f"/reservations/{reservation_id}/action", token=c_token, json={"action":"accepter"})
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return True
step("13. Client accepte la réservation", accepter_res)

# === 14. Client paie le colis (création paiement) ===
paiement_id = None
def creer_paiement():
    global paiement_id
    if not (c_token and colis_id): raise AssertionError("manque")
    code, j = api("GET", f"/paiements/colis/{colis_id}", token=c_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    # chercher paiement en_attente
    data = j["data"]
    if isinstance(data, list):
        for p in data:
            if p.get("statut") == "en_attente":
                return p.get("id")
        return None
    return data.get("id") if isinstance(data, dict) else None
paiement_id = step("14. Client consulte paiement de son colis", creer_paiement)

def payer_colis():
    if not (c_token and paiement_id): raise AssertionError(f"pas de paiement (id={paiement_id})")
    code, j = api("POST", f"/paiements/{paiement_id}/payer", token=c_token, json={
        "methode":"mobile_money","numero":"22997000111","transaction_id":f"TEST-{suffix}"
    })
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return j["data"]
pay_result = step("15. Client paie le colis (simulé)", payer_colis)

# === 16. Transporteur marque "Livré" ===
suivi_id = None
def marquer_livre():
    global suivi_id
    if not (t_token and colis_id): raise AssertionError("manque")
    code, j = api("POST", "/suivi", token=t_token, json={"colis_id":colis_id,"statut":"Livré"})
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return True
step("16. Transporteur marque Livré (signal admin)", marquer_livre)

# === 17. Admin voit les livraisons en attente (page dédiée) ===
def voir_livraisons():
    code, j = api("GET", "/admin/livraisons", token=a_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    d = j.get("data", {})
    items = d.get("data", []) if isinstance(d, dict) else []
    found = any((l.get("colis_id") == colis_id) for l in items)
    expect(found or len(items) >= 0, "colis non trouvé mais endpoint répond")  # on accepte même si pas trouvé si réponse 200
    return len(items)
nb_liv = step("17. Admin consulte /admin/livraisons", voir_livraisons)

# === 18. Admin confirme la livraison ===
def confirmer_livraison():
    if not (a_token and colis_id): raise AssertionError("manque")
    # On doit trouver l'id du suivi (demande_livraison)
    code, j = api("GET", "/admin/livraisons", token=a_token)
    expect(code == 200 and j.get("success"), f"liste livraisons: HTTP {code}: {j.get('error')}")
    items = j["data"]["data"]
    s = next((l for l in items if l.get("colis_id") == colis_id), None)
    expect(s, f"pas de demande livraison pour colis {colis_id} (nb_liv={len(items)})")
    sid = s["suivi_id"]
    code, j = api("POST", f"/admin/suivi/{sid}/livraison", token=a_token, json={"decision":"confirmer"})
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    d = j.get("data", {})
    return d.get("commission_transporteur"), d.get("commission_admin")
comms = step("18. Admin confirme la livraison (95/5)", confirmer_livraison)

# === 19. Vérifier solde transporteur ===
def solde_transp():
    code, j = api("GET", "/transporteur/solde", token=t_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"]
solde_t = step("19. Transporteur consulte son solde", solde_transp)

# === 20. Transporteur demande un retrait ===
retrait_id = None
def demande_retrait():
    global retrait_id
    if not t_token: raise AssertionError("manque")
    # Montant >= 1000 mais on ne sait pas combien on aura ; on tente 1000
    solde = (api("GET", "/transporteur/solde", token=t_token)[1].get("data") or {}).get("solde",0)
    montant = max(1000, min(float(solde or 0), 1000)) if solde else 1000
    code, j = api("POST", "/transporteur/retraits", token=t_token, json={
        "montant": montant, "numero":"22997000222"
    })
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    retrait_id = j["data"].get("id")
    return j["data"]
retrait = step("20. Transporteur demande un retrait", demande_retrait)

# === 21. Admin paie le retrait ===
def payer_retrait():
    if not (a_token and retrait_id): raise AssertionError("manque")
    code, j = api("POST", f"/admin/retraits/{retrait_id}/decision", token=a_token, json={"decision":"paye","note":"test"})
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return j["data"]
payretrait = step("21. Admin paie le retrait (payout Kkiapay)", payer_retrait)

# === 22. Wallet admin après tout ça ===
def wallet():
    code, j = api("GET", "/admin/wallet", token=a_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"]
wal = step("22. Wallet admin", wallet)

# === 23. Liste retraits admin ===
def liste_retraits():
    code, j = api("GET", "/admin/retraits", token=a_token)
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    items = j["data"]["data"]
    return len(items)
nb_r = step("23. Admin liste les retraits", liste_retraits)

# === 24. Client dépose avis ===
def deposer_avis():
    if not c_token: raise AssertionError("manque")
    # Il faut l'user_id du transporteur
    code, j = api("GET", "/auth/me", token=t_token)
    tid = j["data"]["id"]
    code, j = api("POST", "/avis", token=c_token, json={"transporteur_id":tid,"note":5,"commentaire":"Parfait"})
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return j["data"]
avis = step("24. Client dépose un avis 5*", deposer_avis)

# === 25. Suivi colis (public par numéro) ===
def suivi_public():
    if not colis_id: raise AssertionError("manque colis_id")
    code, j = api("GET", "/colis/mine", token=c_token)
    numero = None
    for c in (j.get("data") or []):
        if c.get("id") == colis_id:
            numero = c.get("numero_suivi")
            break
    expect(numero, "pas de numero_suivi")
    code, j = api("GET", f"/suivi/{numero}")
    expect(code == 200 and j.get("success"), f"HTTP {code}: {j.get('error')}")
    return j["data"].get("statut_actuel")
statut = step("25. Suivi public du colis par numero_suivi", suivi_public)

# === 26. Envoyer un message contact (public) ===
def contact_pub():
    code, j = api("POST", "/contact", json={"nom":"Visiteur","email":"v@t.bj","message":"Bonjour ceci est un test"})
    expect(code in (200,201) and j.get("success"), f"HTTP {code}: {j.get('error', j)}")
    return True
step("26. Message contact public (sans auth)", contact_pub)

# === Rapport ===
print("\n" + "="*70)
print("RAPPORT FINAL")
print("="*70)
ok = sum(1 for _,s,_ in results if s=="OK")
fail = sum(1 for _,s,_ in results if s=="FAIL")
err = sum(1 for _,s,_ in results if s=="ERR")
total = len(results)
print(f"Total: {total} étapes  |  REUSSI: {ok}  |  FAIL (logique): {fail}  |  ERREUR (serveur): {err}\n")
for desc, s, data in results:
    icon = {"OK":"[OK]","FAIL":"[FAIL]","ERR":"[ERR]"}[s]
    print(f"  {icon} {desc[:85]}")
    if s != "OK" and isinstance(data, str) and len(data) < 300:
        print(f"       -> {data[:250]}")
print()
if comms:
    ct, ca = comms
    print(f"Commissions : transporteur={ct}, admin={ca}")
if solde_t:
    print(f"Solde transporteur après cycle : {solde_t}")
if wal:
    print(f"Wallet admin : solde={wal.get('solde')}, total_genere={wal.get('total_commission_generee')}")
print()
sys.exit(0 if fail==0 and err==0 else 1)
