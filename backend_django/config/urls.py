"""Routing API — dispatch multi-méthode simple, sans double-wrapping @api_view."""
from functools import wraps

from django.contrib import admin
from django.http import JsonResponse
from django.urls import path
from django.conf import settings
from django.conf.urls.static import static

from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import AllowAny, IsAuthenticated
from rest_framework_simplejwt.views import TokenRefreshView

from core import views as cv
from core.responses import api_success, api_error
from accounts import views as av
from shipping import views as sv
from shipping import admin_views as adv


@api_view(["GET"])
@permission_classes([AllowAny])
def api_root(request):
    return JsonResponse({
        "success": True,
        "data": {
            "name": settings.APP_NAME + " — API",
            "version": settings.APP_VERSION,
            "stack": "Django 5 + DRF",
            "roles": ["client", "transporteur", "admin", "super_admin"],
        },
    })


@api_view(["GET"])
@permission_classes([AllowAny])
def ok_empty_list(request):
    return api_success([])


@api_view(["GET", "POST"])
@permission_classes([AllowAny])
def ok_json(request):
    return api_success({})


def m(GET=None, POST=None, DELETE=None, PUT=None, auth=True):
    perms = [IsAuthenticated] if auth else [AllowAny]
    handler_map = {"GET": GET, "POST": POST, "DELETE": DELETE, "PUT": PUT}
    methods = [mm for mm, f in handler_map.items() if f is not None]

    @api_view(methods)
    @permission_classes(perms)
    def _view(request, *args, **kwargs):
        fn = handler_map.get(request.method)
        if not fn:
            return api_error("Méthode non autorisée", 405)
        return fn(request, *args, **kwargs)

    return _view


# Pour réutiliser nos vues @api_view existantes dans un dispatcher,
# on ne peut pas les appeler comme callable (ça re-déclenche la fabrique).
# On passe donc soit une fonction qui accède à la logique via
# la classe DRF sous-jacente, soit on les enrobe pour bypasser le wrap.
def drf(view_fn):
    """Extrait la fonction sous-jacente d'une vue @api_view de façon à
    pouvoir l'appeler directement depuis un autre @api_view."""
    # @api_view retourne une WrappedAPIView qui, appelée, crée sa propre Request.
    # On va à la place extraire la .cls de la vue et appeler la bonne méthode
    # (get/post/...) sur une instance.
    wrapped_cls = view_fn.cls
    initkwargs = view_fn.initkwargs
    instance = wrapped_cls(**initkwargs)
    instance.format_kwarg = None

    def _caller(request, *args, **kwargs):
        method = request.method.lower()
        view_method = getattr(instance, method, instance.http_method_not_allowed)
        return view_method(request, *args, **kwargs)
    return _caller


# Multi-method routes
colis_view = m(
    auth=True,
    GET=drf(sv.colis_available),
    POST=drf(sv.colis_store),
)
voyages_view = m(
    auth=True,
    GET=drf(sv.voyage_available),
    POST=drf(sv.voyage_store),
)
admin_users_view = m(
    auth=True,
    GET=drf(adv.users_list),
    POST=drf(adv.user_create),
)


urlpatterns = [
    path("admin/django/", admin.site.urls),
    path("api/", api_root),

    # Auth
    path("api/auth/register", av.register),
    path("api/auth/login", av.login),
    path("api/auth/logout", av.logout),
    path("api/auth/me", av.me),
    path("api/auth/refresh", TokenRefreshView.as_view()),

    # Profile
    path("api/profile", cv.profile_dispatch),
    path("api/transporteur-stats", cv.transporteur_stats),

    # Colis (GET+POST)
    path("api/colis", colis_view),
    path("api/colis/mine", sv.colis_mine),
    path("api/colis/available", sv.colis_available),
    path("api/colis/<int:pk>", sv.colis_show),
    path("api/colis/<int:pk>/reservations", sv.colis_reservations),
    path("api/colis/<int:colis_id>/voyages-compatibles", ok_empty_list),

    # Suivi
    path("api/suivi/<str:numero>", sv.suivi_public),
    path("api/suivi", sv.suivi_store),

    # Voyages (GET+POST)
    path("api/voyages", voyages_view),
    path("api/voyages/available", sv.voyage_available),
    path("api/voyages/mine", sv.voyage_mine),

    # Réservations
    path("api/reservations", sv.reserver),
    path("api/reservations/recues", sv.reservations_recues),
    path("api/reservations/<int:pk>/action", sv.reservation_action),

    # Paiements
    path("api/paiements/colis/<int:colis_id>", sv.paiement_for_colis),
    path("api/paiements/<int:pk>/simuler", sv.paiement_simuler),
    path("api/paiements/<int:pk>/payer", sv.paiement_simuler),  # alias utilisé par le front/test
    path("api/paiements/<int:pk>/statut", adv.paiement_statut),

    # Retraits & wallet
    path("api/wallet", sv.wallet_me),
    path("api/me/wallet", sv.wallet_me),
    path("api/transporteur/solde", sv.wallet_me),
    path("api/retraits", sv.retrait_demander),
    path("api/transporteur/retraits", sv.retrait_demander),

    # Fiche transporteur publique
    path("api/transporteurs/<int:tid>", cv.transporteur_public),
    path("api/transporteurs/<int:tid>/avis", sv.avis_transporteur),

    # Avis
    path("api/avis", sv.avis_store),

    # Contact / Kkiapay
    path("api/contact", sv.contact_send),
    path("api/kkiapay/public-config", sv.kkiapay_config),
    path("api/webhooks/kkiapay", ok_json),
    path("api/admin-chat", ok_json),

    # ========== ADMIN ==========
    path("api/admin/stats", adv.stats),

    path("api/admin/users", admin_users_view),
    path("api/admin/users/<int:pk>/role", adv.user_update_role),
    path("api/admin/users/<int:pk>", adv.user_delete),

    path("api/admin/colis", adv.colis_list),
    path("api/admin/colis/<int:pk>/statut", adv.colis_statut),
    path("api/admin/colis/<int:pk>", adv.colis_delete),

    path("api/admin/voyages", adv.voyages_list),
    path("api/admin/voyages/<int:pk>/statut", adv.voyage_statut),
    path("api/admin/voyages/<int:pk>", adv.voyage_delete),

    path("api/admin/transporteurs", adv.transporteurs_list),
    path("api/admin/transporteurs/<int:pk>", adv.transporteur_delete),

    path("api/admin/paiements", adv.paiements_list),

    path("api/admin/livraisons", adv.livraisons_list),
    path("api/admin/suivi/<int:pk>/livraison", adv.livraison_decision),
    path("api/admin/suivi/<int:pk>", adv.suivi_delete),

    path("api/admin/avis", adv.avis_list),
    path("api/admin/avis/<int:pk>/statut", adv.avis_statut),
    path("api/admin/avis/<int:pk>", adv.avis_delete),

    path("api/admin/contact-messages", adv.contact_messages),

    path("api/admin/retraits", adv.retraits_list),
    path("api/admin/retraits/<int:pk>/payer", adv.retrait_payer),
    path("api/admin/retraits/<int:pk>/decision", adv.retrait_decision),
    path("api/admin/wallet", adv.wallet_admin),
    path("api/admin/kkiapay/status", adv.kkiapay_status),
    path("api/admin/notifications", ok_empty_list),
]

if settings.DEBUG:
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
