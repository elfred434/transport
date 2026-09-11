from django.db import migrations, models
import django.db.models.deletion
import django.utils.timezone


class Migration(migrations.Migration):

    dependencies = [
        ("accounts", "0001_initial"),
    ]

    operations = [
        migrations.CreateModel(
            name="PasswordResetToken",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("token", models.CharField(db_index=True, max_length=100, unique=True)),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("expires_at", models.DateTimeField()),
                ("used", models.BooleanField(default=False)),
                ("user", models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, related_name="reset_tokens", to="accounts.user")),
            ],
            options={
                "db_table": "password_reset_tokens",
                "ordering": ["-created_at"],
            },
        ),
    ]
