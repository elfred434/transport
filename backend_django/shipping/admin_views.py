import logging
logger = logging.getLogger(__name__)
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
    nb_admins = User.objects.filter(role=User.ROLE_ADMIN).count()
    nb_super_admins = User.objects.filter(role=User.ROLE_SUPER_ADMIN).count()
    total_colis = Colis.objects.count()
    colis_en_attente = Colis.objects.filter(statut=Colis.STATUT_ATTENTE).count()
    total_voyages = Voyage.objects.count()
    voyages_en_attente = Voyage.objects.filter(statut=Voyage.STATUT_ATTENTE).count()
    nb_paiements = Paiement.objects.filter(statut=Paiement.STATUT_PAYE).count()
    chiffre_affaires = (
        Paiement.objects.filter(statut=Paiement.STATUT_PAYE).aggregate(s=Sum("montant"))["s"]
        or Decimal("0")
    )
    demandes_livraison = SuiviColis.objects.filter(demande_livraison=True).count()
    messages_non_lus = ContactMessage.objects.filter(lu=False).count()
    nb_avis = Avis.objects.count()
    avis_en_attente = Avis.objects.filter(statut="en_attente").count()
    retraits_demandes = Retrait.objects.filter(statut=Retrait.STATUT_DEMANDE).count()
    retraits_payes = Retrait.objects.filter(statut=Retrait.STATUT_PAYE).count()
    total_paye_transporteurs = Transporteur.objects.aggregate(s=Sum("total_paye"))["s"] or Decimal("0")

    w = WalletAdmin.objects.get_or_create(pk=1)[0]
    nb_users = total_clients + total_transporteurs + nb_admins + nb_super_admins
    nb_transporteurs_profils = Transporteur.objects.count()
    return api_success({
        "nb_users": nb_users,
        "nb_clients": total_clients,
        "nb_transporteurs_users": total_transporteurs,
        "nb_transporteurs": nb_transporteurs_profils,
        "nb_admins": nb_admins,
        "nb_super_admins": nb_super_admins,
        "nb_colis": total_colis,
        "nb_voyages": total_voyages,
        "colis_en_attente": colis_en_attente,
        "voyages_en_attente": voyages_en_attente,
        "avis_en_attente": avis_en_attente,
        "demandes_livraison": demandes_livraison,
        "nb_paiements": nb_paiements,
        "montant_total_paye": float(chiffre_affaires),
        "messages_contact_non_lus": messages_non_lus,
        "nb_avis": nb_avis,
        "admin_wallet_solde": float(w.solde),
        "admin_commission_total": float(w.total_genere),
        "retraits_en_attente": retraits_demandes,
        "retraits_payes": retraits_payes,
        "total_paye_transporteurs": float(total_paye_transporteurs),
        "total_frais_admin": 0,
        "notifications_non_lues": demandes_livraison + messages_non_lus + retraits_demandes,
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

    def _serialize_user(u):
        return {
            "id": u.id, "email": u.email, "nom": u.nom, "prenom": u.prenom,
            "telephone": u.telephone, "role": u.role,
            "photo_url": u.photo or None,
            "date_inscription": u.date_creation.isoformat(),
        }

    # Mode "liste plate" (utilisé par SuperAdmin.tsx: charge tous les users pour gestion rôles).
    if request.query_params.get("all") == "1" or request.query_params.get("format") == "list":
        return api_success([_serialize_user(u) for u in qs.order_by("-date_creation")])

    def serialize(objs, many):
        return [_serialize_user(u) for u in objs]
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=serialize)
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
            | Q(ville__icontains=search) | Q(pays__icontains=search)
            | Q(user__nom__icontains=search) | Q(user__prenom__icontains=search)
            | Q(user__email__icontains=search)
        )
    def serialize(objs, many):
        out = []
        for c in objs:
            # Récupère la dernière étape de suivi et la réservation acceptée/terminée
            last_step = SuiviColis.objects.filter(colis=c).order_by("-date_etape").first()
            dem = SuiviColis.objects.filter(colis=c, demande_livraison=True).order_by("-date_etape").first()
            resa = Reservation.objects.filter(colis=c, statut__in=[Reservation.STATUT_ACCEPTE, Reservation.STATUT_TERMINE]).select_related("voyage__user").first()
            out.append({
                "id": c.id,
                "nom_colis": c.nom_colis,
                "image_url": c.image_colis or None,
                "numero_suivi": c.numero_suivi,
                "prenom": c.user.prenom, "nom": c.user.nom, "email": c.user.email,
                "ville": c.ville, "pays": c.pays,
                "poids": str(c.poids), "prix_estime": str(c.prix_estime),
                "statut": c.statut,
                "statut_livraison": last_step.statut if last_step else None,
                "demande_livraison_id": dem.id if dem else None,
                "transporteur_id": resa.voyage.user_id if resa else None,
            })
        return out
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=serialize)
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
    search = request.query_params.get("search", "")
    if search:
        qs = qs.filter(
            Q(pays_depart__icontains=search) | Q(pays_destination__icontains=search)
            | Q(user__nom__icontains=search) | Q(user__prenom__icontains=search)
            | Q(user__email__icontains=search)
        )
    nb_reservations_annot = Count("reservations", filter=Q(reservations__statut="accepte"))
    qs = qs.annotate(nb_reservations=nb_reservations_annot)

    def _serialize(objs, many):
        out = []
        for v in objs:
            out.append({
                "id": v.id,
                "prenom": v.user.prenom if v.user else None,
                "nom": v.user.nom if v.user else None,
                "email": v.user.email if v.user else (v.email or None),
                "pays_depart": v.pays_depart,
                "pays_destination": v.pays_destination,
                "date_depart": v.date_depart.isoformat() if v.date_depart else None,
                "heure_depart": v.heure_depart.isoformat() if v.heure_depart else None,
                "poids_max": str(v.poids_max),
                "nb_reservations": getattr(v, "nb_reservations", 0),
                "statut": v.statut,
            })
        return out

    res = paginate_queryset(qs.order_by("-date_depart", "-heure_depart"), request, serializer=_serialize)
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
def _serialize_livraison(s: SuiviColis):
    c = s.colis
    client = c.user
    # Récupère la réservation acceptée la plus récente (si présente)
    resa = Reservation.objects.filter(colis=c, statut=Reservation.STATUT_ACCEPTE).select_related(
        "transporteur", "voyage__user"
    ).first()
    transp_user = None
    transp_profile = None
    vehicule = None
    compagnie = None
    ville_t = None
    if resa:
        transp_user = resa.transporteur
        try:
            transp_profile = Transporteur.objects.get(user=transp_user)
            vehicule = transp_profile.vehicule or None
            compagnie = transp_profile.compagnie or None
            ville_t = transp_profile.ville or None
        except Transporteur.DoesNotExist:
            pass

    montant = c.prix_estime or Decimal("0")
    cp_pct = Decimal(str(getattr(dj_settings, "COMMISSION_PLATEFORME", "0.05")))
    commission_admin = (montant * cp_pct).quantize(Decimal("1"))
    commission_transporteur = montant - commission_admin

    return {
        "id": s.id,
        "suivi_id": s.id,
        "colis_id": c.id,
        "numero_suivi": c.numero_suivi,
        "nom_colis": c.nom_colis,
        "statut": s.statut,
        "prix_estime": float(montant),
        "poids": str(c.poids),
        "ville": c.ville,
        "pays": c.pays,
        "adresse_depart": c.adresse_depart or None,
        "adresse_destination": c.adresse_destination or None,
        "image_url": c.image_colis or None,
        "client_nom": client.nom if client else None,
        "client_prenom": client.prenom if client else None,
        "client_tel": client.telephone if client else None,
        "client_email": client.email if client else None,
        "transporteur_id": transp_user.id if transp_user else None,
        "transporteur_nom": transp_user.nom if transp_user else None,
        "transporteur_prenom": transp_user.prenom if transp_user else None,
        "transporteur_tel": transp_user.telephone if transp_user else None,
        "transporteur_email": transp_user.email if transp_user else None,
        "vehicule": vehicule,
        "compagnie": compagnie,
        "transporteur_ville": ville_t,
        "date_etape": s.date_etape.isoformat(),
        "commission_transporteur": float(commission_transporteur),
        "commission_admin": float(commission_admin),
    }


@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def livraisons_list(request: Request):
    """Liste des demandes de livraison en attente (Livré posé par transporteur, pas encore confirmé)."""
    qs = SuiviColis.objects.filter(demande_livraison=True).select_related(
        "colis", "colis__user"
    ).order_by("-date_etape")
    res = paginate_queryset(
        qs, request,
        serializer=lambda objs, many: [_serialize_livraison(s) for s in objs],
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
    search = request.query_params.get("search", "")
    if search:
        qs = qs.filter(
            Q(reference__icontains=search) | Q(numero_transaction__icontains=search)
            | Q(user__nom__icontains=search) | Q(user__prenom__icontains=search)
            | Q(user__email__icontains=search)
            | Q(colis__nom_colis__icontains=search) | Q(colis__numero_suivi__icontains=search)
        )

    def _serialize(objs, many):
        out = []
        for p in objs:
            out.append({
                "id": p.id,
                "reference": p.reference,
                "numero_transaction": p.numero_transaction or None,
                "prenom": p.user.prenom if p.user else None,
                "nom": p.user.nom if p.user else None,
                "email": p.user.email if p.user else None,
                "nom_colis": p.colis.nom_colis if p.colis else None,
                "numero_suivi": p.colis.numero_suivi if p.colis else None,
                "montant": float(p.montant),
                "methode_paiement": p.methode,
                "operateur": p.operateur or None,
                "date_creation": p.date_creation.isoformat(),
                "statut": p.statut,
            })
        return out

    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=_serialize)
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
    search = request.query_params.get("search", "")
    if search:
        qs = qs.filter(
            Q(user__nom__icontains=search) | Q(user__prenom__icontains=search)
            | Q(user__email__icontains=search) | Q(user__telephone__icontains=search)
            | Q(ville__icontains=search) | Q(pays__icontains=search)
            | Q(compagnie__icontains=search)
        )
    qs = qs.annotate(nb_colis_transportes=Count(
        "user__colis",
        filter=Q(user__colis__suivi__statut="Livré", user__colis__suivi__confirme_par_admin=True),
        distinct=True,
    ))

    def _serialize(objs, many):
        out = []
        for t in objs:
            out.append({
                "id": t.id,
                "user_id": t.user_id,
                "prenom": t.user.prenom,
                "nom": t.user.nom,
                "email": t.user.email,
                "telephone": t.user.telephone,
                "vehicule": t.vehicule or None,
                "numero_permis": t.numero_permis or None,
                "compagnie": t.compagnie or None,
                "ville": t.ville or None,
                "pays": t.pays or None,
                "nb_colis_transportes": getattr(t, "nb_colis_transportes", 0),
                "solde": float(t.solde or 0),
            })
        return out

    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=_serialize)
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
    statut = request.query_params.get("statut")
    if statut in ("approuve", "refuse", "en_attente"):
        qs = qs.filter(statut=statut)

    def _serialize(objs, many):
        out = []
        for a in objs:
            out.append({
                "id": a.id,
                "user_prenom": a.user.prenom if a.user else "",
                "user_nom": a.user.nom if a.user else "",
                "transporteur_id": a.transporteur_id,
                "transporteur_prenom": a.transporteur.prenom if a.transporteur else "",
                "transporteur_nom": a.transporteur.nom if a.transporteur else "",
                "note": a.note,
                "commentaire": a.commentaire or "",
                "date_avis": a.date_creation.isoformat(),
                "statut": a.statut,
            })
        return out

    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=_serialize)
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
    repondu = request.query_params.get("repondu")
    if repondu == "1":
        qs = qs.exclude(reponse="")
    elif repondu == "0":
        qs = qs.filter(reponse="")
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=lambda objs, many: [
        {"id": m.id, "nom": m.nom, "email": m.email,
         "sujet": m.sujet or "",
         "message": m.message,
         "reponse": m.reponse or None,
         "date_envoi": m.date_creation.isoformat(),
         "date_reponse": m.date_reponse.isoformat() if m.date_reponse else None,
         "lu": m.lu}
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
    type_r = request.query_params.get("type")
    if type_r:
        qs = qs.filter(type=type_r)
    def serialize(objs, many):
        out = []
        for r in objs:
            # Trouve un colis lié au user via réservation/transporteur (approximatif)
            colis_info = {"colis_id": None, "nom_colis": None, "numero_suivi": None}
            out.append({
                "id": r.id,
                "user_id": r.user_id,
                "type": r.type,
                "montant": float(r.montant),
                "statut": r.statut,
                "methode": r.methode,
                "numero": r.numero,
                "reference": r.reference_kkiapay or f"RET-{r.id}",
                "details": r.note or None,
                "colis_id": colis_info["colis_id"],
                "nom": r.user.nom, "prenom": r.user.prenom, "email": r.user.email,
                "nom_colis": colis_info["nom_colis"],
                "numero_suivi": colis_info["numero_suivi"],
                "date_demande": r.date_creation.isoformat(),
                "date_traitement": r.date_modification.isoformat() if r.statut != Retrait.STATUT_DEMANDE else None,
                "kkiapay_response": None,
            })
        return out
    res = paginate_queryset(qs.order_by("-date_creation"), request, serializer=serialize)
    return api_success(res)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def retrait_admin_create(request: Request):
    """Admin retire une somme de son propre wallet vers son numéro."""
    from .views import retrait_demander as _rd
    # On réutilise la logique en créant un retrait de type admin
    # Plus simple: création manuelle
    from decimal import Decimal as D, InvalidOperation
    try:
        montant = D(str(request.data.get("montant", "0")))
    except (InvalidOperation, ValueError):
        return api_error("Montant invalide", 422)
    numero = request.data.get("numero")
    if not numero:
        return api_error("Numéro requis", 422)
    w = _wallet()
    if montant < D("1000"):
        return api_error("Le montant minimum est 1000 XOF", 400)
    if montant > w.solde:
        return api_error(f"Solde insuffisant (solde: {w.solde} XOF)", 400)
    with transaction.atomic():
        r = Retrait.objects.create(
            user=request.user, type=Retrait.TYPE_ADMIN,
            montant=montant, frais=D("0"), montant_net=montant,
            statut=Retrait.STATUT_DEMANDE,
            methode="mobile_money", numero=numero,
        )
        w = _wallet()
        w.solde -= montant
        w.save(update_fields=["solde"])
    return api_success({"message": "Retrait admin créé", "id": r.id}, status_code=201)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def retrait_retry(request: Request, pk: int):
    """Réessayer un payout (placeholder: marque simplement payé, comme simulate)."""
    return _retrait_payer_impl(request, pk)


@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def livraison_bulk(request: Request):
    """Confirmer/refuser plusieurs livraisons d'un coup."""
    ids = request.data.get("ids") or []
    decision = (request.data.get("decision") or "").strip()
    if not isinstance(ids, list) or not ids:
        return api_error("Liste d'ids attendue", 422)
    total_t = Decimal("0"); total_a = Decimal("0"); traitees = 0
    for sid in ids:
        # Construit un request-like pour appeler livraison_decision avec cet id
        # Plus simple: appel direct à la logique
        setattr(request, "data_copy", None)
        from django.http import HttpRequest
        from rest_framework.request import Request as DRFRequest
        from django.test.client import RequestFactory
        factory = RequestFactory()
        django_req = factory.post(f"/admin/suivi/{sid}/livraison", {"decision": decision}, content_type="application/json")
        django_req.user = request.user
        drf_req = DRFRequest(django_req)
        resp = livraison_decision(drf_req, sid)
        if resp.status_code < 400:
            data = resp.data
            if isinstance(data, dict) and data.get("success"):
                d = data.get("data") or {}
                try:
                    total_t += Decimal(str(d.get("commission_transporteur", 0)))
                    total_a += Decimal(str(d.get("commission_plateforme", d.get("commission_admin", 0))))
                except Exception:
                    logger.exception("Erreur sur bulk livraison")
                traitees += 1
    return api_success({
        "traitees": traitees,
        "total_commission_transporteur": str(total_t),
        "total_commission_admin": str(total_a),
        "message": f"{traitees} livraison(s) traitée(s)",
    })


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
    total_retraits_admin = Retrait.objects.filter(
        statut=Retrait.STATUT_PAYE, type=Retrait.TYPE_ADMIN
    ).aggregate(s=Sum("montant_net"))["s"] or Decimal("0")
    total_paye_transp = Transporteur.objects.aggregate(s=Sum("total_paye"))["s"] or Decimal("0")
    return api_success({
        "solde": float(w.solde or 0),
        "total_commission_generee": float(w.total_genere or 0),
        "total_paye_transporteurs": float(total_paye_transp),
        "total_retraits_admin": float(total_retraits_admin),
    })


def _wallet():
    return WalletAdmin.objects.get_or_create(pk=1)[0]


# ---------- Kkiapay status ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def kkiapay_status(request: Request):
    from django.conf import settings as s
    pk = s.KKIAPAY_PUBLIC_KEY or ""
    masked = (pk[:6] + "..." + pk[-4:]) if len(pk) > 10 else None
    return api_success({
        "configured": bool(pk and s.KKIAPAY_PRIVATE_KEY and s.KKIAPAY_SECRET_KEY),
        "sandbox": s.KKIAPAY_SANDBOX,
        "public_key_masked": masked,
        "base_url": "https://api.kkiapay.com" if not s.KKIAPAY_SANDBOX else "https://sandbox.kkiapay.com",
        "simulate_fallback": not bool(pk and s.KKIAPAY_PRIVATE_KEY and s.KKIAPAY_SECRET_KEY),
        "admin_phone": None,
    })


# ---------- Super Admin endpoints ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated])
def superadmin_admins_list(request: Request):
    if request.user.role not in (User.ROLE_SUPER_ADMIN, User.ROLE_ADMIN):
        return api_error("Accès refusé", 403)
    qs = User.objects.filter(role__in=(User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN)).order_by("-date_creation")
    out = [
        {
            "id": u.id,
            "email": u.email,
            "nom": u.nom,
            "prenom": u.prenom,
            "telephone": u.telephone,
            "role": u.role,
            "photo_url": u.photo or None,
            "date_inscription": u.date_creation.isoformat(),
        } for u in qs
    ]
    return api_success(out)


# ---------- Kkiapay balance (sandbox safe) ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def kkiapay_balance(request: Request):
    from django.conf import settings as s
    # En sandbox on retourne un statut par défaut sans appeler l'API Kkiapay
    return api_success({
        "status": "ok" if (s.KKIAPAY_PUBLIC_KEY and s.KKIAPAY_PRIVATE_KEY and s.KKIAPAY_SECRET_KEY) else "not_configured",
        "balance": None,
        "currency": "XOF",
        "sandbox": s.KKIAPAY_SANDBOX,
        "message": "Balance non disponible en mode sandbox." if s.KKIAPAY_SANDBOX else "OK",
    })


# ---------- Contact: marquer lu / répondre ----------
@api_view(["POST"])
@permission_classes([IsAuthenticated, IsAdmin])
def contact_reply(request: Request, pk: int):
    try:
        m = ContactMessage.objects.get(pk=pk)
    except ContactMessage.DoesNotExist:
        return api_error("Message introuvable", 404)
    from django.utils import timezone as _tz
    reponse = (request.data.get("reponse") or "").strip()
    m.lu = True
    if reponse:
        m.reponse = reponse
        m.date_reponse = _tz.now()
    m.save(update_fields=["lu", "reponse", "date_reponse"] if reponse else ["lu"])
    return api_success({"message": "Réponse enregistrée" if reponse else "Marqué comme lu", "id": m.id})
