"""Intégration Kkiapay : helpers de vérification + webhook.

Référence API : https://docs.kkiapay.com/
Le webhook est appelé par Kkiapay avec un header `X-Kkiapay-Signature` en
SHA-256 HMAC du body avec KKIAPAY_SECRET_KEY.

Endpoints utiles :
  POST https://api.kkiapay.me/api/v1/transactions/status  (prod)
  POST https://sandbox.kkiapay.me/api/v1/transactions/status  (test)
    body: { transactionId: "xxx" }
    headers: X-API-KEY: KKIAPAY_PRIVATE_KEY (ou X-Kkiapay-Secret-Key selon version)
"""
from __future__ import annotations

import hashlib
import hmac
import json
import logging
from decimal import Decimal, InvalidOperation
from typing import Optional

import requests as http_requests
from django.conf import settings as dj_settings
from django.views.decorators.csrf import csrf_exempt
from django.utils.decorators import method_decorator
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import AllowAny
from rest_framework.request import Request

from core.responses import api_success, api_error
from shipping.models import Paiement, SuiviColis

logger = logging.getLogger("kkiapay")


def _api_base() -> str:
    return "https://sandbox.kkiapay.me" if dj_settings.KKIAPAY_SANDBOX else "https://api.kkiapay.me"


def _verify_signature(raw_body: bytes, signature: str) -> bool:
    """Vérifie la signature HMAC-SHA256 envoyée par Kkiapay."""
    secret = dj_settings.KKIAPAY_SECRET_KEY
    if not secret or not signature:
        return False
    expected = hmac.new(secret.encode("utf-8"), raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature)


def _mark_paid(p: Paiement, transaction_id: str) -> None:
    """Marque un paiement comme payé + création étape de suivi 'Payé' + envoi email."""
    was_already_paid = p.statut == Paiement.STATUT_PAYE
    if was_already_paid:
        return
    p.statut = Paiement.STATUT_PAYE
    p.numero_transaction = transaction_id
    p.save(update_fields=["statut", "numero_transaction"])
    if p.colis:
        SuiviColis.objects.get_or_create(
            colis=p.colis,
            statut="Payé",
            defaults={"commentaire": f"Paiement confirmé Kkiapay (tx {transaction_id})"},
        )
        # Passe le colis en "En cours"
        SuiviColis.objects.get_or_create(
            colis=p.colis,
            statut="En cours",
            defaults={"commentaire": "Paiement reçu, prise en charge du colis"},
        )
    logger.info("Paiement %s marqué PAYE tx=%s", p.id, transaction_id)

    # Emails transactionnels (jamais bloquant)
    try:
        from core.emails import envoyer_confirmation_paiement, envoyer_colis_en_cours, envoyer_paiement_recu_transporteur
        envoyer_confirmation_paiement(p)
        if p.colis:
            envoyer_colis_en_cours(p.colis)
        envoyer_paiement_recu_transporteur(p)
    except Exception:
        logger.exception("Erreur envoi emails paiement %s", p.id)


def verify_transaction(transaction_id: str) -> Optional[dict]:
    """Appelle l'API Kkiapay pour vérifier une transaction.
    Retourne le JSON de réponse ou None en cas d'erreur / non-config.
    """
    if not dj_settings.KKIAPAY_PRIVATE_KEY:
        logger.warning("Kkiapay non configuré (clé privée manquante)")
        return None
    url = _api_base() + "/api/v1/transactions/status"
    try:
        r = http_requests.post(
            url,
            json={"transactionId": transaction_id},
            headers={
                "X-API-KEY": dj_settings.KKIAPAY_PRIVATE_KEY,
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            timeout=10,
            verify=not dj_settings.KKIAPAY_SKIP_SSL_VERIFY,
        )
        if r.status_code != 200:
            logger.warning("Kkiapay verify %s → HTTP %s: %s", transaction_id, r.status_code, r.text[:200])
            return None
        return r.json()
    except Exception as exc:
        logger.exception("Erreur verify Kkiapay %s: %s", transaction_id, exc)
        return None


@api_view(["POST"])
@permission_classes([AllowAny])
@method_decorator(csrf_exempt, name="dispatch")
def kkiapay_webhook(request: Request):
    """POST /api/webhooks/kkiapay — appelé par Kkiapay quand un paiement est
    confirmé. Vérifie la signature avant de marquer le paiement comme payé.

    Payload typique de Kkiapay :
        { "transactionId": "xxx", "status": "SUCCESS", "amount": 1000, ... }
    On recherche le paiement par numero_transaction (si déjà pré-renseigné) ou
    par référence custom si elle est envoyée.
    """
    raw = request.body
    sig = request.headers.get("X-Kkiapay-Signature", "")
    # La signature n'est requise qu'avec une clé secrète configurée
    if dj_settings.KKIAPAY_SECRET_KEY and not _verify_signature(raw, sig):
        logger.warning("Webhook Kkiapay avec signature invalide (body=%s)", raw[:200])
        return api_error("Signature invalide", 401)

    try:
        payload = json.loads(raw.decode("utf-8"))
    except Exception:
        return api_error("Payload non-JSON", 400)

    tx_id = str(payload.get("transactionId") or payload.get("transaction_id") or "").strip()
    status = str(payload.get("status") or "").upper()
    if not tx_id:
        return api_error("transactionId manquant", 400)

    # On recherche par transaction ID d'abord, puis par référence/montant
    p = (
        Paiement.objects.select_related("colis")
        .filter(numero_transaction=tx_id)
        .first()
    )
    if not p:
        # Tente par montant + statut en_attente (matching approximatif, mais on
        # ne matche que si un seul paiement est en attente avec ce montant)
        try:
            amount = Decimal(str(payload.get("amount") or "0"))
        except (InvalidOperation, ValueError):
            amount = Decimal("0")
        if amount > 0:
            cands = list(
                Paiement.objects.select_related("colis")
                .filter(statut=Paiement.STATUT_ATTENTE, montant=amount)
                .order_by("-date_creation")[:2]
            )
            if len(cands) == 1:
                p = cands[0]

    if not p:
        logger.warning("Webhook Kkiapay : aucun paiement trouvé pour tx=%s payload=%s", tx_id, payload)
        # Retourner 200 pour éviter que Kkiapay ré-essaie en boucle
        return api_success({"received": True, "matched": False})

    if status in ("SUCCESS", "SUCCESSFUL", "COMPLETE", "COMPLETED"):
        _mark_paid(p, tx_id)
    else:
        logger.info("Webhook Kkiapay tx=%s status=%s (non marqué)", tx_id, status)

    return api_success({"received": True, "matched": True, "paiement_id": p.id, "statut": p.statut})
