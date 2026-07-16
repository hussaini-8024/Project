"""AU Labs Agent — lightweight host agent paired with the IT Management panel."""

from __future__ import annotations

import argparse
import json
import os
import platform
import socket
import sys
import threading
import time
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import httpx
import psutil

from aulabs import __app_name__, __version__


def _default_config_path() -> Path:
    if getattr(sys, "frozen", False):
        exe_dir = Path(sys.executable).resolve().parent
        for candidate in (exe_dir / "agent.json", exe_dir.parent / "agent.json"):
            if candidate.exists():
                return candidate
        if platform.system() == "Windows":
            base = Path(os.environ.get("PROGRAMDATA", Path.home() / "AppData" / "Local"))
        else:
            base = Path(os.environ.get("XDG_CONFIG_HOME", Path.home() / ".config"))
        return base / "aulabs" / "agent.json"
    return Path.home() / ".config" / "aulabs" / "agent.json"


def _default_work_dir() -> Path:
    if platform.system() == "Windows":
        root = Path(os.environ.get("PROGRAMDATA", "C:/ProgramData")) / "AULabs" / "agent"
    else:
        root = Path(os.environ.get("AULABS_AGENT_HOME", Path.home() / ".local" / "share" / "aulabs-agent"))
    root.mkdir(parents=True, exist_ok=True)
    return root


class AgentConfig:
    def __init__(self, path: Path | None = None) -> None:
        self.path = path or _default_config_path()
        self.data: dict[str, Any] = {
            "server_url": "http://127.0.0.1:8787",
            "agent_token": "",
            "agent_id": "",
            "agent_name": socket.gethostname(),
            "heartbeat_seconds": 15,
            "enabled": True,
        }
        self.load()

    def load(self) -> None:
        if self.path.exists():
            try:
                self.data.update(json.loads(self.path.read_text(encoding="utf-8")))
            except Exception:
                pass
        if not self.data.get("agent_id"):
            self.data["agent_id"] = uuid.uuid4().hex
            self.save()

    def save(self) -> None:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(self.data, indent=2), encoding="utf-8")


class AgentRuntime:
    def __init__(self, config: AgentConfig) -> None:
        self.config = config
        self.work_dir = _default_work_dir()
        self.session_dir = self.work_dir / "sessions"
        self.session_dir.mkdir(parents=True, exist_ok=True)
        self._stop = threading.Event()

    def collect_metrics(self) -> dict[str, Any]:
        vm = psutil.virtual_memory()
        disk_path = str(self.work_dir)
        try:
            disk = psutil.disk_usage(disk_path)
        except Exception:
            disk = psutil.disk_usage("/")
        return {
            "agent_id": self.config.data["agent_id"],
            "agent_name": self.config.data.get("agent_name") or socket.gethostname(),
            "hostname": socket.gethostname(),
            "platform": {
                "system": platform.system(),
                "release": platform.release(),
                "machine": platform.machine(),
                "python": platform.python_version(),
            },
            "cpu_percent": psutil.cpu_percent(interval=0.1),
            "cpu_count": psutil.cpu_count() or 1,
            "memory": {
                "total": vm.total,
                "used": vm.used,
                "percent": vm.percent,
            },
            "disk": {
                "path": disk_path,
                "total": disk.total,
                "used": disk.used,
                "percent": disk.percent,
            },
            "work_dir": str(self.work_dir),
            "version": __version__,
            "reported_at": datetime.now(timezone.utc).isoformat(),
        }

    def create_local_session(self, label: str = "default") -> dict[str, Any]:
        sid = uuid.uuid4().hex[:12]
        path = self.session_dir / f"{label}-{sid}"
        path.mkdir(parents=True, exist_ok=True)
        (path / "env.sh").write_text(
            f"export AULABS_AGENT={self.config.data['agent_id']}\n"
            f"export AULABS_SESSION={sid}\n"
            f"cd '{path}'\n",
            encoding="utf-8",
        )
        meta = {
            "id": sid,
            "label": label,
            "path": str(path),
            "created_at": datetime.now(timezone.utc).isoformat(),
        }
        (path / "session.json").write_text(json.dumps(meta, indent=2), encoding="utf-8")
        return meta

    def heartbeat_once(self) -> dict[str, Any]:
        url = self.config.data["server_url"].rstrip("/") + "/api/agents/heartbeat"
        payload = self.collect_metrics()
        headers = {}
        token = self.config.data.get("agent_token") or ""
        if token:
            headers["X-Agent-Token"] = token
        with httpx.Client(timeout=10.0) as client:
            res = client.post(url, json=payload, headers=headers)
            res.raise_for_status()
            return res.json()

    def register(self) -> dict[str, Any]:
        url = self.config.data["server_url"].rstrip("/") + "/api/agents/register"
        payload = self.collect_metrics()
        with httpx.Client(timeout=10.0) as client:
            res = client.post(url, json=payload)
            res.raise_for_status()
            data = res.json()
            if data.get("agent_token"):
                self.config.data["agent_token"] = data["agent_token"]
                self.config.save()
            return data

    def run_forever(self) -> None:
        print(f"{__app_name__} Agent v{__version__}")
        print(f"Server : {self.config.data['server_url']}")
        print(f"Agent  : {self.config.data['agent_name']} ({self.config.data['agent_id'][:8]})")
        print(f"Work   : {self.work_dir}")
        try:
            reg = self.register()
            print(f"Registered with panel: {reg.get('status', 'ok')}")
        except Exception as exc:
            print(f"Registration pending (server may be offline): {exc}")

        interval = max(5, int(self.config.data.get("heartbeat_seconds") or 15))
        while not self._stop.is_set():
            try:
                result = self.heartbeat_once()
                cmds = result.get("commands") or []
                for cmd in cmds:
                    self._handle_command(cmd)
                print(f"[{datetime.now().strftime('%H:%M:%S')}] heartbeat ok")
            except Exception as exc:
                print(f"[{datetime.now().strftime('%H:%M:%S')}] heartbeat failed: {exc}")
            self._stop.wait(interval)

    def stop(self) -> None:
        self._stop.set()

    def _handle_command(self, cmd: dict[str, Any]) -> None:
        action = cmd.get("action")
        if action == "create_session":
            meta = self.create_local_session(cmd.get("label") or "remote")
            print(f"Created local session {meta['id']} at {meta['path']}")
        elif action == "ping":
            print("Ping command acknowledged")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=f"{__app_name__} Agent")
    parser.add_argument("command", nargs="?", default="run", choices=["run", "register", "status", "session", "version", "config"])
    parser.add_argument("--server", default=None, help="Panel URL, e.g. http://127.0.0.1:8787")
    parser.add_argument("--name", default=None, help="Agent display name")
    parser.add_argument("--config", default=None, help="Path to agent.json")
    args = parser.parse_args(argv)

    if args.command == "version":
        print(f"{__app_name__} Agent {__version__}")
        return 0

    config = AgentConfig(Path(args.config) if args.config else None)
    if args.server:
        config.data["server_url"] = args.server.rstrip("/")
    if args.name:
        config.data["agent_name"] = args.name
    config.save()

    runtime = AgentRuntime(config)

    if args.command == "config":
        print(json.dumps(config.data, indent=2))
        print(f"config_path={config.path}")
        return 0
    if args.command == "status":
        print(json.dumps(runtime.collect_metrics(), indent=2))
        return 0
    if args.command == "session":
        print(json.dumps(runtime.create_local_session(), indent=2))
        return 0
    if args.command == "register":
        try:
            print(json.dumps(runtime.register(), indent=2))
            return 0
        except Exception as exc:
            print(f"Register failed: {exc}", file=sys.stderr)
            return 1

    try:
        runtime.run_forever()
    except KeyboardInterrupt:
        runtime.stop()
        print("Agent stopped")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
