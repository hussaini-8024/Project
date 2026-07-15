"""
AU-Kamra Agent — remote workstation client (single EXE).

Connects to the AU-Kamra server on the LAN by IP address,
opens the authenticated agent panel, and reports live activity.
"""

from __future__ import annotations

import os
import socket
import sys
import threading
import webbrowser
from contextlib import closing
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse
from urllib.request import urlopen


APP_TITLE = "AU-Kamra-IT Agent"


def _browser_disabled() -> bool:
    return os.environ.get("BROWSER", "").strip().lower() in {"none", "off", "0"}


def probe_server(ip: str, port: int, timeout: float = 2.0) -> bool:
    try:
        with urlopen(f"http://{ip}:{port}/api/health", timeout=timeout) as resp:
            return resp.status == 200
    except Exception:
        return False


def ask_connection() -> tuple[str, int]:
    """Read server IP/port from env, CLI, or interactive prompt."""
    ip = os.environ.get("AU_KAMRA_SERVER_IP", "").strip()
    port_s = os.environ.get("AU_KAMRA_SERVER_PORT", "8765").strip()
    if len(sys.argv) >= 2:
        ip = sys.argv[1].strip()
    if len(sys.argv) >= 3:
        port_s = sys.argv[2].strip()

    if not ip and sys.stdin.isatty():
        print(f"{APP_TITLE}")
        print("Enter the AU-Kamra server LAN IP address.")
        ip = input("Server IP [127.0.0.1]: ").strip() or "127.0.0.1"
        port_s = input("Port [8765]: ").strip() or "8765"

    if not ip:
        ip = "127.0.0.1"
    try:
        port = int(port_s)
    except ValueError:
        port = 8765
    return ip, port


def open_agent(url: str) -> None:
    if _browser_disabled():
        print(f"Open agent UI: {url}")
        return
    if sys.platform.startswith("win"):
        try:
            import webview  # type: ignore

            webview.create_window(APP_TITLE, url, width=1200, height=800, min_size=(900, 600))
            webview.start()
            return
        except Exception:
            pass
    webbrowser.open(url)


def run_local_launcher(default_ip: str, default_port: int) -> str:
    """Tiny local page to collect server IP if no CLI/env provided."""
    html = f"""<!DOCTYPE html>
<html><head><meta charset="utf-8"/><title>{APP_TITLE}</title>
<style>
body{{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Segoe UI,sans-serif;
background:linear-gradient(160deg,#071525,#163752);color:#102033}}
form{{background:#fff;padding:28px;border-radius:18px;width:min(400px,92vw);box-shadow:0 20px 50px rgba(0,0,0,.3)}}
h1{{font-family:Georgia,serif;margin:0 0 8px;font-size:28px}}
p{{color:#5d7085;margin:0 0 16px}}
label{{display:block;font-size:13px;color:#5d7085;margin:10px 0 6px}}
input{{width:100%;padding:11px;border:1px solid #d3deea;border-radius:10px;box-sizing:border-box}}
button{{margin-top:16px;width:100%;padding:12px;border:0;border-radius:10px;background:#1c4b6e;color:#fff;font-weight:600;cursor:pointer}}
.err{{color:#b42318;margin-top:10px}}
</style></head><body>
<form method="GET" action="/go">
<h1>AU-Kamra Agent</h1>
<p>Connect to the loan cards server on your network.</p>
<label>Server IP</label><input name="ip" value="{default_ip}" required/>
<label>Port</label><input name="port" value="{default_port}" required/>
<button type="submit">Connect</button>
</form></body></html>"""

    result = {"url": ""}

    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            parsed = urlparse(self.path)
            if parsed.path == "/go":
                qs = parse_qs(parsed.query)
                ip = (qs.get("ip") or ["127.0.0.1"])[0].strip()
                port = (qs.get("port") or ["8765"])[0].strip()
                target = f"http://{ip}:{port}/agent"
                if not probe_server(ip, int(port)):
                    body = html.replace(
                        "</form>",
                        f'<p class="err">Cannot reach server at {ip}:{port}. Is the server EXE running?</p></form>',
                    )
                    data = body.encode()
                    self.send_response(200)
                    self.send_header("Content-Type", "text/html; charset=utf-8")
                    self.send_header("Content-Length", str(len(data)))
                    self.end_headers()
                    self.wfile.write(data)
                    return
                result["url"] = target
                self.send_response(302)
                self.send_header("Location", target)
                self.end_headers()
                threading.Thread(target=self.server.shutdown, daemon=True).start()
                return
            data = html.encode()
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)

        def log_message(self, fmt, *args):
            return

    # bind ephemeral local port
    with closing(socket.socket(socket.AF_INET, socket.SOCK_STREAM)) as s:
        s.bind(("127.0.0.1", 0))
        local_port = s.getsockname()[1]
    server = HTTPServer(("127.0.0.1", local_port), Handler)
    url = f"http://127.0.0.1:{local_port}/"
    if _browser_disabled():
        print(f"Open launcher: {url}")
    else:
        threading.Timer(0.3, lambda: webbrowser.open(url)).start()
    server.serve_forever()
    return result["url"]


def main() -> None:
    ip, port = ask_connection()
    # If IP provided via CLI/env, go directly; else show launcher page
    if os.environ.get("AU_KAMRA_SERVER_IP") or len(sys.argv) >= 2:
        if not probe_server(ip, port):
            print(f"ERROR: Cannot reach AU-Kamra server at {ip}:{port}")
            print("Start AU-Kamra-IT-Loan-Cards-Server.exe on the server PC first.")
            if sys.stdin.isatty():
                input("Press Enter to exit...")
            sys.exit(1)
        open_agent(f"http://{ip}:{port}/agent")
    else:
        target = run_local_launcher(ip, port)
        if target:
            # Already redirected in browser; keep process alive briefly for webview users
            if sys.platform.startswith("win"):
                try:
                    import webview  # type: ignore

                    webview.create_window(APP_TITLE, target, width=1200, height=800)
                    webview.start()
                except Exception:
                    pass


if __name__ == "__main__":
    main()
