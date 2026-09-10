#!/usr/bin/env python3
"""
test_cycle_interactif.py — Cycle live où l'admin approuve manuellement depuis la page web.
À chaque étape, on attend que l'admin ait agi, puis on continue.
"""
import argparse, sys, time, uuid, requests

def wait(msg):
    print(f"\n⏸  {msg}")
    input("   ▶ Appuyez sur ENTRÉE une fois que c'est fait...")

def post(url, data, token=None):
    h = {"Content-Type": "application/json"}
    if token: h["Authorization"] = f"Bearer {token}"
    r = requests.post(url, json=data, headers=h, timeout=20, verify=False)
    try: return r.status_code, r.json()
    except: return r.status_code, {"raw": r.text}

def get(url, token=None):
    h = {"Authorization": f"Bearer {token}"} if token else {}
    r = requests.get(url, headers=h, timeout=20, verify=False)
    try: return r.status_code, r.json()
    except: return r.status_code, {"raw": r.text}

def poll_until(predicate, url, token=None, timeout=120, interval=3, label="attente"):
    start = time.time()
    while time.time() - start < timeout:
        s, j = get(url, token=token)
        data = j.get("data", []) if isinstance(j.get("data"), list) else j.get("data", {})
        if predicate(data):
            return data
        print(f"   ⏳ {label}...", end="\r", flush=True)
        time.sleep(interval)
    print()
    print(f"❌ Timeout après {timeout}s sur {label}")
    sys.exit(1)

def login(base, email, pw):
    s,j = post(f"{base}/api/auth/login", {"email":email,"password":pw})
    if s != 200 or not j.get("success"):
        print(f"❌ login {email}: {j}"); sys.exit(1)
    return j["data"]["token"], j["data"]["user"]["id"]

def main():
    import urllib3; urllib3.disable_warnings()
    p = argparse.ArgumentParser()
    p.add_argument("--url", default="https://e820-137-255-185-221.ngrok-free.app")
    args = p.parse_args()
    BASE = args.url.rstrip("/")

    print("="*60)
    print("🚀 TEST CYCLE INTERACTIF — admin doit approuver en direct")
    print("="*60)

    admin_tok, admin_id = login(BASE, "admin@transport.bj", "Admin@12345")
    print(f"✅ Connecté en admin (id={admin_id})\n")

    # Créer client + transporteur uniques avec un numéro Bénin valide pour payout
    stamp = time.strftime("%Hh%M")
    rnd = uuid.uuid4().hex[:6]
    tel_base = f"9{int(time.time())%10000000:07d}"[-8:]  # 8 chiffres
    client_email = f"live.client.{stamp}.{rnd}@test.bj"
    transp_email = f"live.transp.{stamp}.{rnd}@test.bj"
    tel_client = f"+22997{tel_base[-6:]}"
    tel_transp = f"+22998{tel_base[-6:]}"
    future = time.strftime("%Y-%m-%d", time.gmtime(time.time()+86400*7))

    s,j = post(f"{BASE}/api/auth/register", {
        "nom": "LiveClient", "prenom": "Test", "email": client_email,
        "password": "Test@12345", "password_confirmation": "Test@12345",
        "role": "client", "telephone": tel_client,
    })
    if s not in (200,201):
        print(f"❌ create client: {j}"); sys.exit(1)
    client_tok, client_id = j["data"]["token"], j["data"]["user"]["id"]
    print(f"✅ Client créé id={client_id} email={client_email} tel={tel_client}")

    s,j = post(f"{BASE}/api/auth/register", {
        "nom": "LiveTransp", "prenom": "Test", "email": transp_email,
        "password": "Test@12345", "password_confirmation": "Test@12345",
        "role": "transporteur", "telephone": tel_transp,
    })
    if s not in (200,201):
        print(f"❌ create transp: {j}"); sys.exit(1)
    transp_tok, transp_id = j["data"]["token"], j["data"]["user"]["id"]
    print(f"✅ Transporteur créé id={transp_id} email={transp_email} tel={tel_transp}")

    # ===== ÉTAPE 1 : Poster colis =====
    print("\n" + "-"*60)
    colis_nom = f"🔴 TEST SIGNAL LIVE [{stamp}] - Colis Démo"
    s,j = post(f"{BASE}/api/colis", {
        "nom_colis": colis_nom,
        "type_produit": "autre",
        "nombre_produits": 1,
        "poids": 3,
        "pays": "Bénin", "ville": "Cotonou",
        "date_limite": future,
        "adresse_depart": "Dantokpa Cotonou",
        "adresse_destination": "Marché Ganhi Porto-Novo",
    }, token=client_tok)
    if s not in (200,201):
        print(f"❌ post colis: {j}"); sys.exit(1)
    colis_id = j["data"]["colis_id"]
    paiement_id = j["data"]["paiement"]["id"]
    print(f"📦 COLIS POSTÉ : id={colis_id} « {colis_nom} » paiement=#{paiement_id}")

    wait("👉 ALLEZ dans votre page Admin → Colis, approuvez ce colis (statut 'approuvé').")

    # Vérifier approuvé
    poll_until(
        lambda data: any(c.get("id")==colis_id and c.get("statut")=="approuve" for c in data) if isinstance(data,list) else False,
        f"{BASE}/api/admin/colis", token=admin_tok, label="attente approbation colis"
    )
    print("✅ Colis approuvé détecté !")

    # ===== ÉTAPE 2 : Proposer voyage =====
    print("-"*60)
    s,j = post(f"{BASE}/api/voyages", {
        "numero_permis": f"LIVE{rnd.upper()}",
        "vehicule": "Toyota Hiace TEST LIVE",
        "compagnie": "Live Trans Démo",
        "ville": "Cotonou", "pays": "Bénin",
        "pays_depart": "Bénin", "pays_destination": "Bénin",
        "date_depart": future, "heure_depart": "10:00",
        "poids_max": 100,
        "email": transp_email, "telephone": tel_transp,
    }, token=transp_tok)
    if s not in (200,201):
        print(f"❌ create voyage: {j}"); sys.exit(1)
    voyage_id = j["data"]["voyage_id"]
    print(f"🚛 VOYAGE PROPOSÉ : id={voyage_id} (compagnie Live Trans Démo)")

    wait("👉 Dans Admin → Voyages, approuvez le voyage 'Live Trans Démo'.")

    poll_until(
        lambda data: any(v.get("id")==voyage_id and v.get("statut")=="approuve" for v in data) if isinstance(data,list) else False,
        f"{BASE}/api/admin/voyages", token=admin_tok, label="attente approbation voyage"
    )
    print("✅ Voyage approuvé détecté !")

    # ===== ÉTAPE 3 : Transporteur réserve =====
    print("-"*60)
    s,j = post(f"{BASE}/api/reservations", {"colis_id":colis_id,"voyage_id":voyage_id}, token=transp_tok)
    if s not in (200,201):
        print(f"❌ réservation: {j}"); sys.exit(1)
    # Récupérer l'id réservation
    s,j = get(f"{BASE}/api/colis/{colis_id}/reservations", token=client_tok)
    res_id = next((r["id"] for r in j.get("data",[]) if r.get("voyage_id")==voyage_id), None)
    if not res_id:
        print(f"❌ réservation non trouvée: {j}"); sys.exit(1)
    print(f"🎫 RÉSERVATION CRÉÉE id={res_id} (transporteur réserve le colis)")

    # Client accepte (auto, c'est notre client de test)
    s,j = post(f"{BASE}/api/reservations/{res_id}/action", {"action":"accepte"}, token=client_tok)
    if s != 200:
        print(f"❌ accept réservation: {j}"); sys.exit(1)
    print("✅ Client a accepté la réservation")

    # ===== ÉTAPE 4 : Paiement =====
    # Initier paiement kkiapay
    s,j = post(f"{BASE}/api/paiements/{paiement_id}/payer", {"methode":"kkiapay"}, token=client_tok)
    print(f"💳 Paiement initié (status={s})")
    # Verify simulé
    s,j = post(f"{BASE}/api/paiements/{paiement_id}/verify-kkiapay",
               {"transactionId":f"LIVE{rnd.upper()}","status":"SUCCESS"}, token=client_tok)
    if s == 200:
        print(f"✅ Paiement vérifié (sandbox) → {j.get('data',{}).get('statut')}")
    else:
        print(f"⚠️  verify-kkiapay: HTTP {s} {j}")

    # ===== ÉTAPE 5 : Transporteur marque LIVRÉ → SIGNAL ADMIN =====
    print("\n" + "="*60)
    print("🚨 MAINTENANT : le transporteur marque LIVRÉ...")
    print("   REGARDEZ VOTRE PAGE ADMIN — un signal devrait apparaître !")
    print("="*60)
    time.sleep(2)
    s,j = post(f"{BASE}/api/suivi", {"colis_id":colis_id,"statut":"Livré"}, token=transp_tok)
    if s not in (200,201):
        print(f"❌ marquer livré: {j}"); sys.exit(1)
    print(f"📬 LIVRÉ SIGNALÉ ! Colis « {colis_nom} » → en attente de confirmation admin")

    # Polling pour vérifier que demande_livraison est visible côté stats
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    stats = j.get("data",{})
    ddl = stats.get("demandes_livraison",0)
    print(f"   Stats admin: demandes_livraison = {ddl}")

    wait("👉 Vous devriez voir le colis en surbrillance dans Admin → Colis. "
         "Cliquez sur CONFIRMER (bouton vert) pour valider la livraison.")

    # Attendre que la livraison soit confirmée (demande_livraison_id devient NULL / colis disparaît des pending)
    def livraison_faite(data):
        for c in data:
            if c.get("id")==colis_id and c.get("demande_livraison_id") is None:
                return True
        return False
    poll_until(livraison_faite, f"{BASE}/api/admin/colis", token=admin_tok,
               timeout=180, label="attente confirmation livraison")
    print("🎉 LIVRAISON CONFIRMÉE par l'admin !")

    # Vérifier soldes
    s,j = get(f"{BASE}/api/admin/wallet", token=admin_tok)
    print(f"💰 Wallet admin solde = {j.get('data',{}).get('solde')} XOF (5% plateforme)")
    s,j = get(f"{BASE}/api/transporteur/solde", token=transp_tok)
    sd = j.get("data",{})
    if isinstance(sd, dict):
        print(f"💸 Solde transporteur : {sd.get('solde')} XOF (en_attente={sd.get('en_attente')}, payé={sd.get('total_paye')})")

    print("\n" + "="*60)
    print("✅ CYCLE COMPLET TERMINÉ AVEC SUCCÈS !")
    print(f"   colis={colis_id} voyage={voyage_id} réservation={res_id} paiement={paiement_id}")
    print("="*60)

if __name__ == "__main__":
    main()
