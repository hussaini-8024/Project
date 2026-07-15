"""Flask application factory and API routes (auth, inventory, presence, loan cards)."""

from __future__ import annotations

import mimetypes
import traceback
import uuid
from datetime import datetime
from functools import wraps
from pathlib import Path
from typing import Any, Callable, Optional

from flask import (
    Flask,
    abort,
    g,
    jsonify,
    render_template,
    request,
    send_file,
)
from werkzeug.exceptions import HTTPException

from au_kamra_loan_cards import APP_NAME, APP_VERSION, data_dir, resource_path
from au_kamra_loan_cards.auth import (
    PERMISSIONS,
    ROLE_LABELS,
    ROLES,
    role_allowed,
)
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
PUBLIC_ENDPOINTS = {"index", "agent_page", "health", "login", "static"}


def create_app() -> Flask:
    template_folder = str(resource_path("templates"))
    static_folder = str(resource_path("static"))
    app = Flask(
        __name__,
        template_folder=template_folder,
        static_folder=static_folder,
        static_url_path="/static",
    )
    app.config["MAX_CONTENT_LENGTH"] = 64 * 1024 * 1024
    db = Database()

    def _token_from_request() -> str:
        auth = request.headers.get("Authorization", "")
        if auth.lower().startswith("bearer "):
            return auth[7:].strip()
        return (
            request.headers.get("X-Auth-Token")
            or request.args.get("token")
            or request.cookies.get("au_token")
            or ""
        ).strip()

    def current_user() -> Optional[dict[str, Any]]:
        return getattr(g, "user", None)

    def require_auth(permission: Optional[str] = None) -> Callable:
        def decorator(fn: Callable) -> Callable:
            @wraps(fn)
            def wrapper(*args: Any, **kwargs: Any):
                user = current_user()
                if not user:
                    return jsonify({"error": "Authentication required"}), 401
                if permission and not role_allowed(user["role"], permission):
                    return jsonify({"error": "Permission denied"}), 403
                return fn(*args, **kwargs)

            return wrapper

        return decorator

    def actor_meta() -> dict[str, Any]:
        user = current_user() or {}
        return {
            "username": user.get("username") or "",
            "created_by": user.get("id"),
            "actor_id": user.get("id"),
            "user_id": user.get("id"),
            "client_type": user.get("client_type") or "",
        }

    @app.before_request
    def load_user():
        g.user = None
        g.token = None
        if request.endpoint in PUBLIC_ENDPOINTS or (
            request.endpoint and request.endpoint.startswith("static")
        ):
            return
        # Allow unauthenticated access only to login/health/index
        token = _token_from_request()
        if not token:
            if request.path.startswith("/api/"):
                return jsonify({"error": "Authentication required"}), 401
            return None
        user = db.get_session_user(token)
        if not user:
            if request.path.startswith("/api/"):
                return jsonify({"error": "Invalid or expired session"}), 401
            return None
        g.user = user
        g.token = token
        # Soft touch on every authenticated API call
        if request.path.startswith("/api/") and request.endpoint != "heartbeat":
            db.touch_session(token, client_ip=request.remote_addr or "")

    @app.get("/")
    def index():
        return render_template(
            "index.html",
            app_name=APP_NAME,
            app_version=APP_VERSION,
            roles=ROLES,
            role_labels=ROLE_LABELS,
        )

    @app.get("/agent")
    def agent_page():
        return render_template(
            "agent.html",
            app_name=APP_NAME,
            app_version=APP_VERSION,
            role_labels=ROLE_LABELS,
        )

    @app.get("/api/health")
    def health():
        return jsonify(
            {
                "ok": True,
                "app": APP_NAME,
                "version": APP_VERSION,
                "mode": "server",
            }
        )

    # ── Auth ──────────────────────────────────────────────────────

    @app.post("/api/auth/login")
    def login():
        payload = request.get_json(force=True, silent=True) or {}
        username = (payload.get("username") or "").strip()
        password = payload.get("password") or ""
        client_type = (payload.get("client_type") or "server").strip()
        if client_type not in {"server", "agent"}:
            client_type = "server"
        if not username or not password:
            return jsonify({"error": "Username and password required"}), 400
        user = db.authenticate(username, password)
        if not user:
            db.log("login_failed", f"Failed login for {username}", client_type=client_type)
            return jsonify({"error": "Invalid username or password"}), 401
        token = db.create_session(
            user["id"],
            client_type=client_type,
            client_ip=request.remote_addr or "",
            client_hostname=(payload.get("hostname") or "").strip(),
        )
        db.log(
            "login",
            f"{user['username']} logged in via {client_type}",
            username=user["username"],
            user_id=user["id"],
            client_type=client_type,
        )
        return jsonify(
            {
                "ok": True,
                "token": token,
                "user": {
                    "id": user["id"],
                    "username": user["username"],
                    "full_name": user["full_name"],
                    "role": user["role"],
                    "permissions": sorted(PERMISSIONS.get(user["role"], set())),
                },
            }
        )

    @app.post("/api/auth/logout")
    @require_auth()
    def logout():
        user = current_user()
        db.destroy_session(g.token)
        db.log(
            "logout",
            f"{user['username']} logged out",
            username=user["username"],
            user_id=user["id"],
            client_type=user.get("client_type") or "",
        )
        return jsonify({"ok": True})

    @app.get("/api/auth/me")
    @require_auth()
    def me():
        user = current_user()
        return jsonify(
            {
                "user": {
                    "id": user["id"],
                    "username": user["username"],
                    "full_name": user["full_name"],
                    "role": user["role"],
                    "client_type": user.get("client_type"),
                    "permissions": sorted(PERMISSIONS.get(user["role"], set())),
                }
            }
        )

    @app.post("/api/auth/heartbeat")
    @require_auth()
    def heartbeat():
        payload = request.get_json(force=True, silent=True) or {}
        db.touch_session(
            g.token,
            current_view=(payload.get("view") or "").strip(),
            current_activity=(payload.get("activity") or "").strip(),
            client_ip=request.remote_addr or "",
        )
        return jsonify(
            {
                "ok": True,
                "server_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            }
        )

    @app.get("/api/presence")
    @require_auth("presence_view")
    def presence():
        db.purge_stale_sessions()
        online = db.online_users()
        return jsonify({"count": len(online), "users": online})

    # ── Users (admin) ─────────────────────────────────────────────

    @app.get("/api/users")
    @require_auth("users_manage")
    def list_users():
        return jsonify({"users": db.list_users(), "roles": ROLE_LABELS})

    @app.post("/api/users")
    @require_auth("users_manage")
    def create_user():
        payload = request.get_json(force=True, silent=True) or {}
        username = (payload.get("username") or "").strip()
        password = payload.get("password") or ""
        full_name = (payload.get("full_name") or "").strip()
        role = (payload.get("role") or "viewer").strip()
        if not username or not password or not full_name:
            return jsonify({"error": "username, password, and full_name are required"}), 400
        if role not in ROLES:
            return jsonify({"error": f"Invalid role. Choose from: {', '.join(ROLES)}"}), 400
        try:
            uid = db.create_user(
                username=username,
                password=password,
                full_name=full_name,
                role=role,
                created_by=current_user()["id"],
            )
        except Exception as exc:
            return jsonify({"error": str(exc)}), 400
        return jsonify({"ok": True, "user": db.get_user(uid)})

    @app.put("/api/users/<int:user_id>")
    @require_auth("users_manage")
    def update_user(user_id: int):
        if not db.get_user(user_id):
            abort(404)
        payload = request.get_json(force=True, silent=True) or {}
        fields: dict[str, Any] = {}
        if "full_name" in payload:
            fields["full_name"] = (payload.get("full_name") or "").strip()
        if "role" in payload:
            role = (payload.get("role") or "").strip()
            if role not in ROLES:
                return jsonify({"error": "Invalid role"}), 400
            fields["role"] = role
        if "is_active" in payload:
            fields["is_active"] = 1 if payload.get("is_active") else 0
        try:
            db.update_user(user_id, **fields)
            if payload.get("password"):
                db.set_user_password(user_id, payload["password"])
        except Exception as exc:
            return jsonify({"error": str(exc)}), 400
        db.log(
            "user_update",
            f"Updated user #{user_id}",
            **{k: actor_meta()[k] for k in ("username", "user_id", "client_type")},
        )
        return jsonify({"ok": True, "user": db.get_user(user_id)})

    @app.delete("/api/users/<int:user_id>")
    @require_auth("users_manage")
    def delete_user(user_id: int):
        try:
            ok = db.delete_user(user_id)
        except ValueError as exc:
            return jsonify({"error": str(exc)}), 400
        if not ok:
            abort(404)
        db.log(
            "user_delete",
            f"Deleted user #{user_id}",
            **{k: actor_meta()[k] for k in ("username", "user_id", "client_type")},
        )
        return jsonify({"ok": True})

    # ── Dashboard / loan cards ────────────────────────────────────

    @app.get("/api/stats")
    @require_auth("loan_view")
    def stats():
        return jsonify(db.stats())

    @app.get("/api/search")
    @require_auth("loan_view")
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
    @require_auth("loan_view")
    def get_card(card_id: int):
        card = db.get_loan_card(card_id)
        if not card:
            abort(404)
        return jsonify(card)

    @app.delete("/api/cards/<int:card_id>")
    @require_auth("loan_delete")
    def delete_card(card_id: int):
        meta = actor_meta()
        file_path = db.delete_loan_card(
            card_id,
            username=meta["username"],
            user_id=meta["user_id"],
            client_type=meta["client_type"],
        )
        if file_path is None:
            abort(404)
        try:
            p = Path(file_path)
            root = data_dir().resolve()
            if p.exists() and str(p.resolve()).startswith(str(root)):
                p.unlink(missing_ok=True)
        except Exception:
            pass
        return jsonify({"ok": True})

    @app.post("/api/upload")
    @require_auth("loan_upload")
    def upload():
        if "files" not in request.files and "file" not in request.files:
            return jsonify({"error": "No files provided"}), 400
        files = request.files.getlist("files") or request.files.getlist("file")
        created = []
        errors = []
        upload_dir = data_dir() / "uploads"
        upload_dir.mkdir(parents=True, exist_ok=True)
        meta = actor_meta()

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
                    created_by=meta["created_by"],
                    username=meta["username"],
                    client_type=meta["client_type"],
                )
                created.append({"id": card_id, "name": parsed.get("name"), "file": original})
            except Exception as exc:
                errors.append({"file": original, "error": str(exc)})
                try:
                    dest.unlink(missing_ok=True)
                except Exception:
                    pass

        return jsonify({"ok": True, "created": created, "errors": errors, "count": len(created)})

    @app.post("/api/cards")
    @require_auth("loan_create")
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
        issue_date = _normalize_date(
            payload.get("issue_date") or datetime.now().strftime("%Y-%m-%d")
        )
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
        generated: dict[str, str] = {}

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

        meta = actor_meta()
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
            created_by=meta["created_by"],
            username=meta["username"],
            client_type=meta["client_type"],
        )
        return jsonify({"ok": True, "id": card_id, "generated": generated, "card": db.get_loan_card(card_id)})

    @app.put("/api/cards/<int:card_id>")
    @require_auth("loan_edit")
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
            "issue_date": _normalize_date(payload.get("issue_date", existing["issue_date"])),
            "notes": payload.get("notes", existing["notes"]),
        }
        if "items" in payload:
            fields["items"] = payload["items"]
        if payload.get("regenerate"):
            card_data = {**existing, **fields, "items": fields.get("items", existing["items"])}
            fmt = existing.get("file_type") or "html"
            path = write_pdf(card_data) if fmt == "pdf" else write_html(card_data)
            fields["file_path"] = str(path)
            fields["file_type"] = path.suffix.lstrip(".")
            fields["original_filename"] = path.name
        fields.update(actor_meta())
        db.update_loan_card(card_id, **fields)
        return jsonify({"ok": True, "card": db.get_loan_card(card_id)})

    @app.get("/api/cards/<int:card_id>/file")
    @require_auth("loan_view")
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
    @require_auth("loan_view")
    def download_file(card_id: int):
        card = db.get_loan_card(card_id)
        if not card or not card.get("file_path"):
            abort(404)
        path = Path(card["file_path"])
        if not path.exists():
            abort(404)
        return send_file(path, as_attachment=True, download_name=path.name)

    # ── Inventory ─────────────────────────────────────────────────

    @app.get("/api/inventory")
    @require_auth("inventory_view")
    def inventory_list():
        results = db.search_inventory(
            item_name=request.args.get("item_name", ""),
            allocation_officer=request.args.get("allocation_officer", ""),
            allocated_to=request.args.get("allocated_to", ""),
            status=request.args.get("status", ""),
            added_date_from=request.args.get("added_date_from", ""),
            added_date_to=request.args.get("added_date_to", ""),
            issue_date_from=request.args.get("issue_date_from", ""),
            issue_date_to=request.args.get("issue_date_to", ""),
            allocation_date_from=request.args.get("allocation_date_from", ""),
            allocation_date_to=request.args.get("allocation_date_to", ""),
            query=request.args.get("q", ""),
        )
        return jsonify({"count": len(results), "results": results})

    @app.get("/api/inventory/<int:inv_id>")
    @require_auth("inventory_view")
    def inventory_get(inv_id: int):
        item = db.get_inventory(inv_id)
        if not item:
            abort(404)
        return jsonify(item)

    @app.post("/api/inventory")
    @require_auth("inventory_manage")
    def inventory_add():
        payload = request.get_json(force=True, silent=True) or {}
        item_name = (payload.get("item_name") or "").strip()
        if not item_name:
            return jsonify({"error": "item_name is required"}), 400
        meta = actor_meta()
        inv_id = db.add_inventory(
            item_name=item_name,
            serial_number=payload.get("serial_number", ""),
            category=payload.get("category", ""),
            status=payload.get("status", "available"),
            quantity=payload.get("quantity", "1"),
            allocation_officer=payload.get("allocation_officer", ""),
            allocated_to=payload.get("allocated_to", ""),
            allocation_date=_normalize_date(payload.get("allocation_date", "")),
            issue_date=_normalize_date(payload.get("issue_date", "")),
            added_date=_normalize_date(payload.get("added_date", "")),
            notes=payload.get("notes", ""),
            created_by=meta["created_by"],
            username=meta["username"],
        )
        return jsonify({"ok": True, "item": db.get_inventory(inv_id)})

    @app.put("/api/inventory/<int:inv_id>")
    @require_auth("inventory_manage")
    def inventory_update(inv_id: int):
        if not db.get_inventory(inv_id):
            abort(404)
        payload = request.get_json(force=True, silent=True) or {}
        # Allocation-only officers may also hit allocate endpoint; manage covers edit
        fields = {
            k: payload.get(k)
            for k in (
                "item_name",
                "serial_number",
                "category",
                "status",
                "quantity",
                "allocation_officer",
                "allocated_to",
                "notes",
            )
            if k in payload
        }
        for date_key in ("allocation_date", "issue_date", "added_date"):
            if date_key in payload:
                fields[date_key] = _normalize_date(payload.get(date_key, ""))
        fields.update(actor_meta())
        db.update_inventory(inv_id, **fields)
        return jsonify({"ok": True, "item": db.get_inventory(inv_id)})

    @app.post("/api/inventory/<int:inv_id>/allocate")
    @require_auth("inventory_allocate")
    def inventory_allocate(inv_id: int):
        item = db.get_inventory(inv_id)
        if not item:
            abort(404)
        payload = request.get_json(force=True, silent=True) or {}
        officer = (payload.get("allocation_officer") or current_user().get("full_name") or "").strip()
        allocated_to = (payload.get("allocated_to") or "").strip()
        if not allocated_to:
            return jsonify({"error": "allocated_to is required"}), 400
        alloc_date = _normalize_date(
            payload.get("allocation_date") or datetime.now().strftime("%Y-%m-%d")
        )
        issue_date = _normalize_date(payload.get("issue_date", ""))
        meta = actor_meta()
        db.update_inventory(
            inv_id,
            status=payload.get("status") or "allocated",
            allocation_officer=officer,
            allocated_to=allocated_to,
            allocation_date=alloc_date,
            issue_date=issue_date or item.get("issue_date") or "",
            **meta,
        )
        return jsonify({"ok": True, "item": db.get_inventory(inv_id)})

    @app.delete("/api/inventory/<int:inv_id>")
    @require_auth("inventory_manage")
    def inventory_delete(inv_id: int):
        meta = actor_meta()
        if not db.delete_inventory(inv_id, actor_id=meta["actor_id"], username=meta["username"]):
            abort(404)
        return jsonify({"ok": True})

    # ── Activity / backup ─────────────────────────────────────────

    @app.get("/api/activity")
    @require_auth("activity_view")
    def activity():
        return jsonify(db.list_activity(limit=int(request.args.get("limit", 100))))

    @app.post("/api/backup")
    @require_auth("backup")
    def api_backup():
        path = backup_zip()
        return jsonify({"ok": True, "path": str(path), "filename": path.name})

    @app.get("/api/backup/download")
    @require_auth("backup")
    def download_backup():
        backups = sorted(
            (data_dir() / "backups").glob("AU_Kamra_Backup_*.zip"), reverse=True
        )
        path = backup_zip() if not backups else backups[0]
        return send_file(path, as_attachment=True, download_name=path.name)

    @app.post("/api/restore")
    @require_auth("backup")
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
    @require_auth("backup")
    def api_export_json():
        dest = data_dir() / "backups" / f"export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        export_json(dest, db)
        return send_file(dest, as_attachment=True, download_name=dest.name)

    @app.get("/api/export/csv")
    @require_auth("backup")
    def api_export_csv():
        dest = data_dir() / "backups" / f"export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
        export_csv(dest, db)
        return send_file(dest, as_attachment=True, download_name=dest.name)

    @app.post("/api/import/json")
    @require_auth("backup")
    def api_import_json():
        if "file" not in request.files:
            return jsonify({"error": "JSON file required"}), 400
        f = request.files["file"]
        tmp = data_dir() / "backups" / f"import_{uuid.uuid4().hex}.json"
        f.save(str(tmp))
        try:
            counts = import_json(tmp, db)
            return jsonify({"ok": True, "imported": counts})
        except Exception as exc:
            return jsonify({"error": str(exc)}), 400
        finally:
            try:
                tmp.unlink(missing_ok=True)
            except Exception:
                pass

    @app.get("/api/data-path")
    @require_auth()
    def data_path_info():
        return jsonify({"data_dir": str(data_dir()), "db": str(db.path)})

    @app.errorhandler(Exception)
    def on_error(err):
        if isinstance(err, HTTPException):
            return err
        traceback.print_exc()
        return jsonify({"error": str(err)}), 500

    return app


def _normalize_date(value: str) -> str:
    value = (value or "").strip()
    if not value:
        return ""
    for fmt in ("%Y-%m-%d", "%d-%m-%Y", "%d/%m/%Y", "%m/%d/%Y", "%d %b %Y", "%d %B %Y"):
        try:
            return datetime.strptime(value, fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    return value
