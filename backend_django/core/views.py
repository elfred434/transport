"""Views utilitaires: dispatch multi-méthodes, profile (+upload photo), messagerie."""
import os
import uuid
from pathlib import Path

from django.conf import settings as dj_settings
from django.db import transaction
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated, AllowAny
from rest_framework.request import Request

from accounts.models import User
from accounts.serializers import UserMeSerializer
from .responses import api_success, api_error


@api_view(["GET", "POST", "DELETE", "PUT"])
@permission_classes([AllowAny])
def method_dispatcher(request: Request, get_fn=None, post_fn=None, delete_fn=None, put_fn=None, auth_required=False):
    if auth_required and not request.user.is_authenticated:
        return api_error("Authentification requise", 401)
    method = request.method.upper()
    fn = {"GET": get_fn, "POST": post_fn, "DELETE": delete_fn, "PUT": put_fn}.get(method)
    if fn is None:
        return api_error("Méthode non autorisée", 405)
    return fn(request)


def _save_upload(file_obj, folder: str) -> str:
    if not file_obj:
        return ""
    storage_dir = Path(dj_settings.MEDIA_ROOT) / folder
    storage_dir.mkdir(parents=True, exist_ok=True)
    ext = Path(file_obj.name).suffix.lower()[:10] or ""
    name = f"{uuid.uuid4().hex}{ext}"
    (storage_dir / name).write_bytes(file_obj.read())
    return f"{dj_settings.MEDIA_URL}{folder}/{name}"


@api_view(["GET", "POST", "PUT"])
@permission_classes([IsAuthenticated])
def profile_dispatch(request: Request):
    """GET /api/profile → {user, transporteur}
    PUT/POST /api/profile → met à jour nom/prenom/email/telephone/photo_profil (+ profil transporteur si applicable).
    """
    from shipping.models import Transporteur

    me = request.user

    if request.method == "GET":
        user_data = {
            "id": me.id,
            "email": me.email,
            "nom": me.nom,
            "prenom": me.prenom,
            "telephone": me.telephone or "",
            "role": me.role,
            "photo_url": me.photo or None,
            "date_inscription": me.date_creation.isoformat(),
        }
        transporteur_data = None
        if me.role == User.ROLE_TRANSPORTEUR:
            t = getattr(me, "transporteur", None)
            if t is None:
                t = Transporteur.objects.create(user=me)
            transporteur_data = {
                "numero_permis": t.numero_permis or None,
                "vehicule": t.vehicule or None,
                "compagnie": t.compagnie or None,
                "adresse": t.adresse or None,
                "ville": t.ville or None,
                "pays": t.pays or None,
                "solde": float(t.solde or 0),
                "date_creation": t.date_creation.isoformat(),
            }
        return api_success({"user": user_data, "transporteur": transporteur_data})

    # PUT/POST update
    fields_user = ("nom", "prenom", "telephone")
    for field in fields_user:
        v = request.data.get(field)
        if v is not None:
            setattr(me, field, v.strip() if isinstance(v, str) else v)
    email = (request.data.get("email") or "").strip().lower()
    if email and email != me.email:
        if User.objects.filter(email=email).exclude(pk=me.pk).exists():
            return api_error("Cet email est déjà utilisé", 422)
        me.email = email
    # Upload photo de profil
    photo_file = (request.FILES or {}).get("photo_profil") or (request.FILES or {}).get("photo")
    if photo_file:
        me.photo = _save_upload(photo_file, "avatars")
    me.save()

    # Mise à jour du profil transporteur si applicable
    if me.role == User.ROLE_TRANSPORTEUR:
        t = getattr(me, "transporteur", None) or Transporteur.objects.create(user=me)
        for field in ("numero_permis", "vehicule", "compagnie", "adresse", "ville", "pays"):
            v = request.data.get(field)
            if v is not None:
                setattr(t, field, v.strip() if isinstance(v, str) else v)
        t.save()

    return api_success({"message": "Profil mis à jour"})


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def transporteur_public(request: Request, tid: int):
    try:
        u = User.objects.get(pk=tid, role=User.ROLE_TRANSPORTEUR)
    except User.DoesNotExist:
        return api_error("Transporteur introuvable", 404)
    t = getattr(u, "transporteur", None)
    from django.db.models import Avg
    note_moyenne = u.avis_recus.aggregate(a=Avg("note"))["a"]
    nb_avis = u.avis_recus.count()
    data = {
        "id": u.id, "nom": u.nom, "prenom": u.prenom, "email": u.email,
        "telephone": u.telephone, "photo": u.photo,
        "compagnie": t.compagnie if t else "",
        "ville": t.ville if t else "",
        "pays": t.pays if t else "",
        "vehicule": t.vehicule if t else "",
        "note_moyenne": float(note_moyenne) if note_moyenne else 0,
        "nb_avis": nb_avis,
    }
    return api_success(data)


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def transporteur_stats(request: Request):
    from shipping.models import Reservation
    from django.db.models import Avg
    t = getattr(request.user, "transporteur", None)
    if not t:
        return api_success({
            "nb_colis_transportes": 0, "nb_colis_en_cours": 0,
            "total_gagne": "0", "note_moyenne": 0,
        })
    nb_termines = Reservation.objects.filter(transporteur=request.user, statut="termine").count()
    nb_en_cours = Reservation.objects.filter(transporteur=request.user, statut="accepte").count()
    note_moyenne = request.user.avis_recus.aggregate(a=Avg("note"))["a"]
    return api_success({
        "nb_colis_transportes": nb_termines,
        "nb_colis_en_cours": nb_en_cours,
        "solde": str(t.solde),
        "total_paye": str(t.total_paye),
        "note_moyenne": float(note_moyenne) if note_moyenne else 0,
    })
"""Endpoints messagerie : conversations privées entre users + chat avec l'admin."""
import os
import uuid
from pathlib import Path

from django.conf import settings as dj_settings
from django.db.models import Q, Max, Count, F, OuterRef, Subquery
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated, AllowAny
from rest_framework.request import Request

from accounts.models import User
from core.permissions import IsAdmin
from core.responses import api_success, api_error
from .models import MessagePrive, MessageAdmin


ADMIN_IDS = None  # not used; role check is preferred


def _is_admin(u: User) -> bool:
    return u.role in (User.ROLE_ADMIN, User.ROLE_SUPER_ADMIN)


def _save_upload(file_obj, folder: str) -> str:
    if not file_obj:
        return ""
    storage_dir = Path(dj_settings.MEDIA_ROOT) / folder
    storage_dir.mkdir(parents=True, exist_ok=True)
    ext = Path(file_obj.name).suffix.lower()[:10] or ""
    name = f"{uuid.uuid4().hex}{ext}"
    (storage_dir / name).write_bytes(file_obj.read())
    return f"{dj_settings.MEDIA_URL}{folder}/{name}"


# ---------- Conversations privées (user ↔ user) ----------
@api_view(["GET"])
@permission_classes([IsAuthenticated])
def conversations_list(request: Request):
    """Liste des conversations du user courant, avec dernier message et nb non lus."""
    me = request.user
    last = MessagePrive.objects.filter(
        Q(expediteur=OuterRef("pk")) | Q(destinataire=OuterRef("pk"))
    )
    # Simpler : deux sous-requêtes pour dernier message entre me et peer
    pairs = []
    peer_ids = set()
    msgs = MessagePrive.objects.filter(Q(expediteur=me) | Q(destinataire=me))
    for m in msgs.only("expediteur_id", "destinataire_id"):
        peer = m.destinataire_id if m.expediteur_id == me.id else m.expediteur_id
        peer_ids.add(peer)
    out = []
    for pid in peer_ids:
        try:
            u = User.objects.get(pk=pid)
        except User.DoesNotExist:
            continue
        last_msg = (
            MessagePrive.objects.filter(
                Q(expediteur=me, destinataire=u) | Q(expediteur=u, destinataire=me)
            )
            .order_by("-date_envoi")
            .first()
        )
        non_lus = MessagePrive.objects.filter(
            expediteur=u, destinataire=me, lu=False
        ).count()
        out.append(
            {
                "id": u.id,
                "prenom": u.prenom,
                "nom": u.nom,
                "photo_url": u.photo or None,
                "dernier_message": last_msg.contenu if last_msg else None,
                "derniere_date": last_msg.date_envoi.isoformat() if last_msg else None,
                "non_lus": non_lus,
            }
        )
    out.sort(key=lambda x: x["derniere_date"] or "", reverse=True)
    return api_success(out)


@api_view(["GET", "POST"])
@permission_classes([IsAuthenticated])
def messages_thread(request: Request):
    me = request.user
    if request.method == "GET":
        peer_id = request.query_params.get("destinataire_id") or request.query_params.get("user_id")
        try:
            peer_id = int(peer_id)
        except (TypeError, ValueError):
            return api_error("destinataire_id manquant", 400)
        try:
            peer = User.objects.get(pk=peer_id)
        except User.DoesNotExist:
            return api_error("Destinataire introuvable", 404)
        # Marquer non lus de peer → me comme lus
        MessagePrive.objects.filter(expediteur=peer, destinataire=me, lu=False).update(lu=True)
        qs = MessagePrive.objects.filter(
            Q(expediteur=me, destinataire=peer) | Q(expediteur=peer, destinataire=me)
        ).order_by("date_envoi")
        out = []
        for m in qs:
            out.append(
                {
                    "id": m.id,
                    "expediteur_id": m.expediteur_id,
                    "destinataire_id": m.destinataire_id,
                    "contenu": m.contenu,
                    "fichier_url": (m.fichier or None) if m.fichier else None,
                    "date_envoi": m.date_envoi.isoformat(),
                    "prenom": m.expediteur.prenom,
                    "nom": m.expediteur.nom,
                }
            )
        return api_success(out)

    # POST: envoyer un message privé à un autre user (pas l'admin — ce dernier utilise admin-chat)
    peer_id = request.data.get("destinataire_id") or request.data.get("user_id")
    try:
        peer_id = int(peer_id)
    except (TypeError, ValueError):
        return api_error("destinataire_id manquant", 400)
    if peer_id == me.id:
        return api_error("Impossible de s'envoyer un message à soi-même", 400)
    try:
        peer = User.objects.get(pk=peer_id)
    except User.DoesNotExist:
        return api_error("Destinataire introuvable", 404)
    if _is_admin(peer):
        return api_error("Pour contacter l'admin, utilisez la page Contact admin (/messagerie-admin)", 400)
    contenu = (request.data.get("contenu") or "").strip()
    file_url = _save_upload(request.FILES.get("fichier"), "messages") if "fichier" in (getattr(request, "FILES", None) or {}) else ""
    if not contenu and not file_url:
        return api_error("Message vide", 400)
    m = MessagePrive.objects.create(
        expediteur=me, destinataire=peer, contenu=contenu, fichier=file_url
    )
    return api_success({"id": m.id, "date_envoi": m.date_envoi.isoformat()}, status_code=201)


# ---------- Chat avec l'admin (user ↔ admin) ----------
@api_view(["GET", "POST"])
@permission_classes([IsAuthenticated])
def admin_chat(request: Request):
    """GET (user) : liste des messages entre user et admins.
    GET (admin) ?user_id= : liste des messages avec un user donné.
    POST (user) : envoyer un message à l'admin.
    POST (admin) {destinataire_id, contenu} : répondre à un user."""
    me = request.user

    if request.method == "GET":
        if _is_admin(me):
            uid = request.query_params.get("user_id")
            try:
                uid = int(uid)
            except (TypeError, ValueError):
                return api_error("user_id manquant", 400)
            try:
                u = User.objects.get(pk=uid)
            except User.DoesNotExist:
                return api_error("Utilisateur introuvable", 404)
            qs = MessageAdmin.objects.filter(user=u).order_by("date_envoi")
            MessageAdmin.objects.filter(user=u, auteur=u, lu=False).update(lu=True)
        else:
            u = me
            qs = MessageAdmin.objects.filter(user=me).order_by("date_envoi")
            # Marquer comme lus les messages qui NE viennent PAS de moi (réponses admin)
            MessageAdmin.objects.filter(user=me).exclude(auteur=me).filter(lu=False).update(lu=True)
        out = []
        for m in qs:
            out.append(
                {
                    "id": m.id,
                    "moi": m.auteur_id == me.id,
                    "prenom": m.auteur.prenom,
                    "nom": m.auteur.nom,
                    "contenu": m.contenu,
                    "fichier_url": (m.fichier or None) if m.fichier else None,
                    "date_envoi": m.date_envoi.isoformat(),
                }
            )
        return api_success(out)

    # POST
    contenu = (request.data.get("contenu") or "").strip()
    file_url = _save_upload(request.FILES.get("fichier"), "messages") if "fichier" in (getattr(request, "FILES", None) or {}) else ""
    if not contenu and not file_url:
        return api_error("Message vide", 400)

    if _is_admin(me):
        uid = request.data.get("destinataire_id") or request.data.get("user_id")
        try:
            uid = int(uid)
        except (TypeError, ValueError):
            return api_error("destinataire_id manquant", 400)
        try:
            u = User.objects.get(pk=uid)
        except User.DoesNotExist:
            return api_error("Utilisateur introuvable", 404)
    else:
        u = me

    MessageAdmin.objects.create(user=u, auteur=me, contenu=contenu, fichier=file_url)
    return api_success({"message": "Message envoyé"}, status_code=201)


@api_view(["GET"])
@permission_classes([IsAuthenticated, IsAdmin])
def admin_chat_conversations(request: Request):
    """Liste des conversations admin : un item par user ayant écrit à l'admin, avec dernier message."""
    pairs = MessageAdmin.objects.values("user_id").distinct()
    out = []
    for row in pairs:
        uid = row["user_id"]
        try:
            u = User.objects.get(pk=uid)
        except User.DoesNotExist:
            continue
        last = MessageAdmin.objects.filter(user=u).order_by("-date_envoi").first()
        non_lus = MessageAdmin.objects.filter(user=u, auteur=u, lu=False).count()
        out.append(
            {
                "user_id": u.id,
                "prenom": u.prenom,
                "nom": u.nom,
                "photo_url": u.photo or None,
                "dernier_message": last.contenu if last else None,
                "derniere_date": last.date_envoi.isoformat() if last else None,
                "non_lus": non_lus,
            }
        )
    out.sort(key=lambda x: x["derniere_date"] or "", reverse=True)
    return api_success(out)
