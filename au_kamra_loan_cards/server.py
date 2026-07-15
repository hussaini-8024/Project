"""Flask application factory and API routes."""

from __future__ import annotations

import mimetypes
import traceback
import uuid
from datetime import datetime
from pathlib import Path

from flask import (
    Flask,
    abort,
    jsonify,
    render_template,
    request,
    send_file,
)

from au_kamra_loan_cards import APP_NAME, APP_VERSION, data_dir, resource_path
from au_kamra_loan_cards.backup import (
    backup_zip,
    export_csv,
    export_json,
    import_json,
    restore_zip,
)
from au_kamra_loan_cards.database import Database
from au_kamra_loan_cards.generator import next_card_number, write_html, write_pdf
from au_kamra_loan_cards.parsers import parse_file


ALLOWED_EXT = {".pdf", ".html", ".htm"}


def create_app() -> Flask:
    template_folder = str(resource_path("templates"))
    static_folder = str(resource_path("static"))
    app = Flask(
        __name__,
        template_folder=template_folder,
        static_folder=static_folder,
        static_url_path="/static",
    )
    app.config["MAX_CONTENT_LENGTH"] = 64 * 1024 * 1024  # 64 MB
    db = Database()

    @app.get("/")
    def index():
        return render_template(
            "index.html",
            app_name=APP_NAME,
            app_version=APP_VERSION,
        )

    @app.get("/api/health")
    def health():
        return jsonify({"ok": True, "app": APP_NAME, "version": APP_VERSION})

    @app.get("/api/stats")
    def stats():
        return jsonify(db.stats())

    @app.get("/api/search")
    def search():
        results = db.search(
            name=request.args.get("name", ""),
            designation=request.args.get("designation", ""),
            department=request.args.get("department", ""),
            issue_date_from=request.args.get("issue_date_from", ""),
            issue_date_to=request.args.get("issue_date_to", ""),
            item_name=request.args.get("item_name", ""),
            tel_extension=request.args.get("tel_extension", ""),
            query=request.args.get("q", ""),
        )
        return jsonify({"count": len(results), "results": results})

    @app.get("/api/cards/<int:card_id>")
    def get_card(card_id: int):
        card = db.get_loan_card(card_id)
        if not card:
            abort(404)
        return jsonify(card)

    @app.delete("/api/cards/<int:card_id>")
    def delete_card(card_id: int):
        file_path = db.delete_loan_card(card_id)
        if file_path is None:
            abort(404)
        # Remove stored file if under data dir
        try:
            p = Path(file_path)
            root = data_dir().resolve()
            if p.exists() and str(p.resolve()).startswith(str(root)):
                p.unlink(missing_ok=True)
        except Exception:
            pass
        return jsonify({"ok": True})

    @app.post("/api/upload")
    def upload():
        if "files" not in request.files and "file" not in request.files:
            return jsonify({"error": "No files provided"}), 400
        files = request.files.getlist("files") or request.files.getlist("file")
        created = []
        errors = []
        upload_dir = data_dir() / "uploads"
        upload_dir.mkdir(parents=True, exist_ok=True)

        for f in files:
            if not f or not f.filename:
                continue
            original = Path(f.filename).name
            ext = Path(original).suffix.lower()
            if ext not in ALLOWED_EXT:
                errors.append({"file": original, "error": "Only PDF or HTML allowed"})
                continue
            stored_name = f"{datetime.now().strftime('%Y%m%d_%H%M%S')}_{uuid.uuid4().hex[:8]}{ext}"
            dest = upload_dir / stored_name
            f.save(str(dest))
            try:
                parsed = parse_file(dest)
                card_id = db.add_loan_card(
                    name=parsed.get("name") or Path(original).stem,
                    designation=parsed.get("designation", ""),
                    department=parsed.get("department", ""),
                    tel_extension=parsed.get("tel_extension", ""),
                    issue_date=_normalize_date(parsed.get("issue_date", "")),
                    card_number=parsed.get("card_number", "") or next_card_number(),
                    file_path=str(dest),
                    file_type=ext.lstrip("."),
                    original_filename=original,
                    notes=parsed.get("notes", ""),
                    source="upload",
                    items=parsed.get("items") or [],
                )
                created.append({"id": card_id, "name": parsed.get("name"), "file": original})
            except Exception as exc:
                errors.append({"file": original, "error": str(exc)})
                try:
                    dest.unlink(missing_ok=True)
                except Exception:
                    pass

        return jsonify(
            {
                "ok": True,
                "created": created,
                "errors": errors,
                "count": len(created),
            }
        )

    @app.post("/api/cards")
    def create_card():
        payload = request.get_json(force=True, silent=True) or {}
        name = (payload.get("name") or "").strip()
        if not name:
            return jsonify({"error": "Name is required"}), 400
        items = payload.get("items") or []
        fmt = (payload.get("format") or "html").lower()
        if fmt not in {"html", "pdf", "both"}:
            fmt = "html"

        card_number = (payload.get("card_number") or "").strip() or next_card_number()
        issue_date = _normalize_date(payload.get("issue_date") or datetime.now().strftime("%Y-%m-%d"))
        card_data = {
            "card_number": card_number,
            "name": name,
            "designation": (payload.get("designation") or "").strip(),
            "department": (payload.get("department") or "").strip(),
            "tel_extension": (payload.get("tel_extension") or "").strip(),
            "issue_date": issue_date,
            "notes": (payload.get("notes") or "").strip(),
            "items": items,
        }

        file_path = ""
        file_type = ""
        original_filename = ""
        generated = {}

        if fmt in {"html", "both"}:
            html_path = write_html(card_data)
            generated["html"] = str(html_path)
            file_path = str(html_path)
            file_type = "html"
            original_filename = html_path.name
        if fmt in {"pdf", "both"}:
            pdf_path = write_pdf(card_data)
            generated["pdf"] = str(pdf_path)
            if not file_path:
                file_path = str(pdf_path)
                file_type = "pdf"
                original_filename = pdf_path.name

        card_id = db.add_loan_card(
            name=card_data["name"],
            designation=card_data["designation"],
            department=card_data["department"],
            tel_extension=card_data["tel_extension"],
            issue_date=card_data["issue_date"],
            card_number=card_data["card_number"],
            file_path=file_path,
            file_type=file_type,
            original_filename=original_filename,
            notes=card_data["notes"],
            source="generated",
            items=items,
        )
        return jsonify({"ok": True, "id": card_id, "generated": generated, "card": db.get_loan_card(card_id)})

    @app.put("/api/cards/<int:card_id>")
    def update_card(card_id: int):
        existing = db.get_loan_card(card_id)
        if not existing:
            abort(404)
        payload = request.get_json(force=True, silent=True) or {}
        fields = {
            "card_number": payload.get("card_number", existing["card_number"]),
            "name": payload.get("name", existing["name"]),
            "designation": payload.get("designation", existing["designation"]),
            "department": payload.get("department", existing["department"]),
            "tel_extension": payload.get("tel_extension", existing["tel_extension"]),
            "issue_date": _normalize_date(
                payload.get("issue_date", existing["issue_date"])
            ),
            "notes": payload.get("notes", existing["notes"]),
        }
        if "items" in payload:
            fields["items"] = payload["items"]
        # Optionally regenerate file
        if payload.get("regenerate"):
            card_data = {**existing, **fields, "items": fields.get("items", existing["items"])}
            fmt = existing.get("file_type") or "html"
            if fmt == "pdf":
                path = write_pdf(card_data)
            else:
                path = write_html(card_data)
            fields["file_path"] = str(path)
            fields["file_type"] = path.suffix.lstrip(".")
            fields["original_filename"] = path.name
        db.update_loan_card(card_id, **fields)
        return jsonify({"ok": True, "card": db.get_loan_card(card_id)})

    @app.get("/api/cards/<int:card_id>/file")
    def open_file(card_id: int):
        card = db.get_loan_card(card_id)
        if not card or not card.get("file_path"):
            abort(404)
        path = Path(card["file_path"])
        if not path.exists():
            abort(404)
        mime = mimetypes.guess_type(str(path))[0] or "application/octet-stream"
        return send_file(path, mimetype=mime, as_attachment=False, download_name=path.name)

    @app.get("/api/cards/<int:card_id>/download")
    def download_file(card_id: int):
        card = db.get_loan_card(card_id)
        if not card or not card.get("file_path"):
            abort(404)
        path = Path(card["file_path"])
        if not path.exists():
            abort(404)
        return send_file(path, as_attachment=True, download_name=path.name)

    @app.get("/api/activity")
    def activity():
        return jsonify(db.list_activity(limit=int(request.args.get("limit", 100))))

    @app.post("/api/backup")
    def api_backup():
        path = backup_zip()
        return jsonify({"ok": True, "path": str(path), "filename": path.name})

    @app.get("/api/backup/download")
    def download_backup():
        backups = sorted((data_dir() / "backups").glob("AU_Kamra_Backup_*.zip"), reverse=True)
        if not backups:
            path = backup_zip()
        else:
            path = backups[0]
        return send_file(path, as_attachment=True, download_name=path.name)

    @app.post("/api/restore")
    def api_restore():
        if "file" not in request.files:
            return jsonify({"error": "Backup ZIP required"}), 400
        f = request.files["file"]
        if not f.filename.lower().endswith(".zip"):
            return jsonify({"error": "Please upload a .zip backup"}), 400
        tmp = data_dir() / "backups" / f"upload_restore_{uuid.uuid4().hex}.zip"
        f.save(str(tmp))
        try:
            restore_zip(tmp)
            return jsonify({"ok": True})
        except Exception as exc:
            return jsonify({"error": str(exc)}), 400
        finally:
            try:
                tmp.unlink(missing_ok=True)
            except Exception:
                pass

    @app.get("/api/export/json")
    def api_export_json():
        dest = data_dir() / "backups" / f"export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        export_json(dest, db)
        return send_file(dest, as_attachment=True, download_name=dest.name)

    @app.get("/api/export/csv")
    def api_export_csv():
        dest = data_dir() / "backups" / f"export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
        export_csv(dest, db)
        return send_file(dest, as_attachment=True, download_name=dest.name)

    @app.post("/api/import/json")
    def api_import_json():
        if "file" not in request.files:
            return jsonify({"error": "JSON file required"}), 400
        f = request.files["file"]
        tmp = data_dir() / "backups" / f"import_{uuid.uuid4().hex}.json"
        f.save(str(tmp))
        try:
            count = import_json(tmp, db)
            return jsonify({"ok": True, "imported": count})
        except Exception as exc:
            return jsonify({"error": str(exc)}), 400
        finally:
            try:
                tmp.unlink(missing_ok=True)
            except Exception:
                pass

    @app.get("/api/data-path")
    def data_path_info():
        return jsonify({"data_dir": str(data_dir()), "db": str(db.path)})

    @app.errorhandler(Exception)
    def on_error(err):
        if isinstance(err, Exception) and hasattr(err, "code"):
            raise err
        traceback.print_exc()
        return jsonify({"error": str(err)}), 500

    return app


def _normalize_date(value: str) -> str:
    value = (value or "").strip()
    if not value:
        return ""
    # Try common formats → YYYY-MM-DD
    for fmt in ("%Y-%m-%d", "%d-%m-%Y", "%d/%m/%Y", "%m/%d/%Y", "%d %b %Y", "%d %B %Y"):
        try:
            return datetime.strptime(value, fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    return value
