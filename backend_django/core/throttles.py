"""Throttles réutilisables pour les endpoints publics sensibles."""
from rest_framework.throttling import AnonRateThrottle


class ContactThrottle(AnonRateThrottle):
    """Limite le spam sur le formulaire contact public."""
    scope = "contact"


class StrictLoginThrottle(AnonRateThrottle):
    """Alias pour throttling login strict (5/min)."""
    scope = "login"
