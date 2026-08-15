from __future__ import annotations

import hashlib
import secrets
from datetime import datetime, timedelta
from typing import Any

import pyotp
from jose import JWTError, jwt
from passlib.context import CryptContext

from app.config import get_settings

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


def hash_password(password: str) -> str:
    return pwd_context.hash(password[:72])


def verify_password(password: str, hashed: str) -> bool:
    return pwd_context.verify(password[:72], hashed)


def create_token(subject: str, minutes: int, token_type: str, extra: dict[str, Any] | None = None) -> str:
    settings = get_settings()
    payload: dict[str, Any] = {
        "sub": subject,
        "type": token_type,
        "jti": secrets.token_hex(16),
        "exp": datetime.utcnow() + timedelta(minutes=minutes),
        "iat": datetime.utcnow(),
    }
    if extra:
        payload.update(extra)
    return jwt.encode(payload, settings.secret_key, algorithm=settings.algorithm)


def decode_token(token: str) -> dict[str, Any]:
    settings = get_settings()
    try:
        return jwt.decode(token, settings.secret_key, algorithms=[settings.algorithm])
    except JWTError as exc:
        raise ValueError("Invalid token") from exc


def public_id(prefix: str, width: int = 9) -> str:
    """Cryptographically random display identifier. Not used for authorization."""
    alphabet = "0123456789ABCDEFGHJKMNPQRSTVWXYZ"
    raw = secrets.token_bytes(16)
    n = int.from_bytes(raw, "big")
    chars = []
    for _ in range(width):
        chars.append(alphabet[n % 32])
        n //= 32
    return f"{prefix}-{''.join(reversed(chars))}"


def sha256_hex(value: str) -> str:
    return hashlib.sha256(value.encode()).hexdigest()


def new_mfa_secret() -> str:
    return pyotp.random_base32()


def verify_totp(secret: str, code: str) -> bool:
    return pyotp.TOTP(secret).verify(code, valid_window=1)


def totp_uri(secret: str, username: str) -> str:
    return pyotp.TOTP(secret).provisioning_uri(name=username, issuer_name="University Cyber Range")
