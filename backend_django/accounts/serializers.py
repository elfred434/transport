from rest_framework import serializers
from .models import User


class UserRegisterSerializer(serializers.ModelSerializer):
    password = serializers.CharField(write_only=True, min_length=6)
    nom = serializers.CharField(required=True, max_length=100)
    prenom = serializers.CharField(required=True, max_length=100)
    telephone = serializers.CharField(required=False, max_length=30, default="")

    class Meta:
        model = User
        fields = ["email", "password", "nom", "prenom", "telephone"]

    def create(self, validated_data):
        # Par défaut, l'inscription publique crée un CLIENT.
        validated_data["role"] = User.ROLE_CLIENT
        return User.objects.create_user(**validated_data)


class UserMeSerializer(serializers.ModelSerializer):
    class Meta:
        model = User
        fields = [
            "id", "email", "nom", "prenom", "telephone", "role",
            "photo", "date_creation",
        ]


class LoginSerializer(serializers.Serializer):
    email = serializers.EmailField()
    password = serializers.CharField()
