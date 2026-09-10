#!/usr/bin/env python3
"""Cycle démo avec pause longue sur le signal pour observer la bannière."""
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
    log(f"🚀 DÉMO SIGNAL ADMIN — regardez : {FRONT}/admin")
    log("="*65)
    admin_tok,_ = (lambda s,j:(j["data"]["token"],j["data"]["user"]["id"]))(*post(f"{BASE}/api/auth/login",{"email":"admin@transport.bj","password":"Admin@12345"}))

    stamp = time.strftime("%Hh%M%S")
    rnd = uuid.uuid4().hex[:5]
    tel = f"9{int(time.time())%10000000:07d}"[-8:]
    ce = f"demo.c.{stamp}.{rnd}@test.bj"
    te = f"demo.t.{stamp}.{rnd}@test.bj"
    future = time.strftime("%Y-%m-%d", time.gmtime(time.time()+86400*7))

    s,j = post(f"{BASE}/api/auth/register", {"nom":"Demo","prenom":"Client","email":ce,"password":"Test@12345","password_confirmation":"Test@12345","role":"client","telephone":f"+22997{tel[-6:]}"})
    ctok, cid = j["data"]["token"], j["data"]["user"]["id"]
    s,j = post(f"{BASE}/api/auth/register", {"nom":"Demo","prenom":"Transp","email":te,"password":"Test@12345","password_confirmation":"Test@12345","role":"transporteur","telephone":f"+22998{tel[-6:]}"})
    ttok, tid = j["data"]["token"], j["data"]["user"]["id"]
    log(f"✅ client=#{cid} transporteur=#{tid}")

    colis_nom = f"🚨 SIGNAL TEST [{stamp}] - REGARDEZ EN HAUT"
    s,j = post(f"{BASE}/api/colis", {"nom_colis":colis_nom,"type_produit":"autre","nombre_produits":1,"poids":2,"pays":"Bénin","ville":"Cotonou","date_limite":future,"adresse_depart":"Dantokpa","adresse_destination":"Ganhi"}, token=ctok)
    colis_id = j["data"]["colis_id"]; pay_id = j["data"]["paiement"]["id"]
    log(f"📦 colis posté #{colis_id}")
    post(f"{BASE}/api/admin/colis/{colis_id}/statut",{"statut":"approuve"},token=admin_tok)
    log("✅ colis approuvé")

    s,j = post(f"{BASE}/api/voyages", {"numero_permis":f"DEM{rnd.upper()}","vehicule":"Démo Signal","compagnie":"Demo Trans","ville":"Cotonou","pays":"Bénin","pays_depart":"Bénin","pays_destination":"Bénin","date_depart":future,"heure_depart":"10:00","poids_max":100,"email":te,"telephone":f"+22998{tel[-6:]}"}, token=ttok)
    vid = j["data"]["voyage_id"]
    post(f"{BASE}/api/admin/voyages/{vid}/statut",{"statut":"approuve"},token=admin_tok)
    log(f"🚛 voyage #{vid} approuvé")

    post(f"{BASE}/api/reservations",{"colis_id":colis_id,"voyage_id":vid},token=ttok)
    s2,j2 = get(f"{BASE}/api/colis/{colis_id}/reservations", token=ctok)
    rid = next(r["id"] for r in j2["data"] if r.get("voyage_id")==vid)
    post(f"{BASE}/api/reservations/{rid}/action",{"action":"accepte"},token=ctok)
    log(f"🤝 réservation #{rid} acceptée")

    post(f"{BASE}/api/paiements/{pay_id}/verify-kkiapay",{"transactionId":f"DEMO{rnd.upper()}{stamp}","status":"SUCCESS"},token=ctok)
    log("💳 paiement vérifié")

    post(f"{BASE}/api/suivi",{"colis_id":colis_id,"statut":"En cours"},token=ttok)

    log("\n"+"="*65)
    log(f"🚨 DANS 5 SECONDES LE TRANSPORTEUR MARQUE 'LIVRÉ' !")
    log(f"   OUVREZ/RAFRAÎCHISSEZ : {FRONT}/admin (Ctrl+F5)")
    log("   Attendez-vous à voir :")
    log("   - 🔊 Beep (si son activé)")
    log("   - 🔴 BANNIÈRE ROUGE CLIGNOTANTE en haut")
    log("   - 🏷️ Badge rouge '1' sur bouton 'colis'")
    log("   - 🟡 Ligne jaune dans tableau colis avec bouton Confirmer")
    log("="*65)
    time.sleep(5)

    s,j = post(f"{BASE}/api/suivi",{"colis_id":colis_id,"statut":"Livré"},token=ttok)
    log(f"📬 SIGNAL ENVOYÉ ! Vous avez 60 SECONDES pour observer le signal...")
    log("    (la confirmation sera automatique après la pause)")

    for i in range(60,0,-5):
        time.sleep(5)
        s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
        ddl = j["data"].get("demandes_livraison",0)
        nlu = j["data"].get("notifications_non_lues",0)
        log(f"    [{i}s restantes] demandes_livraison={ddl}, notifs non lues={nlu}")

    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    pend = j["data"].get("pending_livraisons",[])
    sid = pend[0]["suivi_id"] if pend else None
    if not sid:
        s2,j2 = get(f"{BASE}/api/admin/colis", token=admin_tok)
        sid = next((c["demande_livraison_id"] for c in j2["data"] if c["id"]==colis_id and c.get("demande_livraison_id")),None)

    log(f"\n✅ Confirmation auto du suivi #{sid}")
    s,j = post(f"{BASE}/api/admin/suivi/{sid}/livraison",{"decision":"confirmer"},token=admin_tok)
    d = j.get("data",{})
    log(f"💰 Commissions : transporteur={d.get('commission_transporteur')} XOF, admin={d.get('commission_admin')} XOF")
    log("🎉 La bannière et la ligne jaune doivent disparaître après refresh.")
    log("="*65)
    log("✅ DÉMO TERMINÉE")
    log("="*65)

if __name__=="__main__":
    main()
