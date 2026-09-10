"""Endpoints manquants : colis_show enrichi, retraits transporteur (GET liste),
verify-kkiapay, DELETE contact-messages."""
import uuid
from decimal import Decimal

from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated, AllowAny
from rest_framework.request import Request

from accounts.models import User
from core.pagination import paginate_queryset
from core.permissions import IsAdmin
from core.responses import api_success, api_error

from .models import (
    Colis, ContactMessage, Paiement, Reservation,
    Retrait, SuiviColis, Transporteur, Voyage,
)


# ---------- Colis detail ----------
@api_view(["GET"])
@permission_classes([AllowAny])
def colis_detail_public(request: Request, pk: int):
    """GET /api/colis/<id> — détail d'un colis avec étapes de suivi.
    Accessible sans auth pour que les visiteurs puissent voir un colis partagé."""
    try:
        c = Colis.objects.select_related("user").get(pk=pk)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable", 404)
    etapes = SuiviColis.objects.filter(colis=c).order_by("date_etape")
    return api_success({
        "id": c.id,
        "user_id": c.user_id,
        "nom_colis": c.nom_colis,
        "type_produit": c.type_produit,
        "nombre_produits": c.nombre_produits,
        "image_url": c.image_colis or None,
        "statut": c.statut,
        "prix_estime": float(c.prix_estime),
        "numero_suivi": c.numero_suivi,
        "poids": str(c.poids),
        "dimensions": c.dimensions or None,
        "ville": c.ville,
        "pays": c.pays,
        "adresse_depart": c.adresse_depart,
        "adresse_destination": c.adresse_destination,
        "date_limite": c.date_limite.isoformat() if c.date_limite else None,
        "date_post": c.date_creation.isoformat(),
        "nom": c.user.nom if c.user else None,
        "prenom": c.user.prenom if c.user else None,
        "owner_email": c.user.email if c.user else None,
        "owner_tel": c.user.telephone if c.user else None,
        "etapes_suivi": [
            {"statut": s.statut, "date_etape": s.date_etape.isoformat()} for s in etapes
        ],
    })


# ---------- Liste retraits d'un transporteur ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated])
def retraits_transporteur_list(request: Request):
    if request.user.role != User.ROLE_TRANSPORTEUR:
        # admin peut aussi consulter, mais le front appelle ça surtout pour le transporteur
        if request.user.role not in (User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN):
            return api_error("Seul un transporteur peut consulter ses retraits", 403)
    qs = Retrait.objects.filter(user=request.user).order_by("-date_creation")

    def _serialize(objs, many):
        out = []
        for r in objs:
            out.append({
                "id": r.id,
                "montant": float(r.montant),
                "statut": r.statut,
                "methode": r.methode,
                "numero": r.numero,
                "reference": r.reference_kkiapay or "",
                "details": r.note,
                "colis_id": None,
                "date_demande": r.date_creation.isoformat(),
                "date_traitement": None,  # on n'a pas ce champ dans le modèle actuel
            })
        return out
    res = paginate_queryset(qs, request, serializer=_serialize)
    # Si le front attend un tableau (pas paginé), retourner le tableau data.data
    return api_success(res["data"] if False else res)


# ---------- Verify Kkiapay (sandbox-friendly) ----------
@api_view(["POST"])
@permission_classes([IsAuthenticated])
def paiement_verify_kkiapay(request: Request, pk: int):
    """POST /api/paiements/<id>/verify-kkiapay
    Vérifie un paiement Kkiapay. En sandbox, le paiement est marqué payé si
    un transactionId est fourni (simulation)."""
    try:
        p = Paiement.objects.select_related("colis").get(pk=pk)
    except Paiement.DoesNotExist:
        return api_error("Paiement introuvable", 404)
    if p.colis and p.colis.user_id != request.user.id and request.user.role not in (User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN):
        return api_error("Accès refusé", 403)

    transaction_id = (request.data.get("transactionId")
                      or request.data.get("transaction_id")
                      or request.data.get("reference")
                      or "").strip()
    # En mode sandbox / sans clés Kkiapay, on marque payé si transactionId présent
    from django.conf import settings as dj_settings
    sandbox_or_fallback = dj_settings.KKIAPAY_SANDBOX or not (
        dj_settings.KKIAPAY_PUBLIC_KEY and dj_settings.KKIAPAY_PRIVATE_KEY
        and dj_settings.KKIAPAY_SECRET_KEY
    )
    if sandbox_or_fallback:
        if transaction_id:
            p.statut = Paiement.STATUT_PAYE
            p.numero_transaction = transaction_id
            p.save(update_fields=["statut", "numero_transaction"])
            # Créer une étape de suivi "Payé" si pas déjà
            if p.colis:
                SuiviColis.objects.get_or_create(
                    colis=p.colis, statut="Payé",
                    defaults={"commentaire": "Paiement confirmé (sandbox)"},
                )
            return api_success({
                "message": "Paiement confirmé (sandbox)",
                "numero_transaction": transaction_id,
                "statut": p.statut,
            })
        return api_error("Transaction ID manquant", 400)
    # En production (clés réelles), il faudrait appeler l'API Kkiapay ici.
    return api_error("Vérification Kkiapay non implémentée en production (clés non sandbox)", 501)


# ---------- DELETE contact message ----------
@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def contact_delete(request: Request, pk: int):
    n, _ = ContactMessage.objects.filter(pk=pk).delete()
    if not n:
        return api_error("Message introuvable", 404)
    return api_success({"message": "Message supprimé"})


# ---------- Notifications admin (table globale — pas de destinataire FK) ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def notifications_list(request: Request):
    from .models import NotificationAdmin
    qs = NotificationAdmin.objects.order_by("-date_creation")
    out = [
        {
            "id": n.id,
            "type": n.type,
            "message": n.message,
            "lu": n.lu,
            "colis_id": n.colis_id,
            "date_creation": n.date_creation.isoformat(),
        } for n in qs
    ]
    return api_success(out)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def notifications_read(request: Request, pk: int):
    from .models import NotificationAdmin
    n = NotificationAdmin.objects.filter(pk=pk).first()
    if not n:
        return api_error("Notification introuvable", 404)
    n.lu = True
    n.save(update_fields=["lu"])
    return api_success({"message": "Marquée comme lue"})


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def notifications_read_all(request: Request):
    from .models import NotificationAdmin
    NotificationAdmin.objects.filter(lu=False).update(lu=True)
    return api_success({"message": "Toutes marquées comme lues"})


# ---------- GET /api/admins + GET /api/roles (helpers frontend) ----------
@api_view(["GET"])
@permission_classes([AllowAny])
def roles_list(request: Request):
    return api_success({
        "roles": [
            {"value": "client", "label": "Client"},
            {"value": "transporteur", "label": "Transporteur"},
            {"value": "admin", "label": "Admin"},
            {"value": "super_admin", "label": "Super Admin"},
        ]
    })


# ---------- GET /api/transporteur/retraits doit retourner une liste (TransporteurStats) ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated])
def retraits_transporteur_list_plain(request: Request):
    """Même logique que retraits_transporteur_list mais retourne une liste plate
    (sans pagination), pour /api/transporteur/retraits utilisé par TransporteurStats."""
    if request.user.role != User.ROLE_TRANSPORTEUR:
        if request.user.role not in (User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN):
            return api_error("Accès refusé", 403)
    qs = Retrait.objects.filter(user=request.user).order_by("-date_creation")
    out = []
    for r in qs:
        out.append({
            "id": r.id,
            "montant": float(r.montant),
            "statut": r.statut,
            "methode": r.methode,
            "numero": r.numero,
            "reference": r.reference_kkiapay or "",
            "details": r.note,
            "colis_id": None,
            "date_demande": r.date_creation.isoformat(),
            "date_traitement": None,
        })
    return api_success(out)
