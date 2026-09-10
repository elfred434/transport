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
    return api_success({
        "paiements": items,
        "stats": {
            "montant_total": montant_total,
            "montant_paye": montant_paye,
            "montant_en_attente": montant_total - montant_paye,
            "nb_payes": nb_payes,
            "nb_en_attente": nb_en_attente,
        },
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
