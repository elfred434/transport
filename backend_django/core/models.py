from django.conf import settings
from django.db import models
from django.utils import timezone


class MessagePrive(models.Model):
    """Message privé entre deux utilisateurs."""
    expediteur = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="messages_envoyes"
    )
    destinataire = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="messages_recus"
    )
    contenu = models.TextField(blank=True, default="")
    fichier = models.CharField(max_length=500, blank=True, default="")
    lu = models.BooleanField(default=False)
    date_envoi = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = "messages_prives"
        ordering = ["date_envoi"]


class MessageAdmin(models.Model):
    """Message entre un utilisateur et l'admin.
    - auteur = celui qui envoie ; si auteur est admin/super_admin → réponse admin.
    - user = l'utilisateur (côté client) avec qui l'admin converse."""
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="messages_admin"
    )
    auteur = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="messages_admin_envoyes"
    )
    contenu = models.TextField(blank=True, default="")
    fichier = models.CharField(max_length=500, blank=True, default="")
    lu = models.BooleanField(default=False)
    date_envoi = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = "messages_admin"
        ordering = ["date_envoi"]


class SecurityEvent(models.Model):
    """Journal d'audit de sécurité (ROADMAP #10).

    Événements loggés : connexions réussies/échouées, verrouillages,
    changements de rôle, créations de superadmin, envois de payout,
    réceptions de webhook invalides, erreurs 500.
    """
    CATEGORY_LOGIN = "login"
    CATEGORY_LOGIN_FAIL = "login_fail"
    CATEGORY_LOGOUT = "logout"
    CATEGORY_REGISTER = "register"
    CATEGORY_VERIFY = "verify"
    CATEGORY_ROLE = "role_change"
    CATEGORY_BOOTSTRAP = "bootstrap"
    CATEGORY_WEBHOOK = "webhook"
    CATEGORY_PAYOUT = "payout"
    CATEGORY_PAYMENT = "payment"
    CATEGORY_ERROR = "error_500"
    CATEGORY_FRAUD = "fraud_flag"
    CATEGORY_OTHER = "other"

    CATEGORY_CHOICES = [
        (CATEGORY_LOGIN, "Login OK"),
        (CATEGORY_LOGIN_FAIL, "Login échoué"),
        (CATEGORY_LOGOUT, "Déconnexion"),
        (CATEGORY_REGISTER, "Inscription"),
        (CATEGORY_VERIFY, "Vérif email/OTP"),
        (CATEGORY_ROLE, "Changement de rôle"),
        (CATEGORY_BOOTSTRAP, "Bootstrap superadmin"),
        (CATEGORY_WEBHOOK, "Webhook"),
        (CATEGORY_PAYOUT, "Payout"),
        (CATEGORY_PAYMENT, "Paiement"),
        (CATEGORY_ERROR, "Erreur 500"),
        (CATEGORY_FRAUD, "Fraude détectée"),
        (CATEGORY_OTHER, "Autre"),
    ]

    user = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL,
        null=True, blank=True, related_name="security_events",
    )
    email = models.CharField(max_length=254, blank=True, default="", db_index=True)
    category = models.CharField(max_length=20, choices=CATEGORY_CHOICES, db_index=True)
    action = models.CharField(max_length=80, db_index=True)
    detail = models.JSONField(blank=True, default=dict)
    ip = models.GenericIPAddressField(null=True, blank=True, db_index=True)
    user_agent = models.CharField(max_length=500, blank=True, default="")
    success = models.BooleanField(default=True)
    created_at = models.DateTimeField(default=timezone.now, db_index=True)

    class Meta:
        db_table = "security_events"
        ordering = ["-created_at"]
        indexes = [
            models.Index(fields=["category", "-created_at"]),
            models.Index(fields=["-created_at"]),
        ]

    def __str__(self):
        who = self.email or (self.user.email if self.user else "?")
        status = "OK" if self.success else "FAIL"
        return f"[{self.created_at:%Y-%m-%d %H:%M:%S}] {self.category}/{self.action} {who} → {status}"
