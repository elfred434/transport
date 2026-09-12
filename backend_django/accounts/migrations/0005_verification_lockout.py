# Generated for security hardening — anti brute-force on email verification code.
from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('accounts', '0004_mark_existing_verified'),
    ]

    operations = [
        migrations.AddField(
            model_name='user',
            name='verification_attempts',
            field=models.PositiveSmallIntegerField(default=0),
        ),
        migrations.AddField(
            model_name='user',
            name='verification_locked_until',
            field=models.DateTimeField(blank=True, null=True),
        ),
    ]
