"""Storage accounting and directory management."""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any

from aulabs.config import get_settings
from aulabs.database import get_db, utcnow


def dir_size_bytes(path: Path) -> int:
    total = 0
    if not path.exists():
        return 0
    for root, _dirs, files in os.walk(path):
        for name in files:
            try:
                total += (Path(root) / name).stat().st_size
            except OSError:
                continue
    return total


class StorageService:
    def __init__(self) -> None:
        self.settings = get_settings()
        self.db = get_db()

    def usage_for_user(self, user: dict[str, Any]) -> dict[str, Any]:
        home = Path(user["home_dir"])
        used = dir_size_bytes(home)
        quota = int(user["storage_quota_mb"]) * 1024 * 1024
        mounts = self.db.fetchall(
            "SELECT * FROM storage_mounts WHERE user_id = ? ORDER BY name",
            (user["id"],),
        )
        return {
            "user_id": user["id"],
            "username": user["username"],
            "home_dir": str(home),
            "used_bytes": used,
            "used_mb": round(used / (1024 * 1024), 2),
            "quota_mb": user["storage_quota_mb"],
            "quota_bytes": quota,
            "percent_used": round((used / quota) * 100, 2) if quota else 0,
            "over_quota": used > quota if quota else False,
            "mounts": [
                {
                    "id": m["id"],
                    "name": m["name"],
                    "path": m["path"],
                    "size_mb": m["size_mb"],
                    "created_at": m["created_at"],
                }
                for m in mounts
            ],
        }

    def usage_all(self, users: list[dict[str, Any]]) -> list[dict[str, Any]]:
        return [self.usage_for_user(u) for u in users]

    def create_directory(
        self,
        user: dict[str, Any],
        name: str,
        *,
        actor: str = "admin",
    ) -> dict[str, Any]:
        name = name.strip().replace("..", "").strip("/\\")
        if not name:
            raise ValueError("Directory name required")
        path = Path(user["home_dir"]) / name
        if not str(path.resolve()).startswith(str(Path(user["home_dir"]).resolve())):
            raise ValueError("Path escapes user home")
        path.mkdir(parents=True, exist_ok=True)
        mount_id = self.db.execute(
            """
            INSERT INTO storage_mounts (user_id, name, path, size_mb, created_at)
            VALUES (?, ?, ?, 0, ?)
            """,
            (user["id"], name, str(path), utcnow()),
        )
        self.db.audit(actor, "storage.mkdir", user["username"], str(path))
        return {"id": mount_id, "name": name, "path": str(path)}

    def list_files(self, user: dict[str, Any], rel_path: str = "") -> list[dict[str, Any]]:
        home = Path(user["home_dir"]).resolve()
        target = (home / rel_path).resolve() if rel_path else home
        if not str(target).startswith(str(home)):
            raise ValueError("Path escapes user home")
        if not target.exists() or not target.is_dir():
            raise ValueError("Directory not found")
        entries: list[dict[str, Any]] = []
        for item in sorted(target.iterdir(), key=lambda p: (not p.is_dir(), p.name.lower())):
            try:
                st = item.stat()
                entries.append(
                    {
                        "name": item.name,
                        "path": str(item.relative_to(home)),
                        "is_dir": item.is_dir(),
                        "size": st.st_size if item.is_file() else 0,
                        "modified": st.st_mtime,
                        "mode": oct(st.st_mode & 0o777),
                    }
                )
            except OSError:
                continue
        return entries

    def panel_storage_summary(self) -> dict[str, Any]:
        root = self.settings.data_root
        used = dir_size_bytes(root)
        return {
            "data_root": str(root),
            "users_root": str(self.settings.users_root),
            "used_bytes": used,
            "used_mb": round(used / (1024 * 1024), 2),
        }
