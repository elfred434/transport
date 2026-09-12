"""Exception handler DRF → format {success:false,error:...} identique au Laravel."""
from rest_framework.views import exception_handler
from rest_framework.exceptions import (
    AuthenticationFailed, NotAuthenticated, PermissionDenied, NotFound,
    ValidationError, Throttled,
)
from rest_framework import status
from .responses import api_error


def api_exception_handler(exc, context):
    # Laisse DRF générer sa réponse standard qu'on reformate ensuite
    response = exception_handler(exc, context)
    if response is not None:
        if isinstance(exc, NotAuthenticated):
            return api_error("Authentification requise", status.HTTP_401_UNAUTHORIZED)
        if isinstance(exc, AuthenticationFailed):
            return api_error("Identifiants invalides", status.HTTP_401_UNAUTHORIZED)
        if isinstance(exc, PermissionDenied):
            # Si le détail est un dict (code: EMAIL_NOT_VERIFIED, etc.), le conserver pour que le frontend puisse réagir
            detail = exc.detail
            if isinstance(detail, dict):
                from rest_framework.response import Response
                return Response({"success": False, "error": detail}, status=status.HTTP_403_FORBIDDEN)
            return api_error(str(detail) if detail else "Accès interdit", status.HTTP_403_FORBIDDEN)
        if isinstance(exc, NotFound):
            msg = exc.detail if isinstance(exc.detail, str) else "Ressource introuvable"
            if msg == "Not found.":
                msg = "Endpoint inconnu"
            return api_error(str(msg), status.HTTP_404_NOT_FOUND)
        if isinstance(exc, ValidationError):
            # Aplatit le dict d'erreurs en un seul message lisible
            detail = exc.detail
            if isinstance(detail, dict):
                first = next(iter(detail.values()))
                if isinstance(first, list):
                    msg = str(first[0])
                else:
                    msg = str(first)
            elif isinstance(detail, list):
                msg = str(detail[0])
            else:
                msg = str(detail)
            return api_error(msg, status.HTTP_422_UNPROCESSABLE_ENTITY)
        if isinstance(exc, Throttled):
            from rest_framework.response import Response
            wait = getattr(exc, "wait", None)
            msg = "Trop de requêtes, ralentissez." + (f" Réessaie dans {int(wait)}s." if wait else "")
            resp = Response({"success": False, "error": msg}, status=status.HTTP_429_TOO_MANY_REQUESTS)
            if wait:
                resp["Retry-After"] = str(int(wait))
            return resp
        return api_error(str(exc.detail), response.status_code)

    # Exception non-DRF : 500 générique
    import logging
    logger = logging.getLogger(__name__)
    logger.exception("Erreur non gérée: %s", exc)
    return api_error("Erreur interne du serveur", status.HTTP_500_INTERNAL_SERVER_ERROR)
