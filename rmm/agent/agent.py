"""
DiscloseRMM Agent — runs on managed company PCs.

IMPORTANT: Live screen sharing ALWAYS shows a visible on-screen banner.
Covert / hidden monitoring is intentionally not supported.
"""

from __future__ import annotations

import argparse
import base64
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
            # Fallback: console notice if GUI unavailable
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

    def _id_path(self) -> Path:
        base = Path(os.environ.get("LOCALAPPDATA") or os.environ.get("HOME") or ".")
        d = base / "DiscloseRMM"
        d.mkdir(parents=True, exist_ok=True)
        return d / "agent_id.txt"

    def _load_or_create_id(self) -> str:
        path = self._id_path()
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
        print(f"DiscloseRMM agent connecting to {self.server_url}")
        print("Live view will ALWAYS show a visible banner on this PC.")
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

    def _on_open(self, ws: websocket.WebSocketApp) -> None:
        ws.send(proto.encode(proto.MSG_REGISTER, self._host_info()))
        threading.Thread(target=self._heartbeat_loop, args=(ws,), daemon=True).start()

    def _heartbeat_loop(self, ws: websocket.WebSocketApp) -> None:
        while not self._stop.is_set():
            try:
                ws.send(proto.encode(proto.MSG_HEARTBEAT, {"ts": time.time()}))
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
            print(f"Registered as agent {self.agent_id}")
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
        if system == "windows":
            cmd = ["bash", "-lc", command]
        else:
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
    if suffix in (".exe", ".bat", ".cmd", ".ps1", ".sh", ""):
        if suffix == ".ps1":
            return f'powershell -NoProfile -ExecutionPolicy Bypass -File {quoted} {args}'.strip()
        if suffix == ".sh":
            return f'bash {quoted} {args}'.strip()
        return f"{quoted} {args}".strip()
    return f"{quoted} {args}".strip()


def capture_frame(max_width: int, quality: int, burn_in_banner: bool = True) -> bytes | None:
    """Capture screen; burn-in banner ensures disclosure even in the stream itself."""
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


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="DiscloseRMM Agent (visible remote sessions)")
    p.add_argument("--server", required=True, help="Server base URL, e.g. http://192.168.1.10:8443")
    p.add_argument("--token", required=True, help="Enrollment token from administrator")
    p.add_argument("--agent-id", default=None, help="Optional fixed agent id")
    return p.parse_args()


def main() -> None:
    args = parse_args()
    agent = Agent(args.server, args.token, args.agent_id)
    try:
        agent.run()
    except KeyboardInterrupt:
        agent._stop.set()
        agent._stop_live()
        print("Agent stopped.")


if __name__ == "__main__":
    main()
