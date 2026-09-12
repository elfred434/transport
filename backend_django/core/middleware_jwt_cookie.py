"""Middleware qui lit les JWT depuis des cookies HttpOnly et les expose
dans l'en-tête Authorization pour que VerifiedJWTAuthentication les voie.

Cela permet de ne plus stocker les tokens dans localStorage (protection XSS).
"""
from __future__ import annotations


class JWTCookieMiddleware:
    """Ajoute `Authorization: Bearer <access_token_cookie>` si présent ET qu'aucun
    en-tête Authorization n'est déjà fourni."""

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        auth = request.META.get("HTTP_AUTHORIZATION", "")
        if not auth:
            token = request.COOKIES.get("access_token")
            if token:
                request.META["HTTP_AUTHORIZATION"] = f"Bearer {token}"
        return self.get_response(request)
