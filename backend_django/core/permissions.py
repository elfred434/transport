"""Permissions DRF personnalisées."""
from rest_framework.permissions import BasePermission
from rest_framework.exceptions import PermissionDenied


class IsAdmin(BasePermission):
    def has_permission(self, request, view):
        u = request.user
        if not (u and u.is_authenticated):
            return False
        if callable(getattr(u, "is_admin", None)):
            return bool(u.is_admin())
        return bool(getattr(u, "is_admin", False))


class IsSuperAdmin(BasePermission):
    def has_permission(self, request, view):
        u = request.user
        if not (u and u.is_authenticated):
            return False
        if callable(getattr(u, "is_super_admin", None)):
            return bool(u.is_super_admin())
        return bool(getattr(u, "is_super_admin", False) or getattr(u, "is_superuser", False))


# Endpoints accessibles SANS vérification d'email (après connexion JWT)
_VERIFICATION_WHITELIST = {
    "verify-email",
    "resend-code",
    "me",
    "logout",
    "refresh",
}


class RequireVerifiedEmail(BasePermission):
    """Permission globale: tout utilisateur authentifié non vérifié est bloqué
    sauf sur les endpoints de vérification eux-mêmes. Admins sont contournés."""

    def has_permission(self, request, view):
        user = getattr(request, "user", None)
        if not user or not user.is_authenticated:
            return True
        # Contournement admin
        if getattr(user, "is_staff", False) or user.is_superuser:
            return True
        is_admin = False
        if callable(getattr(user, "is_admin", None)) and user.is_admin():
            is_admin = True
        if callable(getattr(user, "is_super_admin", None)) and user.is_super_admin():
            is_admin = True
        if is_admin:
            return True
        path = request.path.rstrip("/").split("/")[-1]
        if path in _VERIFICATION_WHITELIST:
            return True
        if getattr(user, "email_verified", False):
            return True
        raise PermissionDenied({
            "code": "EMAIL_NOT_VERIFIED",
            "message": "Tu dois vérifier ton adresse email avant d'accéder à cette ressource.",
            "email": user.email,
        })
