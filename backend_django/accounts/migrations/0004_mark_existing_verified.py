"""Data migration : marquer les comptes existants comme vérifiés
pour ne pas bloquer les utilisateurs déjà inscrits.
"""
from django.db import migrations


def mark_existing_users_verified(apps, schema_editor):
    User = apps.get_model("accounts", "User")
    User.objects.filter(email_verified=False).update(email_verified=True)


def reverse(apps, schema_editor):
    pass


class Migration(migrations.Migration):

    dependencies = [
        ("accounts", "0003_add_email_verification"),
    ]

    operations = [
        migrations.RunPython(mark_existing_users_verified, reverse),
    ]
