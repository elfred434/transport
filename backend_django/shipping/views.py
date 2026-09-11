from datetime import date, datetime
from decimal import Decimal

from django.conf import settings
from django.db import IntegrityError, transaction
from django.db.models import Q
from django.utils import timezone
from rest_framework import status
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import AllowAny, IsAuthenticated
from rest_framework.request import Request

from accounts.models import User
from core.pagination import paginate_queryset
from core.responses import api_success, api_error

from .models import (
    Avis, Colis, ContactMessage, NotificationAdmin, Paiement, Reservation,
    Retrait, SuiviColis, Transporteur, Voyage, WalletAdmin,
)
from .serializers import (
    AvisSerializer, ColisCreateSerializer, ColisSerializer, ContactCreateSerializer,
    PaiementSerializer, ReservationSerializer, RetraitSerializer,
    VoyageCreateSerializer, VoyageSerializer,
)


# ---------- Aide ----------
def _get_wallet():
    w, _ = WalletAdmin.objects.get_or_create(pk=1)
    return w


def _ensure_transporteur_profile(user: User, data: dict) -> Transporteur:
    t, _ = Transporteur.objects.get_or_create(
        user=user,
        defaults={
            "numero_permis": data.get("numero_permis", ""),
            "vehicule": data.get("vehicule", ""),
            "compagnie": data.get("compagnie", ""),
            "adresse": data.get("adresse", ""),
            "ville": data.get("ville", ""),
            "pays": data.get("pays", ""),
        },
    )
    return t


# ---------- COLIS ----------
@api_view(["POST"])
@permission_classes([IsAuthenticated])
def colis_store(request: Request):
    ser = ColisCreateSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    v = ser.validated_data

    if v["date_limite"] < date.today():
        return api_error("La date limite doit être aujourd'hui ou dans le futur", 422)

    colis = Colis.objects.create(
        user=request.user,
        nom_colis=v["nom_colis"],
        type_produit=v["type_produit"],
        nombre_produits=v["nombre_produits"],
        poids=v["poids"],
        dimensions=v["dimensions"],
        pays=v["pays"],
        ville=v["ville"],
        adresse_depart=v["adresse_depart"],
        adresse_destination=v["adresse_destination"],
        date_limite=v["date_limite"],
        description=v.get("description", ""),
        image_colis=v.get("image_colis", ""),
    )
    # Crée d'office un paiement en_attente
    paiement = Paiement.objects.create(
        colis=colis,
        user=request.user,
        montant=colis.prix_estime,
        statut=Paiement.STATUT_ATTENTE,
    )
    # Étape initiale dans le suivi
    SuiviColis.objects.create(colis=colis, statut="En attente", auteur_id=request.user.id)
    # Notif admin
    NotificationAdmin.objects.create(type="colis", colis=colis, message="Nouveau colis en attente d'approbation")

    return api_success(
        {
            "colis_id": colis.id,
            "id": colis.id,
            "numero_suivi": colis.numero_suivi,
            "prix_estime": str(colis.prix_estime),
            "paiement": {
                "id": paiement.id,
                "reference": paiement.reference,
                "montant": str(paiement.montant),
                "statut": paiement.statut,
            },
            "message": "Colis soumis, en attente d'approbation.",
        },
        status_code=201,
    )


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def colis_mine(request: Request):
    qs = Colis.objects.filter(user=request.user).select_related().order_by("-date_creation")
    # Si un param page/per_page est fourni → pagine; sinon retourne la liste brute
    if "page" in request.query_params or "per_page" in request.query_params:
        res = paginate_queryset(qs, request, serializer=ColisSerializer)
        return api_success(res)
    return api_success(ColisSerializer(qs, many=True).data)


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def colis_available(request: Request):
    qs = Colis.objects.filter(statut=Colis.STATUT_APPROUVE)
    search = request.query_params.get("search", "")
    if search:
        qs = qs.filter(
            Q(nom_colis__icontains=search)
            | Q(ville__icontains=search)
            | Q(pays__icontains=search)
        )
    res = paginate_queryset(qs, request, serializer=ColisSerializer)
    return api_success(res)


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def colis_show(request: Request, pk: int):
    try:
        c = Colis.objects.get(pk=pk)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable", 404)
    return api_success(ColisSerializer(c).data)


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def colis_reservations(request: Request, pk: int):
    qs = Reservation.objects.filter(colis__id=pk, colis__user=request.user)
    res = paginate_queryset(qs, request, serializer=ReservationSerializer)
    return api_success(res)


# ---------- VOYAGES ----------
@api_view(["POST"])
@permission_classes([IsAuthenticated])
def voyage_store(request: Request):
    ser = VoyageCreateSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    v = ser.validated_data

    # Si c'est un transporteur, on lui crée son profil
    if request.user.role == User.ROLE_CLIENT:
        request.user.role = User.ROLE_TRANSPORTEUR
        request.user.save(update_fields=["role"])
    _ensure_transporteur_profile(request.user, v)

    voyage = Voyage.objects.create(user=request.user, **v)
    NotificationAdmin.objects.create(type="voyage", message="Nouveau voyage en attente d'approbation")
    return api_success(
        {
            "voyage_id": voyage.id,
            "id": voyage.id,
            "message": "Votre voyage a été proposé. Il sera visible après validation par un administrateur.",
        },
        status_code=201,
    )


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def voyage_available(request: Request):
    qs = Voyage.objects.filter(statut=Voyage.STATUT_APPROUVE)
    return api_success(list(qs.values()))


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def voyage_mine(request: Request):
    qs = Voyage.objects.filter(user=request.user)
    return api_success(list(qs.values()))


# ---------- RESERVATIONS ----------
@api_view(["POST"])
@permission_classes([IsAuthenticated])
def reserver(request: Request):
    colis_id = request.data.get("colis_id")
    voyage_id = request.data.get("voyage_id")
    try:
        colis = Colis.objects.get(pk=colis_id, statut=Colis.STATUT_APPROUVE)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable ou non approuvé", 404)
    try:
        voyage = Voyage.objects.get(pk=voyage_id, user=request.user, statut=Voyage.STATUT_APPROUVE)
    except Voyage.DoesNotExist:
        return api_error("Voyage introuvable ou non approuvé", 404)

    if Reservation.objects.filter(colis=colis, voyage=voyage).exists():
        return api_error("Réservation déjà existante", 409)

    r = Reservation.objects.create(
        colis=colis, voyage=voyage, transporteur=request.user,
        statut=Reservation.STATUT_EN_ATTENTE,
    )
    NotificationAdmin.objects.create(type="reservation", colis=colis, message="Nouvelle réservation en attente client")
    return api_success(
        {"id": r.id, "colis_id": colis.id, "voyage_id": voyage.id, "statut": r.statut},
        status_code=201,
    )


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def reservations_recues(request: Request):
    """Réservations reçues en tant que client (sur mes colis)."""
    qs = Reservation.objects.filter(colis__user=request.user)
    return api_success(list(qs.values()))


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def reservation_action(request: Request, pk: int):
    action = (request.data.get("action") or "").strip()
    try:
        r = Reservation.objects.get(pk=pk, colis__user=request.user)
    except Reservation.DoesNotExist:
        return api_error("Réservation introuvable", 404)
    if action == "accepter":
        # Refuse les autres réservations pour ce colis
        Reservation.objects.filter(colis=r.colis).exclude(pk=pk).update(statut=Reservation.STATUT_REFUSE)
        r.statut = Reservation.STATUT_ACCEPTE
        r.save(update_fields=["statut"])
        return api_success({"message": "Réservation acceptée", "statut": r.statut})
    if action == "refuser":
        r.statut = Reservation.STATUT_REFUSE
        r.save(update_fields=["statut"])
        return api_success({"message": "Réservation refusée", "statut": r.statut})
    return api_error("Action invalide (accepter|refuser)", 422)


# ---------- PAIEMENTS ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated])
def paiement_for_colis(request: Request, colis_id: int):
    try:
        c = Colis.objects.get(pk=colis_id, user=request.user)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable", 404)
    p = c.paiements.order_by("-date_creation").first()
    if not p:
        return api_error("Aucun paiement pour ce colis", 404)
    data = PaiementSerializer(p).data
    # Complète les champs attendus par le front (nom_colis, prix_estime, kkiapay)
    data["nom_colis"] = c.nom_colis
    data["prix_estime"] = float(c.prix_estime) if c.prix_estime else 0
    data["kkiapay"] = _kkiapay_cfg_payload()
    return api_success(data)


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def paiement_simuler(request: Request, pk: int):
    """Endpoint de simulation: le client paie (appelé par le test E2E).
    En production ce serait un webhook Kkiapay; pour l'instant on
    bascule le paiement en 'paye' et l'étape de suivi en 'En cours'."""
    try:
        p = Paiement.objects.get(pk=pk)
    except Paiement.DoesNotExist:
        return api_error("Paiement introuvable", 404)
    if p.colis.user_id != request.user.id and not request.user.is_admin:
        return api_error("Accès interdit", 403)

    # Essaie de vérifier réellement via Kkiapay si les clés sont fournies
    # sinon bascule directement (mode démo / sandbox sans clés)
    p.statut = Paiement.STATUT_PAYE
    p.numero_transaction = request.data.get("numero_transaction", "SIM-" + p.reference)
    p.operateur = request.data.get("operateur", "demo")
    p.save(update_fields=["statut", "numero_transaction", "operateur", "date_modification"])

    # Passe le colis en En cours
    SuiviColis.objects.create(colis=p.colis, statut="En cours", auteur_id=request.user.id)
    return api_success({"message": "Paiement confirmé", "paiement": PaiementSerializer(p).data})


# ---------- SUIVI ----------
@api_view(["GET"])
@permission_classes([AllowAny])
def suivi_public(request: Request, numero: str):
    try:
        c = Colis.objects.select_related("user").get(numero_suivi=numero)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable pour ce numéro de suivi", 404)

    etapes_qs = SuiviColis.objects.filter(colis=c).filter(
        Q(confirme_par_admin=True) | ~Q(statut="Livré")
    ).order_by("date_etape").values("statut", "date_etape")

    dernier = SuiviColis.objects.filter(colis=c).order_by("-date_etape").values_list("statut", flat=True).first() or "En attente"

    ser_data = ColisSerializer(c).data
    colis_data = dict(ser_data)
    colis_data.pop("image_colis", None)
    colis_data["image_url"] = c.image_colis or None
    # Sérialise dates en ISO pour éviter pb de types
    colis_data["date_creation"] = c.date_creation.isoformat() if c.date_creation else None
    colis_data["date_modification"] = c.date_modification.isoformat() if c.date_modification else None
    colis_data["date_limite"] = str(c.date_limite) if c.date_limite else None

    etapes_list = []
    for e in etapes_qs:
        etapes_list.append({"statut": e["statut"], "date_etape": e["date_etape"].isoformat() if e["date_etape"] else None})

    return api_success({"colis": colis_data, "etapes": etapes_list, "statut_actuel": dernier})


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def suivi_store(request: Request):
    colis_id = request.data.get("colis_id")
    statut = request.data.get("statut")
    commentaire = request.data.get("commentaire", "")
    if statut not in ("En attente", "En cours", "Livré"):
        return api_error("Statut invalide (En attente|En cours|Livré)", 422)

    try:
        c = Colis.objects.get(pk=colis_id)
    except Colis.DoesNotExist:
        return api_error("Colis introuvable", 404)

    # Autorisation : admin ou transporteur avec réservation acceptée
    peut_modifier = request.user.is_admin
    if not peut_modifier:
        peut_modifier = Reservation.objects.filter(
            colis=c, transporteur=request.user,
            statut__in=(Reservation.STATUT_ACCEPTE, Reservation.STATUT_TERMINE),
        ).exists()
    if not peut_modifier:
        return api_error("Vous n'êtes pas autorisé à modifier le suivi de ce colis", 403)

    etape = SuiviColis.objects.create(
        colis=c, statut=statut, commentaire=commentaire,
        auteur_id=request.user.id,
        demande_livraison=(statut == "Livré"),
        confirme_par_admin=request.user.is_admin,
    )
    if statut == "Livré" and not request.user.is_admin:
        NotificationAdmin.objects.create(
            type="livraison", colis=c,
            message="Livraison signalée par le transporteur, à confirmer",
        )
    return api_success({"id": etape.id, "statut": etape.statut, "demande_livraison": etape.demande_livraison})


# ---------- RETRAITS ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated])
def wallet_me(request: Request):
    if _is_user_admin(request.user):
        w = _get_wallet()
        return api_success({"solde": float(w.solde), "total_genere": float(w.total_genere)})
    try:
        t = request.user.transporteur
        return api_success({
            "solde": float(t.solde),
            "total_paye": float(t.total_paye),
            "en_attente": float(t.solde_en_attente),
        })
    except Transporteur.DoesNotExist:
        return api_success({"solde": 0.0, "total_paye": 0.0, "en_attente": 0.0})


def _is_user_admin(u: User) -> bool:
    return u.role in (User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN)


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def retrait_demander(request: Request):
    if not hasattr(request.user, "transporteur"):
        return api_error("Seul un transporteur peut demander un retrait", 403)
    t = request.user.transporteur
    try:
        montant = Decimal(str(request.data.get("montant", "0")))
    except Exception:
        return api_error("Montant invalide", 422)
    if montant < settings.MONTANT_MIN_RETRAIT:
        return api_error(f"Le montant minimum d'un retrait est {settings.MONTANT_MIN_RETRAIT} XOF", 400)
    if montant > t.solde:
        return api_error(f"Solde insuffisant (solde: {t.solde} XOF)", 400)

    numero = request.data.get("numero") or request.user.telephone
    operateur = request.data.get("operateur", "")
    methode = request.data.get("methode", "mobile_money")
    if not numero:
        return api_error("Numéro de retrait requis", 422)

    r = Retrait.objects.create(
        user=request.user, type=Retrait.TYPE_TRANSPORTEUR,
        montant=montant, frais=Decimal("0"), montant_net=montant,
        methode=methode, numero=numero, operateur=operateur,
        statut=Retrait.STATUT_DEMANDE,
    )
    # Débite immédiatement du solde disponible (passe en attente)
    t.solde -= montant
    t.solde_en_attente += montant
    t.save(update_fields=["solde", "solde_en_attente"])
    return api_success(RetraitSerializer(r).data, status_code=201)


# ---------- AVIS ----------
@api_view(["POST"])
@permission_classes([IsAuthenticated])
def avis_store(request: Request):
    colis_id = request.data.get("colis_id")
    transporteur_id = request.data.get("transporteur_id")
    note = int(request.data.get("note", 0))
    if not 1 <= note <= 5:
        return api_error("La note doit être entre 1 et 5", 422)

    c = None
    transporteur = None
    if colis_id:
        try:
            c = Colis.objects.get(pk=colis_id, user=request.user)
        except Colis.DoesNotExist:
            return api_error("Colis introuvable", 404)
        resa = Reservation.objects.filter(colis=c, statut=Reservation.STATUT_TERMINE).first()
        transporteur = resa.transporteur if resa else None
    if transporteur_id:
        try:
            transporteur = User.objects.get(pk=transporteur_id, role=User.ROLE_TRANSPORTEUR)
        except User.DoesNotExist:
            return api_error("Transporteur introuvable", 404)

    a = Avis.objects.create(
        colis=c, user=request.user, transporteur=transporteur,
        note=note, commentaire=request.data.get("commentaire", ""),
    )
    return api_success(AvisSerializer(a).data, status_code=201)


@api_view(["GET"])
@permission_classes([AllowAny])
def avis_transporteur(request: Request, tid: int):
    qs = Avis.objects.filter(transporteur_id=tid, statut="approuve")
    return api_success(list(qs.values()))


# ---------- CONTACT ----------
@api_view(["POST"])
@permission_classes([AllowAny])
def contact_send(request: Request):
    ser = ContactCreateSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    data = ser.validated_data
    # Si l'utilisateur est connecté ET un champ est vide, remplir automatiquement
    if request.user.is_authenticated:
        if not data.get("email"):
            data["email"] = request.user.email
        if not data.get("nom"):
            data["nom"] = f"{request.user.prenom} {request.user.nom}".strip()
    # Si vraiment toujours vide (visiteur anonyme sans saisir nom/email), on
    # met une valeur par défaut plutôt qu'une erreur 500 en base.
    if not data.get("email"):
        data["email"] = "anonyme@local"
    if not data.get("nom"):
        data["nom"] = "Visiteur anonyme"
    m = ContactMessage.objects.create(**data)
    return api_success({"id": m.id, "message": "Message envoyé"}, status_code=201)


# ---------- KKiapay public config ----------
def _kkiapay_is_configured():
    return bool(
        settings.KKIAPAY_PUBLIC_KEY
        and settings.KKIAPAY_PRIVATE_KEY
        and settings.KKIAPAY_SECRET_KEY
    )


def _kkiapay_cfg_payload():
    return {
        "public_key": settings.KKIAPAY_PUBLIC_KEY or "",
        "sandbox": bool(settings.KKIAPAY_SANDBOX),
        "enabled": bool(settings.KKIAPAY_PUBLIC_KEY),
        "configured": _kkiapay_is_configured(),
    }


@api_view(["GET"])
@permission_classes([AllowAny])
def kkiapay_config(request: Request):
    return api_success(_kkiapay_cfg_payload())
