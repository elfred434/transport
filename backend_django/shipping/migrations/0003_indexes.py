from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ("shipping", "0002_contactmessage_date_reponse_contactmessage_reponse"),
    ]

    operations = [
        migrations.AddIndex(
            model_name="colis",
            index=models.Index(fields=["statut", "-date_creation"], name="colis_statut_created_idx"),
        ),
        migrations.AddIndex(
            model_name="colis",
            index=models.Index(fields=["user", "-date_creation"], name="colis_user_created_idx"),
        ),
        migrations.AddIndex(
            model_name="colis",
            index=models.Index(fields=["ville", "pays", "statut"], name="colis_ville_pays_idx"),
        ),
        migrations.AddIndex(
            model_name="colis",
            index=models.Index(fields=["date_limite"], name="colis_date_limite_idx"),
        ),
        migrations.AddIndex(
            model_name="voyage",
            index=models.Index(fields=["statut", "-date_depart"], name="voyage_statut_depart_idx"),
        ),
        migrations.AddIndex(
            model_name="voyage",
            index=models.Index(fields=["ville"], name="voyage_ville_idx"),
        ),
        migrations.AddIndex(
            model_name="voyage",
            index=models.Index(fields=["pays_depart", "pays_destination"], name="voyage_pays_idx"),
        ),
        migrations.AddIndex(
            model_name="reservation",
            index=models.Index(fields=["statut"], name="reservation_statut_idx"),
        ),
        migrations.AddIndex(
            model_name="reservation",
            index=models.Index(fields=["colis", "statut"], name="reservation_colis_statut_idx"),
        ),
        migrations.AddIndex(
            model_name="reservation",
            index=models.Index(fields=["voyage", "statut"], name="reservation_voyage_statut_idx"),
        ),
        migrations.AddIndex(
            model_name="reservation",
            index=models.Index(fields=["transporteur"], name="reservation_transporteur_idx"),
        ),
        migrations.AddIndex(
            model_name="paiement",
            index=models.Index(fields=["user", "-date_creation"], name="paiement_user_created_idx"),
        ),
        migrations.AddIndex(
            model_name="paiement",
            index=models.Index(fields=["colis"], name="paiement_colis_idx"),
        ),
        migrations.AddIndex(
            model_name="paiement",
            index=models.Index(fields=["statut"], name="paiement_statut_idx"),
        ),
        migrations.AddIndex(
            model_name="paiement",
            index=models.Index(fields=["numero_transaction"], name="paiement_tx_idx"),
        ),
        migrations.AddIndex(
            model_name="suivicolis",
            index=models.Index(fields=["colis", "-date_etape"], name="suivi_colis_date_idx"),
        ),
        migrations.AddIndex(
            model_name="suivicolis",
            index=models.Index(fields=["statut"], name="suivi_statut_idx"),
        ),
        migrations.AddIndex(
            model_name="suivicolis",
            index=models.Index(fields=["demande_livraison", "statut"], name="suivi_demande_liv_idx"),
        ),
    ]
