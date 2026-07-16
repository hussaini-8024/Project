"""SQLite persistence for the RMM server."""

from __future__ import annotations

import hashlib
import secrets
import sqlite3
import time
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator

try:
    from server.paths import writable_root
except Exception:  # pragma: no cover
    def writable_root() -> Path:  # type: ignore
        return Path(__file__).resolve().parent.parent


DB_PATH = writable_root() / "data" / "rmm.db"


def _hash_password(password: str, salt: str) -> str:
    return hashlib.sha256(f"{salt}:{password}".encode("utf-8")).hexdigest()


class Database:
    def __init__(self, path: Path | None = None) -> None:
        self.path = path or DB_PATH
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self._init_schema()

    @contextmanager
    def connect(self) -> Iterator[sqlite3.Connection]:
        conn = sqlite3.connect(self.path)
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
                CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    salt TEXT NOT NULL,
                    password_hash TEXT NOT NULL,
                    created_at REAL NOT NULL
                );

                CREATE TABLE IF NOT EXISTS sessions (
                    token TEXT PRIMARY KEY,
                    admin_id INTEGER NOT NULL,
                    created_at REAL NOT NULL,
                    expires_at REAL NOT NULL,
                    FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS agents (
                    id TEXT PRIMARY KEY,
                    hostname TEXT NOT NULL,
                    username TEXT,
                    os_info TEXT,
                    ip_address TEXT,
                    last_seen REAL,
                    online INTEGER DEFAULT 0,
                    enrolled_at REAL NOT NULL,
                    notes TEXT DEFAULT ''
                );

                CREATE TABLE IF NOT EXISTS groups (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    description TEXT DEFAULT '',
                    created_at REAL NOT NULL
                );

                CREATE TABLE IF NOT EXISTS group_members (
                    group_id INTEGER NOT NULL,
                    agent_id TEXT NOT NULL,
                    PRIMARY KEY(group_id, agent_id),
                    FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE,
                    FOREIGN KEY(agent_id) REFERENCES agents(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS software_packages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    local_path TEXT NOT NULL,
                    args TEXT DEFAULT '',
                    created_at REAL NOT NULL
                );

                CREATE TABLE IF NOT EXISTS jobs (
                    id TEXT PRIMARY KEY,
                    kind TEXT NOT NULL,
                    target_agent_id TEXT,
                    target_group_id INTEGER,
                    package_id INTEGER,
                    command TEXT,
                    status TEXT NOT NULL,
                    result TEXT,
                    created_by TEXT,
                    created_at REAL NOT NULL,
                    finished_at REAL
                );

                CREATE TABLE IF NOT EXISTS audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_username TEXT NOT NULL,
                    action TEXT NOT NULL,
                    target TEXT,
                    detail TEXT,
                    created_at REAL NOT NULL
                );

                CREATE TABLE IF NOT EXISTS network_hosts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    label TEXT NOT NULL,
                    host TEXT NOT NULL,
                    last_status TEXT DEFAULT 'unknown',
                    last_latency_ms REAL,
                    last_checked REAL,
                    created_at REAL NOT NULL
                );

                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS discovered_agents (
                    agent_id TEXT PRIMARY KEY,
                    hostname TEXT,
                    username TEXT,
                    os_info TEXT,
                    ip_address TEXT,
                    enrolled INTEGER DEFAULT 0,
                    last_seen REAL NOT NULL,
                    raw_json TEXT
                );

                CREATE TABLE IF NOT EXISTS pending_pcs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_address TEXT NOT NULL,
                    username TEXT NOT NULL,
                    password TEXT NOT NULL,
                    status TEXT NOT NULL,
                    detail TEXT DEFAULT '',
                    created_at REAL NOT NULL,
                    updated_at REAL NOT NULL
                );
                """
            )

    def ensure_admin(self, username: str, password: str) -> None:
        with self.connect() as conn:
            row = conn.execute("SELECT id FROM admins WHERE username = ?", (username,)).fetchone()
            if row:
                return
            salt = secrets.token_hex(16)
            conn.execute(
                "INSERT INTO admins(username, salt, password_hash, created_at) VALUES (?, ?, ?, ?)",
                (username, salt, _hash_password(password, salt), time.time()),
            )

    def verify_admin(self, username: str, password: str) -> int | None:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT id, salt, password_hash FROM admins WHERE username = ?",
                (username,),
            ).fetchone()
            if not row:
                return None
            if _hash_password(password, row["salt"]) != row["password_hash"]:
                return None
            return int(row["id"])

    def create_session(self, admin_id: int, hours: int = 12) -> str:
        token = secrets.token_urlsafe(32)
        now = time.time()
        with self.connect() as conn:
            conn.execute(
                "INSERT INTO sessions(token, admin_id, created_at, expires_at) VALUES (?, ?, ?, ?)",
                (token, admin_id, now, now + hours * 3600),
            )
        return token

    def get_admin_by_session(self, token: str) -> dict[str, Any] | None:
        now = time.time()
        with self.connect() as conn:
            row = conn.execute(
                """
                SELECT a.id, a.username, s.expires_at
                FROM sessions s JOIN admins a ON a.id = s.admin_id
                WHERE s.token = ?
                """,
                (token,),
            ).fetchone()
            if not row or row["expires_at"] < now:
                return None
            return {"id": row["id"], "username": row["username"]}

    def delete_session(self, token: str) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM sessions WHERE token = ?", (token,))

    def upsert_agent(
        self,
        agent_id: str,
        hostname: str,
        username: str,
        os_info: str,
        ip_address: str,
    ) -> None:
        now = time.time()
        with self.connect() as conn:
            existing = conn.execute("SELECT id FROM agents WHERE id = ?", (agent_id,)).fetchone()
            if existing:
                conn.execute(
                    """
                    UPDATE agents
                    SET hostname=?, username=?, os_info=?, ip_address=?, last_seen=?, online=1
                    WHERE id=?
                    """,
                    (hostname, username, os_info, ip_address, now, agent_id),
                )
            else:
                conn.execute(
                    """
                    INSERT INTO agents(id, hostname, username, os_info, ip_address, last_seen, online, enrolled_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?)
                    """,
                    (agent_id, hostname, username, os_info, ip_address, now, now),
                )

    def touch_agent(self, agent_id: str, online: bool = True) -> None:
        with self.connect() as conn:
            conn.execute(
                "UPDATE agents SET last_seen=?, online=? WHERE id=?",
                (time.time(), 1 if online else 0, agent_id),
            )

    def mark_stale_agents_offline(self, older_than_seconds: float) -> None:
        cutoff = time.time() - older_than_seconds
        with self.connect() as conn:
            conn.execute(
                "UPDATE agents SET online=0 WHERE last_seen IS NULL OR last_seen < ?",
                (cutoff,),
            )

    def list_agents(self) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT * FROM agents ORDER BY hostname COLLATE NOCASE"
            ).fetchall()
            return [dict(r) for r in rows]

    def get_agent(self, agent_id: str) -> dict[str, Any] | None:
        with self.connect() as conn:
            row = conn.execute("SELECT * FROM agents WHERE id = ?", (agent_id,)).fetchone()
            return dict(row) if row else None

    def delete_agent(self, agent_id: str) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM agents WHERE id = ?", (agent_id,))

    def create_group(self, name: str, description: str = "") -> int:
        with self.connect() as conn:
            cur = conn.execute(
                "INSERT INTO groups(name, description, created_at) VALUES (?, ?, ?)",
                (name, description, time.time()),
            )
            return int(cur.lastrowid)

    def list_groups(self) -> list[dict[str, Any]]:
        with self.connect() as conn:
            groups = [dict(r) for r in conn.execute("SELECT * FROM groups ORDER BY name").fetchall()]
            for g in groups:
                members = conn.execute(
                    "SELECT agent_id FROM group_members WHERE group_id = ?",
                    (g["id"],),
                ).fetchall()
                g["member_ids"] = [m["agent_id"] for m in members]
            return groups

    def set_group_members(self, group_id: int, agent_ids: list[str]) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM group_members WHERE group_id = ?", (group_id,))
            for aid in agent_ids:
                conn.execute(
                    "INSERT OR IGNORE INTO group_members(group_id, agent_id) VALUES (?, ?)",
                    (group_id, aid),
                )

    def get_group_member_ids(self, group_id: int) -> list[str]:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT agent_id FROM group_members WHERE group_id = ?",
                (group_id,),
            ).fetchall()
            return [r["agent_id"] for r in rows]

    def delete_group(self, group_id: int) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM groups WHERE id = ?", (group_id,))

    def add_package(self, name: str, local_path: str, args: str = "") -> int:
        with self.connect() as conn:
            cur = conn.execute(
                "INSERT INTO software_packages(name, local_path, args, created_at) VALUES (?, ?, ?, ?)",
                (name, local_path, args, time.time()),
            )
            return int(cur.lastrowid)

    def list_packages(self) -> list[dict[str, Any]]:
        with self.connect() as conn:
            return [dict(r) for r in conn.execute("SELECT * FROM software_packages ORDER BY name").fetchall()]

    def get_package(self, package_id: int) -> dict[str, Any] | None:
        with self.connect() as conn:
            row = conn.execute("SELECT * FROM software_packages WHERE id = ?", (package_id,)).fetchone()
            return dict(row) if row else None

    def delete_package(self, package_id: int) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM software_packages WHERE id = ?", (package_id,))

    def create_job(
        self,
        job_id: str,
        kind: str,
        *,
        target_agent_id: str | None = None,
        target_group_id: int | None = None,
        package_id: int | None = None,
        command: str | None = None,
        created_by: str = "",
    ) -> None:
        with self.connect() as conn:
            conn.execute(
                """
                INSERT INTO jobs(id, kind, target_agent_id, target_group_id, package_id, command, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                """,
                (job_id, kind, target_agent_id, target_group_id, package_id, command, created_by, time.time()),
            )

    def update_job(self, job_id: str, status: str, result: str | None = None) -> None:
        with self.connect() as conn:
            conn.execute(
                "UPDATE jobs SET status=?, result=?, finished_at=? WHERE id=?",
                (status, result, time.time(), job_id),
            )

    def list_jobs(self, limit: int = 100) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT * FROM jobs ORDER BY created_at DESC LIMIT ?",
                (limit,),
            ).fetchall()
            return [dict(r) for r in rows]

    def audit(self, admin_username: str, action: str, target: str = "", detail: str = "") -> None:
        with self.connect() as conn:
            conn.execute(
                "INSERT INTO audit_log(admin_username, action, target, detail, created_at) VALUES (?, ?, ?, ?, ?)",
                (admin_username, action, target, detail, time.time()),
            )

    def list_audit(self, limit: int = 200) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ?",
                (limit,),
            ).fetchall()
            return [dict(r) for r in rows]

    def add_network_host(self, label: str, host: str) -> int:
        with self.connect() as conn:
            cur = conn.execute(
                "INSERT INTO network_hosts(label, host, created_at) VALUES (?, ?, ?)",
                (label, host, time.time()),
            )
            return int(cur.lastrowid)

    def list_network_hosts(self) -> list[dict[str, Any]]:
        with self.connect() as conn:
            return [dict(r) for r in conn.execute("SELECT * FROM network_hosts ORDER BY label").fetchall()]

    def update_network_host_status(
        self, host_id: int, status: str, latency_ms: float | None
    ) -> None:
        with self.connect() as conn:
            conn.execute(
                "UPDATE network_hosts SET last_status=?, last_latency_ms=?, last_checked=? WHERE id=?",
                (status, latency_ms, time.time(), host_id),
            )

    def delete_network_host(self, host_id: int) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM network_hosts WHERE id = ?", (host_id,))

    def get_setting(self, key: str, default: str = "") -> str:
        with self.connect() as conn:
            row = conn.execute("SELECT value FROM settings WHERE key = ?", (key,)).fetchone()
            return row["value"] if row else default

    def set_setting(self, key: str, value: str) -> None:
        with self.connect() as conn:
            conn.execute(
                "INSERT INTO settings(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
                (key, value),
            )

    def upsert_discovered(self, data: dict[str, Any]) -> None:
        agent_id = str(data.get("agent_id") or data.get("ip") or "")
        if not agent_id:
            return
        now = time.time()
        with self.connect() as conn:
            enrolled = 1 if conn.execute("SELECT id FROM agents WHERE id = ?", (agent_id,)).fetchone() else 0
            conn.execute(
                """
                INSERT INTO discovered_agents(agent_id, hostname, username, os_info, ip_address, enrolled, last_seen, raw_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT(agent_id) DO UPDATE SET
                    hostname=excluded.hostname,
                    username=excluded.username,
                    os_info=excluded.os_info,
                    ip_address=excluded.ip_address,
                    enrolled=excluded.enrolled,
                    last_seen=excluded.last_seen,
                    raw_json=excluded.raw_json
                """,
                (
                    agent_id,
                    str(data.get("hostname", "")),
                    str(data.get("username", "")),
                    str(data.get("os_info", "")),
                    str(data.get("ip", data.get("ip_address", ""))),
                    enrolled,
                    now,
                    json_dumps(data),
                ),
            )

    def list_discovered(self, max_age_seconds: float = 120) -> list[dict[str, Any]]:
        cutoff = time.time() - max_age_seconds
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT * FROM discovered_agents WHERE last_seen >= ? ORDER BY hostname COLLATE NOCASE",
                (cutoff,),
            ).fetchall()
            return [dict(r) for r in rows]

    def add_pending_pc(self, ip: str, username: str, password: str) -> int:
        now = time.time()
        with self.connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO pending_pcs(ip_address, username, password, status, detail, created_at, updated_at)
                VALUES (?, ?, ?, 'pending', '', ?, ?)
                """,
                (ip, username, password, now, now),
            )
            return int(cur.lastrowid)

    def list_pending_pcs(self) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute("SELECT * FROM pending_pcs ORDER BY created_at DESC").fetchall()
            out = []
            for r in rows:
                d = dict(r)
                d["password"] = "***"
                out.append(d)
            return out

    def get_pending_pc(self, pending_id: int) -> dict[str, Any] | None:
        with self.connect() as conn:
            row = conn.execute("SELECT * FROM pending_pcs WHERE id = ?", (pending_id,)).fetchone()
            return dict(row) if row else None

    def update_pending_pc(self, pending_id: int, status: str, detail: str = "") -> None:
        with self.connect() as conn:
            conn.execute(
                "UPDATE pending_pcs SET status=?, detail=?, updated_at=? WHERE id=?",
                (status, detail, time.time(), pending_id),
            )

    def delete_pending_pc(self, pending_id: int) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM pending_pcs WHERE id = ?", (pending_id,))


def json_dumps(data: dict[str, Any]) -> str:
    import json

    return json.dumps(data)
