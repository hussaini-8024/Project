"""SQLite persistence for AU Labs IT Management."""

from __future__ import annotations

import json
import sqlite3
import threading
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterator

from aulabs.config import get_settings


def utcnow() -> str:
    return datetime.now(timezone.utc).isoformat()


class Database:
    def __init__(self, path: Path | None = None) -> None:
        settings = get_settings()
        self.path = path or settings.db_path
        self._lock = threading.RLock()
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self._init_schema()

    @contextmanager
    def connect(self) -> Iterator[sqlite3.Connection]:
        with self._lock:
            conn = sqlite3.connect(self.path, check_same_thread=False)
            conn.row_factory = sqlite3.Row
            conn.execute("PRAGMA foreign_keys = ON")
            try:
                yield conn
                conn.commit()
            except Exception:
                conn.rollback()
                raise
            finally:
                conn.close()

    def _init_schema(self) -> None:
        with self.connect() as conn:
            conn.executescript(
                """
                CREATE TABLE IF NOT EXISTS panel_users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'user',
                    display_name TEXT NOT NULL DEFAULT '',
                    email TEXT NOT NULL DEFAULT '',
                    home_dir TEXT NOT NULL,
                    storage_quota_mb INTEGER NOT NULL DEFAULT 1024,
                    permissions TEXT NOT NULL DEFAULT '[]',
                    shell TEXT NOT NULL DEFAULT '/bin/bash',
                    enabled INTEGER NOT NULL DEFAULT 1,
                    linux_uid INTEGER,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS sessions (
                    id TEXT PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    session_type TEXT NOT NULL DEFAULT 'web',
                    working_dir TEXT NOT NULL,
                    env_json TEXT NOT NULL DEFAULT '{}',
                    pid INTEGER,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at TEXT NOT NULL,
                    last_active TEXT NOT NULL,
                    FOREIGN KEY(user_id) REFERENCES panel_users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    actor TEXT NOT NULL,
                    action TEXT NOT NULL,
                    target TEXT NOT NULL DEFAULT '',
                    details TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS storage_mounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    path TEXT NOT NULL,
                    size_mb INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL,
                    FOREIGN KEY(user_id) REFERENCES panel_users(id) ON DELETE CASCADE
                );
                """
            )

    def execute(self, sql: str, params: tuple | list = ()) -> int:
        with self.connect() as conn:
            cur = conn.execute(sql, params)
            return cur.lastrowid or 0

    def fetchone(self, sql: str, params: tuple | list = ()) -> dict[str, Any] | None:
        with self.connect() as conn:
            row = conn.execute(sql, params).fetchone()
            return dict(row) if row else None

    def fetchall(self, sql: str, params: tuple | list = ()) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute(sql, params).fetchall()
            return [dict(r) for r in rows]

    def audit(self, actor: str, action: str, target: str = "", details: str = "") -> None:
        self.execute(
            "INSERT INTO audit_log (actor, action, target, details, created_at) VALUES (?, ?, ?, ?, ?)",
            (actor, action, target, details, utcnow()),
        )


_db: Database | None = None


def get_db() -> Database:
    global _db
    if _db is None:
        _db = Database()
    return _db


def row_permissions(row: dict[str, Any]) -> list[str]:
    raw = row.get("permissions") or "[]"
    if isinstance(raw, list):
        return raw
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return []
