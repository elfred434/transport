"""Middleware de sécurité HTTP (CSP + en-têtes divers).

Ajoute les en-têtes de sécurité sur chaque réponse pour éviter XSS,
clickjacking, MIME sniffing, fuites d'information.
"""
from __future__ import annotations

from django.conf import settings


def _csp() -> str:
    directives = [
        ("default-src", getattr(settings, "CSP_DEFAULT_SRC", ("'self'",))),
        ("script-src", getattr(settings, "CSP_SCRIPT_SRC", ("'self'",))),
        ("style-src", getattr(settings, "CSP_STYLE_SRC", ("'self'",))),
        ("font-src", getattr(settings, "CSP_FONT_SRC", ("'self'",))),
        ("img-src", getattr(settings, "CSP_IMG_SRC", ("'self'", "data:"))),
        ("connect-src", getattr(settings, "CSP_CONNECT_SRC", ("'self'",))),
        ("frame-src", getattr(settings, "CSP_FRAME_SRC", ("'self'",))),
    ]
    return "; ".join(f"{name} {' '.join(values)}" for name, values in directives)


class SecurityHeadersMiddleware:
    """Ajoute les en-têtes de sécurité à toutes les réponses."""

    def __init__(self, get_response):
        self.get_response = get_response
        self._csp = _csp()

    def __call__(self, request):
        response = self.get_response(request)
        # Ne pas écraser les CSP déjà définies
        if "Content-Security-Policy" not in response:
            response["Content-Security-Policy"] = self._csp
        response.setdefault("X-Content-Type-Options", "nosniff")
        response.setdefault("X-Frame-Options", getattr(settings, "X_FRAME_OPTIONS", "DENY"))
        response.setdefault("Referrer-Policy", getattr(settings, "REFERRER_POLICY", "strict-origin-when-cross-origin"))
        response.setdefault("X-XSS-Protection", "1; mode=block")
        response.setdefault("Permissions-Policy", "geolocation=(), microphone=(), camera=()")
        if not settings.DEBUG:
            response.setdefault("Strict-Transport-Security", "max-age=31536000; includeSubDomains; preload")
        return response
