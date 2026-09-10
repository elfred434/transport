"""Views utilitaires: dispatch multi-méthodes, profile, token blacklist."""
from django.conf import settings
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated, AllowAny
from rest_framework.request import Request
from rest_framework.response import Response

from .responses import api_success


@api_view(["GET", "POST", "DELETE", "PUT"])
@permission_classes([AllowAny])
def method_dispatcher(request: Request, get_fn=None, post_fn=None, delete_fn=None, put_fn=None, auth_required=False):
    if auth_required and not request.user.is_authenticated:
        from .responses import api_error
        return api_error("Authentification requise", 401)
    method = request.method.upper()
    fn = {"GET": get_fn, "POST": post_fn, "DELETE": delete_fn, "PUT": put_fn}.get(method)
    if fn is None:
        from .responses import api_error
        return api_error("Méthode non autorisée", 405)
    return fn(request)


@api_view(["GET", "PUT"])
@permission_classes([IsAuthenticated])
def profile_dispatch(request: Request):
    from accounts.serializers import UserMeSerializer
    if request.method == "GET":
        return api_success(UserMeSerializer(request.user).data)
    # PUT update (basic)
    for field in ("nom", "prenom", "telephone", "photo"):
        if field in request.data:
            setattr(request.user, field, request.data[field])
    request.user.save()
    return api_success(UserMeSerializer(request.user).data)


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def transporteur_public(request: Request, tid: int):
    from accounts.models import User
    from shipping.models import Transporteur
    from shipping.serializers import AvisSerializer
    from django.db.models import Avg
    try:
        u = User.objects.get(pk=tid, role=User.ROLE_TRANSPORTEUR)
    except User.DoesNotExist:
        from .responses import api_error
        return api_error("Transporteur introuvable", 404)
    t = getattr(u, "transporteur", None)
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
    if not hasattr(request.user, "transporteur"):
        return api_success({
            "nb_colis_transportes": 0, "nb_colis_en_cours": 0,
            "total_gagne": "0", "note_moyenne": 0,
        })
    t = request.user.transporteur
    from shipping.models import Reservation, Avis
    nb_termines = Reservation.objects.filter(transporteur=request.user, statut="termine").count()
    nb_en_cours = Reservation.objects.filter(transporteur=request.user, statut="accepte").count()
    note_moyenne = request.user.avis_recus.aggregate(a=__import__("django.db.models", fromlist=["Avg"]).Avg("note"))["a"]
    return api_success({
        "nb_colis_transportes": nb_termines,
        "nb_colis_en_cours": nb_en_cours,
        "solde": str(t.solde),
        "total_paye": str(t.total_paye),
        "note_moyenne": float(note_moyenne) if note_moyenne else 0,
    })
