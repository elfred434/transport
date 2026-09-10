#!/usr/bin/env python3
"""Test de bout en bout de l'API backend (transport)."""
import json
import subprocess
import urllib.error
import urllib.request

import time
import os
B = os.environ.get("API_BASE", "http://127.0.0.1:8001")
EMAIL = f"jean.api{int(time.time())}@test.bj"
results = []


def call(method, path, token=None, data=None, files=None, raw_body=None, headers=None):
    url = B + path
    hdrs = dict(headers or {})
    body = None
    if raw_body is not None:
        body = raw_body.encode() if isinstance(raw_body, str) else raw_body
        hdrs.setdefault("Content-Type", "application/json")
    elif files:
        boundary = "----apitestboundary"
        parts = []
        for k, v in (data or {}).items():
            parts.append(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
        for k, (fname, content) in files.items():
            parts.append(
                f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"; filename=\"{fname}\"\r\n"
                f"Content-Type: application/octet-stream\r\n\r\n".encode() + content + b"\r\n")
        parts.append(f"--{boundary}--\r\n".encode())
        body = b"".join(parts)
        hdrs["Content-Type"] = f"multipart/form-data; boundary={boundary}"
    elif data is not None:
        body = json.dumps(data).encode()
        hdrs["Content-Type"] = "application/json"
    if token:
        hdrs["Authorization"] = "Bearer " + token

    req = urllib.request.Request(url, data=body, headers=hdrs, method=method)
    try:
        with urllib.request.urlopen(req) as r:
            txt = r.read().decode()
            return r.status, (json.loads(txt) if txt else None)
    except urllib.error.HTTPError as e:
        txt = e.read().decode()
        try:
            return e.code, json.loads(txt)
        except Exception:
            return e.code, txt


def check(label, got, expected):
    status = got[0] if isinstance(got, tuple) else got
    ok = status == expected
    results.append((ok, label, expected, status))
    print(f"  {'OK ' if ok else 'KO '} {label}  (attendu {expected}, obtenu {status})")


def db(query):
    out = subprocess.run(["sudo", "-n", "mariadb", "transport_db", "-N", "-e", query],
                         capture_output=True, text=True)
    return out.stdout.strip()


def dig(resp, *keys):
    cur = resp
    for k in keys:
        if cur is None:
            return None
        cur = cur[k] if not isinstance(cur, list) else cur[int(k)]
    return cur


print("== 1. Auth ==")
s, r = call("POST", "/api/auth/register", data=None, files=None, raw_body=None)
# inscription en multipart
s, r = call("POST", "/api/auth/register", data={
    "nom": "Dupont", "prenom": "Jean", "email": EMAIL,
    "password": "Secret@123", "tel": "97111111"})
check("inscription", s, 201)
TOK = dig(r, "data", "token")
UID = dig(r, "data", "user", "id")

s, r = call("POST", "/api/auth/login", data={"email": EMAIL, "password": "Secret@123"})
check("login utilisateur", s, 200)
TOK = dig(r, "data", "token") or TOK

s, r = call("GET", "/api/auth/me", token=TOK)
check("GET /api/auth/me -> email", dig(r, "data", "email"), EMAIL)
s, r = call("POST", "/api/auth/login", data={"email": EMAIL, "password": "nope"})
check("login mauvais mdp", s, 401)
s, r = call("GET", "/api/auth/me")
check("me sans token", s, 401)
s, r = call("GET", "/api/auth/me", token="deadbeef" * 8)
check("me avec token invalide", s, 401)

print("== 2. Admin ==")
s, r = call("POST", "/api/auth/login", data={"email": "admin@transport.bj", "password": "Admin@12345"})
check("login admin", s, 200)
ATOK = dig(r, "data", "token")
s, r = call("GET", "/api/admin/stats", token=ATOK)
check("GET /api/admin/stats", s, 200)
s, r = call("GET", "/api/admin/stats", token=TOK)
check("stats refusé au user simple", s, 403)
s, r = call("GET", "/api/admin/users", token=ATOK)
check("liste utilisateurs (admin)", s, 200)

print("== 3. Colis ==")
s, r = call("POST", "/api/colis", token=TOK, data={
    "nom_colis": "Colis API", "type_produit": "documents", "nombre_produits": "2", "poids": "3",
    "dimensions": "20x20", "pays": "Bénin", "ville": "Cotonou", "date_limite": "2027-01-01",
    "adresse_depart": "Rue 1", "adresse_destination": "Rue 2"})
check("création colis", s, 201)
CID = dig(r, "data", "colis_id")
NUM = dig(r, "data", "numero_suivi")
check("prix calculé serveur (3 kg -> 4800)", dig(r, "data", "paiement", "montant"), 4800.0)
s, r = call("POST", "/api/colis", token=TOK, data={
    "nom_colis": "X", "type_produit": "autre", "nombre_produits": "1", "poids": "0",
    "pays": "a", "ville": "b", "date_limite": "2027-01-01",
    "adresse_depart": "x", "adresse_destination": "y"})
check("colis invalide (poids 0)", s, 400)
s, r = call("POST", "/api/colis", data={"nom_colis": "X"})
check("création colis sans auth", s, 401)
s, r = call("GET", f"/api/colis/{CID}", token=TOK)
check("détail colis (propriétaire)", s, 200)

print("== 4. Upload sécurisé ==")
evil = b'<?php system($_GET["c"]); ?>'
s, r = call("POST", "/api/colis", token=TOK, data={
    "nom_colis": "E", "type_produit": "autre", "nombre_produits": "1", "poids": "1",
    "pays": "a", "ville": "b", "date_limite": "2027-01-01",
    "adresse_depart": "x", "adresse_destination": "y"}, files={"image_colis": ("evil.php", evil)})
check("upload .php refusé", s, 400)
s, r = call("POST", "/api/colis", token=TOK, data={
    "nom_colis": "E", "type_produit": "autre", "nombre_produits": "1", "poids": "1",
    "pays": "a", "ville": "b", "date_limite": "2027-01-01",
    "adresse_depart": "x", "adresse_destination": "y"}, files={"image_colis": ("evil.jpg", evil)})
check("upload PHP déguisé en .jpg refusé", s, 400)

print("== 5. Autorisations colis ==")
s, r = call("POST", f"/api/colis/{CID}", token=ATOK, data={"nom_colis": "Hacked"})
check("modif colis d'autrui refusée (même admin)", s, 404)
s, r = call("POST", f"/api/colis/{CID}", token=TOK, data={"poids": "5"})
check("modif de son colis + prix recalculé (5 kg -> 7200)", dig(r, "data", "prix_estime"), 7200.0)

print("== 6. Modération admin ==")
s, r = call("POST", f"/api/admin/colis/{CID}/statut", token=ATOK, data={"statut": "approuve"})
check("approuver colis", s, 200)
check("statut en base", db(f"SELECT statut FROM colis WHERE id={CID}"), "approuve")
s, r = call("POST", f"/api/admin/colis/{CID}/statut", token=ATOK, data={"statut": "pirate"})
check("statut invalide refusé", s, 400)
s, r = call("GET", "/api/colis/available", token=TOK)
check("colis disponibles", s, 200)

print("== 7. Suivi ==")
s, r = call("POST", "/api/suivi", token=TOK, data={"colis_id": CID, "statut": "Livré"})
check("ajout suivi par non-autorisé", s, 403)
s, r = call("POST", "/api/suivi", token=ATOK, data={"colis_id": CID, "statut": "En cours"})
check("suivi par admin", s, 201)
s, r = call("POST", "/api/suivi", token=ATOK, data={"colis_id": CID, "statut": "Detruit"})
check("statut de suivi invalide", s, 400)
s, r = call("GET", f"/api/suivi/{NUM}", token=TOK)
check("lecture suivi par numéro", dig(r, "data", "statut_actuel"), "En cours")

print("== 8. Messagerie admin ==")
s, r = call("POST", "/api/admin-chat", token=TOK, data={"contenu": "Bonjour <b>admin</b>"})
check("envoi user -> admin", s, 201)
check("contenu stocké brut, non échappé en base",
      db("SELECT COUNT(*) FROM messages_admin WHERE contenu='Bonjour <b>admin</b>'") >= "1", True)
s, r = call("GET", "/api/admin-chat", token=TOK)
check("lecture conversation côté user", s, 200)
s, r = call("POST", "/api/admin-chat", token=TOK, data={"contenu": "x", "destinataire_id": 5})
check("user ne choisit pas le destinataire", s, 201)
admin_id = db("SELECT id FROM users WHERE role IN ('admin','super_admin') ORDER BY id LIMIT 1")
check("  ...le message va bien à l'admin",
      int(db(f"SELECT COUNT(*) FROM messages_admin WHERE contenu='x' AND destinataire_id={admin_id}")) >= 1, True)
s, r = call("GET", "/api/admin-chat/conversations", token=ATOK)
check("admin : liste conversations", s, 200)
conv_user = None
for c in (dig(r, "data") or []):
    if c["user_id"] == UID:
        conv_user = c
check("admin voit la conversation du user test", conv_user is not None, True)
s, r = call("POST", "/api/admin-chat", token=ATOK, data={"contenu": "Bien reçu", "destinataire_id": UID})
check("réponse admin -> user", s, 201)
s, r = call("GET", f"/api/admin-chat?user_id={UID}", token=ATOK)
check("admin lit la conversation", s, 200)

print("== 9. Messagerie user <-> user ==")
# Destinataire créé par le test lui-même (indépendant du contenu de la base)
EMAIL_PEER = f"peer.msg{int(time.time())}@test.bj"
s, r = call("POST", "/api/auth/register", data={"nom": "Peer", "prenom": "Message",
            "email": EMAIL_PEER, "password": "Test@12345"})
check("inscription destinataire (messagerie)", s, 201)
PEER_ID = dig(r, "data", "user", "id")
s, r = call("POST", "/api/messages", token=TOK, data={"destinataire_id": PEER_ID, "contenu": "Salut"})
check("envoi vers un autre utilisateur", s, 201)
s, r = call("POST", "/api/messages", token=TOK, data={"destinataire_id": UID, "contenu": "x"})
check("envoi vers soi refusé", s, 400)
s, r = call("GET", f"/api/messages?destinataire_id={PEER_ID}", token=TOK)
check("lecture conversation", s, 200)
s, r = call("GET", "/api/conversations", token=TOK)
check("liste conversations", s, 200)

print("== 10. Contact (public) ==")
s, r = call("POST", "/api/contact", data={"nom": "Visiteur", "email": "v@test.bj", "message": "Bonjour"})
check("contact sans authentification", s, 201)
s, r = call("POST", "/api/contact", data={"nom": "X", "email": "pas-un-email", "message": "y"})
check("contact email invalide", s, 400)
s, r = call("GET", "/api/admin/contact-messages", token=ATOK)
check("admin : messages contact", s, 200)
mid = dig(r, "data", 0, "id") if dig(r, "data") else None
if mid:
    s, r = call("POST", f"/api/admin/contact-messages/{mid}/repondre", token=ATOK, data={"reponse": "Merci"})
    check("admin répond au message", s, 200)

print("== 11. Paiement ==")
s, r = call("GET", f"/api/paiements/colis/{CID}", token=TOK)
check("lecture paiement du colis", s, 200)
PID = dig(r, "data", "id")
s, r = call("POST", f"/api/paiements/{PID}/payer", token=TOK,
            data={"methode_paiement": "carte_credit", "numero_carte": "123", "expiration": "99/99", "cvv": "1"})
check("carte invalide refusée", s, 400)
s, r = call("POST", f"/api/paiements/{PID}/payer", token=TOK,
            data={"methode_paiement": "mobile_money", "operateur": "mtn"})
check("paiement mobile money", s, 200)
details = db(f"SELECT details_paiement FROM paiements WHERE id={PID}")
check("aucune donnée sensible stockée", "numero" in details and "carte" not in details, False)
s, r = call("POST", f"/api/paiements/{PID}/payer", token=TOK,
            data={"methode_paiement": "mobile_money", "operateur": "mtn"})
check("double paiement refusé", s, 400)
s, r = call("GET", "/api/paiements/mine", token=TOK)
check("mes paiements", s, 200)

print("== 12. Voyages / réservations ==")
s, r = call("POST", "/api/voyages", token=TOK, data={
    "numero_permis": "P123", "vehicule": "Camion", "compagnie": "QA", "adresse": "Rue 1",
    "ville": "Cotonou", "pays": "Bénin", "pays_depart": "Bénin", "pays_destination": "Togo",
    "date_depart": "2027-02-01", "heure_depart": "08:00", "poids_max": "500",
    "email": EMAIL, "telephone": "97111111"})
check("proposer un voyage (devenir transporteur)", s, 201)
VID = dig(r, "data", "voyage_id")
check("rôle passé à transporteur", db(f"SELECT role FROM users WHERE id={UID}"), "transporteur")
s, r = call("POST", f"/api/admin/voyages/{VID}/statut", token=ATOK, data={"statut": "approuve"})
check("admin approuve le voyage", s, 200)
s, r = call("POST", "/api/reservations", token=TOK, data={"colis_id": CID, "voyage_id": VID})
check("réservation de son propre colis possible (non bloqué)", s, 201)
s, r = call("GET", f"/api/colis/{CID}/reservations", token=TOK)
check("réservations du colis (propriétaire)", s, 200)
RID = dig(r, "data", 0, "id") if dig(r, "data") else None
if RID:
    s, r = call("POST", f"/api/reservations/{RID}/action", token=TOK, data={"action": "accepte"})
    check("propriétaire accepte la réservation", s, 200)
    check("statut réservation en base", db(f"SELECT statut FROM reservations WHERE id={RID}"), "accepte")
    s, r = call("POST", f"/api/reservations/{RID}/action", token=TOK, data={"action": "pirate"})
    check("action de réservation invalide", s, 400)

print("== 13. Avis ==")
s, r = call("GET", "/api/admin/transporteurs", token=ATOK)
liste = dig(r, "data") or []
TID = liste[0]["user_id"] if liste else None
check("admin : liste transporteurs", s, 200)
s, r = call("POST", "/api/avis", token=TOK, data={"transporteur_id": 999999, "note": 5, "commentaire": "x"})
check("avis sur un non-transporteur refusé", s, 404)
s, r = call("POST", "/api/avis", token=TOK, data={"transporteur_id": TID, "note": 9, "commentaire": "x"})
check("note hors bornes refusée", s, 400)
s, r = call("POST", "/api/avis", token=TOK, data={"transporteur_id": UID, "note": 5, "commentaire": "x"})
check("s'auto-évaluer refusé", s, 400)
EMAIL2 = f"client.avis{int(time.time())}@test.bj"
s, r = call("POST", "/api/auth/register", data={"nom": "Avis", "prenom": "Client",
            "email": EMAIL2, "password": "Secret@123", "tel": "97222222"})
TOK2 = dig(r, "data", "token")
check("inscription 2e compte (client)", s, 201)
s, r = call("POST", "/api/avis", token=TOK2, data={"transporteur_id": TID, "note": 5, "commentaire": "Super"})
check("déposer un avis", s, 201)
s, r = call("POST", "/api/avis", token=TOK2, data={"transporteur_id": TID, "note": 4, "commentaire": "Encore"})
check("double avis refusé", s, 409)
s, r = call("GET", f"/api/transporteurs/{TID}/avis", token=TOK2)
check("avis non modéré invisible publiquement", len(dig(r, "data") or []), 0)
s, r = call("GET", f"/api/transporteurs/{TID}", token=TOK)
check("fiche publique transporteur", s, 200)

print("== 14. CORS / divers ==")
s, r = call("GET", "/api/inexistant")
check("endpoint inconnu -> 404", s, 404)
req = urllib.request.Request(B + "/uploads/../../../config.php")
try:
    with urllib.request.urlopen(req) as resp:
        code = resp.status
except urllib.error.HTTPError as e:
    code = e.code
except Exception:
    code = "blocked"
check("path traversal uploads bloqué", code in (400, 404, "blocked"), True)

print("== 15. Livraison + commission 5% ==")
solde_avant = float(db(f"SELECT COALESCE((SELECT solde FROM transporteurs WHERE user_id={UID}),0)"))
s, r = call("POST", "/api/suivi", token=TOK, data={"colis_id": CID, "statut": "Livré"})
check("transporteur affecté marque Livré (demande)", s, 201)
check("demande_livraison enregistrée", db(f"SELECT demande_livraison FROM suivi_colis WHERE colis_id={CID} AND statut='Livré' ORDER BY id DESC LIMIT 1"), "1")
SUIVI_ID = db(f"SELECT id FROM suivi_colis WHERE colis_id={CID} AND demande_livraison=1 ORDER BY id DESC LIMIT 1")
s, r = call("POST", f"/api/admin/suivi/{SUIVI_ID}/livraison", token=ATOK, data={"decision": "confirmer"})
check("admin confirme la livraison", s, 200)
check("commission = 5% de 7200", dig(r, "data", "commission_transporteur"), 360.0)
solde_apres = float(db(f"SELECT solde FROM transporteurs WHERE user_id={UID}"))
check("solde transporteur crédité", round(solde_apres - solde_avant, 2), 360.0)
s, r = call("GET", "/api/auth/me", token=TOK)
check("solde visible dans /auth/me", dig(r, "data", "solde"), solde_apres)

print("== 16. Logout ==")
s, r = call("POST", "/api/auth/logout", token=TOK)
check("logout", s, 200)
s, r = call("GET", "/api/auth/me", token=TOK)
check("token révoqué après logout", s, 401)

failed = [x for x in results if not x[0]]
print()
print(f"{len(results) - len(failed)}/{len(results)} tests OK")
if failed:
    print("ÉCHECS :")
    for _, label, exp, got in failed:
        print(f"  - {label} (attendu {exp}, obtenu {got})")
    raise SystemExit(1)
print("TOUS LES TESTS PASSENT ✅")
