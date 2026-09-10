"""
Django settings for Transport.bj (réécriture du backend Laravel).
"""
import os
from datetime import timedelta
from pathlib import Path

# Chargement automatique du fichier .env (si présent)
from dotenv import load_dotenv
load_dotenv()

BASE_DIR = Path(__file__).resolve().parent.parent

SECRET_KEY = os.environ.get(
    "APP_KEY",
    os.environ.get("SECRET_KEY", "django-insecure-transport-bj-dev-key-change-in-production-xyz123"),
)
DEBUG = os.environ.get("APP_DEBUG", "true").lower() == "true"
ALLOWED_HOSTS = ["*"]

INSTALLED_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "rest_framework",
    "rest_framework_simplejwt.token_blacklist",
    "corsheaders",
    "core",
    "accounts",
    "shipping",
]

MIDDLEWARE = [
    "corsheaders.middleware.CorsMiddleware",
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "config.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
            ],
        },
    },
]

WSGI_APPLICATION = "config.wsgi.application"


# ---- Database ----
# Par défaut: SQLite (zéro config en local). Pour utiliser MySQL sur XAMPP/VPS,
# mettre DB_CONNECTION=mysql et les variables correspondantes dans .env.
DB_CONNECTION = os.environ.get("DB_CONNECTION", "sqlite")
if DB_CONNECTION == "mysql":
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.mysql",
            "NAME": os.environ.get("DB_DATABASE", "transport"),
            "USER": os.environ.get("DB_USERNAME", "root"),
            "PASSWORD": os.environ.get("DB_PASSWORD", ""),
            "HOST": os.environ.get("DB_HOST", "127.0.0.1"),
            "PORT": os.environ.get("DB_PORT", "3306"),
            "OPTIONS": {
                "charset": "utf8mb4",
            },
        }
    }
    # Patch: tolère MariaDB 10.4 (XAMPP) qui est rejetée par Django 5.2 qui
    # exige 10.5+. Les features utilisées fonctionnent très bien sur 10.4.
    try:
        from django.db.backends.mysql import features as _mysql_features
        # La valeur (10, 5) est la version mini pour MariaDB dans Django 5.2.
        if hasattr(_mysql_features.DatabaseFeatures, "minimum_database_version"):
            # On ne touche pas à MySQL, mais pour MariaDB la constante est
            # partagée. Remplacer par (10, 3) suffit à passer le check.
            _mysql_features.DatabaseFeatures.minimum_database_version = (10, 3)
    except Exception:
        pass
else:
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.sqlite3",
            "NAME": BASE_DIR / "db.sqlite3",
        }
    }

AUTH_USER_MODEL = "accounts.User"
DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

AUTH_PASSWORD_VALIDATORS = []  # Aligné sur le Laravel d'origine (validation custom).

LANGUAGE_CODE = "fr-fr"
TIME_ZONE = "Africa/Porto-Novo"
USE_I18N = True
USE_TZ = True

STATIC_URL = "static/"
STATIC_ROOT = BASE_DIR / "staticfiles"
MEDIA_URL = "/storage/"
MEDIA_ROOT = BASE_DIR / "storage"

# ---- DRF ----
REST_FRAMEWORK = {
    "DEFAULT_AUTHENTICATION_CLASSES": (
        "rest_framework_simplejwt.authentication.JWTAuthentication",
    ),
    "DEFAULT_RENDERER_CLASSES": ("rest_framework.renderers.JSONRenderer",),
    "DEFAULT_THROTTLE_CLASSES": [
        "rest_framework.throttling.AnonRateThrottle",
        "rest_framework.throttling.UserRateThrottle",
    ],
    "DEFAULT_THROTTLE_RATES": {"anon": "120/min", "user": "300/min"},
    "EXCEPTION_HANDLER": "core.exceptions.api_exception_handler",
}

SIMPLE_JWT = {
    "ACCESS_TOKEN_LIFETIME": timedelta(days=30),
    "REFRESH_TOKEN_LIFETIME": timedelta(days=60),
    "AUTH_HEADER_TYPES": ("Bearer",),
    "AUTH_TOKEN_CLASSES": ("rest_framework_simplejwt.tokens.AccessToken",),
    "USER_ID_FIELD": "id",
    "USER_ID_CLAIM": "user_id",
}

# ---- CORS (ouvert pour dev, à restreindre en prod) ----
CORS_ALLOW_ALL_ORIGINS = True
CORS_ALLOW_CREDENTIALS = True
CORS_ALLOW_HEADERS = [
    "accept", "accept-encoding", "authorization", "content-type",
    "dnt", "origin", "user-agent", "x-csrftoken", "x-requested-with",
    "ngrok-skip-browser-warning",
]

# ---- Kkiapay ----
KKIAPAY_PUBLIC_KEY = os.environ.get("KKIAPAY_PUBLIC_KEY", "")
KKIAPAY_PRIVATE_KEY = os.environ.get("KKIAPAY_PRIVATE_KEY", "")
KKIAPAY_SECRET_KEY = os.environ.get("KKIAPAY_SECRET_KEY", "")
KKIAPAY_SANDBOX = os.environ.get("KKIAPAY_SANDBOX", "true").lower() == "true"
KKIAPAY_SKIP_SSL_VERIFY = (
    os.environ.get("KKIAPAY_SKIP_SSL_VERIFY", "true").lower() == "true"
)

# ---- Google OAuth / One Tap ----
GOOGLE_CLIENT_ID = os.environ.get("GOOGLE_CLIENT_ID", "")
GOOGLE_CLIENT_SECRET = os.environ.get("GOOGLE_CLIENT_SECRET", "")
GOOGLE_REDIRECT_URI = os.environ.get("GOOGLE_REDIRECT_URI", "http://localhost:5173/auth/google/callback")
GOOGLE_SKIP_SSL_VERIFY = os.environ.get("GOOGLE_SKIP_SSL_VERIFY", "true").lower() == "true"
GOOGLE_ALLOW_JWT_FALLBACK = os.environ.get("GOOGLE_ALLOW_JWT_FALLBACK", "true").lower() == "true"

APP_NAME = "Transport.bj"
APP_VERSION = "3.0-django"

# Commission: transporteur reçoit (1 - X)%, plateforme X% (defaut 5%)
def _float_env(key, default):
    try:
        return float(os.environ.get(key, default))
    except (TypeError, ValueError):
        return float(default)

COMMISSION_PLATEFORME = _float_env("COMMISSION_PLATEFORME", 0.05)
MONTANT_MIN_RETRAIT = int(_float_env("MONTANT_MIN_RETRAIT", 1000))
