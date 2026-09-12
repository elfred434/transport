"""Helpers d'envoi d'emails transactionnels via Brevo API.

Tous les emails passent par le backend core.brevo_email.BrevoEmailBackend (configuré
dans settings.py quand BREVO_API_KEY est présente). Si Brevo n'est pas configuré
(dev), les emails sont loggés mais pas envoyés (fail_silently=True pour ne jamais
bloquer une action métier).

Utilisation :
    from core.emails import envoyer_email_bienvenue, envoyer_confirmation_paiement, ...
"""
from __future__ import annotations

import logging
from typing import Optional

from django.conf import settings
from django.core.mail import EmailMultiAlternatives
from django.utils import timezone

logger = logging.getLogger("emails")


APP_NAME = "SpiistMove"
BRAND_COLOR = "#3498db"
CONTACT_EMAIL = "elfred434@gmail.com"  # reply-to / contact admin


def _app_name() -> str:
    return getattr(settings, "APP_NAME", APP_NAME) or APP_NAME


def _from() -> str:
    return getattr(settings, "DEFAULT_FROM_EMAIL", f"{_app_name()} <{CONTACT_EMAIL}>")


def _frontend_url() -> str:
    return (getattr(settings, "APP_FRONTEND_URL", "") or "").rstrip("/")


def _wrap_html(title: str, body_html: str, preview: str = "") -> str:
    """Template HTML commun avec entête bleue signature."""
    app = _app_name()
    return f"""<!doctype html><html><body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#333">
<span style="display:none;font-size:1px;color:#f5f7fa;max-height:0;max-width:0;opacity:0;overflow:hidden">{preview}</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
<tr><td style="background:{BRAND_COLOR};padding:24px 32px;text-align:center;color:#fff">
<div style="font-size:24px;font-weight:700;letter-spacing:.3px">🔐 {app}</div>
<div style="font-size:13px;opacity:.85;margin-top:4px">{title}</div>
</td></tr>
<tr><td style="padding:32px;font-size:15px;line-height:1.65;color:#333">{body_html}</td></tr>
<tr><td style="background:#f5f7fa;padding:18px 32px;text-align:center;color:#888;font-size:12px;line-height:1.6">
© {timezone.now().year} {app} — <a href="{_frontend_url()}" style="color:{BRAND_COLOR};text-decoration:none">{_frontend_url().replace('https://','')}</a><br/>
Ce message a été envoyé automatiquement. Merci de ne pas y répondre directement.
</td></tr>
</table>
</td></tr></table></body></html>"""


def _btn(url: str, label: str) -> str:
    return (
        f'<p style="text-align:center;margin:28px 0">'
        f'<a href="{url}" style="display:inline-block;padding:13px 30px;background:{BRAND_COLOR};'
        f'color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px">{label}</a></p>'
    )


def _send(to: str | list[str], subject: str, text: str, html: str) -> bool:
    """Envoie un email multi-parties. Ne lève jamais : loggue simplement."""
    if isinstance(to, str):
        to = [to]
    if not to:
        return False
    try:
        msg = EmailMultiAlternatives(subject, text, _from(), list(to))
        msg.attach_alternative(html, "text/html")
        msg.send(fail_silently=False)
        logger.info("Email envoyé à=%s sujet=%s", to, subject)
        return True
    except Exception as exc:  # ne jamais bloquer une action métier
        logger.exception("Échec envoi email à=%s sujet=%s : %s", to, subject, exc)
        return False


# ---------------------------------------------------------------------------
# 1) Confirmation d'inscription
# ---------------------------------------------------------------------------
def envoyer_email_bienvenue(user) -> bool:
    app = _app_name()
    email = getattr(user, "email", None)
    if not email:
        return False
    first_name = (getattr(user, "first_name", "") or "").strip() or "à toi"
    role = getattr(user, "role", "client")
    role_label = {"client": "expéditeur·rice", "transporteur": "transporteur", "admin": "administrateur"}.get(role, "utilisateur")

    subject = f"Bienvenue sur {app} 🎉"
    preview = f"Ton compte {role_label} est prêt."
    login_url = f"{_frontend_url()}/login"

    text = (
        f"Bonjour {first_name},\n\n"
        f"Ton compte {app} a bien été créé (rôle : {role_label}).\n"
        f"Tu peux te connecter dès maintenant : {login_url}\n\n"
        f"À bientôt sur {app} !\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Compte créé avec succès",
        f"""
        <p>Bonjour <strong>{first_name}</strong>,</p>
        <p>Ton compte <strong>{app}</strong> a bien été créé en tant que <strong>{role_label}</strong>.</p>
        {_btn(login_url, "Accéder à mon compte")}
        <p style="font-size:13px;color:#888">Si le bouton ne fonctionne pas, copie ce lien dans ton navigateur :<br/>
        <a href="{login_url}">{login_url}</a></p>
        <p>À bientôt sur {app} !<br/>L'équipe {app}</p>
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


# ---------------------------------------------------------------------------
# 2) Notification nouveau colis (aux transporteurs compatibles)
# ---------------------------------------------------------------------------
def envoyer_nouveau_colis(transporteurs, colis) -> int:
    """Notifie UNE LISTE de transporteurs qu'un nouveau colis correspond à leur trajet.
    transporteurs: itérable de User
    colis: instance Colis (ville=ville destination, adresse_depart/adresse_destination)
    """
    app = _app_name()
    nb = 0
    colis_url = f"{_frontend_url()}/colis/{colis.pk}" if colis else _frontend_url()
    ville_dest = (getattr(colis, "ville", "") or "—").strip()
    pays = (getattr(colis, "pays", "") or "").strip()
    destination = f"{ville_dest} ({pays})" if pays else ville_dest
    depart = (getattr(colis, "adresse_depart", "") or "—").split(",")[0].strip()
    poids = getattr(colis, "poids", None)
    client = getattr(colis, "user", None)
    client_name = ((getattr(client, "first_name", "") or "").strip() or "Un expéditeur")
    poids_txt = f" • {poids} kg" if poids else ""

    subject = f"Nouveau colis disponible : {destination}"
    preview = f"{client_name} publie un colis{poids_txt} à livrer."

    for t in transporteurs:
        email = getattr(t, "email", None)
        if not email:
            continue
        text = (
            f"Bonjour,\n\n"
            f"{client_name} vient de publier un colis :\n"
            f"  Destination : {destination}\n"
            f"  Adresse de départ : {getattr(colis,'adresse_depart','—')}\n"
            f"  Adresse d'arrivée : {getattr(colis,'adresse_destination','—')}{poids_txt}\n\n"
            f"Voir le colis et faire une proposition : {colis_url}\n\n"
            f"L'équipe {app}"
        )
        html = _wrap_html(
            "Nouveau colis disponible",
            f"""
            <p>Bonjour,</p>
            <p><strong>{client_name}</strong> vient de publier un colis qui peut t'intéresser :</p>
            <table role="presentation" cellpadding="10" cellspacing="0" style="border-collapse:collapse;background:#f8fafc;border-radius:8px;margin:16px 0">
              <tr><td style="color:#666;font-size:12px;width:110px">Destination</td><td style="font-weight:600">{destination}</td></tr>
              <tr><td style="color:#666;font-size:12px">Départ</td><td>{getattr(colis,'adresse_depart','—')}</td></tr>
              <tr><td style="color:#666;font-size:12px">Arrivée</td><td>{getattr(colis,'adresse_destination','—')}</td></tr>
              {f'<tr><td style="color:#666;font-size:12px">Poids</td><td>{poids} kg</td></tr>' if poids else ''}
            </table>
            {_btn(colis_url, "Voir le colis")}
            <p style="font-size:13px;color:#888">Tu peux ignorer cet email si le trajet ne t'intéresse pas.</p>
            """,
            preview=preview,
        )
        if _send(email, subject, text, html):
            nb += 1
    if nb:
        logger.info("Notification nouveau colis %s envoyée à %s transporteurs", colis.pk if colis else "?", nb)
    return nb


# ---------------------------------------------------------------------------
# 3) Confirmation de paiement Kkiapay
# ---------------------------------------------------------------------------
def envoyer_confirmation_paiement(paiement) -> bool:
    app = _app_name()
    user = getattr(paiement, "user", None)
    email = getattr(user, "email", None) if user else None
    colis = getattr(paiement, "colis", None)
    if not email:
        return False

    montant = getattr(paiement, "montant", 0)
    tx = getattr(paiement, "numero_transaction", "") or ""
    colis_ref = f"colis #{colis.pk}" if colis else "ta commande"
    suivi_url = f"{_frontend_url()}/mes-colis"

    subject = f"Paiement confirmé ({app})"
    preview = f"Ton paiement de {montant} FCFA pour {colis_ref} a bien été reçu."
    text = (
        f"Bonjour,\n\n"
        f"Ton paiement de {montant} FCFA pour {colis_ref} a bien été reçu par {app}.\n"
        f"Référence Kkiapay : {tx or '—'}\n\n"
        f"Suivre la livraison : {suivi_url}\n\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Paiement confirmé ✅",
        f"""
        <p>Bonjour,</p>
        <p>Ton paiement de <strong>{montant} FCFA</strong> pour {colis_ref} a bien été reçu par <strong>{app}</strong>.</p>
        <table role="presentation" cellpadding="10" cellspacing="0" style="border-collapse:collapse;background:#f0fdf4;border-left:4px solid #22c55e;border-radius:6px;margin:16px 0">
          <tr><td style="font-weight:600;color:#16a34a">Paiement accepté</td></tr>
          {f'<tr><td style="font-size:13px;color:#666">Référence Kkiapay : <code>{tx}</code></td></tr>' if tx else ''}
        </table>
        {_btn(suivi_url, "Suivre mon colis")}
        <p>Le transporteur est prévenu et la livraison peut démarrer.</p>
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


def envoyer_paiement_recu_transporteur(paiement) -> bool:
    """Notification au transporteur qu'un paiement est dispo sur son wallet."""
    app = _app_name()
    colis = getattr(paiement, "colis", None)
    transporteur_user = None
    if colis:
        # Cherche la réservation acceptée → transporteur
        from shipping.models import Reservation
        res = Reservation.objects.filter(colis=colis, statut=Reservation.STATUT_ACCEPTE).select_related("transporteur").first()
        if res:
            transporteur_user = res.transporteur
    email = getattr(transporteur_user, "email", None) if transporteur_user else None
    if not email:
        return False
    montant = getattr(paiement, "montant", 0)
    wallet_url = f"{_frontend_url()}/wallet"
    subject = f"Paiement disponible sur ton wallet {app}"
    preview = f"Un paiement de {montant} FCFA est crédité sur ton wallet."
    text = (
        f"Bonjour,\n\n"
        f"Un paiement de {montant} FCFA pour le colis #{colis.pk if colis else '—'} est disponible sur ton wallet.\n"
        f"Demander un retrait : {wallet_url}\n\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Paiement crédité sur ton wallet 💰",
        f"""
        <p>Bonjour,</p>
        <p>Un paiement de <strong>{montant} FCFA</strong> pour le colis #{colis.pk if colis else '—'} vient d'être crédité sur ton wallet.</p>
        {_btn(wallet_url, "Voir mon wallet")}
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


# ---------------------------------------------------------------------------
# 4) Notification de livraison / validation
# ---------------------------------------------------------------------------
def envoyer_livraison_confirmee(colis, destinataire: Optional[str] = None) -> bool:
    app = _app_name()
    client = getattr(colis, "user", None)
    email = destinataire or (getattr(client, "email", None) if client else None)
    if not email:
        return False
    colis_url = f"{_frontend_url()}/colis/{colis.pk}"
    ville = (getattr(colis, "ville", "") or "—").strip()
    subject = f"Colis livré : {ville}"
    preview = f"Ton colis n°{colis.pk} a été livré avec succès."
    text = (
        f"Bonjour,\n\n"
        f"Bonne nouvelle : ton colis n°{colis.pk} à destination de {ville} a été livré avec succès.\n\n"
        f"Voir les détails : {colis_url}\n\n"
        f"N'oublie pas de laisser un avis au transporteur !\n\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Colis livré ✅",
        f"""
        <p>Bonjour,</p>
        <p>Bonne nouvelle — ton colis <strong>n°{colis.pk}</strong> à destination de <strong>{ville}</strong> a été <strong>livré avec succès</strong>.</p>
        {_btn(colis_url, "Voir les détails & laisser un avis")}
        <p>Merci d'utiliser {app} 🙏</p>
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


def envoyer_colis_en_cours(colis) -> bool:
    """Prévient le client que son colis est pris en charge (réservation acceptée)."""
    client = getattr(colis, "user", None)
    email = getattr(client, "email", None) if client else None
    if not email:
        return False
    app = _app_name()
    colis_url = f"{_frontend_url()}/colis/{colis.pk}"
    ville = (getattr(colis, "ville", "") or "—").strip()
    subject = "Ton colis est en cours d'acheminement"
    preview = f"Ton colis pour {ville} est pris en charge."
    text = (
        f"Bonjour,\n\n"
        f"Ton colis pour {ville} est maintenant pris en charge par un transporteur.\n"
        f"Suivre : {colis_url}\n\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Colis en cours d'acheminement 🚚",
        f"""
        <p>Bonjour,</p>
        <p>Ton colis pour <strong>{ville}</strong> est maintenant pris en charge par un transporteur.</p>
        {_btn(colis_url, "Suivre mon colis")}
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


# ---------------------------------------------------------------------------
# 5) Contact / réponse admin
# ---------------------------------------------------------------------------
def envoyer_confirmation_contact(contact_msg) -> bool:
    """Accusé de réception à l'expéditeur du formulaire contact."""
    app = _app_name()
    email = getattr(contact_msg, "email", None)
    if not email:
        return False
    subject = f"Message bien reçu — {app}"
    preview = "Nous avons bien reçu ton message et te répondrons rapidement."
    text = (
        f"Bonjour,\n\n"
        f"Nous avons bien reçu ton message sur {app}.\n"
        f"L'équipe revient vers toi dans les plus brefs délais.\n\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Message reçu 📨",
        f"""
        <p>Bonjour,</p>
        <p>Nous avons bien reçu ton message adressé à <strong>{app}</strong>. L'équipe revient vers toi dans les plus brefs délais.</p>
        <p style="font-size:13px;color:#888">Merci pour ta confiance 🙏</p>
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


def envoyer_reponse_admin(contact_msg, reponse: str) -> bool:
    """Notification à l'expéditeur quand l'admin répond au message contact."""
    app = _app_name()
    email = getattr(contact_msg, "email", None)
    if not email:
        return False
    sujet_init = getattr(contact_msg, "sujet", "") or getattr(contact_msg, "message", "")[:60]
    subject = f"Réponse à ton message — {app}"
    preview = "L'équipe SpiistMove a répondu à ta demande."
    contact_url = f"{_frontend_url()}/contact"
    text = (
        f"Bonjour,\n\n"
        f"L'équipe {app} a répondu à ton message « {sujet_init} » :\n\n"
        f"{reponse}\n\n"
        f"Pour toute autre question : {contact_url}\n\n"
        f"L'équipe {app}"
    )
    html = _wrap_html(
        "Réponse de l'équipe",
        f"""
        <p>Bonjour,</p>
        <p>L'équipe <strong>{app}</strong> a répondu à ton message :</p>
        <div style="background:#f8fafc;border-left:4px solid {BRAND_COLOR};padding:14px 18px;border-radius:6px;margin:16px 0;white-space:pre-wrap">{reponse}</div>
        {_btn(contact_url, "Envoyer un nouveau message")}
        """,
        preview=preview,
    )
    return _send(email, subject, text, html)


def envoyer_nouveau_contact_admin(contact_msg) -> bool:
    """Notifie l'admin qu'un nouveau message contact est arrivé."""
    app = _app_name()
    admin_email = getattr(settings, "CONTACT_ADMIN_EMAIL", None) or CONTACT_EMAIL
    if not admin_email:
        return False
    sujet = getattr(contact_msg, "sujet", "") or "(pas de sujet)"
    email_exp = getattr(contact_msg, "email", "?")
    nom_exp = getattr(contact_msg, "nom", "") or "(anonyme)"
    msg = getattr(contact_msg, "message", "") or ""
    admin_url = f"{_frontend_url()}/admin/contact"
    # Si le front n'a pas de panel admin, pointer vers l'admin Django
    if not _frontend_url():
        admin_url = "/admin/django/"
    subject = f"Nouveau message contact — {sujet}"
    preview = f"De {nom_exp} &lt;{email_exp}&gt;"
    text = (
        f"Nouveau message de contact :\n\n"
        f"  De : {nom_exp} <{email_exp}>\n"
        f"  Sujet : {sujet}\n\n"
        f"  {msg}\n\n"
        f"Répondre : {admin_url}"
    )
    html = _wrap_html(
        "Nouveau message contact",
        f"""
        <p><strong>De :</strong> {nom_exp} &lt;<a href="mailto:{email_exp}">{email_exp}</a>&gt;</p>
        <p><strong>Sujet :</strong> {sujet}</p>
        <div style="background:#f8fafc;padding:14px 18px;border-radius:6px;margin:16px 0;white-space:pre-wrap">{msg}</div>
        {_btn(admin_url, "Répondre dans l'admin")}
        """,
        preview=preview,
    )
    return _send(admin_email, subject, text, html)
