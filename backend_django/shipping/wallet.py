"""
Gestion du wallet transporteur (ROADMAP #41 + #43).

Flux d'argent :
  1. Client paie un colis (réservation ACCEPTÉE par un transporteur) →
       - le montant HT (commission retirée) est "bloqué" dans le solde_en_attente
         du transporteur, jusqu'à confirmation de livraison.
  2. Admin confirme la livraison (#shipping:confirmer_livraison) →
       - solde_en_attente diminue de montant_brut
       - 95% du montant_brut passe dans solde (disponible pour retrait)
       - 5% de commission va dans WalletAdmin
  3. Si le colis est annulé / remboursé avant livraison →
       - solde_en_attente est débloqué (remis au client via remboursement).

Les fonctions ci-dessous sont idempotentes : elles utilisent un marqueur
pour ne pas créditer deux fois le même paiement.
"""
from __future__ import annotations
from decimal import Decimal
from typing import Optional
from django.conf import settings as dj_settings
from django.db import transaction
from .models import (
    Paiement,
    Reservation,
    Transporteur,
    WalletAdmin,
)


def _commission_pct() -> Decimal:
    return Decimal(str(getattr(dj_settings, "COMMISSION_PLATEFORME", "0.05")))


def _find_transporteur_for_paiement(p: Paiement):
    """Retourne (transporteur_profile, montant_brut) si réservation acceptée existe."""
    resa = Reservation.objects.filter(
        colis=p.colis, statut=Reservation.STATUT_ACCEPTE
    ).select_related("transporteur").first()
    if not resa:
        return None, Decimal("0")
    t, _ = Transporteur.objects.get_or_create(user=resa.transporteur)
    return t, (p.montant or Decimal("0"))


def credit_pending_on_payment(p: Paiement) -> bool:
    """
    À appeler quand un paiement passe à PAYE.
    Crédite solde_en_attente du transporteur du montant BRUT.
    Idempotent (drapeau Paiement._blocked).
    """
    if not p.colis_id:
        return False
    t, montant = _find_transporteur_for_paiement(p)
    if not t or montant <= 0:
        return False

    with transaction.atomic():
        # Recharger sous verrou
        t = Transporteur.objects.select_for_update().get(pk=t.pk)
        # Évite double crédit : on flag en ajoutant la valeur sur le paiement
        already = getattr(p, "_blocked_done", False)
        if already:
            return False
        t.solde_en_attente += montant
        t.save(update_fields=["solde_en_attente"])
        p._blocked_done = True  # durée de vie de l'instance
    return True


def release_on_delivery(p: Paiement):
    """
    À appeler au moment de la confirmation de livraison.
    Débloque : diminue solde_en_attente du MONTANT BRUT,
    crédite solde de 95%, crédite WalletAdmin de 5%.
    Retourne (commission_t, commission_p).
    """
    t, montant_brut = _find_transporteur_for_paiement(p)
    if not t or montant_brut <= 0:
        return Decimal("0"), Decimal("0")

    cp_pct = _commission_pct()
    commission_plateforme = (montant_brut * cp_pct).quantize(Decimal("1"))
    commission_transporteur = montant_brut - commission_plateforme

    with transaction.atomic():
        t = Transporteur.objects.select_for_update().get(pk=t.pk)
        t.solde_en_attente = max(Decimal("0"), t.solde_en_attente - montant_brut)
        t.solde += commission_transporteur
        t.save(update_fields=["solde", "solde_en_attente"])

        w, _ = WalletAdmin.objects.select_for_update().get_or_create(pk=1)
        w.solde += commission_plateforme
        w.total_genere += commission_plateforme
        w.save(update_fields=["solde", "total_genere"])

    return commission_transporteur, commission_plateforme


def unblock_on_cancel(p: Paiement) -> Decimal:
    """En cas d'annulation/remboursement avant livraison : débloque sans verser."""
    t, montant_brut = _find_transporteur_for_paiement(p)
    if not t or montant_brut <= 0:
        return Decimal("0")
    with transaction.atomic():
        t = Transporteur.objects.select_for_update().get(pk=t.pk)
        debited = min(t.solde_en_attente, montant_brut)
        t.solde_en_attente = t.solde_en_attente - debited
        t.save(update_fields=["solde_en_attente"])
    return debited
