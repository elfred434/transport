# Migration : anti brute-force login (compteur d'échecs + verrou temporel)
from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('accounts', '0005_verification_lockout'),
    ]

    operations = [
        migrations.AddField(
            model_name='user',
            name='failed_login_attempts',
            field=models.PositiveSmallIntegerField(default=0),
        ),
        migrations.AddField(
            model_name='user',
            name='login_locked_until',
            field=models.DateTimeField(blank=True, null=True),
        ),
    ]
