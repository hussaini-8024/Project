"""Authentication helpers — password hashing and role permissions."""

from __future__ import annotations

import hashlib
import hmac
import os
import secrets
from typing import Optional

ROLES = ("administrator", "allocation_officer", "user", "viewer")

ROLE_LABELS = {
    "administrator": "Administrator",
    "allocation_officer": "Allocation Officer",
    "user": "User",
    "viewer": "Viewer",
}

# Permission matrix
PERMISSIONS = {
    "administrator": {
        "loan_view",
        "loan_upload",
        "loan_create",
        "loan_edit",
        "loan_delete",
        "inventory_view",
        "inventory_manage",
        "inventory_allocate",
        "users_manage",
        "backup",
        "activity_view",
        "presence_view",
    },
    "allocation_officer": {
        "loan_view",
        "loan_upload",
        "loan_create",
        "inventory_view",
        "inventory_manage",
        "inventory_allocate",
        "activity_view",
        "presence_view",
    },
    "user": {
        "loan_view",
        "loan_upload",
        "loan_create",
        "inventory_view",
        "activity_view",
    },
    "viewer": {
        "loan_view",
        "inventory_view",
    },
}


def hash_password(password: str, salt: Optional[str] = None) -> str:
    """PBKDF2-SHA256 password hash. Stored as salt$hash_hex."""
    if salt is None:
        salt = secrets.token_hex(16)
    digest = hashlib.pbkdf2_hmac(
        "sha256",
        password.encode("utf-8"),
        salt.encode("utf-8"),
        120000,
    )
    return f"{salt}${digest.hex()}"


def verify_password(password: str, stored: str) -> bool:
    try:
        salt, _ = stored.split("$", 1)
    except ValueError:
        return False
    return hmac.compare_digest(hash_password(password, salt), stored)


def new_token() -> str:
    return secrets.token_urlsafe(32)


def role_allowed(role: str, permission: str) -> bool:
    return permission in PERMISSIONS.get(role, set())


def default_admin_credentials() -> tuple[str, str]:
    """Default bootstrap admin — change after first login."""
    return "admin", os.environ.get("AU_KAMRA_ADMIN_PASSWORD", "admin123")
