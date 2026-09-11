"""Réinitialisation de mot de passe via token BDD + email SMTP.

Fonctionnement :
  1. POST /api/auth/reset-request { email } → crée un token unique en BDD,
     envoie un email si un backend SMTP est configuré ; sinon le lien est
     renvoyé dans la réponse JSON (uniquement quand DEBUG=True).
  2. L'utilisateur clique sur /reset-password?token=...
  3. POST /api/auth/reset-password { token, password } → valide et met à jour.

Configuration email (variables d'environnement) :
  EMAIL_HOST, EMAIL_PORT, EMAIL_HOST_USER, EMAIL_HOST_PASSWORD,
  EMAIL_USE_TLS, DEFAULT_FROM_EMAIL.
Si aucune configuration n'est présente en mode DEBUG, l'envoi est simulé et
le lien apparaît dans la réponse.
"""
from __future__ import annotations

import secrets
import time
from datetime import timedelta

from django.conf import settings
from django.contrib.auth.hashers import make_password
from django.core.mail import send_mail
from django.utils import timezone
from rest_framework.decorators import api_view, permission_classes, throttle_classes
from rest_framework.permissions import AllowAny
from rest_framework.request import Request
from rest_framework.throttling import AnonRateThrottle

from accounts.models import PasswordResetToken, User
from core.responses import api_success, api_error


TOKEN_TTL_SECONDS = 3600  # 1h


class ResetThrottle(AnonRateThrottle):
    scope = "reset"


def _email_configured() -> bool:
    return bool(getattr(settings, "EMAIL_HOST", None))


@api_view(["POST"])
@permission_classes([AllowAny])
@throttle_classes([ResetThrottle])
def reset_request(request: Request):
    email = (request.data.get("email") or "").strip().lower()
    # Nettoyage tokens expirés (toutes les 100 requêtes max)
    if secrets.randbelow(100) == 0:
        PasswordResetToken.objects.filter(expires_at__lt=timezone.now()).delete()

    if "@" not in email:
        return api_error("Adresse email invalide", 422)

    # Toujours répondre le même message pour éviter la détection d'existence
    generic = {"message": "Si ce compte existe, un email de réinitialisation vous a été envoyé.", "dev_reset_link": None}

    try:
        user = User.objects.get(email=email)
    except User.DoesNotExist:
        return api_success(generic)

    token = secrets.token_urlsafe(40)
    PasswordResetToken.objects.create(
        token=token,
        user=user,
        expires_at=timezone.now() + timedelta(seconds=TOKEN_TTL_SECONDS),
    )
    reset_link = f"{getattr(settings, 'APP_FRONTEND_URL', '')}/reset-password?token={token}"

    dev_link = None
    if _email_configured():
        try:
            send_mail(
                subject=f"{getattr(settings, 'APP_NAME', 'SpiistMove')} — Réinitialisation de votre mot de passe",
                message=(
                    f"Bonjour,\n\n"
                    f"Vous avez demandé la réinitialisation de votre mot de passe sur "
                    f"{getattr(settings, 'APP_NAME', 'SpiistMove')}.\n\n"
                    f"Cliquez sur ce lien (valable 1 heure) :\n{reset_link}\n\n"
                    f"Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\n"
                    f"L'équipe {getattr(settings, 'APP_NAME', 'SpiistMove')}"
                ),
                from_email=getattr(settings, "DEFAULT_FROM_EMAIL", "noreply@spiistmove.local"),
                recipient_list=[email],
                fail_silently=False,
            )
        except Exception as exc:  # pragma: no cover
            # Ne pas casser la requête si le SMTP est en panne :
            # en mode DEBUG, on renvoie le lien directement.
            if settings.DEBUG:
                dev_link = reset_link
    else:
        # Pas de SMTP configuré → en dev on expose le lien.
        if settings.DEBUG:
            dev_link = reset_link

    generic["dev_reset_link"] = dev_link
    return api_success(generic)


@api_view(["POST"])
@permission_classes([AllowAny])
def reset_password(request: Request):
    token = (request.data.get("token") or "").strip()
    password = request.data.get("password") or ""
    if not token:
        return api_error("Lien invalide ou expiré", 400)
    try:
        prt = PasswordResetToken.objects.select_related("user").get(token=token, used=False)
    except PasswordResetToken.DoesNotExist:
        return api_error("Lien invalide ou expiré", 400)
    if timezone.now() > prt.expires_at:
        prt.used = True
        prt.save(update_fields=["used"])
        return api_error("Lien expiré", 400)
    if len(password) < 8 or password.isdigit() or password.isalpha():
        return api_error("Le mot de passe doit contenir au moins 8 caractères, avec lettres ET chiffres", 422)
    prt.user.password = make_password(password)
    prt.user.save(update_fields=["password"])
    prt.used = True
    prt.save(update_fields=["used"])
    # Invalide tous les autres tokens de ce même utilisateur
    PasswordResetToken.objects.filter(user=prt.user, used=False).exclude(pk=prt.pk).update(used=True)
    return api_success({"message": "Mot de passe mis à jour. Vous pouvez vous connecter."})
