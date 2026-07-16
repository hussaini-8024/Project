"""Agent install paths — permanent background install (Windows/macOS/Linux)."""

from __future__ import annotations

import hashlib
import json
import os
import platform
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Any

SERVICE_NAME = "AUKamraRemoteManagerAgent"
TASK_NAME = "AU-Kamra Remote Manager Agent"
TASK_NAME_LOGON = "AU-Kamra Remote Manager Agent Logon"
PRODUCT_DIR_NAME = "AUKamraRemoteManager"
LAUNCH_AGENT_LABEL = "com.aukamra.remotemanager.agent"
LINUX_UNIT = "aukamra-agent.service"
RUN_KEY = r"Software\Microsoft\Windows\CurrentVersion\Run"
RUN_VALUE = "AUKamraRemoteManagerAgent"


def is_windows() -> bool:
    return platform.system().lower() == "windows"


def is_macos() -> bool:
    return platform.system().lower() == "darwin"


def is_frozen() -> bool:
    return bool(getattr(sys, "frozen", False))


def current_executable() -> Path:
    if is_frozen():
        return Path(sys.executable).resolve()
    return Path(sys.argv[0]).resolve()


def install_root() -> Path:
    if is_windows():
        base = Path(os.environ.get("ProgramData") or r"C:\ProgramData")
    elif is_macos():
        base = Path.home() / "Library" / "Application Support"
    else:
        try:
            root_user = os.geteuid() == 0
        except AttributeError:
            root_user = False
        base = Path("/opt") if root_user else Path.home() / ".local" / "share"
    root = base / PRODUCT_DIR_NAME
    root.mkdir(parents=True, exist_ok=True)
    return root


def config_path() -> Path:
    return install_root() / "agent_config.json"


def uninstall_hash_path() -> Path:
    return install_root() / "uninstall.hash"


def agent_id_path() -> Path:
    return install_root() / "agent_id.txt"


def log_path() -> Path:
    d = install_root() / "logs"
    d.mkdir(parents=True, exist_ok=True)
    return d / "agent.log"


def installed_agent_exe() -> Path:
    if is_windows():
        return install_root() / "AU-Kamra-Remote-Manager-Agent.exe"
    return install_root() / "AU-Kamra-Remote-Manager-Agent"


def load_config() -> dict[str, Any]:
    path = config_path()
    if not path.is_file():
        return {}
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return {}


def save_config(cfg: dict[str, Any]) -> None:
    config_path().write_text(json.dumps(cfg, indent=2), encoding="utf-8")


def hash_uninstall_password(password: str) -> str:
    return hashlib.sha256(f"AUKamra-uninstall:{password}".encode("utf-8")).hexdigest()


def set_uninstall_password_hash(password_hash: str) -> None:
    uninstall_hash_path().write_text(password_hash.strip(), encoding="utf-8")


def get_uninstall_password_hash() -> str:
    path = uninstall_hash_path()
    if path.is_file():
        return path.read_text(encoding="utf-8").strip()
    return ""


def verify_uninstall_password(password: str) -> bool:
    expected = get_uninstall_password_hash()
    if not expected:
        return False
    return hash_uninstall_password(password) == expected


def copy_self_to_install_dir() -> Path:
    dest = installed_agent_exe()
    src = current_executable()
    if is_frozen() and src.is_file():
        if src.resolve() != dest.resolve():
            shutil.copy2(src, dest)
        if not is_windows():
            dest.chmod(0o755)
        return dest

    root = Path(__file__).resolve().parent.parent
    run_py = root / "run_agent.py"
    if is_windows():
        dest = install_root() / "AU-Kamra-Remote-Manager-Agent.cmd"
        dest.write_text(
            f'@echo off\r\nstart "" /B "{sys.executable}" "{run_py}" %*\r\n',
            encoding="utf-8",
        )
        return dest

    dest = install_root() / "AU-Kamra-Remote-Manager-Agent"
    dest.write_text(
        f"#!/bin/bash\nexec '{sys.executable}' '{run_py}' \"$@\"\n",
        encoding="utf-8",
    )
    dest.chmod(0o755)
    return dest


def _windows_run_command(exe: Path, server: str, token: str) -> str:
    return f'"{exe}" --run --server "{server}" --token "{token}"'


def _register_windows_run_key(exe: Path, server: str, token: str) -> None:
    """Current-user Run key so agent starts even without admin scheduled-task rights."""
    try:
        import winreg  # type: ignore

        cmd = _windows_run_command(exe, server, token)
        key = winreg.CreateKey(winreg.HKEY_CURRENT_USER, RUN_KEY)
        winreg.SetValueEx(key, RUN_VALUE, 0, winreg.REG_SZ, cmd)
        winreg.CloseKey(key)
    except Exception:
        pass


def _unregister_windows_run_key() -> None:
    try:
        import winreg  # type: ignore

        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY, 0, winreg.KEY_SET_VALUE)
        try:
            winreg.DeleteValue(key, RUN_VALUE)
        except FileNotFoundError:
            pass
        winreg.CloseKey(key)
    except Exception:
        pass


def start_background_agent(exe: Path, server: str, token: str) -> None:
    """
    Start the agent as a detached background process.
    Closing any installer/dialog must NOT kill this process.
    """
    args = [str(exe), "--run", "--server", server, "--token", token]
    log = log_path()
    if is_windows():
        # Also kick scheduled tasks if present
        subprocess.run(["schtasks", "/Run", "/TN", TASK_NAME], capture_output=True, text=True)
        subprocess.run(["schtasks", "/Run", "/TN", TASK_NAME_LOGON], capture_output=True, text=True)
        flags = 0
        flags |= getattr(subprocess, "DETACHED_PROCESS", 0x00000008)
        flags |= getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0x00000200)
        flags |= getattr(subprocess, "CREATE_NO_WINDOW", 0x08000000)
        with open(log, "a", encoding="utf-8") as lf:
            subprocess.Popen(
                args,
                cwd=str(install_root()),
                stdin=subprocess.DEVNULL,
                stdout=lf,
                stderr=lf,
                creationflags=flags,
                close_fds=True,
            )
        return

    # POSIX: fully detach
    with open(log, "a", encoding="utf-8") as lf:
        subprocess.Popen(
            args,
            cwd=str(install_root()),
            stdin=subprocess.DEVNULL,
            stdout=lf,
            stderr=lf,
            start_new_session=True,
            close_fds=True,
        )


def _register_windows_autostart(exe: Path, server: str, token: str) -> None:
    tr = _windows_run_command(exe, server, token)
    for name in (TASK_NAME, TASK_NAME_LOGON):
        subprocess.run(["schtasks", "/Delete", "/TN", name, "/F"], capture_output=True, text=True)

    # Boot (SYSTEM) — best permanence
    subprocess.run(
        [
            "schtasks", "/Create", "/TN", TASK_NAME, "/TR", tr,
            "/SC", "ONSTART", "/RU", "SYSTEM", "/RL", "HIGHEST", "/F",
        ],
        capture_output=True,
        text=True,
    )
    # User logon fallback (works without elevating to SYSTEM)
    subprocess.run(
        [
            "schtasks", "/Create", "/TN", TASK_NAME_LOGON, "/TR", tr,
            "/SC", "ONLOGON", "/RL", "HIGHEST", "/F",
        ],
        capture_output=True,
        text=True,
    )
    _register_windows_run_key(exe, server, token)
    start_background_agent(exe, server, token)


def _register_macos_autostart(exe: Path, server: str, token: str) -> None:
    launch_dir = Path.home() / "Library" / "LaunchAgents"
    launch_dir.mkdir(parents=True, exist_ok=True)
    plist_path = launch_dir / f"{LAUNCH_AGENT_LABEL}.plist"
    logs = install_root() / "logs"
    logs.mkdir(parents=True, exist_ok=True)
    plist = f"""<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key><string>{LAUNCH_AGENT_LABEL}</string>
  <key>ProgramArguments</key>
  <array>
    <string>{exe}</string>
    <string>--run</string>
    <string>--server</string>
    <string>{server}</string>
    <string>--token</string>
    <string>{token}</string>
  </array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><true/>
  <key>WorkingDirectory</key><string>{install_root()}</string>
  <key>StandardOutPath</key><string>{logs / "agent.out.log"}</string>
  <key>StandardErrorPath</key><string>{logs / "agent.err.log"}</string>
</dict>
</plist>
"""
    plist_path.write_text(plist, encoding="utf-8")
    subprocess.run(["launchctl", "unload", str(plist_path)], capture_output=True, text=True)
    subprocess.run(["launchctl", "load", str(plist_path)], capture_output=True, text=True)
    subprocess.run(["launchctl", "start", LAUNCH_AGENT_LABEL], capture_output=True, text=True)
    start_background_agent(exe, server, token)


def _register_linux_autostart(exe: Path, server: str, token: str) -> None:
    unit = f"""[Unit]
Description=AU-Kamra IT Experts Remote Manager Agent
After=network-online.target

[Service]
Type=simple
ExecStart={exe} --run --server {server} --token {token}
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
"""
    user_unit_text = unit.replace("WantedBy=multi-user.target", "WantedBy=default.target")
    system_unit = Path(f"/etc/systemd/system/{LINUX_UNIT}")
    user_unit_dir = Path.home() / ".config" / "systemd" / "user"
    try:
        if os.geteuid() == 0:
            system_unit.write_text(unit, encoding="utf-8")
            subprocess.run(["systemctl", "daemon-reload"], check=False)
            subprocess.run(["systemctl", "enable", "--now", LINUX_UNIT], check=False)
            start_background_agent(exe, server, token)
            return
    except Exception:
        pass
    user_unit_dir.mkdir(parents=True, exist_ok=True)
    (user_unit_dir / LINUX_UNIT).write_text(user_unit_text, encoding="utf-8")
    subprocess.run(["systemctl", "--user", "daemon-reload"], check=False)
    subprocess.run(["systemctl", "--user", "enable", "--now", LINUX_UNIT], check=False)
    start_background_agent(exe, server, token)


def install_agent(server: str, token: str, uninstall_password_hash: str = "") -> Path:
    """One-time permanent install. Starts background agent and registers reboot autostart."""
    exe = copy_self_to_install_dir()
    server = server.rstrip("/")
    cfg = {
        "server": server,
        "token": token,
        "installed": True,
        "install_path": str(exe),
        "os": platform.system(),
        "background": True,
    }
    save_config(cfg)
    if uninstall_password_hash:
        set_uninstall_password_hash(uninstall_password_hash)
    if is_windows():
        _register_windows_autostart(exe, server, token)
    elif is_macos():
        _register_macos_autostart(exe, server, token)
    else:
        _register_linux_autostart(exe, server, token)
    return exe


def ensure_running(server: str | None = None, token: str | None = None) -> None:
    """If already installed, make sure background agent is running."""
    cfg = load_config()
    server = (server or cfg.get("server") or "").rstrip("/")
    token = token or cfg.get("token") or ""
    exe = installed_agent_exe()
    if not exe.is_file() or not server or not token:
        return
    start_background_agent(exe, server, token)


def _remove_windows_autostart() -> None:
    for name in (TASK_NAME, TASK_NAME_LOGON):
        subprocess.run(["schtasks", "/End", "/TN", name], capture_output=True, text=True)
        subprocess.run(["schtasks", "/Delete", "/TN", name, "/F"], capture_output=True, text=True)
    _unregister_windows_run_key()
    # Best-effort kill background agent processes for this product
    try:
        subprocess.run(
            ["taskkill", "/F", "/IM", "AU-Kamra-Remote-Manager-Agent.exe"],
            capture_output=True,
            text=True,
        )
    except Exception:
        pass


def _remove_macos_autostart() -> None:
    plist_path = Path.home() / "Library" / "LaunchAgents" / f"{LAUNCH_AGENT_LABEL}.plist"
    subprocess.run(["launchctl", "stop", LAUNCH_AGENT_LABEL], capture_output=True, text=True)
    subprocess.run(["launchctl", "unload", str(plist_path)], capture_output=True, text=True)
    plist_path.unlink(missing_ok=True)


def _remove_linux_autostart() -> None:
    subprocess.run(["systemctl", "disable", "--now", LINUX_UNIT], check=False, capture_output=True)
    subprocess.run(["systemctl", "--user", "disable", "--now", LINUX_UNIT], check=False, capture_output=True)
    for name in (LINUX_UNIT, "disclosermm-agent.service"):
        Path(f"/etc/systemd/system/{name}").unlink(missing_ok=True)
        (Path.home() / ".config" / "systemd" / "user" / name).unlink(missing_ok=True)


def uninstall_agent(password: str) -> None:
    if not verify_uninstall_password(password):
        raise PermissionError(
            "Incorrect uninstall password. Ask your AU-Kamra administrator."
        )
    if is_windows():
        _remove_windows_autostart()
    elif is_macos():
        _remove_macos_autostart()
    else:
        _remove_linux_autostart()
    root = install_root()
    for path in list(root.iterdir()):
        try:
            if path.is_file():
                path.unlink(missing_ok=True)
            elif path.is_dir():
                shutil.rmtree(path, ignore_errors=True)
        except Exception:
            pass
    try:
        root.rmdir()
    except Exception:
        pass


def is_installed() -> bool:
    cfg = load_config()
    return bool(cfg.get("installed")) and installed_agent_exe().is_file()
