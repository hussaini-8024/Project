"""Authentication helpers for AU Labs IT Management."""

from __future__ import annotations

from typing import Any

from passlib.context import CryptContext

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


def hash_password(password: str) -> str:
    return pwd_context.hash(password)


def verify_password(password: str, password_hash: str) -> bool:
    try:
        return pwd_context.verify(password, password_hash)
    except Exception:
        return False


DEFAULT_PERMISSIONS = [
    "files.read",
    "files.write",
    "shell.access",
    "session.create",
]

ADMIN_PERMISSIONS = [
    "files.read",
    "files.write",
    "shell.access",
    "session.create",
    "users.manage",
    "storage.manage",
    "permissions.manage",
    "system.manage",
    "audit.view",
]


def user_public(row: dict[str, Any]) -> dict[str, Any]:
    from aulabs.database import row_permissions

    return {
        "id": row["id"],
        "username": row["username"],
        "role": row["role"],
        "display_name": row.get("display_name") or row["username"],
        "email": row.get("email") or "",
        "home_dir": row["home_dir"],
        "storage_quota_mb": row["storage_quota_mb"],
        "permissions": row_permissions(row),
        "shell": row.get("shell") or "/bin/bash",
        "enabled": bool(row.get("enabled", 1)),
        "created_at": row.get("created_at"),
        "updated_at": row.get("updated_at"),
    }
