"""Isolated user working sessions (web + shell environments)."""

from __future__ import annotations

import json
import os
import signal
import subprocess
import uuid
from pathlib import Path
from typing import Any

from aulabs.config import get_settings
from aulabs.database import get_db, utcnow


class SessionService:
    def __init__(self) -> None:
        self.settings = get_settings()
        self.db = get_db()

    def list_sessions(self, user_id: int | None = None) -> list[dict[str, Any]]:
        if user_id is None:
            rows = self.db.fetchall(
                """
                SELECT s.*, u.username FROM sessions s
                JOIN panel_users u ON u.id = s.user_id
                ORDER BY s.last_active DESC
                """
            )
        else:
            rows = self.db.fetchall(
                """
                SELECT s.*, u.username FROM sessions s
                JOIN panel_users u ON u.id = s.user_id
                WHERE s.user_id = ?
                ORDER BY s.last_active DESC
                """,
                (user_id,),
            )
        return [self._public(r) for r in rows]

    def get_session(self, session_id: str) -> dict[str, Any] | None:
        row = self.db.fetchone(
            """
            SELECT s.*, u.username FROM sessions s
            JOIN panel_users u ON u.id = s.user_id
            WHERE s.id = ?
            """,
            (session_id,),
        )
        return self._public(row) if row else None

    def create_session(
        self,
        user: dict[str, Any],
        *,
        session_type: str = "web",
        working_dir: str | None = None,
        env: dict[str, str] | None = None,
        actor: str | None = None,
    ) -> dict[str, Any]:
        session_id = uuid.uuid4().hex
        home = Path(user["home_dir"])
        work = Path(working_dir) if working_dir else home / "workspace"
        if not str(work.resolve()).startswith(str(home.resolve())):
            raise ValueError("Working directory must be inside user home")
        work.mkdir(parents=True, exist_ok=True)

        session_dir = self.settings.sessions_root / user["username"] / session_id
        session_dir.mkdir(parents=True, exist_ok=True)

        base_env = {
            "AULABS_USER": user["username"],
            "AULABS_HOME": str(home),
            "AULABS_SESSION": session_id,
            "AULABS_SESSION_DIR": str(session_dir),
            "HOME": str(home),
            "USER": user["username"],
            "PATH": os.environ.get("PATH", "/usr/bin:/bin"),
            "TERM": "xterm-256color",
            "SHELL": user.get("shell") or "/bin/bash",
        }
        if env:
            base_env.update(env)

        pid = None
        status = "active"
        if session_type == "shell":
            pid, status = self._spawn_shell(user, work, session_dir, base_env)

        now = utcnow()
        self.db.execute(
            """
            INSERT INTO sessions
            (id, user_id, session_type, working_dir, env_json, pid, status, created_at, last_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                session_id,
                user["id"],
                session_type,
                str(work),
                json.dumps(base_env),
                pid,
                status,
                now,
                now,
            ),
        )
        self.db.audit(actor or user["username"], "session.create", session_id, session_type)
        session = self.get_session(session_id)
        assert session
        return session

    def touch(self, session_id: str) -> None:
        self.db.execute(
            "UPDATE sessions SET last_active = ? WHERE id = ?",
            (utcnow(), session_id),
        )

    def terminate(self, session_id: str, *, actor: str = "system") -> dict[str, Any]:
        row = self.db.fetchone("SELECT * FROM sessions WHERE id = ?", (session_id,))
        if not row:
            raise ValueError("Session not found")
        if row.get("pid"):
            try:
                os.kill(int(row["pid"]), signal.SIGTERM)
            except ProcessLookupError:
                pass
            except PermissionError:
                pass
        self.db.execute(
            "UPDATE sessions SET status = 'terminated', last_active = ? WHERE id = ?",
            (utcnow(), session_id),
        )
        self.db.audit(actor, "session.terminate", session_id)
        session = self.get_session(session_id)
        assert session
        return session

    def run_command(
        self,
        user: dict[str, Any],
        command: str,
        *,
        session_id: str | None = None,
        timeout: int = 30,
    ) -> dict[str, Any]:
        home = Path(user["home_dir"])
        work = home / "workspace"
        env = {
            "AULABS_USER": user["username"],
            "AULABS_HOME": str(home),
            "HOME": str(home),
            "USER": user["username"],
            "PATH": os.environ.get("PATH", "/usr/bin:/bin"),
            "SHELL": user.get("shell") or "/bin/bash",
        }
        if session_id:
            session = self.get_session(session_id)
            if not session or session["user_id"] != user["id"]:
                raise ValueError("Invalid session")
            work = Path(session["working_dir"])
            env.update(session.get("env") or {})
            self.touch(session_id)

        profile = home / ".aulabs_profile"
        wrapped = f"source '{profile}' 2>/dev/null; {command}"
        try:
            proc = subprocess.run(
                [user.get("shell") or "/bin/bash", "-lc", wrapped],
                cwd=str(work),
                env=env,
                capture_output=True,
                text=True,
                timeout=timeout,
            )
            return {
                "ok": proc.returncode == 0,
                "returncode": proc.returncode,
                "stdout": proc.stdout[-20000:],
                "stderr": proc.stderr[-8000:],
                "cwd": str(work),
            }
        except subprocess.TimeoutExpired:
            return {
                "ok": False,
                "returncode": -1,
                "stdout": "",
                "stderr": "Command timed out",
                "cwd": str(work),
            }

    def _spawn_shell(
        self,
        user: dict[str, Any],
        work: Path,
        session_dir: Path,
        env: dict[str, str],
    ) -> tuple[int | None, str]:
        log_path = session_dir / "shell.log"
        pid_path = session_dir / "shell.pid"
        script = session_dir / "run.sh"
        script.write_text(
            "#!/bin/bash\n"
            f"source '{Path(user['home_dir']) / '.aulabs_profile'}' 2>/dev/null\n"
            f"cd '{work}'\n"
            f"exec bash -i >> '{log_path}' 2>&1\n",
            encoding="utf-8",
        )
        os.chmod(script, 0o750)
        try:
            with open(log_path, "a", encoding="utf-8") as log:
                proc = subprocess.Popen(
                    ["/bin/bash", str(script)],
                    cwd=str(work),
                    env=env,
                    stdout=log,
                    stderr=log,
                    start_new_session=True,
                )
            pid_path.write_text(str(proc.pid), encoding="utf-8")
            return proc.pid, "active"
        except Exception as exc:
            log_path.write_text(f"Failed to start shell: {exc}\n", encoding="utf-8")
            return None, "error"

    def _public(self, row: dict[str, Any]) -> dict[str, Any]:
        env = {}
        try:
            env = json.loads(row.get("env_json") or "{}")
        except json.JSONDecodeError:
            env = {}
        alive = False
        status = row["status"]
        if row.get("pid") and status == "active":
            try:
                os.kill(int(row["pid"]), 0)
                alive = True
            except Exception:
                status = "stale"
                alive = False
        elif status == "active" and row.get("session_type") == "web":
            alive = True
        return {
            "id": row["id"],
            "user_id": row["user_id"],
            "username": row.get("username"),
            "session_type": row["session_type"],
            "working_dir": row["working_dir"],
            "env": env,
            "pid": row.get("pid"),
            "status": status,
            "alive": alive,
            "created_at": row["created_at"],
            "last_active": row["last_active"],
        }
