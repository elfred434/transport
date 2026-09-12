"""Authentification JWT qui bloque les comptes à email non vérifié
(à l'exception des endpoints whitelistés explicitement)."""
from rest_framework_simplejwt.authentication import JWTAuthentication
from rest_framework_simplejwt.exceptions import AuthenticationFailed
from rest_framework.permissions import BasePermission
from rest_framework.exceptions import PermissionDenied
from django.conf import settings


# Endpoints où l'email non vérifié est autorisé (même avec JWT)
WHITELISTED_SUFFIXES = {
    "/api/auth/verify-email",
    "/api/auth/resend-code",
    "/api/auth/me",
    "/api/auth/logout",
    "/api/auth/refresh",
}


class VerifiedJWTAuthentication(JWTAuthentication):
    """Après validation du JWT, refuse l'accès si email_verified=False,
    SAUF sur les endpoints de vérification eux-mêmes et admins."""

    def authenticate(self, request):
        result = super().authenticate(request)
        if result is None:
            return None
        user, token = result
        # Admins / super admin
        if getattr(user, "is_staff", False) or user.is_superuser:
            return (user, token)
        if callable(getattr(user, "is_admin", None)) and user.is_admin():
            return (user, token)
        if callable(getattr(user, "is_super_admin", None)) and user.is_super_admin():
            return (user, token)
        # Whitelist endpoints
        path = request.path.rstrip("/")
        if path in WHITELISTED_SUFFIXES:
            return (user, token)
        # Routes reset password publiques mais avec JWT éventuel : whitelist
        if path.startswith("/api/auth/reset-"):
            return (user, token)
        # Routes admin
        if path.startswith("/api/admin/") or path.startswith("/admin/django/"):
            return (user, token)
        # Utilisateur non vérifié
        if not getattr(user, "email_verified", False):
            raise PermissionDenied({
                "code": "EMAIL_NOT_VERIFIED",
                "message": "Tu dois vérifier ton adresse email avant d'accéder à cette ressource.",
                "email": user.email,
            })
        return (user, token)
