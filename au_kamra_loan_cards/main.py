"""
AU-Kamra-IT Loan Cards Management — application entry point.

Starts a local web UI (and optional native window on Windows).
Designed to be packaged into a single .exe with PyInstaller.
"""

from __future__ import annotations

import socket
import sys
import threading
import time
import webbrowser
from contextlib import closing

from au_kamra_loan_cards import APP_NAME, HOST, PORT, data_dir
from au_kamra_loan_cards.server import create_app


def _port_free(port: int) -> bool:
    with closing(socket.socket(socket.AF_INET, socket.SOCK_STREAM)) as sock:
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        try:
            sock.bind((HOST, port))
            return True
        except OSError:
            return False


def find_port(preferred: int = PORT) -> int:
    if _port_free(preferred):
        return preferred
    for candidate in range(preferred + 1, preferred + 40):
        if _port_free(candidate):
            return candidate
    raise RuntimeError("No free local port available")


def _browser_disabled() -> bool:
    import os

    return os.environ.get("BROWSER", "").strip().lower() in {"none", "off", "0"}


def open_ui(url: str) -> None:
    """Prefer a native window on Windows; fall back to the default browser."""
    if _browser_disabled():
        print(f"Browser auto-open disabled. Visit: {url}")
        return
    if sys.platform.startswith("win"):
        try:
            import webview  # type: ignore

            webview.create_window(
                APP_NAME,
                url,
                width=1280,
                height=840,
                min_size=(980, 640),
            )
            webview.start()
            return
        except Exception:
            pass
    webbrowser.open(url)


def main() -> None:
    data_dir()  # ensure folders exist
    port = find_port()
    app = create_app()
    url = f"http://{HOST}:{port}/"

    # Run Flask in a daemon thread when using pywebview (blocking UI loop)
    use_webview = False
    if sys.platform.startswith("win"):
        try:
            import webview  # noqa: F401

            use_webview = True
        except Exception:
            use_webview = False

    if use_webview:

        def run_server() -> None:
            app.run(host=HOST, port=port, debug=False, use_reloader=False, threaded=True)

        thread = threading.Thread(target=run_server, daemon=True)
        thread.start()
        # Wait until server responds
        for _ in range(50):
            try:
                with closing(socket.create_connection((HOST, port), timeout=0.2)):
                    break
            except OSError:
                time.sleep(0.1)
        open_ui(url)
    else:
        print(f"{APP_NAME}")
        print(f"Open in your browser: {url}")
        print("Press Ctrl+C to stop.")
        if not _browser_disabled():
            threading.Timer(0.8, lambda: webbrowser.open(url)).start()
        app.run(host=HOST, port=port, debug=False, use_reloader=False, threaded=True)


if __name__ == "__main__":
    main()
