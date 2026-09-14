"""Utilitaires de validation/formatage téléphone via libphonenumber (ROADMAP #15).

Accepte plusieurs formats :
- "+22997000001" (international)
- "97000001" (Bénin par défaut)
- "0022997000001"
- "+229 97 00 00 01" (espaces)
- "+2250700000000" (CI)
- Numéros avec indicatif pays court ("97 00 00 01") → par défaut Bénin (BJ)
"""
from __future__ import annotations

import re
from typing import Optional

DEFAULT_REGION = "BJ"  # Bénin par défaut

try:
    import phonenumbers
    from phonenumbers import NumberParseException, PhoneNumberType, is_valid_number, format_number, PhoneNumberFormat
    _PHONENUMBERS_OK = True
except ImportError:  # fallback doux au cas où le paquet n'est pas encore installé
    phonenumbers = None
    _PHONENUMBERS_OK = False


_CLEAN_RE = re.compile(r"[^\d+]")


def parse_phone(raw: str, default_region: str = DEFAULT_REGION) -> Optional["phonenumbers.PhoneNumber"]:
    """Parse un numéro et retourne l'objet PhoneNumber, ou None si invalide."""
    if not raw:
        return None
    if not _PHONENUMBERS_OK:
        return None
    cleaned = _CLEAN_RE.sub("", raw)
    # Si l'utilisateur a mis "00229…" au lieu de "+229…"
    if cleaned.startswith("00"):
        cleaned = "+" + cleaned[2:]
    # Numéros locaux à 8 chiffres sans indicatif → on préfixe +229 (Bénin)
    if len(cleaned) == 8 and cleaned.isdigit():
        cleaned = "+229" + cleaned
    try:
        num = phonenumbers.parse(cleaned, default_region)
    except NumberParseException:
        return None
    if not is_valid_number(num):
        return None
    return num


def is_valid_phone(raw: str, default_region: str = DEFAULT_REGION) -> bool:
    return parse_phone(raw, default_region) is not None


def normalize_phone(raw: str, default_region: str = DEFAULT_REGION) -> Optional[str]:
    """Retourne le numéro au format E.164 (+22997000001), ou None si invalide."""
    num = parse_phone(raw, default_region)
    if not num:
        return None
    return format_number(num, PhoneNumberFormat.E164)


def pretty_phone(raw: str, default_region: str = DEFAULT_REGION) -> Optional[str]:
    """Retourne le numéro au format international lisible (+229 97 00 00 01)."""
    num = parse_phone(raw, default_region)
    if not num:
        return None
    return format_number(num, PhoneNumberFormat.INTERNATIONAL)


def guess_region(raw: str) -> Optional[str]:
    """Retourne le code pays (BJ, CI, TG…) du numéro si on peut l'identifier."""
    num = parse_phone(raw, DEFAULT_REGION)
    if not num:
        return None
    cc = phonenumbers.region_code_for_number(num)
    return cc or None


def is_mobile(raw: str, default_region: str = DEFAULT_REGION) -> bool:
    """Vérifie que le numéro est un mobile (important pour les payouts)."""
    num = parse_phone(raw, default_region)
    if not num:
        return False
    ptype = phonenumbers.number_type(num)
    return ptype in (PhoneNumberType.MOBILE, PhoneNumberType.FIXED_LINE_OR_MOBILE)
