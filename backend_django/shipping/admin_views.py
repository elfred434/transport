"""Vues admin / super_admin — réécriture propre des endpoints qui cassaient en 500."""
from decimal import Decimal

from django.conf import settings as dj_settings
from django.db.models import Count, Q, Sum
from django.db import transaction
from rest_framework import status
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated
from rest_framework.request import Request

from accounts.models import User
from core.pagination import paginate_queryset
from core.permissions import IsAdmin
from core.responses import api_success, api_error

from .models import (
    Avis, Colis, ContactMessage, NotificationAdmin, Paiement, Reservation,
    Retrait, SuiviColis, Transporteur, Voyage, WalletAdmin,
)
from .serializers import (
    AvisSerializer, ColisSerializer, PaiementSerializer, RetraitSerializer,
    VoyageSerializer,
)


# ---------- Stats ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def stats(request: Request):
    total_clients = User.objects.filter(role=User.ROLE_CLIENT).count()
    total_transporteurs = User.objects.filter(role=User.ROLE_TRANSPORTEUR).count()
    total_colis = Colis.objects.count()
    colis_en_attente = Colis.objects.filter(statut=Colis.STATUT_ATTENTE).count()
    colis_approuves = Colis.objects.filter(statut=Colis.STATUT_APPROUVE).count()
    total_voyages = Voyage.objects.count()
    total_paiements = Paiement.objects.filter(statut=Paiement.STATUT_PAYE).count()
    chiffre_affaires = (
        Paiement.objects.filter(statut=Paiement.STATUT_PAYE).aggregate(s=Sum("montant"))["s"]
        or Decimal("0")
    )
    w = WalletAdmin.objects.get_or_create(pk=1)[0]
    return api_success({
        "utilisateurs": {
            "clients": total_clients,
            "transporteurs": total_transporteurs,
            "total": total_clients + total_transporteurs,
        },
        "colis": {
            "total": total_colis,
            "en_attente": colis_en_attente,
            "approuves": colis_approuves,
        },
        "voyages": {"total": total_voyages},
        "paiements": {"total": total_paiements, "chiffre_affaires": str(chiffre_affaires)},
        "wallet": {"solde": str(w.solde), "total_genere": str(w.total_genere)},
    })


# ---------- Users CRUD ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def users_list(request: Request):
    qs = User.objects.all()
    role = request.query_params.get("role")
    if role in (User.ROLE_CLIENT, User.ROLE_TRANSPORTEUR, User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN):
        qs = qs.filter(role=role)
    search = request.query_params.get("search", "")
    if search:
        qs = qs.filter(
            Q(nom__icontains=search) | Q(prenom__icontains=search)
            | Q(email__icontains=search) | Q(telephone__icontains=search)
        )
    res = paginate_queryset(
        qs.order_by("-date_creation"), request,
        serializer=lambda objs, many: [
            {
                "id": u.id, "email": u.email, "nom": u.nom, "prenom": u.prenom,
                "telephone": u.telephone, "role": u.role,
                "date_creation": u.date_creation.isoformat(),
            }
            for u in objs
        ],
    )
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def user_create(request: Request):
    from accounts.serializers import UserRegisterSerializer
    ser = UserRegisterSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    role = request.data.get("role", User.ROLE_CLIENT)
    if role not in (User.ROLE_CLIENT, User.ROLE_TRANSPORTEUR, User.ROLE_ADMIN):
        role = User.ROLE_CLIENT
    u = ser.save(role=role)
    return api_success({"id": u.id, "email": u.email, "role": u.role}, status_code=201)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def user_update_role(request: Request, pk: int):
    role = request.data.get("role")
    if role not in (User.ROLE_CLIENT, User.ROLE_TRANSPORTEUR, User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN):
        return api_error("Rôle invalide", 422)
    try:
        u = User.objects.get(pk=pk)
    except User.DoesNotExist:
        return api_error("Utilisateur introuvable", 404)
    u.role = role
    u.save(update_fields=["role"])
    return api_success({"message": "Rôle mis à jour", "role": role})


@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def user_delete(request: Request, pk: int):
    try:
        u = User.objects.get(pk=pk)
    except User.DoesNotExist:
        return api_error("Utilisateur introuvable", 404)
    if u.is_super and u.id == request.user.id:
        return api_error("Impossible de supprimer votre propre compte super admin", 400)
    u.delete()
    return api_success({"message": "Utilisateur supprimé"})


# ---------- Colis admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def colis_list(request: Request):
    qs = Colis.objects.select_related("user").all()
    statut = request.query_params.get("statut")
    if statut in (Colis.STATUT_ATTENTE, Colis.STATUT_APPROUVE, Colis.STATUT_REFUSE):
        qs = qs.filter(statut=statut)
    search = request.query_params.get("search", "")
    if search:
        qs = qs.filter(
            Q(nom_colis__icontains=search) | Q(numero_suivi__icontains=search)
            | Q(ville__icontains=search)
        )
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=ColisSerializer)
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def colis_statut(request: Request, pk: int):
    statut = (request.data.get("statut") or "").strip()
    if statut not in ("en_attente", "approuve", "refuse"):
        return api_error("Statut invalide", 422)
    try:
        c = Colis.objects.get(pk=pk)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable", 404)
    c.statut = statut
    c.save(update_fields=["statut"])
    # Notif lue
    NotificationAdmin.objects.filter(colis=c, type="colis").update(lu=True)
    return api_success({"message": f"Statut du colis mis à jour ({statut})"})


@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def colis_delete(request: Request, pk: int):
    n, _ = Colis.objects.filter(pk=pk).delete()
    if not n:
        return api_error("Colis introuvable", 404)
    return api_success({"message": "Colis supprimé"})


# ---------- Voyages admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def voyages_list(request: Request):
    qs = Voyage.objects.select_related("user").all()
    statut = request.query_params.get("statut")
    if statut in (Colis.STATUT_ATTENTE, Colis.STATUT_APPROUVE, Colis.STATUT_REFUSE):
        qs = qs.filter(statut=statut)
    res = paginate_queryset(qs.order_by("-date_post" if False else "-date_depart"), request, serializer=VoyageSerializer)
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def voyage_statut(request: Request, pk: int):
    statut = (request.data.get("statut") or "").strip()
    if statut not in ("en_attente", "approuve", "refuse"):
        return api_error("Statut invalide", 422)
    try:
        v = Voyage.objects.get(pk=pk)
    except Voyage.DoesNotExist:
        return api_error("Voyage introuvable", 404)
    v.statut = statut
    v.save(update_fields=["statut"])
    return api_success({"message": f"Statut du voyage mis à jour ({statut})"})


@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def voyage_delete(request: Request, pk: int):
    n, _ = Voyage.objects.filter(pk=pk).delete()
    if not n:
        return api_error("Voyage introuvable", 404)
    return api_success({"message": "Voyage supprimé"})


# ---------- Livraisons (décisions) ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def livraisons_list(request: Request):
    """Liste des demandes de livraison en attente (Livré posé par transporteur, pas encore confirmé)."""
    qs = SuiviColis.objects.filter(demande_livraison=True).select_related("colis").order_by("-date_etape")
    res = paginate_queryset(
        qs, request,
        serializer=lambda objs, many: [
            {
                "id": s.id,
                "suivi_id": s.id,
                "colis_id": s.colis_id,
                "numero_suivi": s.colis.numero_suivi,
                "nom_colis": s.colis.nom_colis,
                "statut": s.statut,
                "demande_livraison": s.demande_livraison,
                "date_etape": s.date_etape.isoformat(),
            }
            for s in objs
        ],
    )
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def livraison_decision(request: Request, pk: int):
    """decision=confirmer|refuser"""
    decision = (request.data.get("decision") or "").strip()
    try:
        etape = SuiviColis.objects.select_related("colis").get(pk=pk, demande_livraison=True)
    except SuiviColis.DoesNotExist:
        return api_error("Demande de livraison introuvable", 404)

    # Marque les notifs liées comme lues
    NotificationAdmin.objects.filter(colis=etape.colis, type="livraison").update(lu=True)

    if decision != "confirmer":
        etape.demande_livraison = False
        etape.save(update_fields=["demande_livraison"])
        return api_success({"message": "Livraison refusée"})

    # === Confirmation: calcul de commission 95/5 ===
    with transaction.atomic():
        etape.confirme_par_admin = True
        etape.demande_livraison = False
        etape.save(update_fields=["confirme_par_admin", "demande_livraison"])

        # Termine la réservation
        Reservation.objects.filter(
            colis=etape.colis, statut=Reservation.STATUT_ACCEPTE
        ).update(statut=Reservation.STATUT_TERMINE)

        # Récupère réservation acceptée
        resa = Reservation.objects.filter(
            colis=etape.colis, statut=Reservation.STATUT_TERMINE
        ).select_related("voyage__user", "colis").first()
        if resa:
            montant = etape.colis.prix_estime or Decimal("0")
            commission_plateforme = (montant * Decimal(str(dj_settings.COMMISSION_PLATEFORME))).quantize(Decimal("1"))
            commission_transporteur = montant - commission_plateforme

            t, _ = Transporteur.objects.get_or_create(user=resa.voyage.user)
            t.solde += commission_transporteur
            t.save(update_fields=["solde"])

            w, _ = WalletAdmin.objects.get_or_create(pk=1)
            w.solde += commission_plateforme
            w.total_genere += commission_plateforme
            w.save(update_fields=["solde", "total_genere"])
        else:
            commission_plateforme = Decimal("0")
            commission_transporteur = Decimal("0")

    return api_success({
        "message": "Livraison confirmée, commission versée",
        "commission_plateforme": str(commission_plateforme),
        "commission_transporteur": str(commission_transporteur),
        "commission_admin": str(commission_plateforme),
    })


@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def suivi_delete(request: Request, pk: int):
    n, _ = SuiviColis.objects.filter(pk=pk).delete()
    if not n:
        return api_error("Étape de suivi introuvable", 404)
    return api_success({"message": "Étape supprimée"})


# ---------- Paiements admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def paiements_list(request: Request):
    qs = Paiement.objects.select_related("colis", "user").all()
    statut = request.query_params.get("statut")
    if statut:
        qs = qs.filter(statut=statut)
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=PaiementSerializer)
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def paiement_statut(request: Request, pk: int):
    statut = (request.data.get("statut") or "").strip()
    if statut not in (Paiement.STATUT_ATTENTE, Paiement.STATUT_PAYE, Paiement.STATUT_ECHOUE, Paiement.STATUT_REMBOURSE):
        return api_error("Statut invalide", 422)
    try:
        p = Paiement.objects.get(pk=pk)
    except Paiement.DoesNotExist:
        return api_error("Paiement introuvable", 404)
    p.statut = statut
    p.save(update_fields=["statut"])
    return api_success({"message": f"Paiement mis à jour ({statut})"})


# ---------- Transporteurs admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def transporteurs_list(request: Request):
    qs = Transporteur.objects.select_related("user").all()
    res = paginate_queryset(
        qs.order_by("-date_creation"), request,
        serializer=lambda objs, many: [
            {
                "id": t.id,
                "user_id": t.user_id,
                "nom": t.user.nom, "prenom": t.user.prenom,
                "email": t.user.email, "telephone": t.user.telephone,
                "compagnie": t.compagnie, "ville": t.ville, "pays": t.pays,
                "vehicule": t.vehicule,
                "solde": str(t.solde),
            }
            for t in objs
        ],
    )
    return api_success(res)


@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def transporteur_delete(request: Request, pk: int):
    n, _ = Transporteur.objects.filter(pk=pk).delete()
    if not n:
        return api_error("Transporteur introuvable", 404)
    return api_success({"message": "Fiche transporteur supprimée"})


# ---------- Avis admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def avis_list(request: Request):
    qs = Avis.objects.select_related("user", "colis", "transporteur").all()
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=AvisSerializer)
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def avis_statut(request: Request, pk: int):
    statut = (request.data.get("statut") or "").strip()
    if statut not in ("approuve", "refuse"):
        return api_error("Statut invalide", 422)
    n = Avis.objects.filter(pk=pk).update(statut=statut)
    if not n:
        return api_error("Avis introuvable", 404)
    return api_success({"message": "Statut avis mis à jour"})


@api_view(["DELETE"])
@permission_classes([IsAuthenticated, IsAdmin])
def avis_delete(request: Request, pk: int):
    n, _ = Avis.objects.filter(pk=pk).delete()
    if not n:
        return api_error("Avis introuvable", 404)
    return api_success({"message": "Avis supprimé"})


# ---------- Contact messages ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def contact_messages(request: Request):
    qs = ContactMessage.objects.all()
    res = paginate_queryset(qs, request, serializer=lambda objs, many: [
        {"id": m.id, "nom": m.nom, "email": m.email, "sujet": m.sujet,
         "message": m.message, "lu": m.lu, "date_creation": m.date_creation.isoformat()}
        for m in objs
    ])
    return api_success(res)


# ---------- Retraits admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def retraits_list(request: Request):
    qs = Retrait.objects.select_related("user").all()
    statut = request.query_params.get("statut")
    if statut:
        qs = qs.filter(statut=statut)
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=RetraitSerializer)
    return api_success(res)


def _retrait_payer_impl(request, pk):
    """Logique payout, appelable depuis d'autres vues."""
    try:
        r = Retrait.objects.select_related("user").get(pk=pk, statut=Retrait.STATUT_DEMANDE)
    except Retrait.DoesNotExist:
        return api_error("Retrait introuvable ou déjà payé", 404)
    with transaction.atomic():
        r.statut = Retrait.STATUT_PAYE
        r.reference_kkiapay = request.data.get("reference", "PAYOUT-" + str(r.id))
        r.note = request.data.get("note", "") or r.note
        r.save(update_fields=["statut", "reference_kkiapay", "note"])
        t, _ = Transporteur.objects.get_or_create(user=r.user)
        t.solde_en_attente -= r.montant
        t.total_paye += r.montant_net
        t.save(update_fields=["solde_en_attente", "total_paye"])
        w = WalletAdmin.objects.get_or_create(pk=1)[0]
        w.solde -= r.montant_net
        w.save(update_fields=["solde"])
    return api_success({
        "message": "Retrait payé (payout effectué)",
        "retrait": RetraitSerializer(r).data,
    })


def _retrait_rejeter_impl(request, pk):
    try:
        r = Retrait.objects.get(pk=pk, statut=Retrait.STATUT_DEMANDE)
    except Retrait.DoesNotExist:
        return api_error("Retrait introuvable", 404)
    with transaction.atomic():
        r.statut = Retrait.STATUT_REJETE
        r.note = request.data.get("note", "")
        r.save(update_fields=["statut", "note"])
        t, _ = Transporteur.objects.get_or_create(user=r.user)
        t.solde_en_attente -= r.montant
        t.solde += r.montant
        t.save(update_fields=["solde_en_attente", "solde"])
    return api_success({"message": "Retrait rejeté", "retrait": RetraitSerializer(r).data})


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def retrait_decision(request: Request, pk: int):
    decision = (request.data.get("decision") or "").strip()
    if decision in ("paye", "payer", "confirmer", "accepter"):
        return _retrait_payer_impl(request, pk)
    if decision in ("rejete", "refuser", "rejetter"):
        return _retrait_rejeter_impl(request, pk)
    return api_error("Décision invalide (paye|rejete)", 422)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def retrait_payer(request: Request, pk: int):
    return _retrait_payer_impl(request, pk)


# ---------- Wallet admin ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def wallet_admin(request: Request):
    w = _wallet()
    return api_success({
        "solde": str(w.solde) if w.solde is not None else "0",
        "total_genere": str(w.total_genere) if w.total_genere is not None else "0",
    })


def _wallet():
    return WalletAdmin.objects.get_or_create(pk=1)[0]


# ---------- Kkiapay status ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def kkiapay_status(request: Request):
    from django.conf import settings as s
    return api_success({
        "sandbox": s.KKIAPAY_SANDBOX,
        "configured": bool(s.KKIAPAY_PUBLIC_KEY and s.KKIAPAY_PRIVATE_KEY and s.KKIAPAY_SECRET_KEY),
        "public_key_present": bool(s.KKIAPAY_PUBLIC_KEY),
        "skip_ssl_verify": s.KKIAPAY_SKIP_SSL_VERIFY,
    })
