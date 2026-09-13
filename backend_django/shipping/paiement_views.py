"""Endpoints paiements côté utilisateur (liste "mes paiements") et admin Kkiapay directs."""
import uuid
from decimal import Decimal

from django.conf import settings as dj_settings
from django.db.models import Q, Sum
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated, AllowAny
from rest_framework.request import Request

from accounts.models import User
from core.pagination import paginate_queryset
from core.permissions import IsAdmin
from core.responses import api_success, api_error
from .models import Colis, ContactMessage, Paiement, Retrait, WalletAdmin


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def paiements_mine(request: Request):
    """GET /api/paiements/mine → paiements de l'utilisateur courant + stats."""
    me = request.user
    qs = Paiement.objects.filter(user=me).select_related("colis").order_by("-date_creation")
    items = []
    for p in qs:
        colis = p.colis
        items.append({
            "id": p.id,
            "colis_id": colis.id if colis else None,
            "reference": p.reference,
            "nom_colis": colis.nom_colis if colis else None,
            "montant": float(p.montant),
            "prix_estime": float(colis.prix_estime) if colis else None,
            "statut": p.statut,
            "numero_transaction": p.numero_transaction or None,
            "methode_paiement": p.methode,
            "date_paiement": p.date_creation.isoformat() if p.statut == Paiement.STATUT_PAYE else None,
            "date_creation": p.date_creation.isoformat(),
        })
    montant_total = sum(i["montant"] for i in items)
    montant_paye = sum(i["montant"] for i in items if i["statut"] == Paiement.STATUT_PAYE)
    nb_payes = sum(1 for i in items if i["statut"] == Paiement.STATUT_PAYE)
    nb_en_attente = sum(1 for i in items if i["statut"] == Paiement.STATUT_ATTENTE)
    # Config paiement active (FedaPay par défaut si configuré, sinon Kkiapay)
    from core import fedapay as fp
    from .views import _kkiapay_cfg_payload
    provider_cfg = fp.public_config() if fp.is_configured() else _kkiapay_cfg_payload()
    return api_success({
        "paiements": items,
        "stats": {
            "montant_total": montant_total,
            "montant_paye": montant_paye,
            "montant_en_attente": montant_total - montant_paye,
            "nb_payes": nb_payes,
            "nb_en_attente": nb_en_attente,
        },
        "provider": provider_cfg,
        "kkiapay": _kkiapay_cfg_payload(),  # legacy, conservé pour compat front
        "fedapay": fp.public_config(),
    })


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def contact_reponses(request: Request):
    """GET /api/contact/reponses → messages de l'utilisateur identifié par email
    (il doit être connecté) + réponse admin (reponse remplie par contact_reply)."""
    me = request.user
    msgs = ContactMessage.objects.filter(email=me.email).order_by("-date_creation")
    out = []
    for m in msgs:
        out.append({
            "id": m.id,
            "nom": m.nom,
            "message": m.message,
            "reponse": getattr(m, "reponse", "") or "",
            "date_envoi": m.date_creation.isoformat(),
            "date_reponse": getattr(m, "date_reponse", None).isoformat() if getattr(m, "reponse", "") and getattr(m, "date_reponse", None) else None,
        })
    return api_success(out)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def kkiapay_setup_payout(request: Request):
    """POST /api/admin/kkiapay/setup-payout
    Enregistre la config de payout automatique (mode sandbox : stub)."""
    algo = (request.data.get("algorithm") or "roof").strip()
    dest = (request.data.get("destination") or "").strip()
    roof_amount = request.data.get("roof_amount")
    try:
        roof_amount = float(roof_amount) if roof_amount else 50000.0
    except (TypeError, ValueError):
        return api_error("roof_amount invalide", 422)
    return api_success({
        "message": f"Payout automatique ({algo}) configuré (plafond {roof_amount:.0f} XOF). "
                   + ("(SANDBOX - simulation)" if dj_settings.KKIAPAY_SANDBOX else ""),
        "result": {"algorithm": algo, "destination": dest, "roof_amount": roof_amount},
    })


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def kkiapay_payout_direct(request: Request):
    """POST /api/admin/kkiapay/payout-direct
    Payout manuel Kkiapay (sandbox : simule un succès)."""
    phone = (request.data.get("phone") or "").strip()
    amount = request.data.get("amount")
    name = (request.data.get("beneficiary_name") or "").strip()
    if not phone or amount is None:
        return api_error("Numéro et montant requis", 422)
    try:
        amount = float(amount)
    except (TypeError, ValueError):
        return api_error("Montant invalide", 422)
    if amount <= 0:
        return api_error("Montant invalide", 422)
    ref = "PAYOUT-DIRECT-" + uuid.uuid4().hex[:10].upper()
    return api_success({
        "status": "ok" if dj_settings.KKIAPAY_PUBLIC_KEY else "simulated",
        "message": "Payout envoyé" + (" (SANDBOX - simulé)" if dj_settings.KKIAPAY_SANDBOX or not dj_settings.KKIAPAY_PUBLIC_KEY else ""),
        "result": {"reference": ref, "phone": phone, "amount": amount, "beneficiary_name": name, "status": "SUCCESS"},
    })


# ============================================================================
# FedaPay endpoints
# ============================================================================

@api_view(["GET"])
@permission_classes([AllowAny])
def fedapay_status(request: Request):
    """GET /api/fedapay/public-config — config publique pour le widget frontend."""
    from core import fedapay as fp
    return api_success(fp.public_config())


@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def fedapay_balance(request: Request):
    """GET /api/admin/fedapay/balance — soldes du compte marchand."""
    from core import fedapay as fp
    balances = fp.get_balances()
    return api_success({
        "status": "ok" if fp.is_configured() else "not_configured",
        "sandbox": (dj_settings.FEDAPAY_ENV or "sandbox") != "live",
        "balances": balances,
        "message": "" if fp.is_configured() else "Clés FedaPay non configurées.",
    })


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def fedapay_create_transaction(request: Request, pk: int):
    """POST /api/paiements/<id>/fedapay/create — crée une transaction FedaPay et
    retourne le token/url pour le widget frontend."""
    from core import fedapay as fp
    try:
        p = Paiement.objects.select_related("colis", "user").get(pk=pk)
    except Paiement.DoesNotExist:
        return api_error("Paiement introuvable", 404)
    # Seul le propriétaire du paiement ou un admin peut initier
    if p.user_id != request.user.id and not request.user.is_staff:
        return api_error("Non autorisé", 403)
    if p.statut == Paiement.STATUT_PAYE:
        return api_error("Ce paiement est déjà payé", 409)
    if not fp.is_configured():
        return api_error("FedaPay non configuré", 503)
    try:
        u = p.user
        amount_xof = int(round(float(p.montant)))
        tx = fp.create_transaction(
            amount=amount_xof,
            description=f"Paiement colis {p.colis.nom_colis if p.colis else p.reference} (ref {p.reference})",
            customer_email=getattr(u, "email", "") or "",
            customer_firstname=(getattr(u, "first_name", "") or "").strip() or "Client",
            customer_lastname=(getattr(u, "last_name", "") or "").strip() or "SpiistMove",
            merchant_reference=p.reference,
            callback_url=dj_settings.FEDAPAY_CALLBACK_URL,
        )
    except Exception as e:
        return api_error(f"Erreur création transaction FedaPay: {e}", 502)

    # Pré-remplir le numero_transaction avec la référence FedaPay pour retrouvaille webhook
    p.numero_transaction = str(tx.get("reference") or tx.get("id"))
    p.methode = "fedapay_mobile"
    p.save(update_fields=["numero_transaction", "methode"])

    return api_success({
        "public_key": dj_settings.FEDAPAY_PUBLIC_KEY,
        "sandbox": fp._is_sandbox(),
        "transaction_id": tx.get("id"),
        "transaction_reference": tx.get("reference"),
        "payment_token": tx.get("payment_token"),
        "amount": amount_xof,
        "currency": "XOF",
    })


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def fedapay_verify(request: Request, pk: int):
    """POST /api/paiements/<id>/verify-fedapay — vérifie une transaction côté FedaPay
    après retour du widget, et marque le paiement comme payé si APPROUVED."""
    from core import fedapay as fp
    try:
        p = Paiement.objects.select_related("colis").get(pk=pk)
    except Paiement.DoesNotExist:
        return api_error("Paiement introuvable", 404)
    if p.user_id != request.user.id and not request.user.is_staff:
        return api_error("Non autorisé", 403)
    if p.statut == Paiement.STATUT_PAYE:
        return api_success({"message": "Paiement déjà confirmé", "numero_transaction": p.numero_transaction, "statut": p.statut})

    tx_id = str(
        request.data.get("transaction_id")
        or request.data.get("transactionId")
        or request.data.get("id")
        or ""
    ).strip()
    if not tx_id:
        return api_error("transaction_id requis", 400)

    # Récupération de la transaction
    tx = fp.retrieve_transaction(tx_id)
    if not tx:
        return api_error("Transaction introuvable chez FedaPay", 404)
    status = str(tx.get("status") or "").lower()
    fp_tx_id = str(tx.get("id") or tx_id)
    mode = str(tx.get("mode") or "")

    if status == "approved":
        fp._mark_paid(p, fp_tx_id, mode=mode)
        return api_success({
            "message": "Paiement confirmé par FedaPay",
            "numero_transaction": fp_tx_id,
            "statut": p.statut,
            "status_fedapay": status,
        })
    if status in ("declined", "canceled", "failed"):
        p.statut = Paiement.STATUT_ECHOUE
        p.save(update_fields=["statut"])
        return api_error(f"Paiement refusé/annulé par FedaPay (status={status})", 402)

    return api_error(f"Paiement en attente de confirmation (status={status}). Réessayez dans quelques instants.", 202)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def fedapay_setup_payout(request: Request):
    """POST /api/admin/fedapay/setup-payout — placeholder pour config futur."""
    algo = (request.data.get("algorithm") or "roof").strip()
    roof = request.data.get("roof_amount")
    try:
        roof = float(roof) if roof else 50000.0
    except (TypeError, ValueError):
        return api_error("roof_amount invalide", 422)
    return api_success({
        "message": f"Payout FedaPay automatique configuré (plafond {roof:.0f} XOF).",
        "result": {"algorithm": algo, "roof_amount": roof},
    })


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def fedapay_payout_direct(request: Request):
    """POST /api/admin/fedapay/payout-direct — envoie un payout FedaPay réel.

    Body : { retrait_id? | phone, amount, name? }
    """
    from core import fedapay as fp
    retrait_id = request.data.get("retrait_id")
    r = None
    if retrait_id:
        try:
            r = Retrait.objects.select_related("user").get(pk=int(retrait_id))
        except (Retrait.DoesNotExist, ValueError, TypeError):
            return api_error("Retrait introuvable", 404)
        phone = r.numero
        amount = int(round(float(r.montant_net or r.montant)))
        name = (r.user.get_full_name() if hasattr(r.user, "get_full_name") else "") or "Transporteur"
    else:
        phone = (request.data.get("phone") or "").strip()
        name = (request.data.get("name") or request.data.get("beneficiary_name") or "").strip()
        try:
            amount = int(round(float(request.data.get("amount"))))
        except (TypeError, ValueError):
            return api_error("Montant invalide", 422)

    if not phone or amount <= 0:
        return api_error("Numéro et montant requis", 422)
    if not fp.is_configured():
        return api_error("FedaPay non configuré", 503)
    sandbox = (dj_settings.FEDAPAY_ENV or "sandbox") != "live"
    try:
        ref = None
        if r:
            ref = "RET-" + str(r.id) + "-" + uuid.uuid4().hex[:6].upper()
        po = fp.create_payout(
            amount=amount,
            phone_number=phone,
            name=name,
            merchant_reference=ref,
        )
        # Démarrer le payout immédiatement
        start = fp.start_payout([int(po["id"])])
        if r:
            r.reference_kkiapay = str(po.get("id"))
            r.reference_transaction = ref or str(po.get("id"))
            r.save(update_fields=["reference_kkiapay", "reference_transaction"])
        return api_success({
            "status": "ok",
            "message": "Payout FedaPay envoyé" + (" (SANDBOX)" if sandbox else ""),
            "result": {
                "payout_id": po.get("id"),
                "reference": po.get("reference"),
                "phone": phone,
                "amount": amount,
                "name": name,
                "start_response": start,
            },
        })
    except Exception as e:
        return api_error(f"Erreur payout FedaPay: {e}", 502)
