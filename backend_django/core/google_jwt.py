"""Vérification des id_token Google avec les certificats JWKS officiels.

En production on utilise UNIQUEMENT cette méthode (plus de fallback non signé).
En dev on autorise un fallback si GOOGLE_ALLOW_JWT_FALLBACK=true.
"""
from __future__ import annotations

import json
import logging
import time
from typing import Any

import jwt
import requests as http_requests
from django.conf import settings

logger = logging.getLogger("google_jwt")

# Google fournit ses JWKS à cette URL (cache-Control: max-age=22657, soit ~6h).
_GOOGLE_JWKS_URL = "https://www.googleapis.com/oauth2/v3/certs"
_GOOGLE_TOKENINFO_URL = "https://oauth2.googleapis.com/tokeninfo"
# On garde un cache en mémoire (clé publique + header kid).
_jwks_cache: dict[str, Any] = {"keys": [], "fetched_at": 0}
_JWKS_TTL_SEC = 6 * 3600  # 6h, cohérent avec Cache-Control de Google

_ISSUERS = ("accounts.google.com", "https://accounts.google.com")


def _fetch_jwks(force: bool = False) -> dict[str, Any]:
    now = time.time()
    if not force and _jwks_cache["keys"] and now - _jwks_cache["fetched_at"] < _JWKS_TTL_SEC:
        return _jwks_cache
    try:
        resp = http_requests.get(_GOOGLE_JWKS_URL, timeout=10)
        resp.raise_for_status()
        data = resp.json()
        _jwks_cache["keys"] = data.get("keys", [])
        _jwks_cache["fetched_at"] = now
        logger.info("Google JWKS refreshed, %d keys", len(_jwks_cache["keys"]))
    except Exception as exc:
        logger.warning("Impossible de récupérer les JWKS Google : %s", exc)
    return _jwks_cache


def verify_google_id_token(id_token: str, client_id: str) -> dict[str, Any] | None:
    """Vérifie un id_token Google et retourne le payload décodé.

    Étapes :
      1. Récupérer les clés publiques JWKS de Google (cache 6h).
      2. Trouver la clé correspondant au kid du header.
      3. Vérifier signature, exp, iss, aud.
      4. Retourner le payload ; None si invalide.
    """
    try:
        # Extraire le header pour connaître le kid sans vérifier la signature
        header = jwt.get_unverified_header(id_token)
    except Exception as exc:
        logger.warning("Google id_token header illisible : %s", exc)
        return None

    kid = header.get("kid")
    alg = header.get("alg", "RS256")
    if not kid:
        logger.warning("Google id_token sans kid dans le header")
        return None

    # On cherche la clé ; si on ne la trouve pas on force un refresh (pour le cas où Google a tourné les clés)
    def _find_key():
        for k in _fetch_jwks()["keys"]:
            if k.get("kid") == kid:
                return k
        return None

    key_data = _find_key()
    if not key_data:
        key_data = _find_key()
        _fetch_jwks(force=True)
        key_data = _find_key()
    if not key_data:
        logger.warning("Clé Google kid=%s introuvable dans le JWKS", kid)
        return None

    try:
        public_key = jwt.algorithms.RSAAlgorithm.from_jwk(json.dumps(key_data))
        payload = jwt.decode(
            id_token,
            public_key,
            algorithms=[alg],
            audience=client_id,
            issuer=_ISSUERS,
            options={"require": ["exp", "iat", "iss", "aud", "sub", "email"]},
        )
        return payload
    except jwt.ExpiredSignatureError:
        logger.info("Google id_token expiré")
        return None
    except jwt.InvalidAudienceError:
        logger.warning("Google id_token audience invalide (attendu %s)", client_id)
        return None
    except jwt.InvalidIssuerError:
        logger.warning("Google id_token issuer invalide")
        return None
    except Exception as exc:
        logger.warning("Vérification Google id_token échouée : %s", exc)
        return None
