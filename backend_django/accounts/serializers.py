import re
from rest_framework import serializers
from .models import User


# Téléphone Bénin : +229 / 00229 (optionnels) puis 8 chiffres commençant par 4,5,6,9.
# Accepte aussi les numéros locaux de 8 chiffres sans indicatif.
_BJ_PHONE_RE = re.compile(r"^(?:\+229|00229)?[\s.-]?[4569]\d[\s.-]?\d{2}[\s.-]?\d{2}[\s.-]?\d{2}$")


def _normalize_phone(value: str) -> str:
    """Normalise un numéro béninois en +229XXXXXXXX."""
    if not value:
        return ""
    digits = re.sub(r"\D", "", value)
    if digits.startswith("00229"):
        digits = digits[5:]
    elif digits.startswith("229") and len(digits) == 11:
        digits = digits[3:]
    if len(digits) == 8:
        return "+229" + digits
    return value  # ne pas casser un numéro international qui passerait


def _validate_phone(value: str) -> str:
    """Accepte tous les numéros mais avertit / normalise le format béninois."""
    if not value:
        return ""
    v = value.strip()
    if _BJ_PHONE_RE.match(v):
        return _normalize_phone(v)
    # Accepter numéros internationaux +XXX XXXXXXXXX (7 à 15 chiffres)
    if re.match(r"^\+?\d[\d\s.-]{7,15}$", v):
        return _normalize_phone(v) if re.match(r"^(?:\+?229|00229|229)", v) else re.sub(r"[^\d+]", "", v)
    raise serializers.ValidationError("Numéro de téléphone invalide (format Bénin attendu : +229 XX XX XX XX).")


def _validate_password(value: str) -> str:
    if len(value) < 8:
        raise serializers.ValidationError("Le mot de passe doit contenir au moins 8 caractères.")
    if value.isdigit():
        raise serializers.ValidationError("Le mot de passe ne peut pas être entièrement numérique.")
    if value.isalpha():
        raise serializers.ValidationError("Le mot de passe doit contenir au moins un chiffre.")
    # Interdire les mots de passe les plus communs
    common = {"password", "12345678", "azertyui", "qwertyui", "00000000", "123456789"}
    if value.lower() in common:
        raise serializers.ValidationError("Ce mot de passe est trop commun.")
    return value


class UserRegisterSerializer(serializers.ModelSerializer):
    password = serializers.CharField(write_only=True, required=True)
    nom = serializers.CharField(required=True, max_length=100)
    prenom = serializers.CharField(required=True, max_length=100)
    telephone = serializers.CharField(required=False, max_length=30, default="", allow_blank=True)

    class Meta:
        model = User
        fields = ["email", "password", "nom", "prenom", "telephone"]

    def validate_password(self, value):
        return _validate_password(value)

    def validate_telephone(self, value):
        return _validate_phone(value)

    def create(self, validated_data):
        if "role" not in validated_data or not validated_data.get("role"):
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
