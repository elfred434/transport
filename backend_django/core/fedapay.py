"""Intégration FedaPay — client backend (encaissements, payouts, webhooks).

Références :
  Docs : https://docs.fedapay.com
  API  : https://api.fedapay.com/v1 (live)   https://sandbox-api.fedapay.com/v1 (sandbox)
  Widget JS : https://cdn.fedapay.com/checkout.js

Workflow d'encaissement :
  1. Backend POST /v1/transactions → retourne {id, reference, payment_token, ...}
  2. Frontend ouvre le widget FedaPay.Checkout({ public_key, transaction: { id } })
     OU redirige sur l'URL de paiement construite à partir du payment_token.
  3. Au retour (callback ou onComplete), le frontend POST /api/paiements/<id>/verify-fedapay
     avec le transaction_id ; le backend GET /v1/transactions/{id} et valide.
  4. FedaPay envoie parallèlement un webhook signé (X-FEDAPAY-SIGNATURE) à
     /api/webhooks/fedapay — vérification HMAC SHA-256 obligatoire.

Workflow payout (vers transporteur) :
  1. Backend POST /v1/payouts (amount, currency XOF, recipient phone)
  2. Backend POST /v1/payouts/start avec la liste {id} pour déclencher
  3. Webhook payout.sent/payout.failed → mise à jour statut retrait.
"""
from __future__ import annotations

import hashlib
import hmac
import json
import logging
import time
from decimal import Decimal, InvalidOperation
from typing import Any, Optional, Tuple

import requests as http_requests
from django.conf import settings as dj_settings
from django.views.decorators.csrf import csrf_exempt
from django.utils.decorators import method_decorator
from rest_framework import status as drf_status
from rest_framework.decorators import api_view, permission_classes
from rest_framework.exceptions import AuthenticationFailed, PermissionDenied as DRFPermissionDenied
from rest_framework.permissions import AllowAny
from rest_framework.request import Request

from core.responses import api_success, api_error
from shipping.models import Paiement, Retrait, SuiviColis

logger = logging.getLogger("fedapay")

FEDAPAY_TIMEOUT = 15
WEBHOOK_TOLERANCE_SECONDS = 300  # 5 min anti-replay


# ---------------------------------------------------------------------------
# Configuration helpers
# ---------------------------------------------------------------------------

def _api_base() -> str:
    """URL de base de l'API FedaPay."""
    env = (dj_settings.FEDAPAY_ENV or "sandbox").lower()
    if env == "live":
        return dj_settings.FEDAPAY_API_URL_LIVE
    return dj_settings.FEDAPAY_API_URL_SANDBOX


def _widget_js_url() -> str:
    return dj_settings.FEDAPAY_JS_URL


def _is_sandbox() -> bool:
    return (dj_settings.FEDAPAY_ENV or "sandbox").lower() != "live"


def _auth_headers() -> dict:
    return {
        "Authorization": f"Bearer {dj_settings.FEDAPAY_SECRET_KEY}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }


def is_configured() -> bool:
    """Vrai si les clés FedaPay sont configurées."""
    return bool(
        dj_settings.FEDAPAY_SECRET_KEY
        and dj_settings.FEDAPAY_PUBLIC_KEY
    )


def public_config() -> dict:
    """Payload envoyé au frontend pour initialiser le widget."""
    return {
        "public_key": dj_settings.FEDAPAY_PUBLIC_KEY or "",
        "sandbox": _is_sandbox(),
        "enabled": bool(dj_settings.FEDAPAY_PUBLIC_KEY),
        "configured": is_configured(),
        "widget_js": _widget_js_url(),
        "provider": "fedapay",
        "provider_name": "FedaPay",
    }


# ---------------------------------------------------------------------------
# Appels API
# ---------------------------------------------------------------------------

def create_transaction(
    *,
    amount: int,
    description: str,
    customer_email: Optional[str] = None,
    customer_firstname: Optional[str] = None,
    customer_lastname: Optional[str] = None,
    customer_phone: Optional[str] = None,
    customer_phone_country: str = "bj",
    callback_url: Optional[str] = None,
    merchant_reference: Optional[str] = None,
) -> dict:
    """Crée une transaction côté FedaPay. Retourne le JSON transaction brute.

    En retour, le champ `payment_token` (JWT) et `id` sont utilisés par le
    widget JS et pour les vérifications ultérieures.
    """
    if amount <= 0:
        raise ValueError("amount must be > 0")

    customer: dict[str, Any] = {}
    if customer_email:
        customer["email"] = customer_email
    if customer_firstname:
        customer["firstname"] = customer_firstname
    if customer_lastname:
        customer["lastname"] = customer_lastname
    if customer_phone:
        customer["phone_number"] = {
            "number": customer_phone.lstrip("+").lstrip("229"),
            "country": customer_phone_country.lower(),
        }

    body: dict[str, Any] = {
        "description": description,
        "amount": int(amount),
        "currency": {"iso": "XOF"},
        "callback_url": callback_url or dj_settings.FEDAPAY_CALLBACK_URL,
    }
    if customer:
        body["customer"] = customer
    if merchant_reference:
        body["merchant_reference"] = merchant_reference

    url = _api_base() + "/transactions"
    r = http_requests.post(
        url, json=body, headers=_auth_headers(), timeout=FEDAPAY_TIMEOUT
    )
    if r.status_code not in (200, 201):
        logger.error(
            "FedaPay create_transaction HTTP %s: %s", r.status_code, r.text[:400]
        )
        raise RuntimeError(f"FedaPay create error {r.status_code}: {r.text[:200]}")
    data = r.json()
    # La réponse est enveloppée sous la clé "v1/transaction"
    tx = data.get("v1/transaction") or data
    return tx


def retrieve_transaction(transaction_id: str | int) -> Optional[dict]:
    """Récupère une transaction par son ID FedaPay. Retourne le JSON ou None."""
    if not is_configured():
        return None
    url = f"{_api_base()}/transactions/{transaction_id}"
    try:
        r = http_requests.get(url, headers=_auth_headers(), timeout=FEDAPAY_TIMEOUT)
        if r.status_code != 200:
            logger.warning(
                "FedaPay retrieve tx %s → HTTP %s", transaction_id, r.status_code
            )
            return None
        return r.json().get("v1/transaction") or r.json()
    except Exception as exc:
        logger.exception("Erreur retrieve FedaPay tx %s: %s", transaction_id, exc)
        return None


def get_balances() -> list[dict]:
    """Retourne la liste des balances du compte marchand."""
    if not is_configured():
        return []
    try:
        r = http_requests.get(
            _api_base() + "/balances", headers=_auth_headers(), timeout=FEDAPAY_TIMEOUT
        )
        if r.status_code != 200:
            logger.warning("FedaPay balances HTTP %s", r.status_code)
            return []
        return r.json().get("v1/balances") or []
    except Exception as exc:
        logger.exception("Erreur FedaPay balances: %s", exc)
        return []


def create_payout(
    *,
    amount: int,
    phone_number: str,
    phone_country: str = "bj",
    name: str = "",
    description: str = "Retrait transporteur SpiistMove",
    merchant_reference: Optional[str] = None,
) -> dict:
    """Crée un payout (dépôt) vers un numéro mobile money.

    NOTE: FedaPay distingue create (préparation) du start (envoi effectif).
    Ceci est l'étape create ; appel à `start_payout([id])` pour lancer l'envoi.
    """
    if amount <= 0:
        raise ValueError("amount must be > 0")

    body: dict[str, Any] = {
        "amount": int(amount),
        "currency": {"iso": "XOF"},
        "description": description,
        "recipient": {
            "name": name or "Transporteur SpiistMove",
            "phone_number": {
                "number": phone_number.lstrip("+").lstrip("229"),
                "country": phone_country.lower(),
            },
        },
    }
    if merchant_reference:
        body["merchant_reference"] = merchant_reference

    url = _api_base() + "/payouts"
    r = http_requests.post(
        url, json=body, headers=_auth_headers(), timeout=FEDAPAY_TIMEOUT
    )
    if r.status_code not in (200, 201):
        logger.error(
            "FedaPay create_payout HTTP %s: %s", r.status_code, r.text[:400]
        )
        raise RuntimeError(f"FedaPay payout create error {r.status_code}: {r.text[:200]}")
    data = r.json()
    return data.get("v1/payout") or data


def start_payout(payout_ids: list[int]) -> dict:
    """Déclenche l'envoi d'un ou plusieurs payouts pré-créés."""
    if not payout_ids:
        raise ValueError("payout_ids vide")
    url = _api_base() + "/payouts/start"
    r = http_requests.put(
        url,
        json={"payouts": [{"id": pid} for pid in payout_ids]},
        headers=_auth_headers(),
        timeout=FEDAPAY_TIMEOUT,
    )
    if r.status_code not in (200, 201):
        logger.error(
            "FedaPay start_payout HTTP %s: %s", r.status_code, r.text[:400]
        )
        raise RuntimeError(f"FedaPay payout start error {r.status_code}: {r.text[:200]}")
    return r.json()


# ---------------------------------------------------------------------------
# Vérification webhook
# ---------------------------------------------------------------------------

def _parse_fedapay_signature(header_value: str) -> Tuple[int, str]:
    """Parse l'entête X-FEDAPAY-SIGNATURE de la forme "t=TS,v1=HEX".

    Retourne (timestamp, v1_signature).
    """
    if not header_value:
        raise ValueError("header vide")
    parts = {}
    for chunk in header_value.split(","):
        if "=" in chunk:
            k, v = chunk.split("=", 1)
            parts[k.strip()] = v.strip()
    ts_s = parts.get("t")
    v1 = parts.get("v1") or parts.get("v0")
    if not ts_s or not v1:
        raise ValueError("champs t ou v1 manquants")
    try:
        ts = int(ts_s)
    except (TypeError, ValueError):
        raise ValueError("t non entier")
    return ts, v1


class WebhookSignatureError(Exception):
    """Erreur personnalisée pour webhook signature invalide (pour éviter d'utiliser
    le builtin PermissionError qui n'est pas attrapé par DRF)."""


def verify_webhook_signature(
    raw_body: bytes, signature_header: str, tolerance: int = WEBHOOK_TOLERANCE_SECONDS
) -> dict:
    """Vérifie la signature HMAC-SHA256 envoyée par FedaPay.

    Lève WebhookSignatureError en cas d'erreur. Retourne {"ts": ts} si OK.
    """
    secret = dj_settings.FEDAPAY_WEBHOOK_SECRET
    if not secret:
        raise WebhookSignatureError("FEDAPAY_WEBHOOK_SECRET non configuré")
    try:
        ts, sig = _parse_fedapay_signature(signature_header)
    except ValueError as e:
        raise WebhookSignatureError(f"Signature mal formée: {e}")
    if abs(time.time() - ts) > tolerance:
        raise WebhookSignatureError("Webhook trop ancien (replay?)")
    signed_payload = f"{ts}.".encode("utf-8") + raw_body
    expected = hmac.new(secret.encode("utf-8"), signed_payload, hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, sig):
        expected_alt = hmac.new(secret.encode("utf-8"), raw_body, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected_alt, sig):
            raise WebhookSignatureError("Signature FedaPay invalide")
    return {"ts": ts}


# ---------------------------------------------------------------------------
# Logique métier : marquer paiement / retrait
# ---------------------------------------------------------------------------

def _mark_paid(p: Paiement, transaction_id: str, mode: str = "") -> None:
    """Marque un paiement comme payé (idempotent)."""
    was_already_paid = p.statut == Paiement.STATUT_PAYE
    if was_already_paid:
        return
    p.statut = Paiement.STATUT_PAYE
    p.numero_transaction = transaction_id
    if mode and not p.methode:
        p.methode = mode
    p.save(update_fields=["statut", "numero_transaction", "methode"])
    if p.colis:
        SuiviColis.objects.get_or_create(
            colis=p.colis,
            statut="Payé",
            defaults={"commentaire": f"Paiement confirmé FedaPay (tx {transaction_id})"},
        )
        SuiviColis.objects.get_or_create(
            colis=p.colis,
            statut="En cours",
            defaults={"commentaire": "Paiement reçu, prise en charge du colis"},
        )
    logger.info("Paiement %s marqué PAYE fedapay_tx=%s", p.id, transaction_id)

    # ROADMAP #43 : crédite le solde bloqué du transporteur
    try:
        from shipping import wallet
        wallet.credit_pending_on_payment(p)
    except Exception:
        logger.exception("Échec crédit solde bloqué paiement %s", p.id)

    try:
        from core.emails import (
            envoyer_confirmation_paiement,
            envoyer_colis_en_cours,
            envoyer_paiement_recu_transporteur,
        )
        envoyer_confirmation_paiement(p)
        if p.colis:
            envoyer_colis_en_cours(p.colis)
        envoyer_paiement_recu_transporteur(p)
    except Exception:
        logger.exception("Erreur envoi emails paiement %s", p.id)


def _mark_payout_sent(r: Retrait, payout_id: str) -> None:
    """Marque un retrait comme payé suite à un payout FedaPay réussi."""
    if r.statut == Retrait.STATUT_PAYE:
        return
    r.statut = Retrait.STATUT_PAYE
    r.reference_transaction = payout_id
    r.save(update_fields=["statut", "reference_transaction"])
    logger.info("Retrait %s marqué PAYE fedapay_payout=%s", r.id, payout_id)


def _find_paiement(event_data: dict) -> Optional[Paiement]:
    """Retrouve le Paiement correspondant à un événement transaction.*."""
    tx = event_data.get("transaction") or event_data.get("data") or event_data
    tx_id = str(tx.get("id") or event_data.get("id") or "")
    merchant_ref = tx.get("merchant_reference") or ""
    ref = tx.get("reference") or ""
    # Recherche par numero_transaction d'abord
    if tx_id:
        p = (
            Paiement.objects.select_related("colis")
            .filter(numero_transaction__in=[tx_id, ref, merchant_ref])
            .first()
        )
        if p:
            return p
        # Puis par merchant_reference qui est notre référence SpiistMove
        if merchant_ref:
            p = (
                Paiement.objects.select_related("colis")
                .filter(reference=merchant_ref)
                .first()
            )
            if p:
                return p
    # Fallback approximatif par montant (comportement résilient)
    try:
        amount = Decimal(str(tx.get("amount") or "0"))
    except (InvalidOperation, ValueError, TypeError):
        amount = Decimal("0")
    if amount > 0:
        cands = list(
            Paiement.objects.select_related("colis")
            .filter(statut=Paiement.STATUT_ATTENTE, montant=amount)
            .order_by("-date_creation")[:2]
        )
        if len(cands) == 1:
            return cands[0]
    return None


# ---------------------------------------------------------------------------
# Vue webhook
# ---------------------------------------------------------------------------

@csrf_exempt
@api_view(["POST"])
@permission_classes([AllowAny])
def fedapay_webhook(request: Request):
    """POST /api/webhooks/fedapay

    Reçoit les événements signés de FedaPay. Gère :
      - transaction.approved  → marque le Paiement comme payé
      - transaction.declined  → marque comme échoué
      - transaction.canceled  → marque comme échoué
      - payout.sent           → marque Retrait comme payé
      - payout.failed         → log
    """
    raw = request.body
    sig = request.headers.get("X-FEDAPAY-SIGNATURE", "")

    # Vérif signature — obligatoire si secret configuré
    if dj_settings.FEDAPAY_WEBHOOK_SECRET:
        try:
            verify_webhook_signature(raw, sig)
        except WebhookSignatureError as e:
            logger.warning("Webhook FedaPay signature invalide: %s", e)
            return api_error(f"Signature invalide: {e}", 401)

    try:
        payload = json.loads(raw.decode("utf-8"))
    except Exception:
        return api_error("Payload non-JSON", 400)

    event_name = payload.get("name") or payload.get("type") or payload.get("event") or ""
    event_data = payload.get("data") or payload
    logger.info("Webhook FedaPay event=%s", event_name)

    # ---- Événements transaction ----
    if event_name == "transaction.approved":
        tx = event_data.get("transaction") or event_data
        tx_id = str(tx.get("id") or "")
        mode = str(tx.get("mode") or "")
        if not tx_id:
            return api_error("id transaction manquant", 400)
        p = _find_paiement(event_data)
        if not p:
            logger.warning(
                "Webhook FedaPay: aucun paiement pour tx=%s data=%s",
                tx_id,
                json.dumps(event_data)[:300],
            )
            return api_success({"received": True, "matched": False})
        _mark_paid(p, tx_id, mode=mode)
        return api_success({"received": True, "matched": True, "paiement_id": p.id})

    if event_name in ("transaction.declined", "transaction.canceled"):
        tx = event_data.get("transaction") or event_data
        tx_id = str(tx.get("id") or "")
        p = _find_paiement(event_data)
        if p and p.statut == Paiement.STATUT_ATTENTE:
            p.statut = Paiement.STATUT_ECHOUE
            p.save(update_fields=["statut"])
            logger.info("Paiement %s marqué ECHOUE (event=%s tx=%s)", p.id, event_name, tx_id)
        return api_success({"received": True, "event": event_name, "tx_id": tx_id})

    # ---- Événements payout ----
    if event_name == "payout.sent":
        po = event_data.get("payout") or event_data
        po_id = str(po.get("id") or "")
        merchant_ref = po.get("merchant_reference") or ""
        # Recherche retrait par merchant_reference (notre RET-xxx) ou référence_kkiapay (historique)
        r = None
        if merchant_ref:
            r = Retrait.objects.filter(
                reference_transaction=merchant_ref
            ).first() or Retrait.objects.filter(note__contains=merchant_ref).first()
        if not r and po_id:
            r = Retrait.objects.filter(reference_kkiapay=po_id).first()
        if r:
            _mark_payout_sent(r, po_id)
        return api_success({"received": True, "payout_id": po_id})

    if event_name == "payout.failed":
        po = event_data.get("payout") or event_data
        po_id = str(po.get("id") or "")
        err = po.get("last_error_code") or ""
        logger.warning("Payout FedaPay %s échoué: %s", po_id, err)
        return api_success({"received": True, "payout_id": po_id, "status": "failed"})

    # Événements non gérés : ack quand même
    logger.info("Webhook FedaPay event=%s non géré, ack 200", event_name)
    return api_success({"received": True, "event": event_name, "handled": False})
