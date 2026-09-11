from django.contrib.auth.models import AbstractBaseUser, BaseUserManager, PermissionsMixin
from django.db import models
from django.utils import timezone


class UserManager(BaseUserManager):
    def create_user(self, email, password=None, **extra):
        if not email:
            raise ValueError("Email requis")
        email = self.normalize_email(email)
        user = self.model(email=email, **extra)
        if password:
            user.set_password(password)
        else:
            user.set_unusable_password()
        user.save(using=self._db)
        return user

    def create_superuser(self, email, password, **extra):
        extra.setdefault("role", "super_admin")
        extra.setdefault("is_staff", True)
        extra.setdefault("is_superuser", True)
        return self.create_user(email, password, **extra)


class User(AbstractBaseUser, PermissionsMixin):
    ROLE_CLIENT = "client"
    ROLE_TRANSPORTEUR = "transporteur"
    ROLE_ADMIN = "admin"
    ROLE_SUPER_ADMIN = "super_admin"
    ROLES = [
        (ROLE_CLIENT, "Client"),
        (ROLE_TRANSPORTEUR, "Transporteur"),
        (ROLE_ADMIN, "Admin"),
        (ROLE_SUPER_ADMIN, "Super Admin"),
    ]

    email = models.EmailField(unique=True)
    nom = models.CharField(max_length=100)
    prenom = models.CharField(max_length=100)
    telephone = models.CharField(max_length=30, blank=True, default="")
    role = models.CharField(max_length=20, choices=ROLES, default=ROLE_CLIENT)
    photo = models.CharField(max_length=500, blank=True, default="")
    # Transporteur-specific fields stored on profile (one-to-one via shipping.Transporteur)
    google_id = models.CharField(max_length=100, blank=True, default="")

    is_active = models.BooleanField(default=True)
    is_staff = models.BooleanField(default=False)
    date_creation = models.DateTimeField(default=timezone.now)
    date_modification = models.DateTimeField(auto_now=True)

    objects = UserManager()

    USERNAME_FIELD = "email"
    REQUIRED_FIELDS = ["nom", "prenom"]

    class Meta:
        db_table = "users"
        ordering = ["-date_creation"]

    def __str__(self):
        return f"{self.prenom} {self.nom} <{self.email}>"

    @property
    def is_admin(self):
        return self.role in (self.ROLE_ADMIN, self.ROLE_SUPER_ADMIN)

    @property
    def is_super(self):
        return self.role == self.ROLE_SUPER_ADMIN


class PasswordResetToken(models.Model):
    """Token de réinitialisation de mot de passe (valide 1h, usage unique)."""
    token = models.CharField(max_length=100, unique=True, db_index=True)
    user = models.ForeignKey("accounts.User", on_delete=models.CASCADE, related_name="reset_tokens")
    created_at = models.DateTimeField(auto_now_add=True)
    expires_at = models.DateTimeField()
    used = models.BooleanField(default=False)

    class Meta:
        db_table = "password_reset_tokens"
        ordering = ["-created_at"]
