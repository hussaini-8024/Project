from __future__ import annotations

from collections.abc import Callable
from datetime import datetime

from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy.orm import Session

from app.database import get_db
from app.models import Role, User, UserSession, UserStatus
from app.security import decode_token

bearer = HTTPBearer(auto_error=False)

STAFF_ROLES = {Role.SUPER_ADMIN, Role.ADMINISTRATOR, Role.LAB_MANAGER}
ADMIN_ROLES = {Role.SUPER_ADMIN, Role.ADMINISTRATOR}
INSTRUCTOR_PLUS = ADMIN_ROLES | {Role.INSTRUCTOR, Role.LAB_MANAGER}


def get_client_ip(request: Request) -> str:
    forwarded = request.headers.get("x-forwarded-for")
    if forwarded:
        return forwarded.split(",")[0].strip()
    return request.client.host if request.client else ""


def current_user(
    request: Request,
    creds: HTTPAuthorizationCredentials | None = Depends(bearer),
    db: Session = Depends(get_db),
) -> User:
    token = None
    if creds:
        token = creds.credentials
    if not token:
        token = request.cookies.get("access_token")
    if not token:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Not authenticated")
    try:
        payload = decode_token(token)
    except ValueError:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid or expired session")
    if payload.get("type") != "access":
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid token type")
    user = db.get(User, payload.get("sub"))
    if not user or user.status != UserStatus.ACTIVE:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Account is not active")
    if user.expires_at and user.expires_at < datetime.utcnow():
        user.status = UserStatus.EXPIRED
        db.commit()
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Account expired")
    request.state.user = user
    request.state.session_jti = payload.get("jti", "")
    return user


def optional_user(
    request: Request,
    creds: HTTPAuthorizationCredentials | None = Depends(bearer),
    db: Session = Depends(get_db),
) -> User | None:
    try:
        return current_user(request, creds, db)
    except HTTPException:
        return None


def require_roles(*roles: Role) -> Callable[..., User]:
    def _inner(user: User = Depends(current_user)) -> User:
        if user.role not in roles:
            raise HTTPException(status.HTTP_403_FORBIDDEN, "Insufficient privileges")
        return user

    return _inner


def require_staff(user: User = Depends(current_user)) -> User:
    if user.role not in STAFF_ROLES and user.role != Role.INSTRUCTOR:
        if user.role not in ADMIN_ROLES | {Role.LAB_MANAGER, Role.INSTRUCTOR}:
            raise HTTPException(status.HTTP_403_FORBIDDEN, "Staff access required")
    if user.role not in INSTRUCTOR_PLUS:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Staff access required")
    return user


def require_admin(user: User = Depends(current_user)) -> User:
    if user.role not in ADMIN_ROLES:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Administrator access required")
    return user


def active_session(user: User, db: Session) -> UserSession | None:
    return (
        db.query(UserSession)
        .filter(UserSession.user_id == user.id, UserSession.revoked.is_(False))
        .order_by(UserSession.last_seen_at.desc())
        .first()
    )
