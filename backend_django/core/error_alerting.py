"""Middleware d'alerte admin en cas d'erreur critique (ROADMAP #14 + #10).

- Loggue TOUTES les exceptions non gérées dans la table security_events.
- Envoie un email aux ADMINS en prod (rate-limité à 1 email par 5 minutes
  par (view, exception_type) pour ne pas spammer).
"""
from __future__ import annotations

import logging
import traceback
import time
from collections import defaultdict
from typing import Callable

from django.conf import settings

logger = logging.getLogger(__name__)

# Cache in-memory simple pour rate-limiting des emails
_last_sent: dict[tuple[str, str], float] = {}
_EMAIL_COOLDOWN_SEC = 5 * 60  # 5 minutes entre deux emails du même type


def _should_send_email(key: tuple[str, str]) -> bool:
    if settings.DEBUG:
        return False  # jamais de mails en dev
    if not getattr(settings, "ADMIN_ALERTS_ENABLED", True):
        return False
    now = time.time()
    last = _last_sent.get(key, 0)
    if now - last < _EMAIL_COOLDOWN_SEC:
        return False
    _last_sent[key] = now
    return True


class AdminAlertMiddleware:
    """Capture les exceptions 500 et prévient les admins."""

    def __init__(self, get_response: Callable):
        self.get_response = get_response

    def __call__(self, request):
        return self.get_response(request)

    def process_exception(self, request, exception):
        # 1) Log dans la table security_events
        try:
            from core.audit import log_event, client_ip, user_agent
            log_event(
                request,
                "error_500",
                type(exception).__name__,
                success=False,
                user=getattr(request, "user", None) if getattr(request, "user", None).is_authenticated else None,
                detail={
                    "path": request.path,
                    "method": request.method,
                    "exception": str(exception)[:500],
                },
            )
        except Exception:
            logger.exception("Impossible de logguer l'erreur dans security_events")

        # 2) Email admin rate-limité
        try:
            admin_emails = [a[1] for a in getattr(settings, "ADMINS", [])]
            if admin_emails:
                key = (request.path, type(exception).__name__)
                if _should_send_email(key):
                    self._send_admin_email(request, exception, admin_emails)
        except Exception:
            logger.exception("Échec envoi email admin alerte")

        return None  # laisse Django générer la réponse 500 normale

    def _send_admin_email(self, request, exception, admin_emails: list[str]):
        from django.core.mail import mail_admins
        from django.utils.html import escape
        ip = self._ip(request)
        ua = request.headers.get("user-agent", "")[:300]
        user = getattr(request, "user", None)
        user_str = str(user) if user and user.is_authenticated else "anonyme"
        tb = traceback.format_exc()[:2000]
        subject = f"[SpiistMove ERREUR] {type(exception).__name__} sur {request.path}"
        body = (
            f"Une exception non gérée s'est produite.\n\n"
            f"Path: {request.method} {request.path}\n"
            f"User: {user_str}\n"
            f"IP: {ip}\n"
            f"User-Agent: {ua}\n"
            f"Exception: {type(exception).__name__}: {exception}\n\n"
            f"Traceback:\n{tb}\n"
        )
        html = (
            f"<h2>Exception non gérée sur SpiistMove</h2>"
            f"<p><b>{request.method} {escape(request.path)}</b></p>"
            f"<p>User: <b>{escape(str(user_str))}</b><br/>"
            f"IP: {escape(str(ip))}<br/>"
            f"UA: {escape(ua)}</p>"
            f"<p><b>{escape(type(exception).__name__)}</b>: {escape(str(exception))}</p>"
            f"<pre>{escape(tb)}</pre>"
        )
        try:
            mail_admins(subject, body, html_message=html, fail_silently=True)
        except Exception:
            logger.exception("mail_admins a échoué")

    @staticmethod
    def _ip(request) -> str:
        xff = request.headers.get("x-forwarded-for", "")
        if xff:
            return xff.split(",")[0].strip()
        return request.META.get("REMOTE_ADDR", "") or ""
