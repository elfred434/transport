"""Core app : réponse API standard + exceptions + helpers."""
from rest_framework.response import Response


def api_success(data=None, status_code=200, message=None):
    payload = {"success": True, "data": data if data is not None else {}}
    if message:
        payload["message"] = message
    return Response(payload, status=status_code)


def api_error(message="Erreur", status_code=400):
    return Response({"success": False, "error": message}, status=status_code)
