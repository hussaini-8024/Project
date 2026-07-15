"""Backup, restore, import and export helpers."""

from __future__ import annotations

import json
import shutil
import zipfile
from datetime import datetime
from pathlib import Path
from typing import Any, Optional

from au_kamra_loan_cards import data_dir, db_path
from au_kamra_loan_cards.database import Database, dump_json, load_json


def backup_zip(dest: Optional[Path] = None) -> Path:
    """Create a full backup ZIP (database + uploads + generated)."""
    root = data_dir()
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    if dest is None:
        dest = root / "backups" / f"AU_Kamra_Backup_{stamp}.zip"
    dest = Path(dest)
    dest.parent.mkdir(parents=True, exist_ok=True)

    with zipfile.ZipFile(dest, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        db = db_path()
        if db.exists():
            zf.write(db, arcname="loan_cards.db")
        for folder in ("uploads", "generated"):
            base = root / folder
            if not base.exists():
                continue
            for path in base.rglob("*"):
                if path.is_file():
                    zf.write(path, arcname=str(Path(folder) / path.relative_to(base)))
        # Also include JSON export snapshot
        db_obj = Database()
        payload = db_obj.export_all()
        zf.writestr(
            "export_snapshot.json",
            json.dumps(payload, indent=2, ensure_ascii=False),
        )
    Database().log("backup", f"Backup created: {dest.name}")
    return dest


def restore_zip(src: Path) -> Path:
    """Restore from a backup ZIP into the data directory."""
    src = Path(src)
    if not src.exists():
        raise FileNotFoundError(src)
    root = data_dir()
    # Safety backup first
    if db_path().exists():
        safety = root / "backups" / f"pre_restore_{datetime.now().strftime('%Y%m%d_%H%M%S')}.zip"
        try:
            backup_zip(safety)
        except Exception:
            pass

    with zipfile.ZipFile(src, "r") as zf:
        for info in zf.infolist():
            name = info.filename.replace("\\", "/")
            if name.endswith("/"):
                continue
            # Prevent path traversal
            target = (root / name).resolve()
            if not str(target).startswith(str(root.resolve())):
                continue
            target.parent.mkdir(parents=True, exist_ok=True)
            with zf.open(info) as src_f, open(target, "wb") as out_f:
                shutil.copyfileobj(src_f, out_f)
    Database().log("restore", f"Restored from: {src.name}")
    return root


def export_json(dest: Path, db: Optional[Database] = None) -> Path:
    db = db or Database()
    dest = Path(dest)
    dest.parent.mkdir(parents=True, exist_ok=True)
    dump_json(db.export_all(), dest)
    db.log("export", f"Exported JSON: {dest.name}")
    return dest


def import_json(src: Path, db: Optional[Database] = None) -> dict:
    db = db or Database()
    payload = load_json(Path(src))
    if not isinstance(payload, dict):
        raise ValueError("Invalid export file")
    return db.import_records(payload)


def export_csv(dest: Path, db: Optional[Database] = None) -> Path:
    import csv

    db = db or Database()
    dest = Path(dest)
    dest.parent.mkdir(parents=True, exist_ok=True)
    cards = db.search()
    with dest.open("w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(
            [
                "id",
                "card_number",
                "name",
                "designation",
                "department",
                "tel_extension",
                "issue_date",
                "equipment",
                "file_type",
                "source",
                "notes",
            ]
        )
        for c in cards:
            writer.writerow(
                [
                    c.get("id"),
                    c.get("card_number"),
                    c.get("name"),
                    c.get("designation"),
                    c.get("department"),
                    c.get("tel_extension"),
                    c.get("issue_date"),
                    c.get("equipment_summary"),
                    c.get("file_type"),
                    c.get("source"),
                    c.get("notes"),
                ]
            )
    db.log("export", f"Exported CSV: {dest.name}")
    return dest
