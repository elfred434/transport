"""Bootstrap endpoint (PROD-SAFE).

Historique : cet endpoint permettait de créer le premier super_admin lors du
setup initial, en prouvant qu'on connaissait SECRET_KEY via le header
X-Bootstrap-Key. En PROD il est DÉSACTIVÉ PAR DÉFAUT : tout appel renvoie 410
Gone et est logué en WARNING avec IP + User-Agent, à moins que
ALLOW_BOOTSTRAP=true ne soit défini (valeur à ne mettre QUE le temps de
créer le premier admin, puis à supprimer immédiatement).
"""
import traceback
from django.conf import settings
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import AllowAny
from rest_framework.request import Request

from accounts.models import User
from core.responses import api_success, api_error
import logging
logger = logging.getLogger(__name__)


def _client_ip(request: Request) -> str:
    """Récupère l'IP réelle du client (derrière Render/Cloudflare)."""
    xff = request.headers.get("x-forwarded-for", "")
    if xff:
        return xff.split(",")[0].strip()
    return request.META.get("REMOTE_ADDR", "") or ""


@api_view(["POST"])
@permission_classes([AllowAny])
def bootstrap_superadmin(request: Request):
    allow_bootstrap = (
        settings.DEBUG
        or str(getattr(settings, "ALLOW_BOOTSTRAP", "false")).lower() == "true"
    )

    if not allow_bootstrap:
        ip = _client_ip(request)
        ua = request.headers.get("user-agent", "")
        logger.warning(
            "BOOTSTRAP REFUSÉ en production (ip=%s ua=%s). "
            "Pour réactiver ponctuellement, mettre ALLOW_BOOTSTRAP=true, créer le "
            "superadmin puis retirer la variable.",
            ip, ua,
        )
        return api_error(
            "Endpoint désactivé en production. Contacter l'administrateur.",
            status_code=410,
        )

    try:
        provided = request.headers.get("X-Bootstrap-Key", "")
        if not provided or provided != settings.SECRET_KEY:
            logger.warning(
                "BOOTSTRAP clé invalide (ip=%s ua=%s)",
                _client_ip(request), request.headers.get("user-agent", ""),
            )
            return api_error("Clé bootstrap invalide.", status_code=401)

        if User.objects.filter(role=User.ROLE_SUPER_ADMIN).exists():
            logger.warning(
                "BOOTSTRAP tentative alors qu'un super_admin existe déjà (ip=%s)",
                _client_ip(request),
            )
            return api_error(
                "Un super_admin existe déjà. Utilisez l'interface d'administration.",
                status_code=400,
            )

        data = request.data or {}
        email = (data.get("email") or "").strip().lower()
        password = data.get("password") or ""
        nom = (data.get("nom") or "").strip()
        prenom = (data.get("prenom") or "").strip()
        telephone = (data.get("telephone") or "").strip()

        if not email or not password or not nom or not prenom:
            return api_error("Champs requis: email, password, nom, prenom.", status_code=400)
        if len(password) < 12:
            return api_error("Mot de passe trop court (min 12 caractères pour un superadmin).", status_code=400)
        if User.objects.filter(email=email).exists():
            return api_error("Un utilisateur avec cet email existe déjà.", status_code=400)

        user = User.objects.create_superuser(email=email, password=password)
        user.nom = nom
        user.prenom = prenom
        user.telephone = telephone
        user.role = User.ROLE_SUPER_ADMIN
        user.is_staff = True
        user.is_superuser = True
        user.is_active = True
        user.email_verified = True
        user.save()

        logger.critical(
            "BOOTSTRAP: NOUVEAU super_admin créé: %s (ip=%s ua=%s)",
            email, _client_ip(request), request.headers.get("user-agent", ""),
        )
        return api_success({"id": user.id, "email": user.email, "role": user.role}, status_code=201)
    except Exception as e:
        tb = traceback.format_exc()
        logger.error("BOOTSTRAP ERROR: %s\n%s", e, tb)
        return api_error(f"Erreur: {e}", status_code=500)
