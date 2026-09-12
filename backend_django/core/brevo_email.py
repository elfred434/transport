"""Backend email Django qui envoie via l'API REST Brevo (v3).
Usage : mettre EMAIL_BACKEND="core.brevo_email.BrevoEmailBackend"
et BREVO_API_KEY dans l'environnement.
"""
from django.core.mail.backends.base import BaseEmailBackend
from django.core.mail.message import sanitize_address
import requests
import json
import logging

logger = logging.getLogger(__name__)


class BrevoEmailBackend(BaseEmailBackend):
    def __init__(self, api_key=None, fail_silently=False, **kwargs):
        super().__init__(fail_silently=fail_silently, **kwargs)
        from django.conf import settings
        self.api_key = api_key or getattr(settings, "BREVO_API_KEY", "")
        self.sender_name = getattr(settings, "BREVO_SENDER_NAME", getattr(settings, "APP_NAME", "SpiistMove"))
        self.sender_email = getattr(settings, "BREVO_SENDER_EMAIL", None) or getattr(settings, "DEFAULT_FROM_EMAIL", "").split("<")[-1].rstrip(">").strip()

    def send_messages(self, email_messages):
        if not self.api_key:
            if not self.fail_silently:
                raise ValueError("BREVO_API_KEY non configuré")
            return 0
        sent = 0
        for msg in email_messages:
            try:
                self._send_one(msg)
                sent += 1
            except Exception as e:
                logger.exception("Erreur envoi email Brevo: %s", e)
                if not self.fail_silently:
                    raise
        return sent

    def _send_one(self, msg):
        from_email = msg.from_email
        if "<" in from_email:
            from_name = from_email.split("<")[0].strip().strip('"')
            from_email = from_email.split("<")[1].rstrip(">").strip()
        else:
            from_name = self.sender_name
        # Sender override si pas vérifié: utiliser le sender brevo par défaut
        if not from_email:
            from_email = self.sender_email
            from_name = self.sender_name

        recipients = []
        for addr in msg.to:
            recip_email = sanitize_address(addr, msg.encoding)
            if "<" in recip_email:
                rn, re_ = recip_email.split("<")
                recipients.append({"email": re_.rstrip(">").strip(), "name": rn.strip().strip('"')})
            else:
                recipients.append({"email": recip_email})

        payload = {
            "sender": {"name": from_name or self.sender_name, "email": from_email},
            "to": recipients,
            "subject": msg.subject or "",
        }
        if msg.cc:
            payload["cc"] = [{"email": sanitize_address(a, msg.encoding).split("<")[-1].rstrip(">").strip()} for a in msg.cc]
        if msg.bcc:
            payload["bcc"] = [{"email": sanitize_address(a, msg.encoding).split("<")[-1].rstrip(">").strip()} for a in msg.bcc]
        if msg.attachments:
            payload["attachment"] = []
            for a in msg.attachments:
                # a peut être un MIMEBase ou un tuple (filename, content, mimetype)
                try:
                    if hasattr(a, "get_filename"):
                        fname = a.get_filename()
                        ctype = a.get_content_type()
                        content_b64 = a.get_payload(decode=False).replace("\n", "")
                    else:
                        fname, content, ctype = a
                        import base64
                        content_b64 = base64.b64encode(content if isinstance(content, bytes) else content.encode()).decode()
                    payload["attachment"].append({"name": fname, "content": content_b64, "type": ctype})
                except Exception:
                    logger.exception("Impossible d'attacher une pièce jointe")
        if msg.content_subtype == "html" or getattr(msg, "alternatives", None):
            payload["htmlContent"] = msg.body if msg.content_subtype == "html" else ""
            payload["textContent"] = msg.body if msg.content_subtype != "html" else ""
            for alt_content, alt_type in getattr(msg, "alternatives", []) or []:
                if alt_type == "text/html":
                    payload["htmlContent"] = alt_content
                elif alt_type == "text/plain":
                    payload["textContent"] = alt_content
        else:
            payload["textContent"] = msg.body

        resp = requests.post(
            "https://api.brevo.com/v3/smtp/email",
            headers={"api-key": self.api_key, "Content-Type": "application/json"},
            data=json.dumps(payload),
            timeout=15,
        )
        if resp.status_code >= 300:
            raise RuntimeError(f"Brevo API {resp.status_code}: {resp.text[:300]}")
        mid = resp.json().get("messageId", "")
        logger.info("Email Brevo envoyé à %s | messageId=%s", [r["email"] for r in recipients], mid)
