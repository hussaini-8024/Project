"""Permission catalog and checks."""

from __future__ import annotations

from typing import Any

PERMISSION_CATALOG = [
    {"id": "files.read", "label": "Read files", "description": "Browse and download files in home directory"},
    {"id": "files.write", "label": "Write files", "description": "Create, edit, and delete files"},
    {"id": "shell.access", "label": "Shell access", "description": "Open interactive shell sessions"},
    {"id": "session.create", "label": "Create sessions", "description": "Start isolated working sessions"},
    {"id": "users.manage", "label": "Manage users", "description": "Create and edit panel users"},
    {"id": "storage.manage", "label": "Manage storage", "description": "Adjust quotas and directories"},
    {"id": "permissions.manage", "label": "Manage permissions", "description": "Assign user permissions"},
    {"id": "system.manage", "label": "Manage system", "description": "View and control host OS metrics"},
    {"id": "audit.view", "label": "View audit log", "description": "Inspect panel activity history"},
]


class PermissionService:
    def catalog(self) -> list[dict[str, str]]:
        return list(PERMISSION_CATALOG)

    def validate(self, permissions: list[str]) -> list[str]:
        known = {p["id"] for p in PERMISSION_CATALOG}
        invalid = [p for p in permissions if p not in known]
        if invalid:
            raise ValueError(f"Unknown permissions: {', '.join(invalid)}")
        return list(dict.fromkeys(permissions))

    def summarize(self, user: dict[str, Any]) -> dict[str, Any]:
        granted = set(user.get("permissions") or [])
        if user.get("role") == "admin":
            granted = {p["id"] for p in PERMISSION_CATALOG}
        return {
            "user_id": user["id"],
            "username": user["username"],
            "role": user["role"],
            "permissions": [
                {**p, "granted": p["id"] in granted}
                for p in PERMISSION_CATALOG
            ],
        }
