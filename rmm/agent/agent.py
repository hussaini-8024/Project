"""
DiscloseRMM Agent — permanent install on managed company PCs.

IMPORTANT: Live screen sharing ALWAYS shows a visible on-screen banner.
Covert / hidden monitoring is intentionally not supported.

End users run DiscloseRMM-Agent.exe (no Python required when built).
"""

from __future__ import annotations

import argparse
import base64
import getpass
import io
import os
import platform
import socket
import subprocess
import sys
import tempfile
import threading
import time
import uuid
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parent.parent
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

import requests
import websocket

from agent.discovery import DiscoveryBeacon
from agent.install_service import (
    agent_id_path,
    ensure_running,
    hash_uninstall_password,
    install_agent,
    is_installed,
    load_config,
    save_config,
    set_uninstall_password_hash,
    uninstall_agent,
)
from shared import config
from shared import protocol as proto

try:
    import mss
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    mss = None  # type: ignore
    Image = None  # type: ignore


class VisibleMonitorBanner:
    """Always-on-top window that discloses an active remote session."""

    def __init__(self, title: str, subtitle: str) -> None:
        self.title = title
        self.subtitle = subtitle
        self._thread: threading.Thread | None = None
        self._stop = threading.Event()
        self._root = None

    def show(self) -> None:
        if self._thread and self._thread.is_alive():
            return
        self._stop.clear()
        self._thread = threading.Thread(target=self._run_tk, daemon=True)
        self._thread.start()

    def hide(self) -> None:
        self._stop.set()
        root = self._root
        if root is not None:
            try:
                root.after(0, root.destroy)
            except Exception:
                pass
        self._root = None

    def _run_tk(self) -> None:
        try:
            import tkinter as tk
        except Exception:
            print(f"\n*** {self.title} ***\n{self.subtitle}\n", flush=True)
            while not self._stop.wait(1.0):
                pass
            return

        root = tk.Tk()
        self._root = root
        root.title("Remote Session")
        root.attributes("-topmost", True)
        try:
            root.attributes("-alpha", 0.92)
        except Exception:
            pass
        root.overrideredirect(True)
        width = root.winfo_screenwidth()
        height = 56
        root.geometry(f"{width}x{height}+0+0")
        root.configure(bg="#b45309")
        frame = tk.Frame(root, bg="#b45309")
        frame.pack(fill="both", expand=True)
        tk.Label(
            frame,
            text=self.title,
            fg="white",
            bg="#b45309",
            font=("Segoe UI", 14, "bold"),
        ).pack(anchor="w", padx=16, pady=(6, 0))
        tk.Label(
            frame,
            text=self.subtitle,
            fg="#fff7ed",
            bg="#b45309",
            font=("Segoe UI", 10),
        ).pack(anchor="w", padx=16)

        def poll() -> None:
            if self._stop.is_set():
                root.destroy()
                return
            root.after(200, poll)

        poll()
        try:
            root.mainloop()
        except Exception:
            pass


class Agent:
    def __init__(self, server_url: str, enrollment_token: str, agent_id: str | None = None) -> None:
        self.server_url = server_url.rstrip("/")
        parsed = urlparse(self.server_url)
        scheme = "wss" if parsed.scheme == "https" else "ws"
        self.ws_url = f"{scheme}://{parsed.netloc}/ws/agent"
        self.enrollment_token = enrollment_token
        self.agent_id = agent_id or self._load_or_create_id()
        self.ws: websocket.WebSocketApp | None = None
        self._live = False
        self._live_lock = threading.Lock()
        self._banner = VisibleMonitorBanner(
            config.MONITOR_BANNER_TEXT,
            config.MONITOR_BANNER_SUBTEXT,
        )
        self._stop = threading.Event()
        self._beacon = DiscoveryBeacon(self._discovery_info)

    def _id_path(self) -> Path:
        return agent_id_path()

    def _load_or_create_id(self) -> str:
        path = self._id_path()
        path.parent.mkdir(parents=True, exist_ok=True)
        if path.is_file():
            return path.read_text(encoding="utf-8").strip()
        aid = uuid.uuid4().hex[:16]
        path.write_text(aid, encoding="utf-8")
        return aid

    def _host_info(self) -> dict:
        return {
            "agent_id": self.agent_id,
            "enrollment_token": self.enrollment_token,
            "hostname": platform.node(),
            "username": os.environ.get("USERNAME") or os.environ.get("USER") or "",
            "os_info": f"{platform.system()} {platform.release()} {platform.machine()}",
            "ip_address": self._local_ip(),
            "installed": is_installed(),
        }

    def _discovery_info(self) -> dict:
        return {
            "agent_id": self.agent_id,
            "hostname": platform.node(),
            "username": os.environ.get("USERNAME") or os.environ.get("USER") or "",
            "os_info": f"{platform.system()} {platform.release()} {platform.machine()}",
            "ip": self._local_ip(),
            "server": self.server_url,
            "installed": is_installed(),
        }

    @staticmethod
    def _local_ip() -> str:
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("8.8.8.8", 80))
            ip = s.getsockname()[0]
            s.close()
            return ip
        except Exception:
            return "0.0.0.0"

    def run(self) -> None:
        print("AU-Kamra IT Experts Remote Manager — Agent")
        print(f"Connecting to {self.server_url}")
        print("Live view will ALWAYS show a visible banner on this PC.")
        self._beacon.start()
        while not self._stop.is_set():
            self.ws = websocket.WebSocketApp(
                self.ws_url,
                on_open=self._on_open,
                on_message=self._on_message,
                on_error=self._on_error,
                on_close=self._on_close,
            )
            self.ws.run_forever(ping_interval=20, ping_timeout=10)
            if self._stop.is_set():
                break
            print("Disconnected; reconnecting in 5s...")
            time.sleep(5)
        self._beacon.stop()

    def _on_open(self, ws: websocket.WebSocketApp) -> None:
        ws.send(proto.encode(proto.MSG_REGISTER, self._host_info()))
        threading.Thread(target=self._heartbeat_loop, args=(ws,), daemon=True).start()

    def _heartbeat_loop(self, ws: websocket.WebSocketApp) -> None:
        while not self._stop.is_set():
            try:
                ws.send(proto.encode(proto.MSG_HEARTBEAT, {"ts": time.time(), **self._discovery_info()}))
            except Exception:
                break
            time.sleep(config.AGENT_HEARTBEAT_SECONDS)

    def _on_message(self, ws: websocket.WebSocketApp, message: str) -> None:
        msg_type, payload = proto.decode(message)
        if msg_type == proto.MSG_ACK:
            assigned = payload.get("agent_id")
            if assigned:
                self.agent_id = str(assigned)
                self._id_path().write_text(self.agent_id, encoding="utf-8")
            # Sync uninstall password hash from server
            uh = payload.get("uninstall_password_hash")
            if uh:
                set_uninstall_password_hash(str(uh))
            print(f"Registered as agent {self.agent_id}")
        elif msg_type == proto.MSG_CONFIG:
            uh = payload.get("uninstall_password_hash")
            if uh:
                set_uninstall_password_hash(str(uh))
                print("Updated uninstall password from server administrator.")
        elif msg_type == proto.MSG_REMOTE_UNINSTALL:
            password = str(payload.get("password") or "")
            try:
                uninstall_agent(password)
                ws.send(proto.encode(proto.MSG_STATUS, {"uninstalled": True}))
                self._stop.set()
                ws.close()
            except Exception as exc:
                ws.send(proto.encode(proto.MSG_STATUS, {"uninstalled": False, "error": str(exc)}))
        elif msg_type == proto.MSG_SHELL:
            threading.Thread(target=self._handle_shell, args=(ws, payload), daemon=True).start()
        elif msg_type == proto.MSG_INSTALL:
            threading.Thread(target=self._handle_install, args=(ws, payload), daemon=True).start()
        elif msg_type == proto.MSG_START_LIVE:
            self._start_live(ws, payload)
        elif msg_type == proto.MSG_STOP_LIVE:
            self._stop_live()
        elif msg_type == proto.MSG_ERROR:
            print("Server error:", payload)

    def _on_error(self, ws: websocket.WebSocketApp, error: Exception) -> None:
        print("WebSocket error:", error)

    def _on_close(self, ws: websocket.WebSocketApp, status_code: int, msg: str) -> None:
        self._stop_live()
        print(f"Connection closed ({status_code}): {msg}")

    def _handle_shell(self, ws: websocket.WebSocketApp, payload: dict) -> None:
        job_id = payload.get("job_id")
        command = payload.get("command", "")
        shell = payload.get("shell", "cmd")
        try:
            output, code = run_shell(command, shell)
            ok = code == 0
        except Exception as exc:
            output, code, ok = str(exc), -1, False
        ws.send(
            proto.encode(
                proto.MSG_SHELL_RESULT,
                {"job_id": job_id, "ok": ok, "exit_code": code, "output": output[-12000:]},
            )
        )

    def _handle_install(self, ws: websocket.WebSocketApp, payload: dict) -> None:
        job_id = payload.get("job_id")
        download_path = payload.get("download_path") or ""
        filename = payload.get("filename") or "setup.exe"
        args = (payload.get("args") or "").strip()
        url = self.server_url + download_path
        try:
            with tempfile.TemporaryDirectory(prefix="rmm_pkg_") as tmp:
                dest = Path(tmp) / filename
                r = requests.get(
                    url,
                    params={"token": self.enrollment_token},
                    timeout=300,
                )
                r.raise_for_status()
                dest.write_bytes(r.content)
                cmd = build_install_command(dest, args)
                completed = subprocess.run(
                    cmd,
                    shell=True,
                    capture_output=True,
                    text=True,
                    timeout=1800,
                )
                output = (completed.stdout or "") + (completed.stderr or "")
                ok = completed.returncode == 0
                code = completed.returncode
        except Exception as exc:
            output, code, ok = str(exc), -1, False
        ws.send(
            proto.encode(
                proto.MSG_INSTALL_RESULT,
                {"job_id": job_id, "ok": ok, "exit_code": code, "output": output[-12000:]},
            )
        )

    def _start_live(self, ws: websocket.WebSocketApp, payload: dict) -> None:
        with self._live_lock:
            if self._live:
                return
            self._live = True
        banner_text = payload.get("banner_text") or config.MONITOR_BANNER_TEXT
        banner_sub = payload.get("banner_subtext") or config.MONITOR_BANNER_SUBTEXT
        self._banner = VisibleMonitorBanner(banner_text, banner_sub)
        self._banner.show()
        fps = float(payload.get("fps") or config.LIVE_FPS)
        quality = int(payload.get("quality") or config.LIVE_JPEG_QUALITY)
        max_width = int(payload.get("max_width") or config.LIVE_MAX_WIDTH)
        threading.Thread(
            target=self._live_loop,
            args=(ws, fps, quality, max_width),
            daemon=True,
        ).start()

    def _stop_live(self) -> None:
        with self._live_lock:
            self._live = False
        self._banner.hide()

    def _live_loop(self, ws: websocket.WebSocketApp, fps: float, quality: int, max_width: int) -> None:
        interval = 1.0 / max(fps, 0.5)
        while True:
            with self._live_lock:
                if not self._live:
                    break
            try:
                jpeg = capture_frame(max_width, quality, burn_in_banner=True)
                if jpeg and ws:
                    ws.send(
                        proto.encode(
                            proto.MSG_FRAME,
                            {
                                "ts": time.time(),
                                "mime": "image/jpeg",
                                "data": base64.b64encode(jpeg).decode("ascii"),
                            },
                        )
                    )
            except Exception as exc:
                print("Frame capture error:", exc)
            time.sleep(interval)


def run_shell(command: str, shell: str) -> tuple[str, int]:
    system = platform.system().lower()
    if shell == "powershell":
        cmd = ["powershell", "-NoProfile", "-NonInteractive", "-Command", command]
    elif shell == "bash":
        cmd = ["bash", "-lc", command]
    else:
        if system == "windows":
            cmd = ["cmd", "/c", command]
        else:
            cmd = ["bash", "-lc", command]
    completed = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    out = (completed.stdout or "") + (completed.stderr or "")
    return out, completed.returncode


def build_install_command(path: Path, args: str) -> str:
    suffix = path.suffix.lower()
    quoted = f'"{path}"'
    if suffix in (".msi",):
        return f'msiexec /i {quoted} {args} /qn'.strip()
    if suffix == ".ps1":
        return f'powershell -NoProfile -ExecutionPolicy Bypass -File {quoted} {args}'.strip()
    if suffix == ".sh":
        return f'bash {quoted} {args}'.strip()
    return f"{quoted} {args}".strip()


def capture_frame(max_width: int, quality: int, burn_in_banner: bool = True) -> bytes | None:
    if mss is None or Image is None:
        return None
    with mss.mss() as sct:
        monitor = sct.monitors[1]
        shot = sct.grab(monitor)
        img = Image.frombytes("RGB", shot.size, shot.bgra, "raw", "BGRX")
    if img.width > max_width:
        ratio = max_width / img.width
        img = img.resize((max_width, int(img.height * ratio)))
    if burn_in_banner:
        draw = ImageDraw.Draw(img)
        banner_h = max(36, img.height // 28)
        draw.rectangle([(0, 0), (img.width, banner_h)], fill=(180, 83, 9))
        text = config.MONITOR_BANNER_TEXT
        try:
            font = ImageFont.truetype("arial.ttf", max(12, banner_h // 2))
        except Exception:
            font = ImageFont.load_default()
        draw.text((12, banner_h // 4), text, fill=(255, 255, 255), font=font)
    buf = io.BytesIO()
    img.save(buf, format="JPEG", quality=quality, optimize=True)
    return buf.getvalue()


def _gui_message(title: str, message: str, error: bool = False) -> None:
    try:
        import tkinter as tk
        from tkinter import messagebox

        root = tk.Tk()
        root.withdraw()
        if error:
            messagebox.showerror(title, message)
        else:
            messagebox.showinfo(title, message)
        root.destroy()
    except Exception:
        try:
            print(f"{title}: {message}")
        except Exception:
            pass


def _gui_ask_password(title: str, prompt: str) -> str | None:
    try:
        import tkinter as tk
        from tkinter import simpledialog

        root = tk.Tk()
        root.withdraw()
        value = simpledialog.askstring(title, prompt, show="*")
        root.destroy()
        return value
    except Exception:
        try:
            return getpass.getpass(prompt + " ")
        except Exception:
            return None


def _gui_install_wizard(defaults: dict[str, str] | None = None) -> tuple[str, str] | None:
    """One-time install dialog when agent .exe is double-clicked."""
    try:
        import tkinter as tk
        from tkinter import messagebox
    except Exception:
        return None

    defaults = defaults or {}
    result: dict[str, str] = {}

    root = tk.Tk()
    root.title("AU-Kamra IT Experts Remote Manager")
    root.geometry("520x300")
    root.resizable(False, False)
    tk.Label(
        root,
        text="AU-Kamra IT Experts Remote Manager",
        font=("Segoe UI", 13, "bold"),
    ).pack(pady=(18, 4))
    tk.Label(
        root,
        text="One-time permanent install. Agent runs in the background after reboot.\n"
        "Closing this window will not stop the agent. Uninstall requires admin password.",
        wraplength=480,
        justify="center",
    ).pack(padx=16)
    frm = tk.Frame(root)
    frm.pack(padx=24, pady=16, fill="x")
    tk.Label(frm, text="Server URL").grid(row=0, column=0, sticky="w", pady=6)
    server_var = tk.StringVar(value=defaults.get("server") or "http://192.168.1.10:8443")
    tk.Entry(frm, textvariable=server_var, width=42).grid(row=0, column=1, pady=6)
    tk.Label(frm, text="Enrollment token").grid(row=1, column=0, sticky="w", pady=6)
    token_var = tk.StringVar(value=defaults.get("token") or "")
    tk.Entry(frm, textvariable=token_var, width=42).grid(row=1, column=1, pady=6)

    def do_install() -> None:
        s = server_var.get().strip()
        t = token_var.get().strip()
        if not s or not t:
            messagebox.showerror("Missing info", "Server URL and enrollment token are required.")
            return
        result["server"] = s
        result["token"] = t
        root.destroy()

    tk.Button(
        root,
        text="Install permanently & run in background",
        command=do_install,
        font=("Segoe UI", 10, "bold"),
        padx=12,
        pady=6,
    ).pack(pady=10)
    root.mainloop()
    if "server" in result:
        return result["server"], result["token"]
    return None


def _acquire_single_instance() -> bool:
    """Prevent multiple --run instances. Returns False if another instance holds the lock."""
    try:
        lock_path = agent_id_path().parent / "agent.lock"
        lock_path.parent.mkdir(parents=True, exist_ok=True)
        if platform.system().lower() == "windows":
            # Exclusive create; if exists and process alive, skip. Simple file lock via msvcrt.
            import msvcrt  # type: ignore

            fp = open(lock_path, "a+b")
            fp.seek(0)
            if fp.read(1) == b"":
                fp.write(b"1")
                fp.flush()
            fp.seek(0)
            try:
                msvcrt.locking(fp.fileno(), msvcrt.LK_NBLCK, 1)
            except OSError:
                fp.close()
                return False
            # Keep handle alive for process lifetime
            Agent._lock_fp = fp  # type: ignore[attr-defined]
            return True
        import fcntl  # type: ignore

        fp = open(lock_path, "a+")
        try:
            fcntl.flock(fp.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except OSError:
            fp.close()
            return False
        Agent._lock_fp = fp  # type: ignore[attr-defined]
        return True
    except Exception:
        return True


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(description="AU-Kamra IT Experts Remote Manager Agent")
    p.add_argument("--server", default=None, help="Server base URL, e.g. http://192.168.1.10:8443")
    p.add_argument("--token", default=None, help="Enrollment token from administrator")
    p.add_argument("--agent-id", default=None, help="Optional fixed agent id")
    p.add_argument("--install", action="store_true", help="Install permanently (background + autostart)")
    p.add_argument("--uninstall", action="store_true", help="Uninstall (requires admin password)")
    p.add_argument("--uninstall-password", default=None, help="Uninstall password (or prompt)")
    p.add_argument("--run", action="store_true", help="Background service loop (autostart uses this)")
    return p.parse_args(argv)


def _do_permanent_install(server: str, token: str) -> Path:
    uninstall_hash = ""
    try:
        r = requests.get(f"{server.rstrip('/')}/api/public/agent-bootstrap", timeout=10)
        if r.ok:
            uninstall_hash = str(r.json().get("uninstall_password_hash") or "")
    except Exception:
        pass
    if not uninstall_hash:
        uninstall_hash = hash_uninstall_password(config.DEFAULT_UNINSTALL_PASSWORD)
    return install_agent(server, token, uninstall_hash)


def main(argv: list[str] | None = None) -> None:
    args = parse_args(argv)

    if args.uninstall:
        password = args.uninstall_password or _gui_ask_password(
            "Uninstall AU-Kamra Agent",
            "Enter uninstall password from admin Settings:",
        )
        if not password:
            sys.exit(1)
        try:
            uninstall_agent(password)
            _gui_message("Uninstalled", "AU-Kamra agent was removed from this PC.")
        except PermissionError as exc:
            _gui_message("Uninstall failed", str(exc), error=True)
            sys.exit(2)
        except Exception as exc:
            _gui_message("Uninstall failed", str(exc), error=True)
            sys.exit(1)
        return

    cfg = load_config()
    server = args.server or cfg.get("server")
    token = args.token or cfg.get("token")

    # Background service mode (scheduled task / Run key / detached process)
    if args.run:
        if not server or not token:
            sys.exit(1)
        if not _acquire_single_instance():
            # Another background agent already running
            return
        save_config(
            {
                "server": str(server).rstrip("/"),
                "token": token,
                "installed": True,
                "background": True,
            }
        )
        agent = Agent(str(server), str(token), args.agent_id)
        try:
            agent.run()
        except KeyboardInterrupt:
            agent._stop.set()
            agent._stop_live()
        return

    # Double-click / --install: permanent install then EXIT (background keeps running)
    want_install = args.install or not args.run
    if want_install:
        if is_installed() and server and token and not args.install:
            # Already installed — ensure background is up, show brief notice, exit
            ensure_running(str(server), str(token))
            _gui_message(
                "AU-Kamra Agent",
                "Agent is already installed and running in the background.\n"
                "It will start automatically after reboot.\n\n"
                "To remove it, run the agent with --uninstall (admin password required).",
            )
            return

        if not server or not token or (not is_installed() and not args.server):
            wizard = _gui_install_wizard({"server": server or "", "token": token or ""})
            if not wizard:
                # Silent CLI fallback only if stdin is a TTY
                if sys.stdin and sys.stdin.isatty():
                    server = (server or input("Server URL: ").strip())
                    token = (token or input("Enrollment token: ").strip())
                else:
                    _gui_message(
                        "Install cancelled",
                        "Server URL and enrollment token are required.",
                        error=True,
                    )
                    sys.exit(1)
            else:
                server, token = wizard

        try:
            exe = _do_permanent_install(str(server), str(token))
        except Exception as exc:
            if args.install and args.server and args.token:
                print("Install failed:", exc)
            else:
                _gui_message("Install failed", str(exc), error=True)
            sys.exit(1)

        # Remote/silent push: no dialog. Interactive double-click: confirm then exit.
        if not (args.install and args.server and args.token):
            _gui_message(
                "Installed",
                "AU-Kamra agent installed permanently and is running in the background.\n\n"
                f"Location:\n{exe}\n\n"
                "• Survives reboot automatically\n"
                "• Closing windows will not stop it\n"
                "• Remove only with uninstall password from the admin panel",
            )
        # Critical: do NOT keep this process as the agent — background copy already started
        return

    if not server or not token:
        _gui_message("Missing settings", "Server URL and token required.", error=True)
        sys.exit(1)

    # Explicit foreground run (rare / debug)
    agent = Agent(str(server), str(token), args.agent_id)
    try:
        agent.run()
    except KeyboardInterrupt:
        agent._stop.set()
        agent._stop_live()


if __name__ == "__main__":
    main()