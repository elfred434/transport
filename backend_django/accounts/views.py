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
        "email_verified": bool(getattr(user, "email_verified", False)),
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
    # Envoi du code de vérification d'email (jamais bloquant)
    code_envoye = False
    try:
        from core.verification import envoyer_code_verification
        code_envoye = envoyer_code_verification(user)
    except Exception:
        import logging as _log
        _log.getLogger("accounts").exception("Échec envoi code vérification pour %s", user.email)
    # On NE LOGUE PAS l'utilisateur automatiquement : il doit d'abord vérifier son email.
    return api_success({
        "message": "Inscription réussie ! Un code de vérification a été envoyé à ton adresse email.",
        "email": user.email,
        "code_envoye": code_envoye,
        "email_verified": False,
        "verification_required": True,
    }, status_code=status.HTTP_201_CREATED)


class VerifyEmailThrottle(AnonRateThrottle):
    scope = "verify"


@api_view(["POST"])
@permission_classes([AllowAny])
@throttle_classes([VerifyEmailThrottle])
def verify_email(request: Request):
    """Vérifie l'email à partir du code envoyé (soit après inscription, soit via JWT)."""
    email = (request.data.get("email") or "").strip().lower()
    code = (request.data.get("code") or "").strip()
    # Si connecté, utiliser son email ; sinon exiger email+code (vérification à la sortie d'inscription)
    user = request.user if request.user.is_authenticated else None
    if not user:
        if not email:
            return api_error("Email requis", 400)
        from accounts.models import User as _U
        try:
            user = _U.objects.get(email=email)
        except _U.DoesNotExist:
            return api_error("Aucun compte pour cet email", 404)
    from core.verification import verifier_code
    ok, msg = verifier_code(user, code)
    if not ok:
        return api_error(msg, 400)
    # Si déjà connecté avec JWT, juste refresh le payload ; sinon générer tokens
    tokens = {}
    if request.user.is_authenticated:
        # Renvoyer un nouveau access token avec la valeur email_verified=True
        tokens = _auth_payload(user)
    return api_success({
        "message": msg,
        "email_verified": True,
        **tokens,
    })


@api_view(["POST"])
@permission_classes([AllowAny])
def resend_verification_code(request: Request):
    """Renvoyer le code de vérification (limité dans le temps)."""
    email = (request.data.get("email") or "").strip().lower()
    user = request.user if request.user.is_authenticated else None
    if not user:
        if not email:
            return api_error("Email requis", 400)
        from accounts.models import User as _U
        try:
            user = _U.objects.get(email=email)
        except _U.DoesNotExist:
            # Message générique pour ne pas dévoiler l'existence du compte
            return api_success({"message": "Si ce compte existe, un nouveau code a été envoyé."})
    if user.email_verified:
        return api_error("Cet email est déjà vérifié.", 400)
    # Anti-spam : interdire de renvoyer avant 60s
    from django.utils import timezone
    from datetime import timedelta
    if user.verification_code_expires and (user.verification_code_expires - timedelta(minutes=15 - 1)) > timezone.now():
        # Le code a été généré il y a moins de 60s
        return api_error("Attends au moins 60 secondes avant de demander un nouveau code", 429)
    ok = False
    try:
        from core.verification import envoyer_code_verification
        ok = envoyer_code_verification(user)
    except Exception:
        import logging as _log
        _log.getLogger("accounts").exception("Erreur renvoi code à %s", user.email)
    return api_success({"message": "Un nouveau code a été envoyé à ton adresse email.", "sent": ok})


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

    # Google a déjà vérifié l'email → on marque comme vérifié pour nos nouveaux users
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
        user.email_verified = True
        user.save(update_fields=["email_verified"])
        logger.info("Nouvel utilisateur Google créé (vérifié): %s", email)
    else:
        updates = {}
        if not user.google_id:
            updates["google_id"] = google_id
        if picture and not user.photo:
            updates["photo"] = picture[:500]
        # Si Google confirme l'email et que le compte ne l'est pas encore, le vérifier
        if email_verified and not user.email_verified:
            updates["email_verified"] = True
            updates["verification_code"] = ""
            updates["verification_code_expires"] = None
        if updates:
            for k, v in updates.items():
                setattr(user, k, v)
            user.save(update_fields=list(updates.keys()))

    return api_success(_auth_payload(user))
