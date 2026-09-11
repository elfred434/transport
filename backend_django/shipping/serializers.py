from rest_framework import serializers
from .models import (
    Colis, Voyage, Reservation, Paiement, SuiviColis, Retrait, Avis,
    ContactMessage, Transporteur,
)


class ColisSerializer(serializers.ModelSerializer):
    class Meta:
        model = Colis
        fields = "__all__"


class ColisCreateSerializer(serializers.Serializer):
    nom_colis = serializers.CharField(max_length=200)
    type_produit = serializers.ChoiceField(choices=Colis.TYPES_PRODUIT)
    nombre_produits = serializers.IntegerField(min_value=1, default=1)
    poids = serializers.DecimalField(max_digits=8, decimal_places=2, min_value=0.1)
    dimensions = serializers.CharField(max_length=50, default="30x20x10")
    pays = serializers.CharField(max_length=100)
    ville = serializers.CharField(max_length=100)
    date_limite = serializers.DateField()
    adresse_depart = serializers.CharField(max_length=255)
    adresse_destination = serializers.CharField(max_length=255)
    description = serializers.CharField(required=False, allow_blank=True, default="")
    image_colis = serializers.CharField(required=False, allow_blank=True, default="")


class VoyageCreateSerializer(serializers.Serializer):
    numero_permis = serializers.CharField(max_length=50)
    vehicule = serializers.CharField(max_length=100)
    compagnie = serializers.CharField(max_length=100, required=False, allow_blank=True, default="")
    adresse = serializers.CharField(max_length=255, required=False, allow_blank=True, default="")
    ville = serializers.CharField(max_length=100)
    pays = serializers.CharField(max_length=100)
    pays_depart = serializers.CharField(max_length=100)
    pays_destination = serializers.CharField(max_length=100)
    date_depart = serializers.DateField()
    heure_depart = serializers.TimeField()
    poids_max = serializers.DecimalField(max_digits=8, decimal_places=2, min_value=0.1)
    email = serializers.EmailField(required=False, allow_blank=True, default="")
    telephone = serializers.CharField(max_length=30, required=False, allow_blank=True, default="")


class VoyageSerializer(serializers.ModelSerializer):
    class Meta:
        model = Voyage
        fields = "__all__"


class PaiementSerializer(serializers.ModelSerializer):
    class Meta:
        model = Paiement
        fields = "__all__"


class ReservationSerializer(serializers.ModelSerializer):
    class Meta:
        model = Reservation
        fields = "__all__"


class RetraitSerializer(serializers.ModelSerializer):
    class Meta:
        model = Retrait
        fields = "__all__"


class AvisSerializer(serializers.ModelSerializer):
    class Meta:
        model = Avis
        fields = "__all__"


class ContactCreateSerializer(serializers.Serializer):
    # nom et email sont optionnels : si l'utilisateur est connecté, ils sont
    # remplis automatiquement à partir de request.user
    nom = serializers.CharField(max_length=100, required=False, allow_blank=True, default="")
    email = serializers.EmailField(required=False, allow_blank=True, default="")
    sujet = serializers.CharField(max_length=200, required=False, allow_blank=True, default="")
    message = serializers.CharField()
