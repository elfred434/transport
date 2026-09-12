"""Vérification d'email à l'inscription : code à 6 chiffres, valable 15 minutes.

Sécurisé contre la force brute :
  - 5 essais maximum par code → invalidation du code + verrou 15 min
  - Incrémentation du compteur à chaque échec, réinitialisé au succès ou à l'envoi d'un nouveau code
  - Le code est invalidé (renvoi nécessaire) après le 5e échec
"""
from __future__ import annotations

import logging
import secrets
from datetime import timedelta

from django.utils import timezone

logger = logging.getLogger("verification")

CODE_TTL_MINUTES = 15
CODE_LENGTH = 6
MAX_ATTEMPTS = 5
LOCKOUT_MINUTES = 15


def generer_code() -> str:
    """Génère un code numérique à 6 chiffres (jamais 000000)."""
    return str(secrets.randbelow(10 ** CODE_LENGTH)).zfill(CODE_LENGTH)


def attribuer_code(user) -> str:
    """Génère un code et l'enregistre sur l'utilisateur. Retourne le code.

    Réinitialise aussi les compteurs d'échecs et le verrou.
    """
    code = generer_code()
    user.verification_code = code
    user.verification_code_expires = timezone.now() + timedelta(minutes=CODE_TTL_MINUTES)
    user.verification_attempts = 0
    user.verification_locked_until = None
    user.email_verified = False
    user.save(update_fields=[
        "verification_code",
        "verification_code_expires",
        "verification_attempts",
        "verification_locked_until",
        "email_verified",
    ])
    return code


def verifier_code(user, code: str) -> tuple[bool, str, int | None]:
    """Vérifie le code fourni. Retourne (ok, message, retry_after_seconds).

    retry_after_seconds est renseigné en cas de verrou (HTTP 429).
    """
    if user.email_verified:
        return True, "Email déjà vérifié", None
    if not code or not str(code).strip():
        return False, "Code requis", None
    code = str(code).strip()
    if len(code) != CODE_LENGTH or not code.isdigit():
        return False, "Format de code invalide (6 chiffres attendus)", None

    # Verrou de tentatives dépassé
    if user.verification_locked_until and timezone.now() < user.verification_locked_until:
        seconds_left = int((user.verification_locked_until - timezone.now()).total_seconds())
        return False, "Trop de tentatives. Demande un nouveau code.", max(1, seconds_left)

    if not user.verification_code or not user.verification_code_expires:
        return False, "Aucun code en attente. Demande un nouveau code.", None
    if timezone.now() > user.verification_code_expires:
        return False, "Code expiré. Demande un nouveau code.", None

    if code != user.verification_code:
        user.verification_attempts = (user.verification_attempts or 0) + 1
        retry_after = None
        if user.verification_attempts >= MAX_ATTEMPTS:
            # Invalider le code et verrouiller
            user.verification_code = ""
            user.verification_code_expires = None
            user.verification_locked_until = timezone.now() + timedelta(minutes=LOCKOUT_MINUTES)
            retry_after = LOCKOUT_MINUTES * 60
            msg = "Trop de tentatives incorrectes. Un nouveau code est nécessaire."
        else:
            remaining = MAX_ATTEMPTS - user.verification_attempts
            msg = f"Code incorrect. {remaining} essai{'s' if remaining > 1 else ''} restant{'s' if remaining > 1 else ''}."
        user.save(update_fields=[
            "verification_attempts",
            "verification_code",
            "verification_code_expires",
            "verification_locked_until",
        ])
        return False, msg, retry_after

    # OK : marquer vérifié
    user.email_verified = True
    user.verification_code = ""
    user.verification_code_expires = None
    user.verification_attempts = 0
    user.verification_locked_until = None
    user.save(update_fields=[
        "email_verified",
        "verification_code",
        "verification_code_expires",
        "verification_attempts",
        "verification_locked_until",
    ])
    return True, "Email vérifié avec succès", None


def envoyer_code_verification(user) -> bool:
    """Envoie l'email avec le code. Retourne True si l'email est parti."""
    code = attribuer_code(user)
    app_name = "SpiistMove"
    try:
        from django.conf import settings
        from django.core.mail import EmailMultiAlternatives
        app_name = getattr(settings, "APP_NAME", "SpiistMove")
        sender = getattr(settings, "DEFAULT_FROM_EMAIL", f"{app_name} <noreply@spiistmove.com>")
        subject = f"Ton code de vérification {app_name}"
        text = (
            f"Bonjour,\n\n"
            f"Voici ton code de vérification pour activer ton compte {app_name} :\n\n"
            f"              {code}\n\n"
            f"Ce code est valable {CODE_TTL_MINUTES} minutes.\n"
            f"Attention : après {MAX_ATTEMPTS} codes erronés, tu devras demander un nouveau code.\n"
            f"Si tu n'es pas à l'origine de cette demande, ignore cet email.\n\n"
            f"L'équipe {app_name}"
        )
        digits_html = "".join(
            f'<span style="display:inline-block;padding:14px 18px;margin:4px;background:#f0f7ff;border-radius:8px;border:2px solid #3498db;font-size:28px;font-weight:700;color:#3498db;letter-spacing:6px;font-family:monospace">{c}</span>'
            for c in code
        )
        html = f"""<!doctype html><html><body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#333">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
<tr><td style="background:#3498db;padding:24px 32px;text-align:center;color:#fff">
<div style="font-size:24px;font-weight:700">🔐 {app_name}</div>
<div style="font-size:13px;opacity:.85;margin-top:4px">Vérification de ton adresse email</div>
</td></tr>
<tr><td style="padding:32px;font-size:15px;line-height:1.65">
<p>Bonjour,</p>
<p>Utilise le code ci-dessous pour confirmer ton adresse email et accéder à ton compte :</p>
<p style="text-align:center;margin:28px 0">{digits_html}</p>
<p style="text-align:center;font-size:13px;color:#888">Ce code expire dans <strong>{CODE_TTL_MINUTES} minutes</strong>.<br/>Après <strong>{MAX_ATTEMPTS}</strong> codes erronés, tu devras en demander un nouveau.</p>
<p style="font-size:13px;color:#888">Si tu n'es pas à l'origine de cette demande, ignore simplement cet email.</p>
</td></tr>
<tr><td style="background:#f5f7fa;padding:18px 32px;text-align:center;color:#888;font-size:12px">© {timezone.now().year} {app_name}</td></tr>
</table></td></tr></table></body></html>"""
        msg = EmailMultiAlternatives(subject, text, sender, [user.email])
        msg.attach_alternative(html, "text/html")
        msg.send(fail_silently=False)
        logger.info("Email de vérification envoyé à %s code=%s", user.email, code[:2] + "****")
        return True
    except Exception as exc:
        logger.exception("Échec envoi email vérification à %s : %s", user.email, exc)
        return False
