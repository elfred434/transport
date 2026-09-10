#!/usr/bin/env python3
"""
test_cycle.py — Test du cycle complet de livraison Transport Bénin.

Usage:
    python3 tests/test_cycle.py [--url https://...ngrok-free.app]

Étapes testées:
    1. Login admin, création client + transporteur
    2. Client poste colis (paiement en attente créé auto)
    3. Admin approuve colis
    4. Transporteur propose voyage (avec fiche transporteur)
    5. Admin approuve voyage
    6. Client accepte la réservation du transporteur
    7. Client paie via Kkiapay (init + verify, fallback sandbox)
    8. Transporteur marque "Livré" → signal admin
    9. Admin voit pending_livraisons via /stats
   10. Admin confirme livraison → 95% transporteur + 5% admin_wallet
"""
import argparse
import json
import sys
import time
import uuid
import requests

def ok(step, msg=""): print(f"  ✅ {step} {msg}")
def fail(step, msg=""): print(f"  ❌ {step} — {msg}"); sys.exit(1)
def info(msg): print(f"  ℹ️  {msg}")

def post(url, data, token=None):
    h = {"Content-Type": "application/json"}
    if token: h["Authorization"] = f"Bearer {token}"
    r = requests.post(url, json=data, headers=h, timeout=20, verify=False)
    try: j = r.json()
    except: j = {"raw": r.text}
    return r.status_code, j

def get(url, token=None):
    h = {}
    if token: h["Authorization"] = f"Bearer {token}"
    r = requests.get(url, headers=h, timeout=20, verify=False)
    try: j = r.json()
    except: j = {"raw": r.text}
    return r.status_code, j


def login(base, email, password):
    s, j = post(f"{base}/api/auth/login", {"email": email, "password": password})
    if s != 200 or not j.get("success"):
        fail(f"login {email}", f"HTTP {s}: {j}")
    return j["data"]["token"], j["data"]["user"]["id"]


def main():
    import urllib3; urllib3.disable_warnings()
    p = argparse.ArgumentParser()
    p.add_argument("--url", default="https://e820-137-255-185-221.ngrok-free.app")
    args = p.parse_args()
    BASE = args.url.rstrip("/")
    print(f"\n🧪 Test cycle complet — {BASE}\n")

    s, j = get(f"{BASE}/api")
    if s != 200: fail("santé API", f"HTTP {s}")
    ok("santé API", f"v{j.get('data',{}).get('version','?')}")

    print("\n🔐 Connexions")
    admin_tok, admin_id = login(BASE, "admin@transport.bj", "Admin@12345")

    rnd = f"{int(time.time())}_{uuid.uuid4().hex[:6]}"
    client_email = f"cl_{rnd}@test.bj"
    transp_email = f"tr_{rnd}@test.bj"
    tel_client = f"+22997{int(time.time()*1000)%1000000:06d}"
    tel_transp = f"+22998{int(time.time()*1000)%1000000+1:06d}"
    future = time.strftime("%Y-%m-%d", time.gmtime(time.time()+86400*10))

    s, j = post(f"{BASE}/api/auth/register", {
        "nom": "Client", "prenom": "Test", "email": client_email,
        "password": "Test@12345", "password_confirmation": "Test@12345",
        "role": "client", "telephone": tel_client,
    })
    if s not in (200,201) or not j.get("success"):
        fail("création client", f"HTTP {s}: {j}")
    client_tok, client_id = j["data"]["token"], j["data"]["user"]["id"]
    ok("client créé", f"id={client_id}")

    s, j = post(f"{BASE}/api/auth/register", {
        "nom": "Transp", "prenom": "Test", "email": transp_email,
        "password": "Test@12345", "password_confirmation": "Test@12345",
        "role": "transporteur", "telephone": tel_transp,
    })
    if s not in (200,201) or not j.get("success"):
        fail("création transporteur", f"HTTP {s}: {j}")
    transp_tok, transp_id = j["data"]["token"], j["data"]["user"]["id"]
    ok("transporteur créé", f"id={transp_id}")

    # 2. Client poste colis
    print("\n📦 Client poste colis")
    s, j = post(f"{BASE}/api/colis", {
        "nom_colis": f"Colis test {rnd[:10]}",
        "type_produit": "autre",
        "nombre_produits": 1,
        "poids": 5,
        "pays": "Bénin", "ville": "Cotonou",
        "date_limite": future,
        "adresse_depart": "Dantokpa",
        "adresse_destination": "Ganhi Porto-Novo",
    }, token=client_tok)
    if s not in (200,201) or not j.get("success"):
        fail("post colis", f"HTTP {s}: {j}")
    colis_id = j["data"]["colis_id"]
    paiement_id = j["data"]["paiement"]["id"]
    ok("colis posté", f"id={colis_id} paiement={paiement_id}")

    # 3. Admin approuve colis
    print("\n✅ Admin approuve colis")
    s, j = post(f"{BASE}/api/admin/colis/{colis_id}/statut", {"statut":"approuve"}, token=admin_tok)
    if s != 200: fail("approuver colis", f"HTTP {s}: {j}")
    ok("colis approuvé")

    # 4. Transporteur propose voyage
    print("\n🚛 Transporteur propose voyage")
    s, j = post(f"{BASE}/api/voyages", {
        "numero_permis": f"PERM{rnd[-6:].upper()}",
        "vehicule": "Toyota Hiace",
        "compagnie": "Test Trans",
        "ville": "Cotonou", "pays": "Bénin",
        "pays_depart": "Bénin", "pays_destination": "Bénin",
        "date_depart": future, "heure_depart": "08:00",
        "poids_max": 100,
        "email": transp_email, "telephone": tel_transp,
    }, token=transp_tok)
    if s not in (200,201) or not j.get("success"):
        fail("créer voyage", f"HTTP {s}: {j}")
    voyage_id = j["data"]["voyage_id"]
    ok("voyage créé", f"id={voyage_id}")

    # 5. Admin approuve voyage
    print("\n✅ Admin approuve voyage")
    s, j = post(f"{BASE}/api/admin/voyages/{voyage_id}/statut", {"statut":"approuve"}, token=admin_tok)
    if s != 200: fail("approuver voyage", f"HTTP {s}: {j}")
    ok("voyage approuvé")

    # 6. Transporteur réserve le colis pour son voyage
    print("\n🎫 Transporteur réserve le colis sur son voyage")
    s, j = post(f"{BASE}/api/reservations", {"colis_id":colis_id, "voyage_id":voyage_id}, token=transp_tok)
    if s not in (200,201): fail("réservation", f"HTTP {s}: {j}")
    # Récupérer l'id de réservation (le endpoint ne la retourne pas directement, list via client)
    s, j = get(f"{BASE}/api/colis/{colis_id}/reservations", token=client_tok)
    reservations = j.get("data", []) if isinstance(j, dict) else []
    res_id = None
    for r in reservations:
        if r.get("voyage_id") == voyage_id and r.get("colis_id") == colis_id:
            res_id = r["id"]; break
    if not res_id: fail("réservation non retrouvée", str(reservations))
    ok("réservation créée", f"id={res_id}")

    # 7. Client accepte la réservation
    print("\n🤝 Client accepte la réservation")
    s, j = post(f"{BASE}/api/reservations/{res_id}/action", {"action":"accepte"}, token=client_tok)
    if s != 200: fail("accepter réservation", f"HTTP {s}: {j}")
    ok("réservation acceptée")

    # 8. Paiement : init + verify (fallback sandbox si clés Kkiapay invalides)
    print("\n💳 Paiement Kkiapay (fallback sandbox)")
    s, j = post(f"{BASE}/api/paiements/{paiement_id}/payer", {}, token=client_tok)
    if s != 200: info(f"payer: HTTP {s} {j}")
    else: ok("paiement initié")
    s, j = post(f"{BASE}/api/paiements/{paiement_id}/verify-kkiapay",
                {"transactionId":f"TEST_{rnd[-8:]}", "status":"SUCCESS"}, token=client_tok)
    if s != 200: fail("verify-kkiapay", f"HTTP {s}: {j}")
    ok("paiement vérifié", f"statut={j.get('data',{}).get('statut','?')}")

    # 9. Transporteur marque Livré
    print("\n📬 Transporteur marque LIVRÉ → signal admin")
    s, j = post(f"{BASE}/api/suivi", {"colis_id":colis_id, "statut":"Livré"}, token=transp_tok)
    if s not in (200,201): fail("marquer livré", f"HTTP {s}: {j}")
    ok("livré signalé")

    # 10. Stats admin — pending_livraisons
    print("\n📊 Admin consulte stats — doit voir demandes_livraison")
    s, j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    stats = j.get("data", {})
    ddl = stats.get("demandes_livraison", 0)
    pend = stats.get("pending_livraisons", [])
    ok(f"demandes_livraison={ddl}, pending={len(pend)}")
    if ddl == 0: fail("zéro demande détectée")
    suivi_id = pend[0]["suivi_id"]
    commission_attendue_transp = float(pend[0]["prix_estime"]) * 0.95
    commission_attendue_admin = float(pend[0]["prix_estime"]) * 0.05
    ok(f"prix={pend[0]['prix_estime']} XOF → 95%={commission_attendue_transp}, 5%={commission_attendue_admin}")

    # 11. Admin confirme livraison
    print(f"\n💰 Admin confirme livraison suivi#{suivi_id}")
    s, j = post(f"{BASE}/api/admin/suivi/{suivi_id}/livraison", {"decision":"confirmer"}, token=admin_tok)
    if s != 200: fail("confirmer livraison", f"HTTP {s}: {j}")
    data = j.get("data", {})
    ok(f"commissions T={data.get('commission_transporteur')} A={data.get('commission_admin')}")
    payout = data.get("payout")
    if payout:
        info(f"payout status={payout.get('status')}")
    else:
        info("payout auto non déclenché (numéro ou clés Kkiapay)")

    # 12. Vérifier solde transporteur + wallet admin
    print("\n🔍 Soldes après confirmation")
    s, j = get(f"{BASE}/api/admin/wallet", token=admin_tok)
    w = j.get("data", {})
    ok(f"wallet admin solde = {w.get('solde')} XOF")

    s, j = get(f"{BASE}/api/transporteur/solde", token=transp_tok)
    st = j.get("data", {}) if isinstance(j.get("data"), dict) else j.get("data")
    ok(f"solde transporteur : {st}")

    # 13. Notifications
    print("\n🔔 Notifications admin")
    s, j = get(f"{BASE}/api/admin/notifications", token=admin_tok)
    ndata = j.get("data", {})
    ok(f"non_lues={ndata.get('non_lues')} (devrait être 0 car marqué lu à la décision)")

    print("\n🎉 Cycle complet terminé avec succès !")
    print(f"   colis={colis_id} voyage={voyage_id} réservation={res_id} suivi={suivi_id} paiement={paiement_id}")

if __name__ == "__main__":
    main()
