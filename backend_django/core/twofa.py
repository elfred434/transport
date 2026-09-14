"""2FA par email pour les admins (ROADMAP #9).

Workflow :
1. POST /api/auth/login avec email+mdp valides pour un admin →
   réponse 202 {require_2fa:true, challenge_id} + email avec code 6 chiffres.
2. POST /api/auth/2fa/verify {challenge_id, code} → 200 + cookies d'auth.

Pour les utilisateurs non-admin, le login fonctionne comme avant (pas de 2FA).
"""
from __future__ import annotations

import random
import string
import secrets
from datetime import timedelta

from django.conf import settings
from django.utils import timezone

CODE_TTL_MINUTES = 10
MAX_ATTEMPTS = 5
CODE_LENGTH = 6


def generate_code() -> str:
    return "".join(random.choices(string.digits, k=CODE_LENGTH))


def generate_challenge_id() -> str:
    return secrets.token_urlsafe(32)


def is_admin_user(user) -> bool:
    return bool(getattr(user, "is_staff", False)) or getattr(user, "role", "") in ("admin", "super_admin")


def send_2fa_email(user, code: str) -> bool:
    """Envoie le code 2FA par email. Retourne True si envoyé."""
    try:
        from django.core.mail import EmailMultiAlternatives
        app_name = getattr(settings, "APP_NAME", "SpiistMove")
        sender = getattr(settings, "DEFAULT_FROM_EMAIL", f"{app_name} <noreply@spiistmove.com>")
        subject = f"[SpiistMove] Code de connexion admin : {code}"
        text = (
            f"Bonjour {user.prenom},\n\n"
            f"Une tentative de connexion à l'espace d'administration de {app_name} "
            f"vient d'avoir lieu.\n\n"
            f"Code de vérification (valable {CODE_TTL_MINUTES} minutes) :\n\n"
            f"    {code}\n\n"
            f"Si ce n'est pas toi, change ton mot de passe immédiatement.\n\n"
            f"L'équipe {app_name}"
        )
        html = f"""<!doctype html><html><body style="font-family:Arial,sans-serif;color:#333;max-width:560px;margin:24px auto">
<h2 style="color:#c0392b">🔐 Code de connexion admin</h2>
<p>Bonjour <b>{user.prenom}</b>,</p>
<p>Une tentative de connexion à l'espace d'administration vient d'avoir lieu.</p>
<p style="font-size:32px;font-weight:700;letter-spacing:8px;text-align:center;background:#f8f9fa;padding:16px;border-radius:8px;font-family:monospace">{code}</p>
<p style="font-size:13px;color:#888">Valable {CODE_TTL_MINUTES} minutes. Si ce n'est pas toi, change ton mot de passe immédiatement.</p>
</body></html>"""
        msg = EmailMultiAlternatives(subject, text, sender, [user.email])
        msg.attach_alternative(html, "text/html")
        msg.send(fail_silently=True)
        return True
    except Exception:
        import logging
        logging.getLogger(__name__).exception("Échec envoi email 2FA à %s", user.email)
        return False


def start_2fa_challenge(user) -> str:
    """Crée un challenge 2FA pour l'utilisateur. Retourne le challenge_id.
    Ne retourne PAS le code (envoyé par email uniquement)."""
    code = generate_code()
    cid = generate_challenge_id()
    user.twofa_code = code
    user.twofa_expires = timezone.now() + timedelta(minutes=CODE_TTL_MINUTES)
    user.twofa_attempts = 0
    user.twofa_pending_token = cid
    user.save(update_fields=["twofa_code", "twofa_expires", "twofa_attempts", "twofa_pending_token"])
    send_2fa_email(user, code)
    return cid


def verify_2fa(user, challenge_id: str, code: str) -> tuple[bool, str]:
    """Vérifie le code 2FA. Retourne (ok, message). Si ok, efface le challenge."""
    if user.twofa_pending_token != challenge_id:
        return False, "Challenge invalide ou expiré."
    now = timezone.now()
    if not user.twofa_expires or now > user.twofa_expires:
        _clear_2fa(user)
        return False, "Code expiré. Recommence la connexion."
    if user.twofa_attempts >= MAX_ATTEMPTS:
        _clear_2fa(user)
        return False, "Trop de tentatives. Recommence la connexion."
    user.twofa_attempts = (user.twofa_attempts or 0) + 1
    if not secrets.compare_digest(str(user.twofa_code or ""), str(code or "").strip()):
        user.save(update_fields=["twofa_attempts"])
        remaining = MAX_ATTEMPTS - user.twofa_attempts
        return False, f"Code incorrect ({remaining} essais restants)."
    _clear_2fa(user)
    return True, "OK"


def _clear_2fa(user) -> None:
    user.twofa_code = ""
    user.twofa_expires = None
    user.twofa_attempts = 0
    user.twofa_pending_token = ""
    user.save(update_fields=["twofa_code", "twofa_expires", "twofa_attempts", "twofa_pending_token"])
