"""Bootstrap endpoint: create the first super_admin using Django SECRET_KEY.
Usage (one-shot, retirer après usage) :
  POST /api/bootstrap/superadmin
  Header: X-Bootstrap-Key: <SECRET_KEY>
  Body: {"email":"...","password":"...","nom":"...","prenom":"...","telephone":"..."}
Ne fonctionne que si AUCUN super_admin n'existe déjà dans la base.
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


@api_view(["POST"])
@permission_classes([AllowAny])
def bootstrap_superadmin(request: Request):
    try:
        # Vérification du secret
        provided = request.headers.get("X-Bootstrap-Key", "")
        if not provided or provided != settings.SECRET_KEY:
            return api_error("Clé bootstrap invalide.", status_code=401)

        if User.objects.filter(role=User.ROLE_SUPER_ADMIN).exists():
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
        if len(password) < 8:
            return api_error("Mot de passe trop court (min 8 caractères).", status_code=400)
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

        logger.warning("BOOTSTRAP: super_admin créé: %s", email)
        return api_success({"id": user.id, "email": user.email, "role": user.role}, status_code=201)
    except Exception as e:
        tb = traceback.format_exc()
        logger.error("BOOTSTRAP ERROR: %s\n%s", e, tb)
        return api_error(f"Erreur: {e}", status_code=500)
