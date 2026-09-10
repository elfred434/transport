from rest_framework import status
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import AllowAny, IsAuthenticated
from rest_framework.request import Request
from django.contrib.auth import authenticate
from rest_framework_simplejwt.tokens import RefreshToken

from core.responses import api_success, api_error
from .models import User
from .serializers import UserRegisterSerializer, UserMeSerializer


def _tokens_for(user: User):
    refresh = RefreshToken.for_user(user)
    return {
        "access": str(refresh.access_token),
        "refresh": str(refresh),
    }


def _auth_payload(user: User):
    return {
        "user": UserMeSerializer(user).data,
        "token": str(RefreshToken.for_user(user).access_token),
        "refresh": str(RefreshToken.for_user(user)),
        "role": user.role,
    }


@api_view(["POST"])
@permission_classes([AllowAny])
def register(request: Request):
    data = request.data
    # Détermine le rôle: par défaut client, ?role=transporteur lors d'inscription transporteur
    role = (data.get("role") or User.ROLE_CLIENT).strip()
    if role not in (User.ROLE_CLIENT, User.ROLE_TRANSPORTEUR):
        role = User.ROLE_CLIENT

    ser = UserRegisterSerializer(data=data)
    ser.is_valid(raise_exception=True)
    user = ser.save(role=role)
    return api_success(_auth_payload(user), status_code=status.HTTP_201_CREATED)


@api_view(["POST"])
@permission_classes([AllowAny])
def login(request: Request):
    email = (request.data.get("email") or "").strip().lower()
    password = request.data.get("password") or ""
    user = authenticate(request, email=email, password=password)
    if not user:
        # Essai manuel (quelques backends custom ignorent is_active)
        try:
            u = User.objects.get(email=email)
            if u.check_password(password) and u.is_active:
                user = u
        except User.DoesNotExist:
            user = None
    if not user:
        return api_error("Identifiants invalides", status.HTTP_401_UNAUTHORIZED)
    return api_success(_auth_payload(user))


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def logout(request: Request):
    try:
        # Blacklist le refresh si on reçoit un token refresh dans le body
        from rest_framework_simplejwt.token_blacklist.models import (
            BlacklistedToken, OutstandingToken,
        )
        refresh = request.data.get("refresh")
        if refresh:
            token = RefreshToken(refresh)
            token.blacklist()
    except Exception:
        pass
    return api_success({"message": "Déconnexion réussie"})


@api_view(["GET"])
@permission_classes([IsAuthenticated])
def me(request: Request):
    return api_success(UserMeSerializer(request.user).data)
