from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ("accounts", "0006_login_lockout"),
    ]

    operations = [
        migrations.AddField(
            model_name="user",
            name="twofa_code",
            field=models.CharField(blank=True, default="", max_length=6),
        ),
        migrations.AddField(
            model_name="user",
            name="twofa_expires",
            field=models.DateTimeField(blank=True, null=True),
        ),
        migrations.AddField(
            model_name="user",
            name="twofa_attempts",
            field=models.PositiveSmallIntegerField(default=0),
        ),
        migrations.AddField(
            model_name="user",
            name="twofa_pending_token",
            field=models.CharField(blank=True, default="", max_length=500),
        ),
    ]
