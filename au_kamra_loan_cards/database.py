"""SQLite persistence for users, inventory, loan cards, sessions, and activity."""

from __future__ import annotations

import json
import sqlite3
from contextlib import contextmanager
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Iterator, Optional

from au_kamra_loan_cards import ONLINE_TIMEOUT_SECONDS, db_path
from au_kamra_loan_cards.auth import (
    ROLES,
    default_admin_credentials,
    hash_password,
    new_token,
    verify_password,
)


def _now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


def _now_dt() -> datetime:
    return datetime.now(timezone.utc)


class Database:
    def __init__(self, path: Optional[Path] = None) -> None:
        self.path = Path(path) if path else db_path()
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self._init_schema()
        self._ensure_admin()

    @contextmanager
    def connect(self) -> Iterator[sqlite3.Connection]:
        conn = sqlite3.connect(str(self.path))
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
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE COLLATE NOCASE,
                    password_hash TEXT NOT NULL,
                    full_name TEXT NOT NULL,
                    role TEXT NOT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    created_by INTEGER
                );

                CREATE TABLE IF NOT EXISTS sessions (
                    token TEXT PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    client_type TEXT NOT NULL DEFAULT 'server',
                    client_ip TEXT,
                    client_hostname TEXT,
                    current_view TEXT,
                    current_activity TEXT,
                    last_seen TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS inventory (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    item_name TEXT NOT NULL,
                    serial_number TEXT,
                    category TEXT,
                    status TEXT NOT NULL DEFAULT 'available',
                    quantity TEXT DEFAULT '1',
                    allocation_officer TEXT,
                    allocated_to TEXT,
                    allocation_date TEXT,
                    issue_date TEXT,
                    added_date TEXT,
                    notes TEXT,
                    created_by INTEGER,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS loan_cards (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    card_number TEXT,
                    name TEXT NOT NULL,
                    designation TEXT,
                    department TEXT,
                    tel_extension TEXT,
                    issue_date TEXT,
                    file_path TEXT,
                    file_type TEXT,
                    original_filename TEXT,
                    notes TEXT,
                    source TEXT DEFAULT 'upload',
                    created_by INTEGER,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS equipment_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    loan_card_id INTEGER NOT NULL,
                    item_name TEXT NOT NULL,
                    serial_number TEXT,
                    quantity TEXT,
                    remarks TEXT,
                    FOREIGN KEY (loan_card_id) REFERENCES loan_cards(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS activity_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    action TEXT NOT NULL,
                    details TEXT,
                    username TEXT,
                    user_id INTEGER,
                    client_type TEXT,
                    created_at TEXT NOT NULL
                );

                CREATE INDEX IF NOT EXISTS idx_cards_name ON loan_cards(name);
                CREATE INDEX IF NOT EXISTS idx_cards_dept ON loan_cards(department);
                CREATE INDEX IF NOT EXISTS idx_cards_desig ON loan_cards(designation);
                CREATE INDEX IF NOT EXISTS idx_cards_tel ON loan_cards(tel_extension);
                CREATE INDEX IF NOT EXISTS idx_cards_issue ON loan_cards(issue_date);
                CREATE INDEX IF NOT EXISTS idx_items_name ON equipment_items(item_name);
                CREATE INDEX IF NOT EXISTS idx_inv_name ON inventory(item_name);
                CREATE INDEX IF NOT EXISTS idx_inv_status ON inventory(status);
                CREATE INDEX IF NOT EXISTS idx_inv_officer ON inventory(allocation_officer);
                CREATE INDEX IF NOT EXISTS idx_inv_alloc_date ON inventory(allocation_date);
                CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
                CREATE INDEX IF NOT EXISTS idx_sessions_seen ON sessions(last_seen);
                """
            )
            self._migrate(conn)

    def _migrate(self, conn: sqlite3.Connection) -> None:
        """Add columns introduced in v2 without wiping existing data."""
        def cols(table: str) -> set[str]:
            return {r[1] for r in conn.execute(f"PRAGMA table_info({table})").fetchall()}

        card_cols = cols("loan_cards")
        if "created_by" not in card_cols:
            conn.execute("ALTER TABLE loan_cards ADD COLUMN created_by INTEGER")

        log_cols = cols("activity_log")
        if "username" not in log_cols:
            conn.execute("ALTER TABLE activity_log ADD COLUMN username TEXT")
        if "user_id" not in log_cols:
            conn.execute("ALTER TABLE activity_log ADD COLUMN user_id INTEGER")
        if "client_type" not in log_cols:
            conn.execute("ALTER TABLE activity_log ADD COLUMN client_type TEXT")

    def _ensure_admin(self) -> None:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT id FROM users WHERE role='administrator' LIMIT 1"
            ).fetchone()
            if row:
                return
            username, password = default_admin_credentials()
            now = _now()
            conn.execute(
                """
                INSERT INTO users (username, password_hash, full_name, role, is_active, created_at, updated_at)
                VALUES (?, ?, ?, 'administrator', 1, ?, ?)
                """,
                (username, hash_password(password), "System Administrator", now, now),
            )
            conn.execute(
                "INSERT INTO activity_log (action, details, username, created_at) VALUES (?, ?, ?, ?)",
                ("bootstrap", f"Default administrator '{username}' created", username, now),
            )

    def log(
        self,
        action: str,
        details: str = "",
        *,
        username: str = "",
        user_id: Optional[int] = None,
        client_type: str = "",
    ) -> None:
        with self.connect() as conn:
            conn.execute(
                """
                INSERT INTO activity_log (action, details, username, user_id, client_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                (action, details, username, user_id, client_type, _now()),
            )

    # ── Auth / users ──────────────────────────────────────────────

    def authenticate(self, username: str, password: str) -> Optional[dict[str, Any]]:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT * FROM users WHERE username=? COLLATE NOCASE",
                (username.strip(),),
            ).fetchone()
        if not row:
            return None
        user = dict(row)
        if not user["is_active"]:
            return None
        if not verify_password(password, user["password_hash"]):
            return None
        user.pop("password_hash", None)
        return user

    def create_session(
        self,
        user_id: int,
        *,
        client_type: str = "server",
        client_ip: str = "",
        client_hostname: str = "",
    ) -> str:
        token = new_token()
        now = _now()
        with self.connect() as conn:
            conn.execute(
                """
                INSERT INTO sessions
                    (token, user_id, client_type, client_ip, client_hostname,
                     current_view, current_activity, last_seen, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    token,
                    user_id,
                    client_type,
                    client_ip,
                    client_hostname,
                    "dashboard",
                    "Logged in",
                    now,
                    now,
                ),
            )
        return token

    def get_session_user(self, token: str) -> Optional[dict[str, Any]]:
        if not token:
            return None
        with self.connect() as conn:
            row = conn.execute(
                """
                SELECT u.id, u.username, u.full_name, u.role, u.is_active,
                       s.client_type, s.client_ip, s.current_view, s.current_activity, s.last_seen
                FROM sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.token = ?
                """,
                (token,),
            ).fetchone()
        if not row:
            return None
        user = dict(row)
        if not user["is_active"]:
            return None
        return user

    def touch_session(
        self,
        token: str,
        *,
        current_view: str = "",
        current_activity: str = "",
        client_ip: str = "",
    ) -> None:
        fields = ["last_seen=?"]
        params: list[Any] = [_now()]
        if current_view:
            fields.append("current_view=?")
            params.append(current_view)
        if current_activity:
            fields.append("current_activity=?")
            params.append(current_activity)
        if client_ip:
            fields.append("client_ip=?")
            params.append(client_ip)
        params.append(token)
        with self.connect() as conn:
            conn.execute(
                f"UPDATE sessions SET {', '.join(fields)} WHERE token=?",
                params,
            )

    def destroy_session(self, token: str) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM sessions WHERE token=?", (token,))

    def list_users(self) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute(
                """
                SELECT id, username, full_name, role, is_active, created_at, updated_at
                FROM users ORDER BY username COLLATE NOCASE
                """
            ).fetchall()
            return [dict(r) for r in rows]

    def get_user(self, user_id: int) -> Optional[dict[str, Any]]:
        with self.connect() as conn:
            row = conn.execute(
                """
                SELECT id, username, full_name, role, is_active, created_at, updated_at
                FROM users WHERE id=?
                """,
                (user_id,),
            ).fetchone()
            return dict(row) if row else None

    def create_user(
        self,
        *,
        username: str,
        password: str,
        full_name: str,
        role: str,
        created_by: Optional[int] = None,
    ) -> int:
        if role not in ROLES:
            raise ValueError(f"Invalid role: {role}")
        now = _now()
        with self.connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO users (username, password_hash, full_name, role, is_active, created_at, updated_at, created_by)
                VALUES (?, ?, ?, ?, 1, ?, ?, ?)
                """,
                (
                    username.strip(),
                    hash_password(password),
                    full_name.strip(),
                    role,
                    now,
                    now,
                    created_by,
                ),
            )
            uid = int(cur.lastrowid)
        self.log("user_create", f"Created user {username} ({role})", user_id=created_by)
        return uid

    def update_user(self, user_id: int, **fields: Any) -> None:
        allowed = {"full_name", "role", "is_active", "password_hash"}
        updates = {k: v for k, v in fields.items() if k in allowed and v is not None}
        if "role" in updates and updates["role"] not in ROLES:
            raise ValueError(f"Invalid role: {updates['role']}")
        if not updates:
            return
        updates["updated_at"] = _now()
        with self.connect() as conn:
            cols = ", ".join(f"{k}=?" for k in updates)
            conn.execute(
                f"UPDATE users SET {cols} WHERE id=?",
                (*updates.values(), user_id),
            )

    def set_user_password(self, user_id: int, password: str) -> None:
        self.update_user(user_id, password_hash=hash_password(password))

    def delete_user(self, user_id: int) -> bool:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT role FROM users WHERE id=?", (user_id,)
            ).fetchone()
            if not row:
                return False
            if row["role"] == "administrator":
                admins = conn.execute(
                    "SELECT COUNT(*) AS n FROM users WHERE role='administrator' AND is_active=1"
                ).fetchone()["n"]
                if admins <= 1:
                    raise ValueError("Cannot delete the last active administrator")
            conn.execute("DELETE FROM users WHERE id=?", (user_id,))
        return True

    def online_users(self, timeout_seconds: int = ONLINE_TIMEOUT_SECONDS) -> list[dict[str, Any]]:
        cutoff = (_now_dt() - timedelta(seconds=timeout_seconds)).strftime(
            "%Y-%m-%d %H:%M:%S"
        )
        with self.connect() as conn:
            rows = conn.execute(
                """
                SELECT u.id AS user_id, u.username, u.full_name, u.role,
                       s.client_type, s.client_ip, s.client_hostname,
                       s.current_view, s.current_activity, s.last_seen, s.created_at AS session_started
                FROM sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.last_seen >= ? AND u.is_active = 1
                ORDER BY s.last_seen DESC
                """,
                (cutoff,),
            ).fetchall()
            return [dict(r) for r in rows]

    def purge_stale_sessions(self, timeout_seconds: int = ONLINE_TIMEOUT_SECONDS * 10) -> None:
        cutoff = (_now_dt() - timedelta(seconds=timeout_seconds)).strftime(
            "%Y-%m-%d %H:%M:%S"
        )
        with self.connect() as conn:
            conn.execute("DELETE FROM sessions WHERE last_seen < ?", (cutoff,))

    # ── Inventory ─────────────────────────────────────────────────

    def add_inventory(self, **fields: Any) -> int:
        now = _now()
        added_date = (fields.get("added_date") or now[:10]).strip()
        with self.connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO inventory (
                    item_name, serial_number, category, status, quantity,
                    allocation_officer, allocated_to, allocation_date, issue_date,
                    added_date, notes, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    (fields.get("item_name") or "").strip(),
                    (fields.get("serial_number") or "").strip(),
                    (fields.get("category") or "").strip(),
                    (fields.get("status") or "available").strip(),
                    (fields.get("quantity") or "1").strip(),
                    (fields.get("allocation_officer") or "").strip(),
                    (fields.get("allocated_to") or "").strip(),
                    (fields.get("allocation_date") or "").strip(),
                    (fields.get("issue_date") or "").strip(),
                    added_date,
                    (fields.get("notes") or "").strip(),
                    fields.get("created_by"),
                    now,
                    now,
                ),
            )
            inv_id = int(cur.lastrowid)
        self.log(
            "inventory_add",
            f"Inventory #{inv_id}: {fields.get('item_name')}",
            user_id=fields.get("created_by"),
            username=fields.get("username") or "",
        )
        return inv_id

    def update_inventory(self, inv_id: int, **fields: Any) -> None:
        allowed = {
            "item_name",
            "serial_number",
            "category",
            "status",
            "quantity",
            "allocation_officer",
            "allocated_to",
            "allocation_date",
            "issue_date",
            "added_date",
            "notes",
        }
        updates = {k: v for k, v in fields.items() if k in allowed and v is not None}
        if not updates:
            return
        updates["updated_at"] = _now()
        with self.connect() as conn:
            cols = ", ".join(f"{k}=?" for k in updates)
            conn.execute(
                f"UPDATE inventory SET {cols} WHERE id=?",
                (*updates.values(), inv_id),
            )
        self.log(
            "inventory_update",
            f"Inventory #{inv_id}",
            user_id=fields.get("actor_id"),
            username=fields.get("username") or "",
        )

    def delete_inventory(self, inv_id: int, *, actor_id: Optional[int] = None, username: str = "") -> bool:
        with self.connect() as conn:
            cur = conn.execute("DELETE FROM inventory WHERE id=?", (inv_id,))
            deleted = cur.rowcount > 0
        if deleted:
            self.log("inventory_delete", f"Inventory #{inv_id}", user_id=actor_id, username=username)
        return deleted

    def get_inventory(self, inv_id: int) -> Optional[dict[str, Any]]:
        with self.connect() as conn:
            row = conn.execute("SELECT * FROM inventory WHERE id=?", (inv_id,)).fetchone()
            return dict(row) if row else None

    def search_inventory(
        self,
        *,
        item_name: str = "",
        allocation_officer: str = "",
        allocated_to: str = "",
        status: str = "",
        added_date_from: str = "",
        added_date_to: str = "",
        issue_date_from: str = "",
        issue_date_to: str = "",
        allocation_date_from: str = "",
        allocation_date_to: str = "",
        query: str = "",
    ) -> list[dict[str, Any]]:
        clauses: list[str] = []
        params: list[Any] = []

        def like(field: str, value: str) -> None:
            if value.strip():
                clauses.append(f"{field} LIKE ?")
                params.append(f"%{value.strip()}%")

        like("item_name", item_name)
        like("allocation_officer", allocation_officer)
        like("allocated_to", allocated_to)
        if status.strip():
            clauses.append("status = ?")
            params.append(status.strip())

        def date_range(field: str, fr: str, to: str) -> None:
            if fr.strip():
                clauses.append(f"{field} >= ?")
                params.append(fr.strip())
            if to.strip():
                clauses.append(f"{field} <= ?")
                params.append(to.strip())

        date_range("added_date", added_date_from, added_date_to)
        date_range("issue_date", issue_date_from, issue_date_to)
        date_range("allocation_date", allocation_date_from, allocation_date_to)

        if query.strip():
            q = f"%{query.strip()}%"
            clauses.append(
                """(
                    item_name LIKE ? OR serial_number LIKE ? OR category LIKE ?
                    OR allocation_officer LIKE ? OR allocated_to LIKE ?
                    OR status LIKE ? OR notes LIKE ?
                )"""
            )
            params.extend([q] * 7)

        where = ("WHERE " + " AND ".join(clauses)) if clauses else ""
        sql = f"SELECT * FROM inventory {where} ORDER BY added_date DESC, item_name COLLATE NOCASE"
        with self.connect() as conn:
            return [dict(r) for r in conn.execute(sql, params).fetchall()]

    def inventory_stats(self) -> dict[str, Any]:
        with self.connect() as conn:
            total = conn.execute("SELECT COUNT(*) AS n FROM inventory").fetchone()["n"]
            available = conn.execute(
                "SELECT COUNT(*) AS n FROM inventory WHERE status='available'"
            ).fetchone()["n"]
            allocated = conn.execute(
                "SELECT COUNT(*) AS n FROM inventory WHERE status='allocated'"
            ).fetchone()["n"]
            issued = conn.execute(
                "SELECT COUNT(*) AS n FROM inventory WHERE status='issued'"
            ).fetchone()["n"]
        return {
            "total": total,
            "available": available,
            "allocated": allocated,
            "issued": issued,
        }

    # ── Loan cards ────────────────────────────────────────────────

    def add_loan_card(
        self,
        *,
        name: str,
        designation: str = "",
        department: str = "",
        tel_extension: str = "",
        issue_date: str = "",
        card_number: str = "",
        file_path: str = "",
        file_type: str = "",
        original_filename: str = "",
        notes: str = "",
        source: str = "upload",
        items: Optional[list[dict[str, str]]] = None,
        created_by: Optional[int] = None,
        username: str = "",
        client_type: str = "",
    ) -> int:
        now = _now()
        with self.connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO loan_cards (
                    card_number, name, designation, department, tel_extension,
                    issue_date, file_path, file_type, original_filename, notes,
                    source, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    card_number,
                    name.strip(),
                    designation.strip(),
                    department.strip(),
                    tel_extension.strip(),
                    issue_date.strip(),
                    file_path,
                    file_type,
                    original_filename,
                    notes,
                    source,
                    created_by,
                    now,
                    now,
                ),
            )
            card_id = int(cur.lastrowid)
            for item in items or []:
                conn.execute(
                    """
                    INSERT INTO equipment_items
                        (loan_card_id, item_name, serial_number, quantity, remarks)
                    VALUES (?, ?, ?, ?, ?)
                    """,
                    (
                        card_id,
                        (item.get("item_name") or "").strip() or "Item",
                        (item.get("serial_number") or "").strip(),
                        (item.get("quantity") or "").strip() or "1",
                        (item.get("remarks") or "").strip(),
                    ),
                )
        self.log(
            "add",
            f"Loan card #{card_id}: {name}",
            username=username,
            user_id=created_by,
            client_type=client_type,
        )
        return card_id

    def update_loan_card(self, card_id: int, **fields: Any) -> None:
        allowed = {
            "card_number",
            "name",
            "designation",
            "department",
            "tel_extension",
            "issue_date",
            "notes",
            "file_path",
            "file_type",
            "original_filename",
        }
        updates = {k: v for k, v in fields.items() if k in allowed and v is not None}
        items = fields.get("items")
        if not updates and items is None:
            return
        updates["updated_at"] = _now()
        with self.connect() as conn:
            if updates:
                cols = ", ".join(f"{k}=?" for k in updates)
                conn.execute(
                    f"UPDATE loan_cards SET {cols} WHERE id=?",
                    (*updates.values(), card_id),
                )
            if items is not None:
                conn.execute(
                    "DELETE FROM equipment_items WHERE loan_card_id=?", (card_id,)
                )
                for item in items:
                    conn.execute(
                        """
                        INSERT INTO equipment_items
                            (loan_card_id, item_name, serial_number, quantity, remarks)
                        VALUES (?, ?, ?, ?, ?)
                        """,
                        (
                            card_id,
                            (item.get("item_name") or "").strip() or "Item",
                            (item.get("serial_number") or "").strip(),
                            (item.get("quantity") or "").strip() or "1",
                            (item.get("remarks") or "").strip(),
                        ),
                    )
        self.log(
            "update",
            f"Loan card #{card_id}",
            username=fields.get("username") or "",
            user_id=fields.get("actor_id"),
            client_type=fields.get("client_type") or "",
        )

    def delete_loan_card(
        self,
        card_id: int,
        *,
        username: str = "",
        user_id: Optional[int] = None,
        client_type: str = "",
    ) -> Optional[str]:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT file_path FROM loan_cards WHERE id=?", (card_id,)
            ).fetchone()
            if not row:
                return None
            file_path = row["file_path"]
            conn.execute("DELETE FROM loan_cards WHERE id=?", (card_id,))
        self.log(
            "delete",
            f"Loan card #{card_id}",
            username=username,
            user_id=user_id,
            client_type=client_type,
        )
        return file_path

    def get_loan_card(self, card_id: int) -> Optional[dict[str, Any]]:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT * FROM loan_cards WHERE id=?", (card_id,)
            ).fetchone()
            if not row:
                return None
            card = dict(row)
            items = conn.execute(
                "SELECT * FROM equipment_items WHERE loan_card_id=? ORDER BY id",
                (card_id,),
            ).fetchall()
            card["items"] = [dict(i) for i in items]
            return card

    def search(
        self,
        *,
        name: str = "",
        designation: str = "",
        department: str = "",
        issue_date_from: str = "",
        issue_date_to: str = "",
        item_name: str = "",
        tel_extension: str = "",
        query: str = "",
    ) -> list[dict[str, Any]]:
        clauses: list[str] = []
        params: list[Any] = []

        def like(field: str, value: str) -> None:
            if value.strip():
                clauses.append(f"c.{field} LIKE ?")
                params.append(f"%{value.strip()}%")

        like("name", name)
        like("designation", designation)
        like("department", department)
        like("tel_extension", tel_extension)

        if issue_date_from.strip():
            clauses.append("c.issue_date >= ?")
            params.append(issue_date_from.strip())
        if issue_date_to.strip():
            clauses.append("c.issue_date <= ?")
            params.append(issue_date_to.strip())

        join = ""
        if item_name.strip():
            join = "INNER JOIN equipment_items e ON e.loan_card_id = c.id"
            clauses.append("e.item_name LIKE ?")
            params.append(f"%{item_name.strip()}%")

        if query.strip():
            q = f"%{query.strip()}%"
            clauses.append(
                """(
                    c.name LIKE ? OR c.designation LIKE ? OR c.department LIKE ?
                    OR c.tel_extension LIKE ? OR c.card_number LIKE ?
                    OR c.issue_date LIKE ? OR c.notes LIKE ?
                    OR EXISTS (
                        SELECT 1 FROM equipment_items ei
                        WHERE ei.loan_card_id = c.id
                          AND (ei.item_name LIKE ? OR ei.serial_number LIKE ?)
                    )
                )"""
            )
            params.extend([q] * 9)

        where = ("WHERE " + " AND ".join(clauses)) if clauses else ""
        sql = f"""
            SELECT DISTINCT c.*,
                (SELECT GROUP_CONCAT(item_name, ', ')
                   FROM equipment_items WHERE loan_card_id = c.id) AS equipment_summary
            FROM loan_cards c
            {join}
            {where}
            ORDER BY c.issue_date DESC, c.name COLLATE NOCASE
        """
        with self.connect() as conn:
            rows = conn.execute(sql, params).fetchall()
            return [dict(r) for r in rows]

    def stats(self) -> dict[str, Any]:
        with self.connect() as conn:
            total = conn.execute("SELECT COUNT(*) AS n FROM loan_cards").fetchone()["n"]
            depts = conn.execute(
                "SELECT COUNT(DISTINCT department) AS n FROM loan_cards WHERE department != ''"
            ).fetchone()["n"]
            items = conn.execute(
                "SELECT COUNT(*) AS n FROM equipment_items"
            ).fetchone()["n"]
            recent = conn.execute(
                "SELECT COUNT(*) AS n FROM loan_cards WHERE created_at >= date('now', '-7 day')"
            ).fetchone()["n"]
        inv = self.inventory_stats()
        online = len(self.online_users())
        return {
            "total_cards": total,
            "departments": depts,
            "equipment_items": items,
            "added_this_week": recent,
            "inventory_total": inv["total"],
            "inventory_available": inv["available"],
            "inventory_allocated": inv["allocated"],
            "inventory_issued": inv["issued"],
            "users_online": online,
        }

    def list_activity(self, limit: int = 100) -> list[dict[str, Any]]:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT * FROM activity_log ORDER BY id DESC LIMIT ?",
                (limit,),
            ).fetchall()
            return [dict(r) for r in rows]

    def export_all(self) -> dict[str, Any]:
        with self.connect() as conn:
            cards = [dict(r) for r in conn.execute("SELECT * FROM loan_cards").fetchall()]
            for card in cards:
                items = conn.execute(
                    "SELECT item_name, serial_number, quantity, remarks FROM equipment_items WHERE loan_card_id=?",
                    (card["id"],),
                ).fetchall()
                card["items"] = [dict(i) for i in items]
            inventory = [dict(r) for r in conn.execute("SELECT * FROM inventory").fetchall()]
            users = [
                dict(r)
                for r in conn.execute(
                    "SELECT id, username, full_name, role, is_active, created_at FROM users"
                ).fetchall()
            ]
            activity = [
                dict(r)
                for r in conn.execute(
                    "SELECT action, details, username, client_type, created_at FROM activity_log ORDER BY id"
                ).fetchall()
            ]
        return {
            "app": "AU-Kamra-IT Loan Cards Management",
            "version": "2.0.0",
            "exported_at": _now(),
            "loan_cards": cards,
            "inventory": inventory,
            "users": users,
            "activity_log": activity,
        }

    def import_records(
        self, payload: dict[str, Any], *, skip_files: bool = True
    ) -> dict[str, int]:
        cards = payload.get("loan_cards") or []
        inventory = payload.get("inventory") or []
        card_count = 0
        inv_count = 0
        for card in cards:
            self.add_loan_card(
                name=card.get("name") or "Unknown",
                designation=card.get("designation") or "",
                department=card.get("department") or "",
                tel_extension=card.get("tel_extension") or "",
                issue_date=card.get("issue_date") or "",
                card_number=card.get("card_number") or "",
                file_path="" if skip_files else (card.get("file_path") or ""),
                file_type=card.get("file_type") or "",
                original_filename=card.get("original_filename") or "",
                notes=card.get("notes") or "",
                source=card.get("source") or "import",
                items=card.get("items") or [],
            )
            card_count += 1
        for inv in inventory:
            self.add_inventory(
                item_name=inv.get("item_name") or "Item",
                serial_number=inv.get("serial_number") or "",
                category=inv.get("category") or "",
                status=inv.get("status") or "available",
                quantity=inv.get("quantity") or "1",
                allocation_officer=inv.get("allocation_officer") or "",
                allocated_to=inv.get("allocated_to") or "",
                allocation_date=inv.get("allocation_date") or "",
                issue_date=inv.get("issue_date") or "",
                added_date=inv.get("added_date") or "",
                notes=inv.get("notes") or "",
            )
            inv_count += 1
        self.log("import", f"Imported {card_count} cards, {inv_count} inventory items")
        return {"loan_cards": card_count, "inventory": inv_count}

    def clear_all(self) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM equipment_items")
            conn.execute("DELETE FROM loan_cards")
            conn.execute("DELETE FROM inventory")
            conn.execute("DELETE FROM activity_log")
            conn.execute("DELETE FROM sessions")
        self.log("clear", "All records cleared")


def dump_json(data: Any, path: Path) -> None:
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")


def load_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))
