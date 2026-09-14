"""Utilitaires d'audit de sécurité (ROADMAP #10).

Usage :
    from core.audit import log_event
    log_event(request, "login", "password_login", success=True, email=email, user=user)
"""
from __future__ import annotations

from typing import TYPE_CHECKING, Any

from django.utils import timezone

if TYPE_CHECKING:
    from rest_framework.request import Request
    from django.contrib.auth.models import AbstractBaseUser


def client_ip(request: "Request | None") -> str | None:
    if not request:
        return None
    xff = request.headers.get("x-forwarded-for", "")
    if xff:
        return xff.split(",")[0].strip() or None
    return request.META.get("REMOTE_ADDR")


def user_agent(request: "Request | None") -> str:
    if not request:
        return ""
    return request.headers.get("user-agent", "")[:500]


def log_event(
    request: "Request | None",
    category: str,
    action: str,
    *,
    success: bool = True,
    user: "AbstractBaseUser | None" = None,
    email: str | None = None,
    detail: dict[str, Any] | None = None,
    ip: str | None = None,
) -> int:
    """Enregistre un événement de sécurité dans la table `security_events`.

    Fonctionne même si la table/migration n'a pas encore été appliquée (erreur
    silencieuse pour ne pas casser une requête).
    """
    try:
        from core.models import SecurityEvent  # imported lazily
        ev = SecurityEvent(
            user=user if user and getattr(user, "pk", None) else None,
            email=(email or "")[:254] if email else "",
            category=category[:20],
            action=action[:80],
            detail=detail or {},
            ip=ip or client_ip(request),
            user_agent=user_agent(request),
            success=bool(success),
            created_at=timezone.now(),
        )
        # Pre-remplir l'email depuis l'objet user si absent
        if not ev.email and ev.user_id:
            ev.email = getattr(ev.user, "email", "") or ""
        ev.save()
        return ev.id
    except Exception:
        # Ne jamais faire échouer la requête métier à cause d'un problème d'audit
        import logging
        logging.getLogger(__name__).exception("log_event: impossible d'écrire l'événement d'audit")
        return 0
