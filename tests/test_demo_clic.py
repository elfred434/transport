#!/usr/bin/env python3
"""
Démo signal admin : le script crée le colis, l'approuve, le transporte,
marque "Livré" (signal), puis ATTEND que L'ADMIN CLIQUE SUR CONFIRMER
dans l'interface web. Pas de confirmation automatique.
"""
import sys, time, uuid, requests

def log(msg): print(f"[{time.strftime('%H:%M:%S')}] {msg}", flush=True)
def post(url, data, token=None):
    h = {"Content-Type":"application/json"}
    if token: h["Authorization"]=f"Bearer {token}"
    r = requests.post(url, json=data, headers=h, timeout=20, verify=False)
    try: return r.status_code, r.json()
    except: return r.status_code, {"raw":r.text}
def get(url, token=None):
    h = {"Authorization":f"Bearer {token}"} if token else {}
    r = requests.get(url, headers=h, timeout=20, verify=False)
    try: return r.status_code, r.json()
    except: return r.status_code, {"raw":r.text}

def main():
    import urllib3; urllib3.disable_warnings()
    BASE = "https://e820-137-255-185-221.ngrok-free.app"
    FRONT = "https://4e0d-137-255-185-221.ngrok-free.app"
    log("="*65)
    log(f"🚀 DÉMO SIGNAL — VOUS CLIQUEZ SUR CONFIRMER DANS L'INTERFACE")
    log(f"   Page admin : {FRONT}/admin")
    log("="*65)

    admin_tok,_ = (lambda s,j:(j["data"]["token"],j["data"]["user"]["id"]))(*post(f"{BASE}/api/auth/login",{"email":"admin@transport.bj","password":"Admin@12345"}))

    stamp = time.strftime("%Hh%M%S")
    rnd = uuid.uuid4().hex[:5]
    tel = f"9{int(time.time())%10000000:07d}"[-8:]
    ce = f"cl.{stamp}.{rnd}@test.bj"
    te = f"tr.{stamp}.{rnd}@test.bj"
    future = time.strftime("%Y-%m-%d", time.gmtime(time.time()+86400*7))

    s,j = post(f"{BASE}/api/auth/register", {"nom":"Demo","prenom":"Client","email":ce,"password":"Test@12345","password_confirmation":"Test@12345","role":"client","telephone":f"+22997{tel[-6:]}"})
    ctok,cid = j["data"]["token"], j["data"]["user"]["id"]
    s,j = post(f"{BASE}/api/auth/register", {"nom":"Demo","prenom":"Transp","email":te,"password":"Test@12345","password_confirmation":"Test@12345","role":"transporteur","telephone":f"+22998{tel[-6:]}"})
    ttok,tid = j["data"]["token"], j["data"]["user"]["id"]
    log(f"✅ client=#{cid} transporteur=#{tid}")

    colis_nom = f"🔴 CLIQUEZ CONFIRMER [{stamp}]"
    s,j = post(f"{BASE}/api/colis", {"nom_colis":colis_nom,"type_produit":"autre","nombre_produits":1,"poids":2,"pays":"Bénin","ville":"Cotonou","date_limite":future,"adresse_depart":"Dantokpa","adresse_destination":"Ganhi"}, token=ctok)
    colis_id = j["data"]["colis_id"]; pay_id = j["data"]["paiement"]["id"]
    log(f"📦 colis posté #{colis_id} : {colis_nom}")
    post(f"{BASE}/api/admin/colis/{colis_id}/statut",{"statut":"approuve"},token=admin_tok)
    log("✅ colis approuvé")

    s,j = post(f"{BASE}/api/voyages", {"numero_permis":f"DEM{rnd.upper()}","vehicule":"Démo Voiture","compagnie":"Demo Click","ville":"Cotonou","pays":"Bénin","pays_depart":"Bénin","pays_destination":"Bénin","date_depart":future,"heure_depart":"10:00","poids_max":100,"email":te,"telephone":f"+22998{tel[-6:]}"}, token=ttok)
    vid = j["data"]["voyage_id"]
    post(f"{BASE}/api/admin/voyages/{vid}/statut",{"statut":"approuve"},token=admin_tok)
    log(f"🚛 voyage #{vid} approuvé")

    post(f"{BASE}/api/reservations",{"colis_id":colis_id,"voyage_id":vid},token=ttok)
    s2,j2 = get(f"{BASE}/api/colis/{colis_id}/reservations", token=ctok)
    rid = next(r["id"] for r in j2["data"] if r.get("voyage_id")==vid)
    post(f"{BASE}/api/reservations/{rid}/action",{"action":"accepte"},token=ctok)
    log(f"🤝 réservation #{rid} acceptée")

    post(f"{BASE}/api/paiements/{pay_id}/verify-kkiapay",{"transactionId":f"CLK{rnd.upper()}{stamp}","status":"SUCCESS"},token=ctok)
    log("💳 paiement vérifié (sandbox)")

    post(f"{BASE}/api/suivi",{"colis_id":colis_id,"statut":"En cours"},token=ttok)

    log("\n"+"="*65)
    log("🚨 DANS 3 SECONDES, SIGNAL 'LIVRÉ' ENVOYÉ !")
    log(f"   👉 ALLEZ sur : {FRONT}/admin")
    log("   1. Si la page est déjà ouverte, faites Ctrl+F5")
    log("   2. Vous devriez voir la bannière rouge + le badge + la ligne jaune")
    log("   3. CLIQUEZ SUR LE BOUTON VERT « CONFIRMER » vous-même")
    log("   Je ne vais PAS confirmer à votre place.")
    log("="*65)
    time.sleep(3)

    s,j = post(f"{BASE}/api/suivi",{"colis_id":colis_id,"statut":"Livré"},token=ttok)
    log("\n📬 SIGNAL ENVOYÉ ! En attente de votre clic sur Confirmer...")

    # Récupérer le suivi_id pour info
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    pend = j["data"].get("pending_livraisons", [])
    sid = pend[0]["suivi_id"] if pend else "?"
    prix = pend[0]["prix_estime"] if pend else "?"
    log(f"   colis=#{colis_id} suivi=#{sid} prix={prix} XOF")
    log(f"   Commission attendue : transp 95%, admin 5%")

    # Attendre que l'admin confirme (max 5 minutes)
    start = time.time()
    while time.time() - start < 300:
        s,j = get(f"{BASE}/api/admin/colis", token=admin_tok)
        c = next((x for x in j["data"] if x["id"]==colis_id), None)
        if c and not c.get("demande_livraison_id"):
            break
        elapsed = int(time.time()-start)
        if elapsed % 10 == 0:
            ddl = get(f"{BASE}/api/admin/stats",token=admin_tok)[1]["data"].get("demandes_livraison")
            log(f"   ⏳ en attente... ({elapsed}s) demandes_livraison={ddl}")
        time.sleep(3)
    else:
        log("❌ Timeout 5 minutes — vous n'avez pas cliqué")
        return

    elapsed = int(time.time()-start)
    log(f"\n🎉 VOUS AVEZ CONFIRMÉ en {elapsed} secondes !")

    # Vérifier soldes
    s,j = get(f"{BASE}/api/admin/wallet", token=admin_tok)
    w = j.get("data",{})
    log(f"💰 Wallet admin solde = {w.get('solde')} XOF")
    s,j = get(f"{BASE}/api/transporteur/solde", token=ttok)
    sd = j.get("data",{}) if isinstance(j.get("data"),dict) else {}
    if isinstance(sd, dict):
        log(f"💸 Solde transporteur : {sd.get('solde')} XOF  (en_attente={sd.get('en_attente')}, payé={sd.get('total_paye')})")
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    log(f"📊 demandes_livraison final = {j['data'].get('demandes_livraison')}")

    log("\n"+"="*65)
    log("✅ DÉMO TERMINÉE — c'est vous qui avez confirmé !")
    log("="*65)

if __name__=="__main__":
    main()
