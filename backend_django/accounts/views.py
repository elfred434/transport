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

from core.responses import api_success, api_error
from .models import User
from .serializers import UserRegisterSerializer, UserMeSerializer


class LoginThrottle(AnonRateThrottle):
    scope = "login"


class ResetThrottle(AnonRateThrottle):
    scope = "reset"


class ResendThrottle(AnonRateThrottle):
    scope = "resend"


def _tokens_for(user: User):
    refresh = RefreshToken.for_user(user)
    return {
        "access": str(refresh.access_token),
        "refresh": str(refresh),
    }


def _auth_payload(user: User):
    refresh = RefreshToken.for_user(user)
    return {
        "user": UserMeSerializer(user).data,
        "token": str(refresh.access_token),
        "refresh": str(refresh),
        "role": user.role,
        "email_verified": bool(getattr(user, "email_verified", False)),
    }


def _auth_response(user: User, extra: dict | None = None, status_code: int = 200):
    """Retourne une Response avec les JWT dans le JSON ET dans des cookies HttpOnly.

    Les cookies sont la forme XSS-safe ; le JSON est conservé pour la rétrocompatibilité
    avec les clients qui liraient encore localStorage. Le frontend doit migrer vers
    la lecture des cookies (credentials: include, plus de lecture de 'transport_token').
    """
    from rest_framework.response import Response
    from core.cookies import set_auth_cookies
    payload = _auth_payload(user)
    data = {"success": True, "data": {**payload, **(extra or {})}}
    resp = Response(data, status=status_code)
    set_auth_cookies(resp, access=payload["token"], refresh=payload["refresh"])
    return resp


@api_view(["POST"])
@permission_classes([AllowAny])
def register(request: Request):
    data = request.data
    # Détermine le rôle: par défaut client, ?role=transporteur lors d'inscription transporteur
    role = (data.get("role") or User.ROLE_CLIENT).strip()
    if role not in (User.ROLE_CLIENT, User.ROLE_TRANSPORTEUR):
        role = User.ROLE_CLIENT

    email = (data.get("email") or "").strip().lower()

    # Anti-énumération (H-3): si l'email existe déjà, envoyer un email "tu as déjà un compte"
    # mais retourner la même réponse qu'une inscription réussie.
    existing = User.objects.filter(email=email).first() if email else None
    if existing:
        try:
            _envoyer_email_compte_existant(existing)
        except Exception:
            logger.exception("Échec envoi email compte-existant à %s", email)
        return api_success({
            "message": "Inscription réussie ! Un code de vérification a été envoyé à ton adresse email.",
            "email": email,
            "code_envoye": True,
            "email_verified": False,
            "verification_required": True,
        }, status_code=status.HTTP_201_CREATED)

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
        logger.exception("Échec envoi code vérification pour %s", user.email)
    # On NE LOGUE PAS l'utilisateur automatiquement : il doit d'abord vérifier son email.
    return api_success({
        "message": "Inscription réussie ! Un code de vérification a été envoyé à ton adresse email.",
        "email": user.email,
        "code_envoye": code_envoye,
        "email_verified": False,
        "verification_required": True,
    }, status_code=status.HTTP_201_CREATED)


def _envoyer_email_compte_existant(user) -> None:
    """Envoie un email discret à l'utilisateur qui tente de s'inscrire avec un email déjà pris."""
    from django.conf import settings
    from django.core.mail import EmailMultiAlternatives
    from django.utils import timezone
    app_name = getattr(settings, "APP_NAME", "SpiistMove")
    sender = getattr(settings, "DEFAULT_FROM_EMAIL", f"{app_name} <noreply@spiistmove.com>")
    frontend = (getattr(settings, "APP_FRONTEND_URL", "") or "").rstrip("/")
    reset_url = f"{frontend}/reset-request" if frontend else "#"
    subject = f"Tentative d'inscription sur {app_name}"
    text = (
        f"Bonjour,\n\n"
        f"Quelqu'un (peut-être toi) a tenté de créer un compte sur {app_name} avec cette adresse email, "
        f"mais un compte existe déjà.\n\n"
        f"Si c'est toi : tu peux te connecter directement ou demander une réinitialisation de mot de passe :\n"
        f"{reset_url}\n\n"
        f"Si ce n'est pas toi, tu peux ignorer cet email en toute sécurité.\n\n"
        f"L'équipe {app_name}"
    )
    html = f"""<!doctype html><html><body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#333">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
<tr><td style="background:#3498db;padding:24px 32px;text-align:center;color:#fff">
<div style="font-size:24px;font-weight:700">🔐 {app_name}</div>
</td></tr>
<tr><td style="padding:32px;font-size:15px;line-height:1.65">
<p>Bonjour,</p>
<p>Quelqu'un a tenté de créer un compte sur {app_name} avec cette adresse email, mais un compte <strong>existe déjà</strong>.</p>
<p>Si c'est toi, tu peux te connecter directement ou <a href="{reset_url}">réinitialiser ton mot de passe</a>.</p>
<p style="font-size:13px;color:#888">Si ce n'est pas toi, ignore simplement cet email.</p>
</td></tr>
<tr><td style="background:#f5f7fa;padding:18px 32px;text-align:center;color:#888;font-size:12px">© {timezone.now().year} {app_name}</td></tr>
</table></td></tr></table></body></html>"""
    msg = EmailMultiAlternatives(subject, text, sender, [user.email])
    msg.attach_alternative(html, "text/html")
    msg.send(fail_silently=True)


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
    from rest_framework.response import Response
    ok, msg, retry_after = verifier_code(user, code)
    if not ok:
        status_code = 429 if retry_after else 400
        headers = {"Retry-After": str(retry_after)} if retry_after else {}
        return Response({"success": False, "error": msg}, status=status_code, headers=headers)
    # Utilisateur vérifié : émettre tokens dans cookies (+ JSON rétrocompatible)
    return _auth_response(user, extra={"message": msg, "email_verified": True})


@api_view(["POST"])
@permission_classes([AllowAny])
@throttle_classes([ResendThrottle])
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
    return _auth_response(user)


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def logout(request: Request):
    """Blackliste refresh + access et supprime la session + cookies."""
    from rest_framework.response import Response
    from rest_framework_simplejwt.token_blacklist.models import (
        BlacklistedToken, OutstandingToken,
    )
    from rest_framework_simplejwt.tokens import AccessToken
    from core.cookies import clear_auth_cookies
    # Refresh token dans le body OU dans le cookie
    refresh = (request.data or {}).get("refresh") or request.COOKIES.get("refresh_token")
    if refresh:
        try:
            RefreshToken(refresh).blacklist()
        except Exception:
            logger.exception("Logout: échec blacklist refresh")
    # Access token
    auth = request.META.get("HTTP_AUTHORIZATION", "")
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
    resp = Response({"success": True, "data": {"message": "Déconnexion réussie"}})
    clear_auth_cookies(resp)
    return resp


# --- Refresh custom qui lit le refresh depuis le cookie et repose les cookies ---
from rest_framework import status as _status


@api_view(["POST"])
@permission_classes([AllowAny])
def token_refresh(request: Request):
    """POST /api/auth/refresh — accepte refresh dans le body OU dans le cookie refresh_token.

    Retourne les nouveaux tokens dans le JSON ET dans des cookies HttpOnly.
    """
    from rest_framework.response import Response
    from core.cookies import set_auth_cookies
    refresh = (request.data or {}).get("refresh") or request.COOKIES.get("refresh_token")
    if not refresh:
        return Response({"success": False, "error": "Refresh token requis"}, status=_status.HTTP_400_BAD_REQUEST)
    try:
        token = RefreshToken(refresh)
        if getattr(token, "blacklist_after_rotation", None) or True:
            try:
                token.blacklist()
            except Exception:
                pass
        new_access = str(token.access_token)
        new_refresh = str(token)
        data = {
            "success": True,
            "data": {"access": new_access, "refresh": new_refresh},
        }
        resp = Response(data, status=200)
        set_auth_cookies(resp, access=new_access, refresh=new_refresh)
        return resp
    except Exception as exc:
        logger.warning("Refresh invalide: %s", exc)
        # Nettoyer les cookies invalides
        from core.cookies import clear_auth_cookies
        resp = Response({"success": False, "error": "Session expirée"}, status=_status.HTTP_401_UNAUTHORIZED)
        clear_auth_cookies(resp)
        return resp


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def me(request: Request):
    return api_success(UserMeSerializer(request.user).data)


# ---------- Google One Tap ----------
@api_view(["POST"])
@permission_classes([AllowAny])
def google_one_tap(request: Request):
    """POST /api/auth/google/one-tap — Vérifie le id_token Google via JWKS,
    crée/connecte l'utilisateur et retourne un JWT interne (cookies HttpOnly)."""
    credential = (request.data.get("credential") or request.data.get("id_token") or "").strip()
    if not credential:
        return api_error("Credential Google manquant", status.HTTP_400_BAD_REQUEST)

    expected_aud = os.environ.get("GOOGLE_CLIENT_ID", "")
    payload = None

    # 1) Vérification JWKS (sécurisée, en PROD comme en DEV si une clé client est configurée)
    if expected_aud:
        try:
            from core.google_jwt import verify_google_id_token
            payload = verify_google_id_token(credential, expected_aud)
        except Exception as e:
            logger.warning("Erreur vérification JWKS Google: %s", e)

    # 2) Fallback DEV UNIQUEMENT (décodage sans signature) : jamais en prod
    allow_dev_fallback = dj_settings.DEBUG and os.environ.get("GOOGLE_ALLOW_JWT_FALLBACK", "false").lower() == "true"
    if payload is None and allow_dev_fallback:
        logger.warning("Google One Tap: fallback DEV sans signature activé (NE PAS utiliser en prod)")
        try:
            parts = credential.split(".")
            if len(parts) == 3:
                padded = parts[1] + "=" * (-len(parts[1]) % 4)
                decoded = json.loads(base64.urlsafe_b64decode(padded))
                # Audience validation even in fallback
                if not expected_aud or decoded.get("aud") == expected_aud:
                    payload = decoded
        except Exception:
            logger.exception("Google JWT fallback dev: token invalide")

    if payload is None:
        return api_error(
            "Vérification Google impossible. Assure-toi d'utiliser un compte Google valide.",
            status.HTTP_401_UNAUTHORIZED,
        )

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

    return _auth_response(user)
