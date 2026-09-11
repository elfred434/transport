"""Upload sécurisé de fichiers (images colis, photos profil).

Chaque fichier est enregistré sous storage/<bucket>/<uuid><ext>, avec une
validation de type MIME et de taille. Aucune migration BD n'est nécessaire :
les champs en CharField stockent simplement l'URL retournée.
"""
from __future__ import annotations

import mimetypes
import os
import re
import uuid
from typing import Optional

from django.conf import settings
from django.core.files.storage import default_storage
from django.core.files.base import ContentFile
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated
from rest_framework.request import Request

from core.responses import api_error, api_success


# Buckets autorisés + contraintes
_BUCKETS: dict[str, dict] = {
    "colis": {
        "max_bytes": 5 * 1024 * 1024,  # 5 MB
        "allow_types": {"image/jpeg", "image/png", "image/gif", "image/webp"},
        "allow_exts": {".jpg", ".jpeg", ".png", ".gif", ".webp"},
    },
    "profiles": {
        "max_bytes": 3 * 1024 * 1024,  # 3 MB
        "allow_types": {"image/jpeg", "image/png", "image/webp"},
        "allow_exts": {".jpg", ".jpeg", ".png", ".webp"},
    },
}


def _sanitize_filename(name: str) -> str:
    """Ne garde que l'extension et remplace le nom par un UUID."""
    ext = ""
    if name and "." in name:
        ext = "." + name.rsplit(".", 1)[-1].lower()
    ext = re.sub(r"[^a-z0-9.]", "", ext)
    return ext


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def upload(request: Request) -> object:
    """POST /api/upload?bucket=colis&field=image_colis
    Retourne { url: '/storage/colis/<uuid>.jpg' }.
    """
    bucket = (request.query_params.get("bucket") or "").strip()
    field = (request.query_params.get("field") or "file").strip()
    cfg: Optional[dict] = _BUCKETS.get(bucket)
    if not cfg:
        return api_error(f"Bucket invalide (autorisés : {', '.join(_BUCKETS)})", 400)

    # Recherche du fichier dans request.FILES (FormData multi-part)
    f = request.FILES.get(field) or next(iter(request.FILES.values()), None) if request.FILES else None
    if not f:
        return api_error("Aucun fichier reçu (champ FormData attendu)", 400)

    # Taille
    if f.size > cfg["max_bytes"]:
        mb = cfg["max_bytes"] / (1024 * 1024)
        return api_error(f"Fichier trop volumineux (max {mb:.0f} MB)", 413)

    # Extension / MIME
    ext = _sanitize_filename(f.name)
    if ext not in cfg["allow_exts"]:
        return api_error(
            f"Type de fichier non autorisé (autorisés : {', '.join(sorted(cfg['allow_exts']))})",
            422,
        )
    detected = mimetypes.guess_type(f.name)[0] or ""
    if detected and detected not in cfg["allow_types"]:
        return api_error(f"Type MIME non autorisé : {detected}", 422)

    # Écriture atomique avec nom UUID pour éviter collisions et path traversal
    safe_name = f"{uuid.uuid4().hex}{ext}"
    rel_path = os.path.join(bucket, safe_name)
    saved_path = default_storage.save(rel_path, ContentFile(f.read()))

    # URL publique relative (servie via MEDIA_URL par le dev server / nginx)
    url = settings.MEDIA_URL + saved_path.replace(os.sep, "/")
    return api_success({"url": url, "name": f.name, "size": f.size})
