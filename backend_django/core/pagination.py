"""Pagination qui reproduit le format Laravel: {data:[...], meta:{...}}."""
from django.core.paginator import Paginator, EmptyPage
from rest_framework.request import Request


def paginate_queryset(qs, request: Request, per_page_default=15, serializer=None):
    try:
        per_page = int(request.query_params.get("per_page", per_page_default))
        per_page = max(1, min(per_page, 100))
    except (TypeError, ValueError):
        per_page = per_page_default
    try:
        page = int(request.query_params.get("page", 1))
    except (TypeError, ValueError):
        page = 1

    paginator = Paginator(qs, per_page)
    try:
        page_obj = paginator.page(page)
    except EmptyPage:
        page_obj = paginator.page(paginator.num_pages if paginator.num_pages > 0 else 1)

    items = list(page_obj.object_list)
    if serializer is not None:
        # serializer peut être une classe DRF, ou une callable(objs, many=True)
        # qui retourne soit un Serializer, soit une liste de dicts.
        try:
            items = serializer(items, many=True).data
        except AttributeError:
            items = serializer(items, many=True)

    return {
        "data": items,
        "pagination": {
            "page": page_obj.number,
            "per_page": per_page,
            "total": paginator.count,
            "last_page": max(1, paginator.num_pages),
            "from": page_obj.start_index(),
            "to": page_obj.end_index(),
            "current_page": page_obj.number,
        },
        # Alias "meta" au cas ou d'autres consommateurs liraient meta
        "meta": {
            "current_page": page_obj.number,
            "per_page": per_page,
            "total": paginator.count,
            "last_page": max(1, paginator.num_pages),
            "from": page_obj.start_index(),
            "to": page_obj.end_index(),
        },
    }
