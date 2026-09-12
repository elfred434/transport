"""Helpers pour stocker les JWT dans des cookies HttpOnly (contre le vol par XSS)."""
from __future__ import annotations

from django.conf import settings


def auth_cookie_kwargs(name: str) -> dict:
    """Options de sécurité communes aux cookies d'auth."""
    is_prod = not settings.DEBUG
    return {
        "httponly": True,
        "secure": is_prod,
        "samesite": "Lax",
        "path": "/",
        # access : 2h (cohérent avec SIMPLE_JWT), refresh : 7j
        "max_age": 2 * 60 * 60 if name == "access_token" else 7 * 24 * 60 * 60,
    }


def set_auth_cookies(response, access: str, refresh: str | None = None):
    """Pose les cookies HttpOnly access_token et (optionnel) refresh_token.

    Pose aussi un cookie NON-HttpOnly `spiistmove_logged_in=1` que le frontend
    peut lire pour savoir si une session est active (sans exposer de secret).
    """
    response.set_cookie("access_token", access, **auth_cookie_kwargs("access_token"))
    if refresh:
        response.set_cookie("refresh_token", refresh, **auth_cookie_kwargs("refresh_token"))
    is_prod = not settings.DEBUG
    response.set_cookie(
        "spiistmove_logged_in", "1",
        max_age=7 * 24 * 60 * 60,
        httponly=False, secure=is_prod, samesite="Lax", path="/",
    )
    return response


def clear_auth_cookies(response):
    """Supprime les cookies d'auth (logout)."""
    response.delete_cookie("access_token", path="/")
    response.delete_cookie("refresh_token", path="/")
    response.delete_cookie("spiistmove_logged_in", path="/")
    return response
