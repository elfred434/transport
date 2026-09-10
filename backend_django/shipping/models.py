"""Modèles métier: Colis, Voyage, Reservation, Paiement, SuiviColis, Retrait, Avis, Contact."""
from django.conf import settings
from django.db import models
from django.utils import timezone
import random
import string


def generate_ref(prefix, length=10):
    chars = string.ascii_uppercase + string.digits
    return prefix + "".join(random.choices(chars, k=length))


class TimeStampedModel(models.Model):
    date_creation = models.DateTimeField(default=timezone.now)
    date_modification = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True


class Transporteur(TimeStampedModel):
    """Profil transporteur (1-1 avec un user role=transporteur)."""
    user = models.OneToOneField(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="transporteur"
    )
    numero_permis = models.CharField(max_length=50, blank=True, default="")
    vehicule = models.CharField(max_length=100, blank=True, default="")
    compagnie = models.CharField(max_length=100, blank=True, default="")
    adresse = models.CharField(max_length=255, blank=True, default="")
    ville = models.CharField(max_length=100, blank=True, default="")
    pays = models.CharField(max_length=100, blank=True, default="")
    photo_vehicule = models.CharField(max_length=500, blank=True, default="")
    # Wallet
    solde = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    total_paye = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    solde_en_attente = models.DecimalField(max_digits=12, decimal_places=2, default=0)

    class Meta:
        db_table = "transporteurs"


class Colis(TimeStampedModel):
    STATUT_ATTENTE = "en_attente"
    STATUT_APPROUVE = "approuve"
    STATUT_REFUSE = "refuse"
    STATUT_CHOICES = [
        (STATUT_ATTENTE, "En attente"),
        (STATUT_APPROUVE, "Approuvé"),
        (STATUT_REFUSE, "Refusé"),
    ]
    TYPES_PRODUIT = ["alimentaire", "electronique", "vetements", "documents", "autre"]

    user = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="colis"
    )
    numero_suivi = models.CharField(max_length=30, unique=True)
    nom_colis = models.CharField(max_length=200)
    type_produit = models.CharField(max_length=30)
    nombre_produits = models.PositiveIntegerField(default=1)
    poids = models.DecimalField(max_digits=8, decimal_places=2)
    dimensions = models.CharField(max_length=50, blank=True, default="")
    pays = models.CharField(max_length=100)
    ville = models.CharField(max_length=100)
    adresse_depart = models.CharField(max_length=255)
    adresse_destination = models.CharField(max_length=255)
    date_limite = models.DateField()
    image_colis = models.CharField(max_length=500, blank=True, default="")
    description = models.TextField(blank=True, default="")
    prix_estime = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    statut = models.CharField(max_length=20, choices=STATUT_CHOICES, default=STATUT_ATTENTE)

    class Meta:
        db_table = "colis"
        ordering = ["-date_creation"]

    @staticmethod
    def prix_pour_poids(kg: float) -> int:
        """Tarification simple: forfait de base + prix au kg."""
        kg = float(kg or 0)
        base = 1500
        per_kg = 1200
        return int(round(base + per_kg * kg))

    def save(self, *args, **kwargs):
        if not self.numero_suivi:
            self.numero_suivi = generate_ref("SUI-", 8)
        if not self.prix_estime:
            self.prix_estime = self.prix_pour_poids(self.poids)
        super().save(*args, **kwargs)


class Voyage(TimeStampedModel):
    STATUT_ATTENTE = "en_attente"
    STATUT_APPROUVE = "approuve"
    STATUT_REFUSE = "refuse"
    STATUT_CHOICES = Colis.STATUT_CHOICES
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="voyages"
    )
    numero_permis = models.CharField(max_length=50)
    vehicule = models.CharField(max_length=100)
    compagnie = models.CharField(max_length=100, blank=True, default="")
    adresse = models.CharField(max_length=255, blank=True, default="")
    ville = models.CharField(max_length=100)
    pays = models.CharField(max_length=100)
    pays_depart = models.CharField(max_length=100)
    pays_destination = models.CharField(max_length=100)
    date_depart = models.DateField()
    heure_depart = models.TimeField()
    poids_max = models.DecimalField(max_digits=8, decimal_places=2, default=0)
    email = models.EmailField(blank=True, default="")
    telephone = models.CharField(max_length=30, blank=True, default="")
    statut = models.CharField(max_length=20, choices=STATUT_CHOICES, default=Colis.STATUT_ATTENTE)

    class Meta:
        db_table = "voyages"
        ordering = ["-date_depart", "-heure_depart"]


class Reservation(TimeStampedModel):
    STATUT_EN_ATTENTE = "en_attente"
    STATUT_ACCEPTE = "accepte"
    STATUT_REFUSE = "refuse"
    STATUT_TERMINE = "termine"
    STATUT_CHOICES = [
        (STATUT_EN_ATTENTE, "En attente"),
        (STATUT_ACCEPTE, "Accepté"),
        (STATUT_REFUSE, "Refusé"),
        (STATUT_TERMINE, "Terminé"),
    ]
    colis = models.ForeignKey(Colis, on_delete=models.CASCADE, related_name="reservations")
    voyage = models.ForeignKey(Voyage, on_delete=models.CASCADE, related_name="reservations")
    transporteur = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="reservations"
    )
    statut = models.CharField(max_length=20, choices=STATUT_CHOICES, default=STATUT_EN_ATTENTE)

    class Meta:
        db_table = "reservations"
        unique_together = ("colis", "voyage")


class Paiement(TimeStampedModel):
    STATUT_ATTENTE = "en_attente"
    STATUT_PAYE = "paye"
    STATUT_ECHOUE = "echoue"
    STATUT_REMBOURSE = "rembourse"
    STATUT_CHOICES = [
        (STATUT_ATTENTE, "En attente"),
        (STATUT_PAYE, "Payé"),
        (STATUT_ECHOUE, "Échoué"),
        (STATUT_REMBOURSE, "Remboursé"),
    ]
    colis = models.ForeignKey(Colis, on_delete=models.CASCADE, related_name="paiements")
    user = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE)
    reference = models.CharField(max_length=50, unique=True)
    montant = models.DecimalField(max_digits=10, decimal_places=2)
    statut = models.CharField(max_length=20, choices=STATUT_CHOICES, default=STATUT_ATTENTE)
    numero_transaction = models.CharField(max_length=100, blank=True, default="")
    operateur = models.CharField(max_length=50, blank=True, default="")
    methode = models.CharField(max_length=30, blank=True, default="mobile_money")

    class Meta:
        db_table = "paiements"
        ordering = ["-date_creation"]

    def save(self, *args, **kwargs):
        if not self.reference:
            self.reference = generate_ref("PAY", 12)
        super().save(*args, **kwargs)


class SuiviColis(TimeStampedModel):
    STATUT_CHOICES = [
        ("En attente", "En attente"),
        ("En cours", "En cours"),
        ("Livré", "Livré"),
    ]
    colis = models.ForeignKey(Colis, on_delete=models.CASCADE, related_name="suivi")
    statut = models.CharField(max_length=20, choices=STATUT_CHOICES, default="En attente")
    commentaire = models.TextField(blank=True, default="")
    auteur_id = models.IntegerField(null=True, blank=True)
    date_etape = models.DateTimeField(default=timezone.now)
    # Livraison
    demande_livraison = models.BooleanField(default=False)
    confirme_par_admin = models.BooleanField(default=False)

    class Meta:
        db_table = "suivi_colis"
        ordering = ["date_etape"]


class Retrait(TimeStampedModel):
    STATUT_DEMANDE = "demande"
    STATUT_PAYE = "paye"
    STATUT_REJETE = "rejete"
    STATUT_CHOICES = [
        (STATUT_DEMANDE, "Demandé"),
        (STATUT_PAYE, "Payé"),
        (STATUT_REJETE, "Rejeté"),
    ]
    TYPE_TRANSPORTEUR = "transporteur"
    TYPE_ADMIN = "admin"
    TYPE_CHOICES = [(TYPE_TRANSPORTEUR, "Transporteur"), (TYPE_ADMIN, "Admin")]

    user = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="retraits")
    type = models.CharField(max_length=20, choices=TYPE_CHOICES, default=TYPE_TRANSPORTEUR)
    montant = models.DecimalField(max_digits=12, decimal_places=2)
    frais = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    montant_net = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    statut = models.CharField(max_length=20, choices=STATUT_CHOICES, default=STATUT_DEMANDE)
    methode = models.CharField(max_length=30, default="mobile_money")
    numero = models.CharField(max_length=30)
    operateur = models.CharField(max_length=50, blank=True, default="")
    reference_kkiapay = models.CharField(max_length=100, blank=True, default="")
    reference_transaction = models.CharField(max_length=100, blank=True, default="")
    note = models.TextField(blank=True, default="")

    class Meta:
        db_table = "retraits"
        ordering = ["-date_creation"]


class Avis(TimeStampedModel):
    colis = models.ForeignKey(Colis, on_delete=models.SET_NULL, null=True, related_name="avis")
    user = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="avis")
    transporteur = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name="avis_recus",
        null=True, blank=True,
    )
    note = models.PositiveSmallIntegerField()  # 1..5
    commentaire = models.TextField(blank=True, default="")
    statut = models.CharField(max_length=20, default="approuve")

    class Meta:
        db_table = "avis"
        ordering = ["-date_creation"]


class ContactMessage(TimeStampedModel):
    nom = models.CharField(max_length=100)
    email = models.EmailField()
    sujet = models.CharField(max_length=200, blank=True, default="")
    message = models.TextField()
    lu = models.BooleanField(default=False)
    reponse = models.TextField(blank=True, default="")
    date_reponse = models.DateTimeField(null=True, blank=True)

    class Meta:
        db_table = "contact_messages"
        ordering = ["-date_creation"]


class NotificationAdmin(TimeStampedModel):
    type = models.CharField(max_length=30)
    colis = models.ForeignKey(Colis, on_delete=models.CASCADE, null=True, blank=True, related_name="notifs_admin")
    message = models.TextField(blank=True, default="")
    lu = models.BooleanField(default=False)

    class Meta:
        db_table = "notifications_admin"
        ordering = ["-date_creation"]


class WalletAdmin(TimeStampedModel):
    """One-row: solde + total_genere de la plateforme."""
    solde = models.DecimalField(max_digits=12, decimal_places=2, default=800)
    total_genere = models.DecimalField(max_digits=12, decimal_places=2, default=0)

    class Meta:
        db_table = "wallet_admin"
