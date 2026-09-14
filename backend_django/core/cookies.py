"""Helpers pour stocker les JWT dans des cookies HttpOnly (contre le vol par XSS)."""
from __future__ import annotations

from django.conf import settings


def auth_cookie_kwargs(name: str, remember: bool = False) -> dict:
    """Options de sécurité communes aux cookies d'auth.

    Cross-site (front sur Vercel, API sur Render) : SameSite=None + Secure
    est requis pour que fetch(credentials:'include') envoie/reçoive les
    cookies.
    """
    is_prod = not settings.DEBUG
    samesite = "None" if is_prod else "Lax"
    # access : 2h ; refresh : 7j par défaut, 30j si remember_me
    if name == "access_token":
        max_age = 2 * 60 * 60
    else:
        max_age = 30 * 24 * 60 * 60 if remember else 7 * 24 * 60 * 60
    return {
        "httponly": True,
        "secure": is_prod,
        "samesite": samesite,
        "path": "/",
        "max_age": max_age,
    }


def set_auth_cookies(response, access: str, refresh: str | None = None, remember: bool = False):
    """Pose les cookies HttpOnly access_token et (optionnel) refresh_token.

    Pose aussi un cookie NON-HttpOnly `spiistmove_logged_in=1`.
    Si remember=True, le refresh dure 30j au lieu de 7.
    """
    is_prod = not settings.DEBUG
    samesite = "None" if is_prod else "Lax"
    logged_in_max = 30 * 24 * 60 * 60 if remember else 7 * 24 * 60 * 60
    response.set_cookie("access_token", access, **auth_cookie_kwargs("access_token", remember))
    if refresh:
        response.set_cookie("refresh_token", refresh, **auth_cookie_kwargs("refresh_token", remember))
    response.set_cookie(
        "spiistmove_logged_in", "1",
        max_age=logged_in_max,
        httponly=False, secure=is_prod, samesite=samesite, path="/",
    )
    return response


def clear_auth_cookies(response):
    """Supprime les cookies d'auth (logout)."""
    response.delete_cookie("access_token", path="/")
    response.delete_cookie("refresh_token", path="/")
    response.delete_cookie("spiistmove_logged_in", path="/")
    return response
