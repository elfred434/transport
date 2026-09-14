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
    """Valide et normalise le téléphone via libphonenumber (ROADMAP #15).

    Accepte les formats locaux béninois (8 chiffres), avec espaces, avec
    indicatif (+229, 00229, 229), et les numéros internationaux des pays
    UEMOA (CI, TG, SN, BF, ML, NE) et internationaux.
    Retourne le numéro en format E.164 (+22997000001).
    """
    if not value:
        return ""
    v = value.strip()
    # Fallback regex simple si phonenumbers pas installé (démarrage à froid)
    try:
        from core.phone_utils import normalize_phone, pretty_phone, is_valid_phone, guess_region
        normalized = normalize_phone(v)
        if normalized:
            return normalized
        # Message d'erreur plus précis
        region = guess_region(v)
        if region:
            raise serializers.ValidationError(
                f"Numéro {pretty_phone(v)} invalide pour {region}."
            )
        raise serializers.ValidationError(
            "Numéro invalide. Format attendu : 8 chiffres pour le Bénin (ex: 97 00 00 01) "
            "ou indicatif international (ex: +225 07 00 00 00 00)."
        )
    except ImportError:
        pass
    # --- Fallback (phonenumbers non installé) ---
    if _BJ_PHONE_RE.match(v):
        return _normalize_phone(v)
    if re.match(r"^\+?\d[\d\s.-]{7,15}$", v):
        return _normalize_phone(v) if re.match(r"^(?:\+?229|00229|229)", v) else re.sub(r"[^\d+]", "", v)
    raise serializers.ValidationError("Numéro invalide (ex: 97 00 00 01 ou +229 97 00 00 01).")


_COMMON_PASSWORDS = {
    # Top 50+ mdp les plus utilisés (OWASP / HIBP)
    "password", "12345678", "123456789", "1234567890", "00000000", "11111111",
    "qwertyui", "qwerty123", "azertyui", "azerty123", "azertyuiop", "qwertyuiop",
    "abcdefgh", "abcd1234", "password1", "passw0rd", "iloveyou", "admin123",
    "welcome1", "letmein1", "monkey12", "dragon12", "master12", "login123",
    "princess1", "qwerty1", "football1", "charlie", "shadow", "sunshine",
    "trustno1", "spiistmove", "spiistmove1", "transport", "benin", "cotonou",
}


def _validate_password(value: str) -> str:
    if len(value) < 8:
        raise serializers.ValidationError("Le mot de passe doit contenir au moins 8 caractères.")
    if value.isdigit():
        raise serializers.ValidationError("Le mot de passe ne peut pas être entièrement numérique.")
    if value.isalpha():
        raise serializers.ValidationError("Le mot de passe doit contenir au moins un chiffre.")
    if value.isalnum() and len(value) < 12:
        raise serializers.ValidationError(
            "Ajoute au moins un caractère spécial (ex: ! @ # $ %) ou utilise 12+ caractères."
        )
    if value.lower() in _COMMON_PASSWORDS:
        raise serializers.ValidationError("Ce mot de passe est trop commun, choisis-en un plus sûr.")
    # Interdire les répétitions évidentes (aaaabbbb, 11112222, etc.)
    if len(set(value.lower())) <= 3:
        raise serializers.ValidationError("Le mot de passe est trop prévisible (trop peu de caractères différents).")
    return value


class UserRegisterSerializer(serializers.ModelSerializer):
    password = serializers.CharField(write_only=True, required=True)
    confirm_password = serializers.CharField(write_only=True, required=False, default="")
    nom = serializers.CharField(required=True, max_length=100)
    prenom = serializers.CharField(required=True, max_length=100)
    telephone = serializers.CharField(required=False, max_length=30, default="", allow_blank=True)

    class Meta:
        model = User
        fields = ["email", "password", "confirm_password", "nom", "prenom", "telephone"]

    def validate_password(self, value):
        return _validate_password(value)

    def validate_telephone(self, value):
        return _validate_phone(value)

    def validate(self, attrs):
        pwd = attrs.get("password", "")
        pwd2 = (attrs.get("confirm_password") or "").strip()
        if pwd2 and pwd != pwd2:
            raise serializers.ValidationError({"confirm_password": "Les mots de passe ne correspondent pas."})
        return attrs

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
