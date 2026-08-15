from __future__ import annotations

from datetime import datetime, timedelta

from fastapi import APIRouter, Depends, HTTPException, Request, Response, status
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.config import get_settings
from app.database import get_db
from app.deps import current_user, get_client_ip
from app.models import Role, User, UserSession, UserStatus
from app.security import (
    create_token,
    decode_token,
    hash_password,
    public_id,
    sha256_hex,
    totp_uri,
    verify_password,
    verify_totp,
    new_mfa_secret,
)
from app.services import audit
from app.services.labs import restore_lab

router = APIRouter(prefix="/auth", tags=["auth"])


class LoginIn(BaseModel):
    username: str
    password: str
    totp: str | None = None


class TokenOut(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "bearer"
    user: dict


class PasswordChange(BaseModel):
    current_password: str
    new_password: str = Field(min_length=10)


class MfaEnable(BaseModel):
    code: str


def _user_public(user: User) -> dict:
    return {
        "id": user.id,
        "public_id": user.public_id,
        "username": user.username,
        "email": user.email,
        "full_name": user.full_name,
        "role": user.role.value,
        "status": user.status.value,
        "course": user.course,
        "mfa_enabled": user.mfa_enabled,
        "lab_id": user.lab.public_id if user.lab else None,
        "quota": user.quota.name if user.quota else None,
    }


@router.post("/login", response_model=TokenOut)
def login(body: LoginIn, request: Request, response: Response, db: Session = Depends(get_db)) -> TokenOut:
    settings = get_settings()
    ip = get_client_ip(request)
    user = db.query(User).filter(User.username == body.username).first()
    if not user:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid credentials")
    if user.locked_until and user.locked_until > datetime.utcnow():
        raise HTTPException(status.HTTP_423_LOCKED, "Account temporarily locked")
    if user.status != UserStatus.ACTIVE:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Account is not active")
    if not verify_password(body.password, user.hashed_password):
        user.failed_logins += 1
        if user.failed_logins >= settings.rate_limit_login:
            user.locked_until = datetime.utcnow() + timedelta(minutes=settings.lockout_minutes)
        db.commit()
        audit.record(db, user=user, action="auth.login", result="failure", ip=ip, detail="bad password")
        db.commit()
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid credentials")
    if user.mfa_enabled:
        if not body.totp or not user.mfa_secret or not verify_totp(user.mfa_secret, body.totp):
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "MFA code required")
    user.failed_logins = 0
    user.locked_until = None
    user.last_login_at = datetime.utcnow()
    session = UserSession(
        public_id=public_id("SES"),
        user_id=user.id,
        refresh_token_hash="",
        ip=ip,
        user_agent=request.headers.get("user-agent", "")[:250],
        expires_at=datetime.utcnow() + timedelta(days=settings.refresh_token_days),
    )
    db.add(session)
    db.flush()
    access = create_token(user.id, settings.access_token_minutes, "access", {"role": user.role.value, "sid": session.public_id})
    refresh = create_token(user.id, settings.refresh_token_days * 24 * 60, "refresh", {"sid": session.id})
    session.refresh_token_hash = sha256_hex(refresh)
    if user.role == Role.STUDENT:
        restore_lab(db, user)
    audit.record(db, user=user, action="auth.login", ip=ip, session_id=session.public_id)
    db.commit()
    response.set_cookie(
        "access_token",
        access,
        httponly=True,
        secure=settings.cookie_secure,
        samesite=settings.cookie_samesite,  # type: ignore[arg-type]
        max_age=settings.access_token_minutes * 60,
    )
    return TokenOut(access_token=access, refresh_token=refresh, user=_user_public(user))


@router.post("/refresh")
def refresh(body: dict, db: Session = Depends(get_db)) -> dict:
    token = body.get("refresh_token", "")
    try:
        payload = decode_token(token)
    except ValueError:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid refresh token")
    if payload.get("type") != "refresh":
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid token type")
    session = db.get(UserSession, payload.get("sid"))
    if not session or session.revoked or session.refresh_token_hash != sha256_hex(token):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Session revoked")
    settings = get_settings()
    access = create_token(session.user_id, settings.access_token_minutes, "access", {"sid": session.public_id})
    return {"access_token": access, "token_type": "bearer"}


@router.post("/logout")
def logout(request: Request, response: Response, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    for s in db.query(UserSession).filter(UserSession.user_id == user.id, UserSession.revoked.is_(False)):
        s.revoked = True
    audit.record(db, user=user, action="auth.logout", ip=get_client_ip(request))
    db.commit()
    response.delete_cookie("access_token")
    return {"ok": True}


@router.get("/me")
def me(user: User = Depends(current_user)) -> dict:
    return _user_public(user)


@router.post("/password")
def change_password(body: PasswordChange, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    if not verify_password(body.current_password, user.hashed_password):
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Current password is incorrect")
    user.hashed_password = hash_password(body.new_password)
    audit.record(db, user=user, action="auth.password_change")
    db.commit()
    return {"ok": True}


@router.post("/mfa/setup")
def mfa_setup(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    if user.role not in (Role.SUPER_ADMIN, Role.ADMINISTRATOR) and not user.mfa_enabled:
        # MFA is required for administrators; optional but available for others
        pass
    secret = new_mfa_secret()
    user.mfa_secret = secret
    db.commit()
    return {"secret": secret, "otpauth_url": totp_uri(secret, user.username)}


@router.post("/mfa/enable")
def mfa_enable(body: MfaEnable, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    if not user.mfa_secret or not verify_totp(user.mfa_secret, body.code):
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Invalid MFA code")
    user.mfa_enabled = True
    audit.record(db, user=user, action="auth.mfa_enable")
    db.commit()
    return {"ok": True}
