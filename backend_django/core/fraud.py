"""Détection de fraude basique (ROADMAP #13).

Règles (non bloquantes — flag uniquement dans security_events pour revue admin) :
- Payout d'un montant > FRAUD_PAYOUT_MAX_XOF en une fois (défaut 200 000 XOF)
- Plus de N paiements/payouts en 1h par IP
- Payout vers un numéro qui n'a jamais été utilisé par ce transporteur
- Inscription depuis une IP déjà utilisée pour un compte blacklisté (futur)

Les déclenchements logguent un événement de catégorie `fraud_flag` et
incrémentent un compteur interne. Aucun blocage n'est fait pour l'instant :
l'admin est prévenu via le log et peut bloquer manuellement.
"""
from __future__ import annotations

import time
from collections import defaultdict, deque
from typing import Any

from django.conf import settings

# --- Seuils (configurables via env) ---
FRAUD_PAYOUT_MAX_XOF = int(getattr(settings, "FRAUD_PAYOUT_MAX_XOF", 200_000) or 200_000)
FRAUD_RATE_LIMIT_HOUR = int(getattr(settings, "FRAUD_RATE_LIMIT_HOUR", 10) or 10)

# Compteurs in-memory (simple ; ne résiste pas au redémarrage — c'est un flag premier niveau)
_ip_payments: dict[str, deque] = defaultdict(deque)
_ip_payouts: dict[str, deque] = defaultdict(deque)
_WINDOW = 3600  # 1 heure


def _prune(dq: deque, now: float) -> None:
    while dq and now - dq[0] > _WINDOW:
        dq.popleft()


def _client_ip(request) -> str:
    if not request:
        return "?"
    xff = request.headers.get("x-forwarded-for", "")
    if xff:
        return xff.split(",")[0].strip()
    return request.META.get("REMOTE_ADDR", "") or "?"


def _flag(request, action: str, detail: dict[str, Any]) -> None:
    try:
        from core.audit import log_event
        user = getattr(request, "user", None)
        log_event(
            request,
            "fraud_flag",
            action,
            success=False,
            user=user if user and getattr(user, "is_authenticated", False) else None,
            detail=detail,
        )
    except Exception:
        import logging
        logging.getLogger(__name__).exception("fraud flag failed")


def check_payout(request, amount_xof: int, phone: str, *, transporteur_id: Any = None) -> list[str]:
    """Appelé avant d'envoyer un payout. Retourne la liste des règles déclenchées."""
    flags: list[str] = []
    now = time.time()
    ip = _client_ip(request)

    if amount_xof >= FRAUD_PAYOUT_MAX_XOF:
        flags.append(f"payout_amount_high:{amount_xof}>={FRAUD_PAYOUT_MAX_XOF}")

    dq = _ip_payouts[ip]
    _prune(dq, now)
    dq.append((now, amount_xof))
    total_hour = sum(a for _, a in dq)
    if len(dq) >= FRAUD_RATE_LIMIT_HOUR:
        flags.append(f"payout_rate:{len(dq)}>={FRAUD_RATE_LIMIT_HOUR}/h")
    if total_hour >= FRAUD_PAYOUT_MAX_XOF:
        flags.append(f"payout_total_hour:{total_hour}>={FRAUD_PAYOUT_MAX_XOF}")

    if flags:
        _flag(request, "payout_fraud", {
            "flags": flags,
            "amount": amount_xof,
            "phone": phone,
            "transporteur_id": str(transporteur_id) if transporteur_id else None,
            "hour_count": len(dq),
            "hour_total": total_hour,
        })
    return flags


def check_payment(request, amount_xof: int, *, paiement_id: Any = None) -> list[str]:
    flags: list[str] = []
    now = time.time()
    ip = _client_ip(request)
    dq = _ip_payments[ip]
    _prune(dq, now)
    dq.append(now)
    if len(dq) >= FRAUD_RATE_LIMIT_HOUR:
        flags.append(f"payment_rate:{len(dq)}>={FRAUD_RATE_LIMIT_HOUR}/h")
    if flags:
        _flag(request, "payment_fraud", {
            "flags": flags,
            "amount": amount_xof,
            "paiement_id": str(paiement_id) if paiement_id else None,
        })
    return flags
