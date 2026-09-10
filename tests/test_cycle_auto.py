#!/usr/bin/env python3
"""
test_cycle_auto.py — Cycle live, détection automatique des actions admin par polling.
Pas d'interaction clavier nécessaire : le script attend que l'admin ait agi sur la page web.
"""
import argparse, sys, time, uuid, requests

def log(msg, end="\n"):
    print(msg, flush=True, end=end)

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

def poll_until(predicate, fetcher, timeout=300, interval=3, label="attente"):
    start = time.time()
    i = 0
    while time.time() - start < timeout:
        data = fetcher()
        if predicate(data):
            log(f"   ✅ {label}")
            return data
        i += 1
        if i % 4 == 1:
            log(f"   ⏳ {label} depuis {int(time.time()-start)}s...", end="\r")
        time.sleep(interval)
    log(f"\n❌ Timeout après {timeout}s : {label}")
    sys.exit(1)

def login(base, email, pw):
    s,j = post(f"{base}/api/auth/login", {"email":email,"password":pw})
    if s != 200 or not j.get("success"):
        log(f"❌ login {email}: {j}"); sys.exit(1)
    return j["data"]["token"], j["data"]["user"]["id"]

def main():
    import urllib3; urllib3.disable_warnings()
    p = argparse.ArgumentParser()
    p.add_argument("--url", default="https://e820-137-255-185-221.ngrok-free.app")
    args = p.parse_args()
    BASE = args.url.rstrip("/")

    log("="*60)
    log("🚀 TEST CYCLE EN DIRECT — approuvez sur la page admin")
    log("="*60)

    admin_tok, admin_id = login(BASE, "admin@transport.bj", "Admin@12345")
    log(f"✅ Connecté admin id={admin_id}\n")

    stamp = time.strftime("%Hh%M%S")
    rnd = uuid.uuid4().hex[:5]
    tel_base = f"9{int(time.time())%10000000:07d}"[-8:]
    client_email = f"live.c.{stamp}.{rnd}@test.bj"
    transp_email = f"live.t.{stamp}.{rnd}@test.bj"
    tel_client = f"+22997{tel_base[-6:]}"
    tel_transp = f"+22998{tel_base[-6:]}"
    future = time.strftime("%Y-%m-%d", time.gmtime(time.time()+86400*7))

    s,j = post(f"{BASE}/api/auth/register", {
        "nom": "LiveC", "prenom": "Test", "email": client_email,
        "password": "Test@12345", "password_confirmation": "Test@12345",
        "role": "client", "telephone": tel_client,
    })
    client_tok, client_id = j["data"]["token"], j["data"]["user"]["id"]
    log(f"✅ Client id={client_id}  tel={tel_client}")

    s,j = post(f"{BASE}/api/auth/register", {
        "nom": "LiveT", "prenom": "Test", "email": transp_email,
        "password": "Test@12345", "password_confirmation": "Test@12345",
        "role": "transporteur", "telephone": tel_transp,
    })
    transp_tok, transp_id = j["data"]["token"], j["data"]["user"]["id"]
    log(f"✅ Transporteur id={transp_id}  tel={tel_transp}\n")

    # === ÉTAPE 1 : POSTER COLIS ===
    colis_nom = f"🔴 TEST LIVE [{stamp}] - Colis Démo"
    s,j = post(f"{BASE}/api/colis", {
        "nom_colis": colis_nom,
        "type_produit": "autre",
        "nombre_produits": 1,
        "poids": 3,
        "pays": "Bénin", "ville": "Cotonou",
        "date_limite": future,
        "adresse_depart": "Dantokpa",
        "adresse_destination": "Ganhi Porto-Novo",
    }, token=client_tok)
    colis_id = j["data"]["colis_id"]
    paiement_id = j["data"]["paiement"]["id"]
    log(f"📦 ÉTAPE 1 : colis posté  id={colis_id}")
    log(f"   👉 ALLEZ dans Admin → Colis, approuvez « {colis_nom} »")

    poll_until(
        lambda lst: any(c.get("id")==colis_id and c.get("statut")=="approuve" for c in lst),
        lambda: get(f"{BASE}/api/admin/colis", token=admin_tok)[1].get("data", []),
        label=f"approbation colis #{colis_id}"
    )

    # === ÉTAPE 2 : VOYAGE ===
    s,j = post(f"{BASE}/api/voyages", {
        "numero_permis": f"LIVE{rnd.upper()}",
        "vehicule": f"🔴 TEST LIVE [{stamp}] Toyota",
        "compagnie": "Live Trans Démo",
        "ville": "Cotonou", "pays": "Bénin",
        "pays_depart": "Bénin", "pays_destination": "Bénin",
        "date_depart": future, "heure_depart": "10:00",
        "poids_max": 100,
        "email": transp_email, "telephone": tel_transp,
    }, token=transp_tok)
    voyage_id = j["data"]["voyage_id"]
    log(f"\n🚛 ÉTAPE 2 : voyage proposé id={voyage_id}")
    log(f"   👉 Dans Admin → Voyages, approuvez « Live Trans Démo » / {future}")

    poll_until(
        lambda lst: any(v.get("id")==voyage_id and v.get("statut")=="approuve" for v in lst),
        lambda: get(f"{BASE}/api/admin/voyages", token=admin_tok)[1].get("data", []),
        label=f"approbation voyage #{voyage_id}"
    )

    # === ÉTAPE 3 : RÉSERVATION + ACCEPTATION CLIENT ===
    s,j = post(f"{BASE}/api/reservations", {"colis_id":colis_id,"voyage_id":voyage_id}, token=transp_tok)
    s2,j2 = get(f"{BASE}/api/colis/{colis_id}/reservations", token=client_tok)
    res_id = next((r["id"] for r in j2.get("data",[]) if r.get("voyage_id")==voyage_id), None)
    s,j = post(f"{BASE}/api/reservations/{res_id}/action", {"action":"accepte"}, token=client_tok)
    log(f"\n🎫 ÉTAPE 3 : réservation acceptée id={res_id}")

    # === ÉTAPE 4 : PAIEMENT ===
    s,j = post(f"{BASE}/api/paiements/{paiement_id}/payer", {"methode":"kkiapay"}, token=client_tok)
    s,j = post(f"{BASE}/api/paiements/{paiement_id}/verify-kkiapay",
               {"transactionId":f"LIVE{rnd.upper()}{stamp}","status":"SUCCESS"}, token=client_tok)
    pay_stat = j.get('data',{}).get('statut','?') if isinstance(j.get('data'),dict) else '?'
    log(f"💳 ÉTAPE 4 : paiement vérifié → {pay_stat}")

    # === ÉTAPE 5 : LIVRÉ → SIGNAL ===
    log("\n"+"="*60)
    log("🚨 LE TRANSPORTEUR MARQUE 'LIVRÉ' MAINTENANT...")
    s,j = post(f"{BASE}/api/suivi", {"colis_id":colis_id,"statut":"Livré"}, token=transp_tok)
    log("📬 Signal envoyé à l'admin !")
    log("   👉 OUVREZ Admin → Colis : la ligne doit être en JAUNE avec bouton CONFIRMER")
    time.sleep(2)
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    log(f"   Stats: demandes_livraison = {j['data'].get('demandes_livraison')}")

    poll_until(
        lambda lst: not any(c.get("id")==colis_id and c.get("demande_livraison_id") is not None for c in lst),
        lambda: get(f"{BASE}/api/admin/colis", token=admin_tok)[1].get("data", []),
        timeout=300,
        label=f"confirmation livraison colis #{colis_id} (cliquez sur Confirmer !)"
    )

    # === RÉSULTAT ===
    s,j = get(f"{BASE}/api/admin/wallet", token=admin_tok)
    w = j.get("data",{})
    s,j = get(f"{BASE}/api/transporteur/solde", token=transp_tok)
    sd = j.get("data",{}) if isinstance(j.get("data"),dict) else {}

    log("\n"+"="*60)
    log("🎉 CYCLE COMPLET TERMINÉ !")
    log(f"   💰 Wallet admin solde : {w.get('solde')} XOF")
    if isinstance(sd, dict):
        log(f"   💸 Solde transporteur : {sd.get('solde')} XOF  (en_attente={sd.get('en_attente')}, payé={sd.get('total_paye')})")
    log(f"   colis=#{colis_id} voyage=#{voyage_id} réservation=#{res_id} paiement=#{paiement_id}")
    log("="*60)

if __name__ == "__main__":
    main()
