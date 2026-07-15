"""SQLite persistence for loan cards, equipment items, and activity log."""

from __future__ import annotations

import json
import sqlite3
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterator, Optional

from au_kamra_loan_cards import db_path


def _now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


class Database:
    def __init__(self, path: Optional[Path] = None) -> None:
        self.path = Path(path) if path else db_path()
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self._init_schema()

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
                    created_at TEXT NOT NULL
                );

                CREATE INDEX IF NOT EXISTS idx_cards_name ON loan_cards(name);
                CREATE INDEX IF NOT EXISTS idx_cards_dept ON loan_cards(department);
                CREATE INDEX IF NOT EXISTS idx_cards_desig ON loan_cards(designation);
                CREATE INDEX IF NOT EXISTS idx_cards_tel ON loan_cards(tel_extension);
                CREATE INDEX IF NOT EXISTS idx_cards_issue ON loan_cards(issue_date);
                CREATE INDEX IF NOT EXISTS idx_items_name ON equipment_items(item_name);
                """
            )

    def log(self, action: str, details: str = "") -> None:
        with self.connect() as conn:
            conn.execute(
                "INSERT INTO activity_log (action, details, created_at) VALUES (?, ?, ?)",
                (action, details, _now()),
            )

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
    ) -> int:
        now = _now()
        with self.connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO loan_cards (
                    card_number, name, designation, department, tel_extension,
                    issue_date, file_path, file_type, original_filename, notes,
                    source, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        self.log("add", f"Loan card #{card_id}: {name}")
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
        self.log("update", f"Loan card #{card_id}")

    def delete_loan_card(self, card_id: int) -> Optional[str]:
        with self.connect() as conn:
            row = conn.execute(
                "SELECT file_path FROM loan_cards WHERE id=?", (card_id,)
            ).fetchone()
            if not row:
                return None
            file_path = row["file_path"]
            conn.execute("DELETE FROM loan_cards WHERE id=?", (card_id,))
        self.log("delete", f"Loan card #{card_id}")
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
        return {
            "total_cards": total,
            "departments": depts,
            "equipment_items": items,
            "added_this_week": recent,
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
            activity = [
                dict(r)
                for r in conn.execute(
                    "SELECT action, details, created_at FROM activity_log ORDER BY id"
                ).fetchall()
            ]
        return {
            "app": "AU-Kamra-IT Loan Cards Management",
            "exported_at": _now(),
            "loan_cards": cards,
            "activity_log": activity,
        }

    def import_records(
        self, payload: dict[str, Any], *, skip_files: bool = True
    ) -> int:
        cards = payload.get("loan_cards") or []
        count = 0
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
            count += 1
        self.log("import", f"Imported {count} loan card(s)")
        return count

    def clear_all(self) -> None:
        with self.connect() as conn:
            conn.execute("DELETE FROM equipment_items")
            conn.execute("DELETE FROM loan_cards")
            conn.execute("DELETE FROM activity_log")
        self.log("clear", "All records cleared")


def dump_json(data: Any, path: Path) -> None:
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")


def load_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))
