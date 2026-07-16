"""Application configuration for AU Labs IT Management."""

from __future__ import annotations

import os
import secrets
from pathlib import Path
from functools import lru_cache

from pydantic_settings import BaseSettings


def _default_data_root() -> Path:
    env = os.environ.get("AULABS_DATA_ROOT")
    if env:
        return Path(env)
    # Prefer /opt/aulabs when writable (production install), else local data/
    opt = Path("/opt/aulabs")
    if opt.exists() and os.access(opt, os.W_OK):
        return opt
    return Path(__file__).resolve().parent.parent / "data"


class Settings(BaseSettings):
    app_name: str = "AU Labs IT Management"
    app_version: str = "1.0.0"
    host: str = "127.0.0.1"
    port: int = 8787
    secret_key: str = ""
    data_root: Path = Path()
    users_root: Path = Path()
    sessions_root: Path = Path()
    db_path: Path = Path()
    admin_username: str = "admin"
    admin_password: str = "aulabs-admin"
    session_cookie: str = "aulabs_session"
    session_ttl_hours: int = 12
    default_storage_mb: int = 1024
    bind_localhost_only: bool = True

    class Config:
        env_prefix = "AULABS_"

    def ensure_paths(self) -> None:
        self.data_root.mkdir(parents=True, exist_ok=True)
        self.users_root.mkdir(parents=True, exist_ok=True)
        self.sessions_root.mkdir(parents=True, exist_ok=True)
        self.db_path.parent.mkdir(parents=True, exist_ok=True)


@lru_cache
def get_settings() -> Settings:
    root = _default_data_root()
    secret = os.environ.get("AULABS_SECRET_KEY") or secrets.token_hex(32)
    settings = Settings(
        secret_key=secret,
        data_root=root,
        users_root=root / "users",
        sessions_root=root / "sessions",
        db_path=root / "aulabs.db",
        host=os.environ.get("AULABS_HOST", "127.0.0.1"),
        port=int(os.environ.get("AULABS_PORT", "8787")),
        admin_username=os.environ.get("AULABS_ADMIN_USER", "admin"),
        admin_password=os.environ.get("AULABS_ADMIN_PASS", "aulabs-admin"),
        default_storage_mb=int(os.environ.get("AULABS_DEFAULT_STORAGE_MB", "1024")),
    )
    settings.ensure_paths()
    return settings
