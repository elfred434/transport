#!/usr/bin/env python3
"""
test_cycle_fullauto.py — Cycle complet automatique avec pauses pour que l'admin
voie les changements en direct sur la page web. Toutes les approbations sont
faites via l'API avec le token admin.
"""
import sys, time, uuid, requests

def log(msg, end="\n"):
    t = time.strftime("%H:%M:%S")
    print(f"[{t}] {msg}", flush=True, end=end)

def pause(seconds, msg=""):
    if msg: log(f"⏸  {msg} ({seconds}s)")
    time.sleep(seconds)

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

def expect(s, j, cond, label):
    if not cond:
        log(f"❌ ÉCHEC {label} — HTTP {s}: {j}")
        sys.exit(1)
    log(f"✅ {label}")

def login(base, email, pw):
    s,j = post(f"{base}/api/auth/login", {"email":email,"password":pw})
    expect(s, j, s==200 and j.get("success"), f"login {email}")
    return j["data"]["token"], j["data"]["user"]["id"]

def main():
    import urllib3; urllib3.disable_warnings()
    BASE = sys.argv[1] if len(sys.argv)>1 else "https://e820-137-255-185-221.ngrok-free.app"
    BASE = BASE.rstrip("/")

    log("="*65)
    log("🚀 CYCLE COMPLET 100% AUTOMATIQUE — regardez la page admin !")
    log("="*65)

    admin_tok, admin_id = login(BASE, "admin@transport.bj", "Admin@12345")
    pause(2)

    # Vérifier l'état initial
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    st0 = j["data"]
    log(f"📊 État initial : {st0['colis_en_attente']} colis en attente, "
        f"{st0['voyages_en_attente']} voyages, {st0['demandes_livraison']} livraisons")
    pause(2)

    # ==== CRÉER CLIENT + TRANSPORTEUR ====
    stamp = time.strftime("%Hh%M%S")
    rnd = uuid.uuid4().hex[:5]
    tel = f"9{int(time.time())%10000000:07d}"[-8:]
    client_email = f"auto.c.{stamp}.{rnd}@test.bj"
    transp_email = f"auto.t.{stamp}.{rnd}@test.bj"
    tel_client = f"+22997{tel[-6:]}"
    tel_transp = f"+22998{tel[-6:]}"
    future = time.strftime("%Y-%m-%d", time.gmtime(time.time()+86400*7))

    s,j = post(f"{BASE}/api/auth/register", {
        "nom":"AutoC","prenom":"Test", "email":client_email,
        "password":"Test@12345","password_confirmation":"Test@12345",
        "role":"client","telephone":tel_client,
    })
    client_tok, client_id = j["data"]["token"], j["data"]["user"]["id"]
    log(f"👤 Client créé id={client_id}  tel={tel_client}")

    s,j = post(f"{BASE}/api/auth/register", {
        "nom":"AutoT","prenom":"Test","email":transp_email,
        "password":"Test@12345","password_confirmation":"Test@12345",
        "role":"transporteur","telephone":tel_transp,
    })
    transp_tok, transp_id = j["data"]["token"], j["data"]["user"]["id"]
    log(f"🚚 Transporteur créé id={transp_id}  tel={tel_transp}")
    pause(2)

    # ==== ÉTAPE 1 : CLIENT POSTE UN COLIS ====
    colis_nom = f"🔴 AUTO CYCLE [{stamp}] - Démo 100% auto"
    log(f"\n📦 [1/11] Client poste un colis : « {colis_nom} »")
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
    expect(s, j, s in (200,201), f"colis créé id={colis_id} paiement=#{paiement_id}")
    pause(3, "colis visible dans la liste admin (statut en_attente)")

    # ==== ÉTAPE 2 : ADMIN APPROUVE LE COLIS ====
    log(f"\n✅ [2/11] Admin approuve le colis #{colis_id}")
    s,j = post(f"{BASE}/api/admin/colis/{colis_id}/statut", {"statut":"approuve"}, token=admin_tok)
    expect(s,j,s==200,f"colis #{colis_id} approuvé")
    pause(3, "colis passe en 'approuvé' dans la liste")

    # ==== ÉTAPE 3 : TRANSPORTEUR PROPOSE VOYAGE ====
    log(f"\n🚛 [3/11] Transporteur propose un voyage")
    s,j = post(f"{BASE}/api/voyages", {
        "numero_permis":f"AUTO{rnd.upper()}",
        "vehicule":f"🔴 AUTO [{stamp}] - Vehicule Démo",
        "compagnie":"Auto Trans Démo",
        "ville":"Cotonou","pays":"Bénin",
        "pays_depart":"Bénin","pays_destination":"Bénin",
        "date_depart":future,"heure_depart":"10:00",
        "poids_max":100,
        "email":transp_email,"telephone":tel_transp,
    }, token=transp_tok)
    voyage_id = j["data"]["voyage_id"]
    expect(s,j,s in(200,201),f"voyage créé id={voyage_id}")
    pause(3, "voyage en_attente visible dans Admin → Voyages")

    # ==== ÉTAPE 4 : ADMIN APPROUVE VOYAGE ====
    log(f"\n✅ [4/11] Admin approuve le voyage #{voyage_id}")
    s,j = post(f"{BASE}/api/admin/voyages/{voyage_id}/statut", {"statut":"approuve"}, token=admin_tok)
    expect(s,j,s==200,f"voyage #{voyage_id} approuvé")
    pause(3, "voyage approuvé")

    # ==== ÉTAPE 5 : TRANSPORTEUR RÉSERVE LE COLIS ====
    log(f"\n🎫 [5/11] Transporteur réserve le colis sur son voyage")
    s,j = post(f"{BASE}/api/reservations", {"colis_id":colis_id,"voyage_id":voyage_id}, token=transp_tok)
    s2,j2 = get(f"{BASE}/api/colis/{colis_id}/reservations", token=client_tok)
    res_id = next((r["id"] for r in j2.get("data",[]) if r.get("voyage_id")==voyage_id), None)
    expect(s,j,s in(200,201) and res_id is not None, f"réservation créée id={res_id}")
    pause(2)

    # ==== ÉTAPE 6 : CLIENT ACCEPTE LA RÉSERVATION ====
    log(f"\n🤝 [6/11] Client accepte la réservation #{res_id}")
    s,j = post(f"{BASE}/api/reservations/{res_id}/action", {"action":"accepte"}, token=client_tok)
    expect(s,j,s==200,"réservation acceptée")
    pause(2)

    # ==== ÉTAPE 7 : PAIEMENT ====
    log(f"\n💳 [7/11] Client paie via Kkiapay (sandbox fallback)")
    # Le vrai flux Kkiapay passe directement par verify-kkiapay :
    # /payer est un endpoint legacy qui ne sert que pour carte/mobile_money
    s,j = post(f"{BASE}/api/paiements/{paiement_id}/verify-kkiapay",
               {"transactionId":f"AUTO{rnd.upper()}{stamp}","status":"SUCCESS"}, token=client_tok)
    pay_stat = j.get('data',{}).get('kkiapay_status',j.get('data',{}).get('message','?')) if isinstance(j.get('data'),dict) else '?'
    expect(s,j,s==200,f"paiement Kkiapay vérifié → {pay_stat}")
    pause(3, "paiement passe en 'payé' dans Admin → Paiements")

    # ==== ÉTAPE 8 : TRANSPORTEUR MARQUE 'En cours' ====
    log(f"\n📦 [8/11] Transporteur marque 'En cours'")
    s,j = post(f"{BASE}/api/suivi", {"colis_id":colis_id,"statut":"En cours"}, token=transp_tok)
    expect(s,j,s in(200,201),"étape 'En cours' enregistrée")
    pause(2)

    # ==== ÉTAPE 9 : TRANSPORTEUR MARQUE 'LIVRÉ' → SIGNAL ADMIN ====
    log(f"\n🚨 [9/11] Transporteur marque 'LIVRÉ' → SIGNAL ADMIN")
    s,j = post(f"{BASE}/api/suivi", {"colis_id":colis_id,"statut":"Livré"}, token=transp_tok)
    expect(s,j,s in(200,201),"📬 LIVRÉ SIGNALÉ !")

    # Vérifier stats
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    ddl = j["data"].get("demandes_livraison",0)
    pend = j["data"].get("pending_livraisons",[])
    log(f"     Stats admin: demandes_livraison = {ddl}")
    if pend:
        suivi_id = pend[0]["suivi_id"]
        log(f"     suivi_id={suivi_id}  colis={pend[0]['nom_colis']}  prix={pend[0]['prix_estime']} XOF")
    else:
        # récupérer via colis
        s2,j2 = get(f"{BASE}/api/admin/colis", token=admin_tok)
        c = next((x for x in j2["data"] if x["id"]==colis_id), None)
        suivi_id = c["demande_livraison_id"] if c else None
        log(f"     demande_livraison_id={suivi_id} (fallback depuis liste colis)")

    pause(5, "REGARDEZ LA PAGE ADMIN : la bannière / le badge / la ligne en JAUNE devraient apparaître")

    # ==== ÉTAPE 10 : ADMIN CONFIRME LA LIVRAISON ====
    log(f"\n💰 [10/11] Admin CONFIRME la livraison suivi#{suivi_id} → 95% transporteur + 5% admin")
    s,j = post(f"{BASE}/api/admin/suivi/{suivi_id}/livraison", {"decision":"confirmer"}, token=admin_tok)
    expect(s,j,s==200,"livraison confirmée")
    data = j.get("data",{})
    log(f"     Commission transporteur (95%) = {data.get('commission_transporteur')} XOF")
    log(f"     Commission admin        (5%)  = {data.get('commission_admin')} XOF")
    payout = data.get("payout")
    if payout:
        log(f"     Payout auto Kkiapay → status = {payout.get('status')}")
    else:
        log(f"     Payout auto non déclenché (numéro test ou clés sandbox) → retrait en attente")

    pause(3, "la ligne jaune disparaît, soldes mis à jour")

    # ==== ÉTAPE 11 : BILAN ====
    log(f"\n📊 [11/11] BILAN")
    s,j = get(f"{BASE}/api/admin/wallet", token=admin_tok)
    w = j.get("data",{})
    log(f"     💰 Wallet admin solde : {w.get('solde')} XOF")
    s,j = get(f"{BASE}/api/transporteur/solde", token=transp_tok)
    sd = j.get("data",{}) if isinstance(j.get("data"),dict) else {}
    if isinstance(sd, dict):
        log(f"     💸 Solde transporteur : {sd.get('solde')} XOF  (en_attente={sd.get('en_attente')}, payé={sd.get('total_paye')})")
    s,j = get(f"{BASE}/api/admin/stats", token=admin_tok)
    st1 = j["data"]
    log(f"     📊 Stats finales : colis={st1['nb_colis']} voyage={st1['nb_voyages']} "
        f"demandes_livraison={st1['demandes_livraison']} retraits_en_attente={st1.get('retraits_en_attente')}")

    log("\n"+"="*65)
    log("🎉 CYCLE 100% AUTOMATIQUE TERMINÉ AVEC SUCCÈS")
    log(f"   colis=#{colis_id} voyage=#{voyage_id} réservation=#{res_id} paiement=#{paiement_id} suivi=#{suivi_id}")
    log("="*65)

if __name__ == "__main__":
    main()
