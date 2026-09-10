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
