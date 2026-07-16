"""AU Labs Setup — VLC-style graphical installer for Server and Agent."""

from __future__ import annotations

import argparse
import os
import platform
import shutil
import stat
import subprocess
import sys
import threading
import webbrowser
from pathlib import Path


APP_TITLE = "AU Labs IT Management Setup"
APP_VERSION = "1.0.0"


def is_windows() -> bool:
    return platform.system() == "Windows"


def is_frozen() -> bool:
    return bool(getattr(sys, "frozen", False))


def bundle_dir() -> Path:
    """Directory containing bundled payload (server/agent binaries or source)."""
    if is_frozen():
        meipass = getattr(sys, "_MEIPASS", None)
        if meipass:
            payload = Path(meipass) / "payload"
            if payload.exists():
                return payload
        return Path(sys.executable).resolve().parent
    # Dev mode: project root
    return Path(__file__).resolve().parent.parent


def default_install_dir() -> Path:
    if is_windows():
        pf = os.environ.get("PROGRAMFILES", r"C:\Program Files")
        return Path(pf) / "AU Labs IT Management"
    if os.geteuid() == 0 if hasattr(os, "geteuid") else False:
        return Path("/opt/aulabs")
    return Path.home() / "AU Labs IT Management"


def find_payload_binary(name: str) -> Path | None:
    """Locate server/agent binary inside installer payload or sibling dist."""
    root = bundle_dir()
    candidates = []
    if is_windows():
        candidates += [
            root / f"{name}.exe",
            root / "bin" / f"{name}.exe",
            root / name / f"{name}.exe",
        ]
    candidates += [
        root / name,
        root / "bin" / name,
        root / name / name,
    ]
    # Sibling dist when running setup from source tree builds
    dist = Path(__file__).resolve().parent.parent / "dist"
    if is_windows():
        candidates += [dist / f"{name}.exe", dist / name / f"{name}.exe"]
    else:
        candidates += [dist / name, dist / name / name]
    for c in candidates:
        if c.exists() and c.is_file():
            return c
    return None


def write_launcher(path: Path, target: Path, env: dict[str, str] | None = None) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if is_windows():
        lines = ["@echo off", f'cd /d "{target.parent}"']
        if env:
            for k, v in env.items():
                lines.append(f"set {k}={v}")
        lines.append(f'"{target}" %*')
        path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    else:
        env_exports = ""
        if env:
            env_exports = "\n".join(f'export {k}="{v}"' for k, v in env.items()) + "\n"
        path.write_text(
            "#!/usr/bin/env bash\n"
            "set -euo pipefail\n"
            f"{env_exports}"
            f'cd "{target.parent}"\n'
            f'exec "{target}" "$@"\n',
            encoding="utf-8",
        )
        path.chmod(path.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)


def create_desktop_shortcut(install_dir: Path, name: str, target: Path, args: str = "") -> None:
    if is_windows():
        # Create a .bat shortcut on the Desktop (no pywin32 dependency)
        desktop = Path.home() / "Desktop"
        if not desktop.exists():
            desktop = Path.home() / "OneDrive" / "Desktop"
        if desktop.exists():
            bat = desktop / f"{name}.bat"
            bat.write_text(
                f'@echo off\nstart "" "{target}" {args}\n',
                encoding="utf-8",
            )
        return
    apps = Path.home() / ".local" / "share" / "applications"
    apps.mkdir(parents=True, exist_ok=True)
    desktop_file = apps / f"{name.lower().replace(' ', '-')}.desktop"
    desktop_file.write_text(
        "[Desktop Entry]\n"
        "Type=Application\n"
        f"Name={name}\n"
        f"Exec=\"{target}\" {args}\n"
        "Terminal=false\n"
        "Categories=System;Network;\n",
        encoding="utf-8",
    )


def install_components(
    install_dir: Path,
    *,
    install_server: bool,
    install_agent: bool,
    start_after: bool,
    server_host: str,
    server_port: int,
    agent_server_url: str,
    progress=None,
) -> dict:
    def log(msg: str) -> None:
        if progress:
            progress(msg)
        else:
            print(msg)

    install_dir = Path(install_dir)
    bin_dir = install_dir / "bin"
    data_dir = install_dir / "data"
    bin_dir.mkdir(parents=True, exist_ok=True)
    data_dir.mkdir(parents=True, exist_ok=True)

    installed = {"server": None, "agent": None, "install_dir": str(install_dir)}

    if install_server:
        log("Installing AU Labs Server...")
        src = find_payload_binary("AULabsServer")
        if src is None:
            # Fallback: copy package and create venv-less runner via current interpreter
            log("Bundled server binary not found — installing portable Python package runner")
            dest = bin_dir / ("AULabsServer.bat" if is_windows() else "AULabsServer")
            pkg_root = bundle_dir()
            # Copy source package for portable mode
            pkg_dest = install_dir / "app"
            if pkg_dest.exists():
                shutil.rmtree(pkg_dest, ignore_errors=True)
            if (pkg_root / "aulabs").exists():
                shutil.copytree(
                    pkg_root / "aulabs",
                    pkg_dest / "aulabs",
                    ignore=shutil.ignore_patterns("__pycache__", "*.pyc"),
                )
                for fname in ("requirements.txt", "pyproject.toml"):
                    if (pkg_root / fname).exists():
                        shutil.copy2(pkg_root / fname, pkg_dest / fname)
            py = sys.executable
            env = {
                "AULABS_DATA_ROOT": str(data_dir),
                "AULABS_HOST": server_host,
                "AULABS_PORT": str(server_port),
                "PYTHONPATH": str(pkg_dest),
            }
            if is_windows():
                dest.write_text(
                    "@echo off\n"
                    f'set AULABS_DATA_ROOT={data_dir}\n'
                    f'set AULABS_HOST={server_host}\n'
                    f'set AULABS_PORT={server_port}\n'
                    f'set PYTHONPATH={pkg_dest}\n'
                    f'"{py}" -m aulabs serve\n',
                    encoding="utf-8",
                )
            else:
                write_launcher(dest, Path(py), env)
                # Overwrite launcher to call -m aulabs
                dest.write_text(
                    "#!/usr/bin/env bash\n"
                    "set -euo pipefail\n"
                    f'export AULABS_DATA_ROOT="{data_dir}"\n'
                    f'export AULABS_HOST="{server_host}"\n'
                    f'export AULABS_PORT="{server_port}"\n'
                    f'export PYTHONPATH="{pkg_dest}"\n'
                    f'exec "{py}" -m aulabs serve\n',
                    encoding="utf-8",
                )
                dest.chmod(dest.stat().st_mode | 0o755)
            installed["server"] = str(dest)
        else:
            ext = ".exe" if is_windows() else ""
            dest = bin_dir / f"AULabsServer{ext}"
            shutil.copy2(src, dest)
            if not is_windows():
                dest.chmod(dest.stat().st_mode | 0o755)
            # Env sidecar
            env_file = install_dir / "aulabs.env"
            env_file.write_text(
                f"AULABS_DATA_ROOT={data_dir}\n"
                f"AULABS_HOST={server_host}\n"
                f"AULABS_PORT={server_port}\n",
                encoding="utf-8",
            )
            installed["server"] = str(dest)
            create_desktop_shortcut(install_dir, "AU Labs Server", dest)
        log("Server installed.")

    if install_agent:
        log("Installing AU Labs Agent...")
        src = find_payload_binary("AULabsAgent")
        ext = ".exe" if is_windows() else ""
        dest = bin_dir / f"AULabsAgent{ext}"
        if src is None:
            pkg_dest = install_dir / "app"
            pkg_root = bundle_dir()
            if not (pkg_dest / "aulabs").exists() and (pkg_root / "aulabs").exists():
                shutil.copytree(
                    pkg_root / "aulabs",
                    pkg_dest / "aulabs",
                    ignore=shutil.ignore_patterns("__pycache__", "*.pyc"),
                )
            py = sys.executable
            agent_cfg = install_dir / "agent.json"
            agent_cfg.write_text(
                "{\n"
                f'  "server_url": "{agent_server_url}",\n'
                f'  "agent_name": "{platform.node()}",\n'
                '  "heartbeat_seconds": 15\n'
                "}\n",
                encoding="utf-8",
            )
            if is_windows():
                dest = bin_dir / "AULabsAgent.bat"
                dest.write_text(
                    "@echo off\n"
                    f'set PYTHONPATH={install_dir / "app"}\n'
                    f'"{py}" -m aulabs.agent run --server {agent_server_url} --config "{agent_cfg}"\n',
                    encoding="utf-8",
                )
            else:
                dest.write_text(
                    "#!/usr/bin/env bash\n"
                    "set -euo pipefail\n"
                    f'export PYTHONPATH="{install_dir / "app"}"\n'
                    f'exec "{py}" -m aulabs.agent run --server "{agent_server_url}" --config "{agent_cfg}"\n',
                    encoding="utf-8",
                )
                dest.chmod(dest.stat().st_mode | 0o755)
        else:
            shutil.copy2(src, dest)
            if not is_windows():
                dest.chmod(dest.stat().st_mode | 0o755)
            agent_cfg = install_dir / "agent.json"
            agent_cfg.write_text(
                "{\n"
                f'  "server_url": "{agent_server_url}",\n'
                f'  "agent_name": "{platform.node()}",\n'
                '  "heartbeat_seconds": 15\n'
                "}\n",
                encoding="utf-8",
            )
        installed["agent"] = str(dest)
        create_desktop_shortcut(install_dir, "AU Labs Agent", dest)
        log("Agent installed.")

    # Uninstaller
    uninstaller = bin_dir / ("Uninstall.bat" if is_windows() else "uninstall.sh")
    if is_windows():
        uninstaller.write_text(
            "@echo off\n"
            f'rmdir /s /q "{install_dir}"\n'
            "echo AU Labs IT Management removed.\n"
            "pause\n",
            encoding="utf-8",
        )
    else:
        uninstaller.write_text(
            "#!/usr/bin/env bash\n"
            f'rm -rf "{install_dir}"\n'
            'rm -f "$HOME/.local/share/applications/au-labs-"*.desktop 2>/dev/null || true\n'
            'echo "AU Labs IT Management removed."\n',
            encoding="utf-8",
        )
        uninstaller.chmod(uninstaller.stat().st_mode | 0o755)

    readme = install_dir / "README.txt"
    readme.write_text(
        f"{APP_TITLE}\n"
        f"Version: {APP_VERSION}\n"
        f"Install dir: {install_dir}\n\n"
        f"Server URL: http://{server_host}:{server_port}\n"
        "Default login: admin / aulabs-admin\n\n"
        "Start Server: bin/AULabsServer\n"
        "Start Agent : bin/AULabsAgent\n"
        "Uninstall   : bin/uninstall.sh (or Uninstall.bat on Windows)\n",
        encoding="utf-8",
    )

    if start_after and installed.get("server"):
        log("Starting server...")
        try:
            subprocess.Popen(
                [installed["server"]],
                cwd=str(install_dir),
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                start_new_session=True,
            )
            webbrowser.open(f"http://{server_host}:{server_port}")
        except Exception as exc:
            log(f"Could not auto-start server: {exc}")

    if start_after and installed.get("agent"):
        log("Starting agent...")
        try:
            subprocess.Popen(
                [installed["agent"]],
                cwd=str(install_dir),
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                start_new_session=True,
            )
        except Exception as exc:
            log(f"Could not auto-start agent: {exc}")

    log("Setup completed successfully.")
    return installed


def run_gui() -> int:
    import tkinter as tk
    from tkinter import filedialog, messagebox, ttk

    root = tk.Tk()
    root.title(APP_TITLE)
    root.geometry("620x520")
    root.minsize(560, 480)

    # Colors — teal/slate, not purple
    bg = "#0b1a24"
    fg = "#e8f2f7"
    accent = "#2ec4b6"
    muted = "#8aa3b3"
    root.configure(bg=bg)

    style = ttk.Style(root)
    try:
        style.theme_use("clam")
    except Exception:
        pass
    style.configure("TFrame", background=bg)
    style.configure("TLabel", background=bg, foreground=fg, font=("Segoe UI", 10))
    style.configure("Title.TLabel", background=bg, foreground=fg, font=("Segoe UI", 18, "bold"))
    style.configure("Sub.TLabel", background=bg, foreground=muted, font=("Segoe UI", 10))
    style.configure("TCheckbutton", background=bg, foreground=fg)
    style.configure("TButton", font=("Segoe UI", 10, "bold"))
    style.configure("Accent.TButton", background=accent, foreground="#042018")

    install_var = tk.StringVar(value=str(default_install_dir()))
    server_var = tk.BooleanVar(value=True)
    agent_var = tk.BooleanVar(value=True)
    start_var = tk.BooleanVar(value=True)
    host_var = tk.StringVar(value="127.0.0.1")
    port_var = tk.StringVar(value="8787")
    agent_url_var = tk.StringVar(value="http://127.0.0.1:8787")
    status_var = tk.StringVar(value="Ready to install")
    page = tk.IntVar(value=0)

    container = ttk.Frame(root, padding=24)
    container.pack(fill="both", expand=True)

    pages: list[ttk.Frame] = []

    # Page 0 — Welcome
    p0 = ttk.Frame(container)
    ttk.Label(p0, text="AU Labs", style="Title.TLabel").pack(anchor="w")
    ttk.Label(p0, text="IT Management Setup", style="Title.TLabel").pack(anchor="w", pady=(0, 12))
    ttk.Label(
        p0,
        text="Install the Server panel and/or Agent on this computer.\n"
        "No Python, pip, or terminal commands required — like installing VLC.",
        style="Sub.TLabel",
        justify="left",
    ).pack(anchor="w")
    ttk.Label(p0, text=f"Version {APP_VERSION}", style="Sub.TLabel").pack(anchor="w", pady=(18, 0))
    pages.append(p0)

    # Page 1 — Components
    p1 = ttk.Frame(container)
    ttk.Label(p1, text="Choose components", style="Title.TLabel").pack(anchor="w", pady=(0, 12))
    ttk.Checkbutton(p1, text="AU Labs Server (web panel — users, storage, sessions, OS)", variable=server_var).pack(anchor="w", pady=4)
    ttk.Checkbutton(p1, text="AU Labs Agent (connects host to the panel)", variable=agent_var).pack(anchor="w", pady=4)
    ttk.Label(p1, text="Server bind host", style="Sub.TLabel").pack(anchor="w", pady=(16, 2))
    ttk.Entry(p1, textvariable=host_var, width=40).pack(anchor="w")
    ttk.Label(p1, text="Server port", style="Sub.TLabel").pack(anchor="w", pady=(8, 2))
    ttk.Entry(p1, textvariable=port_var, width=12).pack(anchor="w")
    ttk.Label(p1, text="Agent server URL", style="Sub.TLabel").pack(anchor="w", pady=(8, 2))
    ttk.Entry(p1, textvariable=agent_url_var, width=40).pack(anchor="w")
    pages.append(p1)

    # Page 2 — Location
    p2 = ttk.Frame(container)
    ttk.Label(p2, text="Installation folder", style="Title.TLabel").pack(anchor="w", pady=(0, 12))
    row = ttk.Frame(p2)
    row.pack(fill="x", anchor="w")
    ttk.Entry(row, textvariable=install_var, width=48).pack(side="left", fill="x", expand=True)

    def browse() -> None:
        chosen = filedialog.askdirectory(initialdir=install_var.get() or str(Path.home()))
        if chosen:
            install_var.set(chosen)

    ttk.Button(row, text="Browse…", command=browse).pack(side="left", padx=(8, 0))
    ttk.Checkbutton(p2, text="Start after installation and open the panel", variable=start_var).pack(anchor="w", pady=(18, 0))
    pages.append(p2)

    # Page 3 — Install progress / finish
    p3 = ttk.Frame(container)
    ttk.Label(p3, text="Installing", style="Title.TLabel").pack(anchor="w", pady=(0, 12))
    log_box = tk.Text(p3, height=14, bg="#041018", fg="#b7ecdf", insertbackground=fg, relief="flat", font=("Consolas", 9))
    log_box.pack(fill="both", expand=True)
    ttk.Label(p3, textvariable=status_var, style="Sub.TLabel").pack(anchor="w", pady=(8, 0))
    pages.append(p3)

    nav = ttk.Frame(container)
    nav.pack(side="bottom", fill="x", pady=(16, 0))

    def show_page(n: int) -> None:
        for i, fr in enumerate(pages):
            if i == n:
                fr.pack(fill="both", expand=True)
            else:
                fr.pack_forget()
        page.set(n)
        back_btn.configure(state=("normal" if n > 0 and n < 3 else "disabled"))
        if n >= 3:
            next_btn.configure(text="Finish", state="normal")
        elif n == 2:
            next_btn.configure(text="Install")
        else:
            next_btn.configure(text="Next")

    def append_log(msg: str) -> None:
        log_box.insert("end", msg + "\n")
        log_box.see("end")
        status_var.set(msg)
        root.update_idletasks()

    installing = {"done": False, "ok": False}

    def do_install() -> None:
        try:
            install_components(
                Path(install_var.get()),
                install_server=server_var.get(),
                install_agent=agent_var.get(),
                start_after=start_var.get(),
                server_host=host_var.get().strip() or "127.0.0.1",
                server_port=int(port_var.get() or "8787"),
                agent_server_url=agent_url_var.get().strip() or "http://127.0.0.1:8787",
                progress=lambda m: root.after(0, append_log, m),
            )
            installing["ok"] = True
            root.after(0, append_log, "You can close this setup window.")
            root.after(0, lambda: status_var.set("Installation complete"))
        except Exception as exc:
            installing["ok"] = False
            root.after(0, append_log, f"ERROR: {exc}")
            root.after(0, lambda: messagebox.showerror(APP_TITLE, str(exc)))
        finally:
            installing["done"] = True
            root.after(0, lambda: next_btn.configure(state="normal", text="Finish"))

    def on_next() -> None:
        n = page.get()
        if n < 2:
            if n == 1 and not (server_var.get() or agent_var.get()):
                messagebox.showwarning(APP_TITLE, "Select at least one component.")
                return
            show_page(n + 1)
            return
        if n == 2:
            show_page(3)
            next_btn.configure(state="disabled")
            back_btn.configure(state="disabled")
            log_box.delete("1.0", "end")
            threading.Thread(target=do_install, daemon=True).start()
            return
        root.destroy()

    def on_back() -> None:
        n = page.get()
        if n > 0 and n < 3:
            show_page(n - 1)

    back_btn = ttk.Button(nav, text="Back", command=on_back)
    back_btn.pack(side="left")
    ttk.Button(nav, text="Cancel", command=root.destroy).pack(side="right")
    next_btn = ttk.Button(nav, text="Next", command=on_next)
    next_btn.pack(side="right", padx=(0, 8))

    show_page(0)
    root.mainloop()
    return 0


def run_console(args: argparse.Namespace) -> int:
    def progress(msg: str) -> None:
        print(f"[setup] {msg}")

    result = install_components(
        Path(args.dir),
        install_server=args.server,
        install_agent=args.agent,
        start_after=args.start,
        server_host=args.host,
        server_port=args.port,
        agent_server_url=args.agent_url,
        progress=progress,
    )
    print(result)
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=APP_TITLE)
    parser.add_argument("--console", action="store_true", help="Run without GUI")
    parser.add_argument("--dir", default=str(default_install_dir()))
    parser.add_argument("--server", action="store_true", default=None)
    parser.add_argument("--no-server", action="store_true")
    parser.add_argument("--agent", action="store_true", default=None)
    parser.add_argument("--no-agent", action="store_true")
    parser.add_argument("--start", action="store_true", help="Start after install")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8787)
    parser.add_argument("--agent-url", default="http://127.0.0.1:8787")
    ns = parser.parse_args(argv)

    # Defaults: both components unless explicitly disabled
    install_server = True
    install_agent = True
    if ns.no_server:
        install_server = False
    if ns.no_agent:
        install_agent = False
    if ns.server:
        install_server = True
    if ns.agent:
        install_agent = True
    ns.server = install_server
    ns.agent = install_agent

    # Double-click / no meaningful args → GUI
    gui_requested = not ns.console and (
        argv is None
        or len(sys.argv) <= 1
        or (argv is not None and len(argv) == 0)
    )
    if gui_requested:
        try:
            return run_gui()
        except Exception as exc:
            print(f"GUI unavailable ({exc}); falling back to console setup.")
            ns.start = True
            return run_console(ns)

    return run_console(ns)

if __name__ == "__main__":
    raise SystemExit(main())
