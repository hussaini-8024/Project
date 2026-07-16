"""User management — isolated directories and panel accounts."""

from __future__ import annotations

import json
import os
import pwd
import shutil
import subprocess
from pathlib import Path
from typing import Any

from aulabs.auth import (
    ADMIN_PERMISSIONS,
    DEFAULT_PERMISSIONS,
    hash_password,
    user_public,
    verify_password,
)
from aulabs.config import get_settings
from aulabs.database import get_db, row_permissions, utcnow


class UserService:
    def __init__(self) -> None:
        self.settings = get_settings()
        self.db = get_db()

    def ensure_admin(self) -> None:
        existing = self.db.fetchone(
            "SELECT id FROM panel_users WHERE username = ?",
            (self.settings.admin_username,),
        )
        if existing:
            return
        home = self.settings.users_root / self.settings.admin_username
        home.mkdir(parents=True, exist_ok=True)
        self._seed_home(home, self.settings.admin_username)
        now = utcnow()
        self.db.execute(
            """
            INSERT INTO panel_users
            (username, password_hash, role, display_name, email, home_dir,
             storage_quota_mb, permissions, shell, enabled, created_at, updated_at)
            VALUES (?, ?, 'admin', ?, '', ?, ?, ?, '/bin/bash', 1, ?, ?)
            """,
            (
                self.settings.admin_username,
                hash_password(self.settings.admin_password),
                "AU Labs Administrator",
                str(home),
                max(self.settings.default_storage_mb, 4096),
                json.dumps(ADMIN_PERMISSIONS),
                now,
                now,
            ),
        )
        self.db.audit("system", "bootstrap_admin", self.settings.admin_username)

    def authenticate(self, username: str, password: str) -> dict[str, Any] | None:
        row = self.db.fetchone(
            "SELECT * FROM panel_users WHERE username = ? AND enabled = 1",
            (username,),
        )
        if not row or not verify_password(password, row["password_hash"]):
            return None
        return user_public(row)

    def list_users(self) -> list[dict[str, Any]]:
        rows = self.db.fetchall("SELECT * FROM panel_users ORDER BY username")
        return [user_public(r) for r in rows]

    def get_user(self, user_id: int) -> dict[str, Any] | None:
        row = self.db.fetchone("SELECT * FROM panel_users WHERE id = ?", (user_id,))
        return user_public(row) if row else None

    def get_by_username(self, username: str) -> dict[str, Any] | None:
        row = self.db.fetchone("SELECT * FROM panel_users WHERE username = ?", (username,))
        return user_public(row) if row else None

    def create_user(
        self,
        *,
        username: str,
        password: str,
        display_name: str = "",
        email: str = "",
        storage_quota_mb: int | None = None,
        permissions: list[str] | None = None,
        role: str = "user",
        actor: str = "admin",
    ) -> dict[str, Any]:
        username = username.strip().lower()
        if not username or not username.replace("_", "").replace("-", "").isalnum():
            raise ValueError("Username must be alphanumeric (dash/underscore allowed)")
        if self.get_by_username(username):
            raise ValueError("Username already exists")

        home = self.settings.users_root / username
        if home.exists():
            raise ValueError(f"Home directory already exists: {home}")

        home.mkdir(parents=True, exist_ok=True)
        self._seed_home(home, username)
        linux_uid = self._try_create_linux_user(username, home)

        quota = storage_quota_mb or self.settings.default_storage_mb
        perms = permissions or list(DEFAULT_PERMISSIONS)
        if role == "admin":
            perms = list(ADMIN_PERMISSIONS)

        now = utcnow()
        user_id = self.db.execute(
            """
            INSERT INTO panel_users
            (username, password_hash, role, display_name, email, home_dir,
             storage_quota_mb, permissions, shell, enabled, linux_uid, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, '/bin/bash', 1, ?, ?, ?)
            """,
            (
                username,
                hash_password(password),
                role,
                display_name or username,
                email,
                str(home),
                quota,
                json.dumps(perms),
                linux_uid,
                now,
                now,
            ),
        )
        self.db.audit(actor, "user.create", username, f"quota={quota}mb")
        user = self.get_user(user_id)
        assert user
        return user

    def update_user(
        self,
        user_id: int,
        *,
        display_name: str | None = None,
        email: str | None = None,
        storage_quota_mb: int | None = None,
        permissions: list[str] | None = None,
        enabled: bool | None = None,
        password: str | None = None,
        actor: str = "admin",
    ) -> dict[str, Any]:
        row = self.db.fetchone("SELECT * FROM panel_users WHERE id = ?", (user_id,))
        if not row:
            raise ValueError("User not found")

        fields: list[str] = []
        values: list[Any] = []
        if display_name is not None:
            fields.append("display_name = ?")
            values.append(display_name)
        if email is not None:
            fields.append("email = ?")
            values.append(email)
        if storage_quota_mb is not None:
            fields.append("storage_quota_mb = ?")
            values.append(storage_quota_mb)
        if permissions is not None:
            fields.append("permissions = ?")
            values.append(json.dumps(permissions))
        if enabled is not None:
            fields.append("enabled = ?")
            values.append(1 if enabled else 0)
        if password:
            fields.append("password_hash = ?")
            values.append(hash_password(password))

        fields.append("updated_at = ?")
        values.append(utcnow())
        values.append(user_id)
        self.db.execute(
            f"UPDATE panel_users SET {', '.join(fields)} WHERE id = ?",
            values,
        )
        self.db.audit(actor, "user.update", row["username"])
        user = self.get_user(user_id)
        assert user
        return user

    def delete_user(self, user_id: int, *, actor: str = "admin", purge_home: bool = False) -> None:
        row = self.db.fetchone("SELECT * FROM panel_users WHERE id = ?", (user_id,))
        if not row:
            raise ValueError("User not found")
        if row["role"] == "admin" and row["username"] == self.settings.admin_username:
            raise ValueError("Cannot delete the primary administrator")

        self.db.execute("DELETE FROM sessions WHERE user_id = ?", (user_id,))
        self.db.execute("DELETE FROM storage_mounts WHERE user_id = ?", (user_id,))
        self.db.execute("DELETE FROM panel_users WHERE id = ?", (user_id,))
        if purge_home:
            home = Path(row["home_dir"])
            if home.exists() and str(home).startswith(str(self.settings.users_root)):
                shutil.rmtree(home, ignore_errors=True)
        self.db.audit(actor, "user.delete", row["username"], f"purge_home={purge_home}")

    def set_permissions(self, user_id: int, permissions: list[str], actor: str = "admin") -> dict[str, Any]:
        return self.update_user(user_id, permissions=permissions, actor=actor)

    def has_permission(self, user: dict[str, Any], permission: str) -> bool:
        if user.get("role") == "admin":
            return True
        return permission in (user.get("permissions") or [])

    def _seed_home(self, home: Path, username: str) -> None:
        for sub in ("workspace", "documents", "downloads", ".config", ".sessions"):
            (home / sub).mkdir(parents=True, exist_ok=True)
        readme = home / "README.txt"
        if not readme.exists():
            readme.write_text(
                f"Welcome to AU Labs IT Management\n"
                f"User: {username}\n"
                f"Home: {home}\n"
                f"Your isolated working environment lives here.\n",
                encoding="utf-8",
            )
        profile = home / ".aulabs_profile"
        if not profile.exists():
            profile.write_text(
                f"export AULABS_USER={username}\n"
                f"export AULABS_HOME={home}\n"
                f"export PS1='[AU Labs:{username}] \\w\\$ '\n"
                f"cd \"$AULABS_HOME/workspace\" 2>/dev/null || cd \"$AULABS_HOME\"\n",
                encoding="utf-8",
            )
        try:
            os.chmod(home, 0o750)
        except OSError:
            pass

    def _try_create_linux_user(self, username: str, home: Path) -> int | None:
        """Best-effort system user creation when running as root."""
        if os.geteuid() != 0:
            try:
                return os.getuid()
            except Exception:
                return None
        sys_name = f"aulabs_{username}"[:32]
        try:
            pwd.getpwnam(sys_name)
            return pwd.getpwnam(sys_name).pw_uid
        except KeyError:
            pass
        try:
            subprocess.run(
                [
                    "useradd",
                    "-M",
                    "-d",
                    str(home),
                    "-s",
                    "/bin/bash",
                    "-c",
                    f"AU Labs user {username}",
                    sys_name,
                ],
                check=True,
                capture_output=True,
                text=True,
            )
            return pwd.getpwnam(sys_name).pw_uid
        except Exception:
            return None
