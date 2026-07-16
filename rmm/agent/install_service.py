"""Agent install paths, permanent Windows/macOS/Linux install, password-gated uninstall."""

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
PRODUCT_DIR_NAME = "AUKamraRemoteManager"
LAUNCH_AGENT_LABEL = "com.aukamra.remotemanager.agent"
LINUX_UNIT = "aukamra-agent.service"


def is_windows() -> bool:
    return platform.system().lower() == "windows"


def is_macos() -> bool:
    return platform.system().lower() == "darwin"


def is_linux() -> bool:
    return platform.system().lower() == "linux"


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

    # Source/dev install: write a launcher that invokes run_agent.py
    root = Path(__file__).resolve().parent.parent
    run_py = root / "run_agent.py"
    if is_windows():
        dest = install_root() / "AU-Kamra-Remote-Manager-Agent.cmd"
        dest.write_text(
            f'@echo off\r\n"{sys.executable}" "{run_py}" %*\r\n',
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


def _register_windows_autostart(exe: Path, server: str, token: str) -> None:
    tr = f'"{exe}" --run --server "{server}" --token "{token}"'
    subprocess.run(
        ["schtasks", "/Delete", "/TN", TASK_NAME, "/F"],
        capture_output=True,
        text=True,
    )
    create = subprocess.run(
        [
            "schtasks", "/Create", "/TN", TASK_NAME, "/TR", tr,
            "/SC", "ONSTART", "/RU", "SYSTEM", "/RL", "HIGHEST", "/F",
        ],
        capture_output=True,
        text=True,
    )
    if create.returncode != 0:
        subprocess.run(
            [
                "schtasks", "/Create", "/TN", TASK_NAME, "/TR", tr,
                "/SC", "ONLOGON", "/RL", "HIGHEST", "/F",
            ],
            capture_output=True,
            text=True,
            check=False,
        )
    subprocess.run(["schtasks", "/Run", "/TN", TASK_NAME], capture_output=True, text=True)


def _register_macos_autostart(exe: Path, server: str, token: str) -> None:
    launch_dir = Path.home() / "Library" / "LaunchAgents"
    launch_dir.mkdir(parents=True, exist_ok=True)
    plist_path = launch_dir / f"{LAUNCH_AGENT_LABEL}.plist"
    log_dir = install_root() / "logs"
    log_dir.mkdir(parents=True, exist_ok=True)
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
  <key>StandardOutPath</key><string>{log_dir / "agent.out.log"}</string>
  <key>StandardErrorPath</key><string>{log_dir / "agent.err.log"}</string>
</dict>
</plist>
"""
    plist_path.write_text(plist, encoding="utf-8")
    subprocess.run(["launchctl", "unload", str(plist_path)], capture_output=True, text=True)
    subprocess.run(["launchctl", "load", str(plist_path)], capture_output=True, text=True)
    subprocess.run(["launchctl", "start", LAUNCH_AGENT_LABEL], capture_output=True, text=True)


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
            return
    except Exception:
        pass
    user_unit_dir.mkdir(parents=True, exist_ok=True)
    user_unit = user_unit_dir / LINUX_UNIT
    user_unit.write_text(user_unit_text, encoding="utf-8")
    subprocess.run(["systemctl", "--user", "daemon-reload"], check=False)
    subprocess.run(["systemctl", "--user", "enable", "--now", LINUX_UNIT], check=False)


def install_agent(server: str, token: str, uninstall_password_hash: str = "") -> Path:
    """One-time permanent install. Copies binary, saves config, registers autostart."""
    exe = copy_self_to_install_dir()
    cfg = {
        "server": server.rstrip("/"),
        "token": token,
        "installed": True,
        "install_path": str(exe),
        "os": platform.system(),
    }
    save_config(cfg)
    if uninstall_password_hash:
        set_uninstall_password_hash(uninstall_password_hash)
    server = server.rstrip("/")
    if is_windows():
        _register_windows_autostart(exe, server, token)
    elif is_macos():
        _register_macos_autostart(exe, server, token)
    else:
        _register_linux_autostart(exe, server, token)
    return exe


def _remove_windows_autostart() -> None:
    subprocess.run(["schtasks", "/End", "/TN", TASK_NAME], capture_output=True, text=True)
    subprocess.run(["schtasks", "/Delete", "/TN", TASK_NAME, "/F"], capture_output=True, text=True)


def _remove_macos_autostart() -> None:
    plist_path = Path.home() / "Library" / "LaunchAgents" / f"{LAUNCH_AGENT_LABEL}.plist"
    subprocess.run(["launchctl", "stop", LAUNCH_AGENT_LABEL], capture_output=True, text=True)
    subprocess.run(["launchctl", "unload", str(plist_path)], capture_output=True, text=True)
    plist_path.unlink(missing_ok=True)


def _remove_linux_autostart() -> None:
    subprocess.run(["systemctl", "disable", "--now", LINUX_UNIT], check=False, capture_output=True)
    subprocess.run(["systemctl", "--user", "disable", "--now", LINUX_UNIT], check=False, capture_output=True)
    # legacy unit name cleanup
    for name in (LINUX_UNIT, "disclosermm-agent.service"):
        Path(f"/etc/systemd/system/{name}").unlink(missing_ok=True)
        (Path.home() / ".config" / "systemd" / "user" / name).unlink(missing_ok=True)


def uninstall_agent(password: str) -> None:
    """Remove permanent install only if uninstall password matches."""
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
