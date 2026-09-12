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
# ALLOWED_HOSTS : depuis l'env (virgules) + Render auto + localhost.
RENDER_EXTERNAL_HOSTNAME = os.environ.get("RENDER_EXTERNAL_HOSTNAME", "")
_extra_hosts = [h.strip() for h in os.environ.get("ALLOWED_HOSTS", "").split(",") if h.strip()]
ALLOWED_HOSTS = list(filter(None, [
    "localhost", "127.0.0.1", RENDER_EXTERNAL_HOSTNAME, *_extra_hosts,
]))
# En debug on autorise tout pour simplifier le dev local
if DEBUG:
    ALLOWED_HOSTS = ["*"]

# En production, faire confiance au proxy de Render/Cloudflare pour X-Forwarded-Proto
if not DEBUG:
    SECURE_PROXY_SSL_HEADER = ("HTTP_X_FORWARDED_PROTO", "https")
    USE_X_FORWARDED_HOST = True

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
    "whitenoise.middleware.WhiteNoiseMiddleware",  # sert les static en prod sans Nginx
    "core.middleware.SecurityHeadersMiddleware",
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
# Ordre de priorité :
#   1. DATABASE_URL (Render, Railway, Vercel Postgres, Neon, Heroku)  — PostgreSQL
#   2. DB_CONNECTION=mysql (XAMPP/WAMP/VPS avec MariaDB/MySQL)
#   3. Sinon SQLite par défaut (dev local sans config)
DATABASE_URL = os.environ.get("DATABASE_URL", "")
if DATABASE_URL:
    # Parse postgres:// ou postgresql:// (Render Postgres, Neon, Railway, Heroku).
    # Neon utilise "postgres://user:pass@ep-xxx.region.aws.neon.tech/db?sslmode=require".
    import urllib.parse as _urlparse
    parsed = _urlparse.urlparse(DATABASE_URL)
    if parsed.scheme in ("postgres", "postgresql"):
        _qs = _urlparse.parse_qs(parsed.query or "")
        _opts: dict = {}
        for k in ("sslmode", "sslcert", "sslkey", "sslrootcert", "options"):
            v = _qs.get(k)
            if v:
                _opts[k] = v[0]
        # Neon exige SSL ; on force sslmode=require en prod si pas précisé.
        if not DEBUG and "sslmode" not in _opts:
            _opts["sslmode"] = "require"
        DATABASES = {
            "default": {
                "ENGINE": "django.db.backends.postgresql",
                "NAME": parsed.path.lstrip("/"),
                "USER": parsed.username or "",
                "PASSWORD": parsed.password or "",
                "HOST": parsed.hostname or "",
                "PORT": str(parsed.port or 5432),
                "CONN_MAX_AGE": 60,
                "OPTIONS": _opts,
            }
        }
    else:
        DATABASES = {"default": {"ENGINE": "django.db.backends.sqlite3", "NAME": BASE_DIR / "db.sqlite3"}}
elif os.environ.get("DB_CONNECTION") == "mysql":
    # Backend custom "config.db_backends.mysql" : hérite de django.db.backends.mysql
    # mais force can_return_columns_from_insert=False et version minimum abaissée
    # afin de fonctionner sur MariaDB 10.4 (XAMPP), qui ne supporte pas la clause
    # RETURNING … exigée par Django 5.2 sur MariaDB ≥ 10.5.
    DATABASES = {
        "default": {
            "ENGINE": "config.db_backends.mysql",
            "NAME": os.environ.get("DB_DATABASE", "transport"),
            "USER": os.environ.get("DB_USERNAME", "root"),
            "PASSWORD": os.environ.get("DB_PASSWORD", ""),
            "HOST": os.environ.get("DB_HOST", "127.0.0.1"),
            "PORT": os.environ.get("DB_PORT", "3306"),
            "OPTIONS": {
                "charset": "utf8mb4",
                "init_command": (
                    "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,"
                    "NO_ENGINE_SUBSTITUTION';"
                ),
            },
        }
    }
else:
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.sqlite3",
            "NAME": BASE_DIR / "db.sqlite3",
        }
    }

AUTH_USER_MODEL = "accounts.User"
DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

AUTH_PASSWORD_VALIDATORS = [
    {"NAME": "django.contrib.auth.password_validation.MinimumLengthValidator", "OPTIONS": {"min_length": 8}},
    {"NAME": "django.contrib.auth.password_validation.CommonPasswordValidator"},
    {"NAME": "django.contrib.auth.password_validation.NumericPasswordValidator"},
]


LANGUAGE_CODE = "fr-fr"
TIME_ZONE = "Africa/Porto-Novo"
USE_I18N = True
USE_TZ = True

STATIC_URL = "/static/"
STATIC_ROOT = BASE_DIR / "staticfiles"
STATICFILES_STORAGE = "whitenoise.storage.CompressedManifestStaticFilesStorage"
MEDIA_URL = "/storage/"
MEDIA_ROOT = BASE_DIR / "storage"

SIMPLE_JWT = {
    "ACCESS_TOKEN_LIFETIME": timedelta(hours=2),
    "REFRESH_TOKEN_LIFETIME": timedelta(days=7),
    "ROTATE_REFRESH_TOKENS": True,
    "BLACKLIST_AFTER_ROTATION": True,
    "AUTH_HEADER_TYPES": ("Bearer",),
    "AUTH_TOKEN_CLASSES": ("rest_framework_simplejwt.tokens.AccessToken",),
    "USER_ID_FIELD": "id",
    "USER_ID_CLAIM": "user_id",
    # Vérifie aussi la blacklist pour les access tokens (outstanding_token + jti)
    "BLACKLIST_TOKEN_CHECKS": ("access", "refresh"),
}

# ---- CORS (ouvert en dev ; restreint par liste blanche en prod) ----
_cors_env = os.environ.get("CORS_ALLOWED_ORIGINS", "http://localhost:5173,http://127.0.0.1:5173")
CORS_ALLOWED_ORIGINS = [o.strip() for o in _cors_env.split(",") if o.strip()]
# Ajoute automatiquement les origines connues si elles sont fournies
_auto_origins = [
    os.environ.get("APP_FRONTEND_URL", ""),
    os.environ.get("FRONTEND_URL", ""),
    "https://" + RENDER_EXTERNAL_HOSTNAME if RENDER_EXTERNAL_HOSTNAME else "",
    os.environ.get("VERCEL_URL", ""),  # fourni automatiquement par Vercel
]
for _o in _auto_origins:
    if _o and _o not in CORS_ALLOWED_ORIGINS:
        CORS_ALLOWED_ORIGINS.append(_o.rstrip("/"))
_cors_all = os.environ.get("CORS_ALLOW_ALL_ORIGINS", "true" if DEBUG else "false").lower() == "true"
CORS_ALLOW_ALL_ORIGINS = _cors_all
CORS_ALLOW_CREDENTIALS = True
CORS_ALLOW_HEADERS = [
    "accept", "accept-encoding", "authorization", "content-type",
    "dnt", "origin", "user-agent", "x-csrftoken", "x-requested-with",
    "ngrok-skip-browser-warning",
]

# ---- DRF (auth JSON + throttling + handler d'exception custom) ----
REST_FRAMEWORK = {
    "DEFAULT_AUTHENTICATION_CLASSES": (
        "rest_framework_simplejwt.authentication.JWTAuthentication",
    ),
    "DEFAULT_RENDERER_CLASSES": ("rest_framework.renderers.JSONRenderer",),
    "DEFAULT_THROTTLE_CLASSES": [
        "rest_framework.throttling.AnonRateThrottle",
        "rest_framework.throttling.UserRateThrottle",
    ],
    "DEFAULT_THROTTLE_RATES": {
        "anon": "60/minute",
        "user": "300/minute",
        "login": "10/minute",
        "reset": "3/minute",
    },
    "EXCEPTION_HANDLER": "core.exceptions.api_exception_handler",
}

# ---- Security headers middleware (CSP, HSTS, X-Frame, etc.) ----
SECURE_BROWSER_XSS_FILTER = True
SECURE_CONTENT_TYPE_NOSNIFF = True
X_FRAME_OPTIONS = "DENY"
REFERRER_POLICY = "strict-origin-when-cross-origin"
SECURE_CROSS_ORIGIN_OPENER_POLICY = "same-origin"
if not DEBUG:
    SECURE_SSL_REDIRECT = True
    SECURE_HSTS_SECONDS = 31536000
    SECURE_HSTS_INCLUDE_SUBDOMAINS = True
    SECURE_HSTS_PRELOAD = True
    SESSION_COOKIE_SECURE = True
    CSRF_COOKIE_SECURE = True
    SESSION_COOKIE_HTTPONLY = True
    CSRF_COOKIE_HTTPONLY = True

CSP_DEFAULT_SRC = ("'self'",)
CSP_SCRIPT_SRC = ("'self'", "'unsafe-inline'", "https://cdn.kkiapay.me", "https://www.google.com", "https://www.gstatic.com")
CSP_STYLE_SRC = ("'self'", "'unsafe-inline'", "https://cdnjs.cloudflare.com", "https://cdn.kkiapay.me", "https://fonts.googleapis.com", "https://ka-f.fontawesome.com")
CSP_FONT_SRC = ("'self'", "https://fonts.gstatic.com", "https://cdnjs.cloudflare.com", "https://ka-f.fontawesome.com")
CSP_IMG_SRC = ("'self'", "data:", "blob:", "https:", "http:")  # images colis/profils + uploads locaux
CSP_CONNECT_SRC = ("'self'", "https://api.kkiapay.me", "https://sandbox.kkiapay.me", "https://oauth2.googleapis.com", "https://accounts.google.com")
CSP_FRAME_SRC = ("'self'", "https://www.google.com", "https://accounts.google.com", "https://cdn.kkiapay.me")

# ---- Logging (console + fichier en prod) ----
LOG_DIR = BASE_DIR / "logs"
LOG_DIR.mkdir(exist_ok=True)
LOGGING = {
    "version": 1,
    "disable_existing_loggers": False,
    "formatters": {
        "verbose": {"format": "[%(asctime)s] %(levelname)s %(name)s %(message)s"},
    },
    "handlers": {
        "console": {"class": "logging.StreamHandler", "formatter": "verbose"},
        "file": {
            "class": "logging.handlers.RotatingFileHandler",
            "filename": str(LOG_DIR / "app.log"),
            "maxBytes": 5 * 1024 * 1024,
            "backupCount": 5,
            "formatter": "verbose",
        },
    },
    "root": {
        "handlers": ["console", "file"],
        "level": "INFO",
    },
    "loggers": {
        "django": {"level": "INFO", "handlers": ["console", "file"], "propagate": False},
        "django.request": {"level": "ERROR", "handlers": ["console", "file"], "propagate": False},
        "kkiapay": {"level": "INFO", "handlers": ["console", "file"], "propagate": False},
    },
}

# ---- Nom de l'application ----
APP_NAME = os.environ.get("APP_NAME", "SpiistMove")
APP_VERSION = "3.0-django"

# ---- Email (Brevo API v3 uniquement — pas de SMTP) ----
BREVO_API_KEY = os.environ.get("BREVO_API_KEY", "")
BREVO_SENDER_NAME = os.environ.get("BREVO_SENDER_NAME", APP_NAME)
BREVO_SENDER_EMAIL = os.environ.get("BREVO_SENDER_EMAIL", os.environ.get("DEFAULT_FROM_EMAIL", "").split("<")[-1].rstrip(">").strip())

if BREVO_API_KEY:
    EMAIL_BACKEND = "core.brevo_email.BrevoEmailBackend"
    DEFAULT_FROM_EMAIL = os.environ.get(
        "DEFAULT_FROM_EMAIL",
        f"{BREVO_SENDER_NAME} <{BREVO_SENDER_EMAIL}>" if BREVO_SENDER_EMAIL else f"{APP_NAME} <noreply@spiistmove.com>",
    )
else:
    EMAIL_BACKEND = "django.core.mail.backends.console.EmailBackend"
    DEFAULT_FROM_EMAIL = os.environ.get("DEFAULT_FROM_EMAIL", f"{APP_NAME} <noreply@spiistmove.com>")

APP_FRONTEND_URL = os.environ.get("APP_FRONTEND_URL", "http://localhost:5173")
CONTACT_ADMIN_EMAIL = os.environ.get("CONTACT_ADMIN_EMAIL", DEFAULT_FROM_EMAIL or "elfred434@gmail.com")

# ---- Kkiapay ----
KKIAPAY_PUBLIC_KEY = os.environ.get("KKIAPAY_PUBLIC_KEY", "")
KKIAPAY_PRIVATE_KEY = os.environ.get("KKIAPAY_PRIVATE_KEY", "")
KKIAPAY_SECRET_KEY = os.environ.get("KKIAPAY_SECRET_KEY", "")
KKIAPAY_SANDBOX = os.environ.get("KKIAPAY_SANDBOX", "true").lower() == "true"
KKIAPAY_SKIP_SSL_VERIFY = (
    os.environ.get("KKIAPAY_SKIP_SSL_VERIFY", "true").lower() == "true"
)

# ---- Google OAuth / One Tap ----
GOOGLE_CLIENT_ID = os.environ.get("GOOGLE_CLIENT_ID", os.environ.get("VITE_GOOGLE_CLIENT_ID", ""))
GOOGLE_CLIENT_SECRET = os.environ.get("GOOGLE_CLIENT_SECRET", "")
# Google One Tap n'utilise pas de redirect_uri (c'est côté client), mais si on ajoute
# le flux OAuth serveur un jour, on a besoin d'une URL de retour valide.
_default_redirect = os.environ.get("APP_FRONTEND_URL", "http://localhost:5173").rstrip("/") + "/auth/google/callback"
GOOGLE_REDIRECT_URI = os.environ.get("GOOGLE_REDIRECT_URI", _default_redirect)
GOOGLE_SKIP_SSL_VERIFY = os.environ.get("GOOGLE_SKIP_SSL_VERIFY", "false").lower() == "true"
# En prod, on N'autorise JAMAIS le fallback JWT non vérifié (sécurité)
GOOGLE_ALLOW_JWT_FALLBACK = os.environ.get("GOOGLE_ALLOW_JWT_FALLBACK", "false" if not DEBUG else "true").lower() == "true"

# Commission: plateforme reçoit X%, transporteur reçoit (1 - X)% (defaut 95% / 5%)
def _float_env(key, default):
    try:
        return float(os.environ.get(key, default))
    except (TypeError, ValueError):
        return float(default)

COMMISSION_PLATEFORME = _float_env("COMMISSION_PLATEFORME", 0.95)
MONTANT_MIN_RETRAIT = int(_float_env("MONTANT_MIN_RETRAIT", 1000))
