# Re-export everything from the official MySQL backend features module so
# Django's internals can still find symbols when it imports this package.
from django.db.backends.mysql.features import *  # noqa: F401,F403
from .base import DatabaseFeatures  # noqa: F401 — override
