"""
Calculateur de prix (ROADMAP #29/#30).

Tarification simple sans API payante :
  prix = base + (poids_kg * tarif_kg) + (distance_km * tarif_km)
  + surcharge type produit (électronique/fragile)
  Arrondi aux 100 XOF supérieurs.

Les distances utilisent une table statique des principales villes UEMOA
(Haversine à partir de coordonnées GPS connues). Si la ville n'est pas
référencée, on retombe sur une distance forfaitaire nationale ou régionale.
"""
from __future__ import annotations
import math
from decimal import Decimal
from typing import Optional, Tuple

# --- Tarifs (XOF) ---
BASE = 1500                 # forfait prise en charge
TARIF_KG = 1200             # par kg
TARIF_KM = 15               # par km (simulation UEMOA)
FRAGILE_SURCHARGE = 0.20    # +20% pour électronique/alimentaire
MIN_PRICE = 1500
MAX_DISTANCE_LOCAL = 100    # km pour "même ville/pays voisin"

# --- Coordonnées GPS approx. ville -> (lat, lon) ---
CITIES: dict[str, Tuple[float, float]] = {
    # Bénin
    "cotonou":     (6.3694, 2.4183),
    "porto-novo":  (6.4969, 2.6289),
    "parakou":     (9.3379, 2.6591),
    "abomey-calavi":(6.4492, 2.3519),
    "bohicon":     (7.1815, 2.0717),
    "djougou":     (9.7087, 1.6670),
    "natitingou": (10.3052, 1.3796),
    # Togo
    "lomé":        (6.1725, 1.2314),
    "sokodé":      (8.9808, 1.1374),
    "kara":        (9.5508, 1.1893),
    # Nigéria
    "lagos":       (6.5244, 3.3792),
    "abuja":       (9.0765, 7.3986),
    "ibadan":      (7.3775, 3.9470),
    # Ghana
    "accra":       (5.6037,-0.1870),
    "kumasi":      (6.6885,-1.6244),
    # Côte d'ivoire
    "abidjan":     (5.3599,-4.0083),
    "yamoussoukro":(6.8276,-5.2893),
    "bouaké":      (7.6905,-5.0390),
    # Burkina Faso
    "ouagadougou": (12.3714,-1.5197),
    "bobo-dioulasso":(11.1772,-4.2979),
    # Mali
    "bamako":      (12.6392,-8.0029),
    "sikasso":     (11.3170,-5.6664),
    # Niger
    "niamey":      (13.5116, 2.1254),
    # Sénégal
    "dakar":       (14.7167,-17.4677),
    # Cameroun
    "douala":      (4.0511,9.7679),
    "yaoundé":     (3.8480,11.5021),
    # France
    "paris":       (48.8566,2.3522),
    "marseille":   (43.2965,5.3698),
    "lyon":        (45.7640,4.8357),
}


def _norm(s: str) -> str:
    return (s or "").strip().lower().replace("é","e").replace("è","e")\
        .replace("ê","e").replace("à","a").replace("ô","o").replace("-", " ")


def _haversine(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    R = 6371.0  # km
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp = math.radians(lat2 - lat1)
    dl = math.radians(lon2 - lon1)
    a = math.sin(dp/2)**2 + math.cos(p1)*math.cos(p2)*math.sin(dl/2)**2
    return 2 * R * math.asin(math.sqrt(a))


def estimate_distance(ville_from: str, ville_to: str,
                      pays_from: Optional[str]=None,
                      pays_to: Optional[str]=None) -> int:
    """Retourne une distance estimée en km (entier, arrondi)."""
    cf = CITIES.get(_norm(ville_from))
    ct = CITIES.get(_norm(ville_to))
    if cf and ct:
        return int(round(_haversine(*cf, *ct)))
    # Fallback : même ville = forfait local, autre pays = forfait régional
    if _norm(ville_from) == _norm(ville_to) and pays_from == pays_to:
        return 25
    if pays_from and pays_to and _norm(pays_from) == _norm(pays_to):
        return MAX_DISTANCE_LOCAL
    # Pays voisins (UEMOA)
    uemoa = {"benin","togo","nigeria","ghana","cote d'ivoire","burkina faso",
             "mali","niger","senegal","guinee-bissau"}
    if pays_from and pays_to and _norm(pays_from) in uemoa and _norm(pays_to) in uemoa:
        return 400
    # International (Europe)
    return 4500


def estimate_price(poids_kg: float,
                   ville_depart: str = "",
                   ville_destination: str = "",
                   pays_depart: Optional[str] = None,
                   pays_destination: Optional[str] = None,
                   type_produit: str = "") -> dict:
    """Retourne {montant, details: {base, prix_poids, prix_distance, supplement, distance_km}}."""
    try:
        kg = max(0.1, float(poids_kg or 0))
    except (TypeError, ValueError):
        kg = 1.0
    distance_km = estimate_distance(ville_depart, ville_destination, pays_depart, pays_destination)

    prix_poids = round(TARIF_KG * kg)
    prix_distance = round(TARIF_KM * distance_km)
    subtotal = BASE + prix_poids + prix_distance
    supplement_pct = FRAGILE_SURCHARGE if (type_produit or "").lower() in ("electronique", "alimentaire", "fragile") else 0.0
    supplement = int(round(subtotal * supplement_pct))
    total = subtotal + supplement
    # Arrondi aux 100 XOF supérieurs
    total = int(math.ceil(total / 100.0) * 100)
    total = max(MIN_PRICE, total)
    return {
        "montant": total,
        "details": {
            "base": BASE,
            "prix_poids": prix_poids,
            "prix_distance": prix_distance,
            "supplement": supplement,
            "distance_km": distance_km,
            "poids_kg": round(kg, 2),
            "tarif_kg": TARIF_KG,
            "tarif_km": TARIF_KM,
        },
    }


def decimal_price(**kw) -> Decimal:
    return Decimal(str(estimate_price(**kw)["montant"]))
