from rest_framework import status
from rest_framework.decorators import api_view, permission_classes, throttle_classes
from rest_framework.permissions import AllowAny, IsAuthenticated
from rest_framework.request import Request
from rest_framework.throttling import AnonRateThrottle
from django.contrib.auth import authenticate
from django.conf import settings as dj_settings
from rest_framework_simplejwt.tokens import RefreshToken

import base64
import json
import logging
logger = logging.getLogger(__name__)
import os
import secrets

import requests as http_requests

from core.responses import api_success, api_error
from .models import User
from .serializers import UserRegisterSerializer, UserMeSerializer


logger = logging.getLogger(__name__)


class LoginThrottle(AnonRateThrottle):
    scope = "login"


class ResetThrottle(AnonRateThrottle):
    scope = "reset"

logger = logging.getLogger(__name__)


def _tokens_for(user: User):
    refresh = RefreshToken.for_user(user)
    return {
        "access": str(refresh.access_token),
        "refresh": str(refresh),
    }


def _auth_payload(user: User):
    return {
        "user": UserMeSerializer(user).data,
        "token": str(RefreshToken.for_user(user).access_token),
        "refresh": str(RefreshToken.for_user(user)),
        "role": user.role,
    }


@api_view(["POST"])
@permission_classes([AllowAny])
def register(request: Request):
    data = request.data
    # Détermine le rôle: par défaut client, ?role=transporteur lors d'inscription transporteur
    role = (data.get("role") or User.ROLE_CLIENT).strip()
    if role not in (User.ROLE_CLIENT, User.ROLE_TRANSPORTEUR):
        role = User.ROLE_CLIENT

    ser = UserRegisterSerializer(data=data)
    ser.is_valid(raise_exception=True)
    user = ser.save(role=role)
    # Crée automatiquement le profil Transporteur si le rôle est transporteur
    if role == User.ROLE_TRANSPORTEUR:
        from shipping.models import Transporteur
        Transporteur.objects.get_or_create(user=user)
    # Email de bienvenue (ne doit jamais casser l'inscription)
    try:
        from core.emails import envoyer_email_bienvenue
        envoyer_email_bienvenue(user)
    except Exception:
        import logging as _log
        _log.getLogger("accounts").exception("Échec email bienvenue pour %s", user.email)
    return api_success(_auth_payload(user), status_code=status.HTTP_201_CREATED)


@api_view(["POST"])
@permission_classes([AllowAny])
@throttle_classes([LoginThrottle])
def login(request: Request):
    email = (request.data.get("email") or "").strip().lower()
    password = request.data.get("password") or ""
    user = authenticate(request, email=email, password=password)
    if not user:
        # Essai manuel (quelques backends custom ignorent is_active)
        try:
            u = User.objects.get(email=email)
            if u.check_password(password) and u.is_active:
                user = u
        except User.DoesNotExist:
            user = None
    if not user:
        return api_error("Identifiants invalides", status.HTTP_401_UNAUTHORIZED)
    return api_success(_auth_payload(user))


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def logout(request: Request):
    """Blackliste refresh + access et supprime la session."""
    from rest_framework_simplejwt.token_blacklist.models import (
        BlacklistedToken, OutstandingToken,
    )
    from rest_framework_simplejwt.tokens import Token, AccessToken
    from rest_framework_simplejwt.exceptions import TokenError
    # Refresh token dans le body
    refresh = (request.data or {}).get("refresh")
    if refresh:
        try:
            RefreshToken(refresh).blacklist()
        except Exception:
            logger.exception("Logout: échec blacklist refresh")
    # Access token dans l'en-tête Authorization
    auth = request.headers.get("Authorization", "")
    if auth.startswith("Bearer "):
        access = auth[7:].strip()
        try:
            tok = AccessToken(access)
            jti = tok.get("jti")
            if jti:
                import datetime as _dt
                outstanding, _ = OutstandingToken.objects.get_or_create(
                    jti=jti,
                    defaults={
                        "token": access,
                        "user_id": tok.get("user_id"),
                        "expires_at": _dt.datetime.fromtimestamp(tok["exp"], tz=_dt.timezone.utc),
                    },
                )
                BlacklistedToken.objects.get_or_create(token=outstanding)
        except Exception:
            logger.exception("Logout: échec blacklist access")
    return api_success({"message": "Déconnexion réussie"})


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def me(request: Request):
    return api_success(UserMeSerializer(request.user).data)


# ---------- Google One Tap ----------
@api_view(["POST"])
@permission_classes([AllowAny])
def google_one_tap(request: Request):
    """POST /api/auth/google/one-tap — Vérifie le id_token Google,
    crée/connecte l'utilisateur et retourne un JWT interne."""
    credential = (request.data.get("credential") or request.data.get("id_token") or "").strip()
    if not credential:
        return api_error("Credential Google manquant", status.HTTP_400_BAD_REQUEST)

    skip_ssl = dj_settings.DEBUG or os.environ.get("GOOGLE_SKIP_SSL_VERIFY", "true").lower() == "true"
    payload = None
    try:
        verify = not skip_ssl
        resp = http_requests.get(
            "https://oauth2.googleapis.com/tokeninfo",
            params={"id_token": credential},
            timeout=10,
            verify=verify,
        )
        if resp.status_code == 200:
            payload = resp.json()
        else:
            logger.warning("Google tokeninfo échoué: %s %s", resp.status_code, resp.text[:200])
    except Exception as e:
        logger.warning("Google tokeninfo exception: %s", e)

    # Fallback dev : décoder JWT sans vérif signature
    allow_jwt_fallback = os.environ.get("GOOGLE_ALLOW_JWT_FALLBACK", "true").lower() == "true"
    if payload is None:
        if not (dj_settings.DEBUG or allow_jwt_fallback):
            return api_error("Vérification Google impossible (SSL/réseau)", status.HTTP_502_BAD_GATEWAY)
        try:
            parts = credential.split(".")
            if len(parts) != 3:
                return api_error("Token Google invalide", status.HTTP_401_UNAUTHORIZED)
            padded = parts[1] + "=" * (-len(parts[1]) % 4)
            payload = json.loads(base64.urlsafe_b64decode(padded))
        except Exception:
            logger.exception("Google JWT fallback: token invalide")
            return api_error("Token Google invalide", status.HTTP_401_UNAUTHORIZED)

    google_id = payload.get("sub")
    email = (payload.get("email") or "").strip().lower()
    email_verified = payload.get("email_verified")
    name = payload.get("name") or ""
    given_name = payload.get("given_name") or ""
    family_name = payload.get("family_name") or ""
    picture = payload.get("picture") or ""
    aud = payload.get("aud") or ""

    if not google_id or "@" not in email:
        return api_error("Payload Google incomplet", status.HTTP_401_UNAUTHORIZED)

    expected_aud = os.environ.get("GOOGLE_CLIENT_ID", "")
    if expected_aud and aud != expected_aud:
        return api_error("Audience Google invalide", status.HTTP_401_UNAUTHORIZED)

    if not email_verified and not dj_settings.DEBUG:
        return api_error("Email Google non vérifié", status.HTTP_401_UNAUTHORIZED)

    # Chercher par google_id puis par email
    user = User.objects.filter(google_id=google_id).first() \
        or User.objects.filter(email=email).first()

    if not user:
        prenom = given_name.strip() or (name.split(" ")[0] if name else "Google")
        nom = family_name.strip() or (name.split(" ")[-1] if name and " " in name else "User")
        user = User.objects.create_user(
            email=email,
            password=None,  # unusable
            nom=nom[:100],
            prenom=prenom[:100],
            google_id=google_id,
            role=User.ROLE_CLIENT,
            photo=picture[:500],
        )
        logger.info("Nouvel utilisateur Google créé: %s", email)
    else:
        updates = {}
        if not user.google_id:
            updates["google_id"] = google_id
        if picture and not user.photo:
            updates["photo"] = picture[:500]
        if updates:
            for k, v in updates.items():
                setattr(user, k, v)
            user.save(update_fields=list(updates.keys()))

    return api_success(_auth_payload(user))
