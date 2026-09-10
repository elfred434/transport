"""Réinitialisation de mot de passe (mode dev : lien renvoyé dans la réponse)."""
import secrets
import time

from django.contrib.auth.hashers import make_password
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import AllowAny
from rest_framework.request import Request

from accounts.models import User
from core.responses import api_success, api_error


# Stockage en mémoire (clé=token) : suffisant pour dev/test.
# Structure {token: (user_id, expire_ts)}
_TOKENS: dict[str, tuple[int, float]] = {}
TOKEN_TTL_SECONDS = 3600  # 1h


@api_view(["POST"])
@permission_classes([AllowAny])
def reset_request(request: Request):
    email = (request.data.get("email") or "").strip().lower()
    if "@" not in email:
        return api_error("Adresse email invalide", 422)
    try:
        user = User.objects.get(email=email)
    except User.DoesNotExist:
        # Pour ne pas fuiter les emails existants, renvoyer le même message
        return api_success({
            "message": "Si ce compte existe, un email de réinitialisation vous a été envoyé.",
            "dev_reset_link": None,
        })
    token = secrets.token_urlsafe(32)
    _TOKENS[token] = (user.id, time.time() + TOKEN_TTL_SECONDS)
    # Nettoyage des tokens expirés
    now = time.time()
    for t in [k for k, v in _TOKENS.items() if v[1] < now]:
        _TOKENS.pop(t, None)
    return api_success({
        "message": "Si ce compte existe, un email de réinitialisation vous a été envoyé.",
        # En dev: on renvoie directement le lien pour faciliter les tests.
        "dev_reset_link": f"/reset-password?token={token}",
    })


@api_view(["POST"])
@permission_classes([AllowAny])
def reset_password(request: Request):
    token = (request.data.get("token") or "").strip()
    password = request.data.get("password") or ""
    if not token or token not in _TOKENS:
        return api_error("Lien invalide ou expiré", 400)
    user_id, exp = _TOKENS[token]
    if time.time() > exp:
        _TOKENS.pop(token, None)
        return api_error("Lien expiré", 400)
    if len(password) < 6:
        return api_error("Mot de passe trop court (6 caractères minimum)", 422)
    try:
        user = User.objects.get(pk=user_id)
    except User.DoesNotExist:
        _TOKENS.pop(token, None)
        return api_error("Utilisateur introuvable", 404)
    user.password = make_password(password)
    user.save(update_fields=["password"])
    _TOKENS.pop(token, None)
    return api_success({"message": "Mot de passe mis à jour. Vous pouvez vous connecter."})
