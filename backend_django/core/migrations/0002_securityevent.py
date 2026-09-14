from django.db import migrations, models
import django.db.models.deletion
import django.utils.timezone


class Migration(migrations.Migration):

    dependencies = [
        ("core", "0001_initial"),
        migrations.swappable_dependency(auth_user := "accounts.User"),
    ]

    operations = [
        migrations.CreateModel(
            name="SecurityEvent",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("email", models.CharField(blank=True, db_index=True, default="", max_length=254)),
                ("category", models.CharField(choices=[
                    ("login", "Login OK"),
                    ("login_fail", "Login échoué"),
                    ("logout", "Déconnexion"),
                    ("register", "Inscription"),
                    ("verify", "Vérif email/OTP"),
                    ("role_change", "Changement de rôle"),
                    ("bootstrap", "Bootstrap superadmin"),
                    ("webhook", "Webhook"),
                    ("payout", "Payout"),
                    ("payment", "Paiement"),
                    ("error_500", "Erreur 500"),
                    ("fraud_flag", "Fraude détectée"),
                    ("other", "Autre"),
                ], db_index=True, max_length=20)),
                ("action", models.CharField(db_index=True, max_length=80)),
                ("detail", models.JSONField(blank=True, default=dict)),
                ("ip", models.GenericIPAddressField(blank=True, db_index=True, null=True)),
                ("user_agent", models.CharField(blank=True, default="", max_length=500)),
                ("success", models.BooleanField(default=True)),
                ("created_at", models.DateTimeField(db_index=True, default=django.utils.timezone.now)),
                ("user", models.ForeignKey(blank=True, null=True, on_delete=django.db.models.deletion.SET_NULL, related_name="security_events", to=auth_user)),
            ],
            options={
                "db_table": "security_events",
                "ordering": ["-created_at"],
                "indexes": [
                    models.Index(fields=["category", "-created_at"], name="sec_event_cat_created"),
                    models.Index(fields=["-created_at"], name="sec_event_created_desc"),
                    models.Index(fields=["email", "-created_at"], name="sec_event_email_created"),
                ],
            },
        ),
    ]
