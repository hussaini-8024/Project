"""Agent install paths, permanent Windows/Linux install, password-gated uninstall."""

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


def is_windows() -> bool:
    return platform.system().lower() == "windows"


def is_frozen() -> bool:
    return bool(getattr(sys, "frozen", False))


def current_executable() -> Path:
    if is_frozen():
        return Path(sys.executable).resolve()
    return Path(sys.argv[0]).resolve()


def install_root() -> Path:
    if is_windows():
        base = Path(os.environ.get("ProgramData") or r"C:\ProgramData")
    else:
        base = Path("/opt") if os.geteuid() == 0 else Path.home() / ".local" / "share"
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
        return install_root() / "DiscloseRMM-Agent.exe"
    return install_root() / "DiscloseRMM-Agent"


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
    return hashlib.sha256(f"DiscloseRMM-uninstall:{password}".encode("utf-8")).hexdigest()


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
    return hashlib.sha256(f"DiscloseRMM-uninstall:{password}".encode("utf-8")).hexdigest() == expected


def copy_self_to_install_dir() -> Path:
    dest = installed_agent_exe()
    src = current_executable()
    if is_frozen() and src.is_file():
        if src.resolve() != dest.resolve():
            shutil.copy2(src, dest)
        return dest

    # Source/dev install: write a launcher that invokes run_agent.py (no .exe yet)
    root = Path(__file__).resolve().parent.parent
    run_py = root / "run_agent.py"
    if is_windows():
        dest = install_root() / "DiscloseRMM-Agent.cmd"
        dest.write_text(
            f'@echo off\r\n"{sys.executable}" "{run_py}" %*\r\n',
            encoding="utf-8",
        )
        return dest

    dest = install_root() / "DiscloseRMM-Agent"
    dest.write_text(
        f"#!/bin/bash\nexec '{sys.executable}' '{run_py}' \"$@\"\n",
        encoding="utf-8",
    )
    dest.chmod(0o755)
    return dest


def _register_windows_autostart(exe: Path, server: str, token: str) -> None:
    # Scheduled task at startup (SYSTEM) — survives reboot; official uninstall required to remove
    tr = f'"{exe}" --run --server "{server}" --token "{token}"'
    # Delete existing quietly, then create
    subprocess.run(
        ["schtasks", "/Delete", "/TN", TASK_NAME, "/F"],
        capture_output=True,
        text=True,
    )
    create = subprocess.run(
        [
            "schtasks",
            "/Create",
            "/TN",
            TASK_NAME,
            "/TR",
            tr,
            "/SC",
            "ONSTART",
            "/RU",
            "SYSTEM",
            "/RL",
            "HIGHEST",
            "/F",
        ],
        capture_output=True,
        text=True,
    )
    if create.returncode != 0:
        # Fallback: current-user logon task
        subprocess.run(
            [
                "schtasks",
                "/Create",
                "/TN",
                TASK_NAME,
                "/TR",
                tr,
                "/SC",
                "ONLOGON",
                "/RL",
                "HIGHEST",
                "/F",
            ],
            capture_output=True,
            text=True,
            check=False,
        )
    # Start immediately
    subprocess.run(["schtasks", "/Run", "/TN", TASK_NAME], capture_output=True, text=True)


def _register_linux_autostart(exe: Path, server: str, token: str) -> None:
    unit = f"""[Unit]
Description=DiscloseRMM Agent
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
    system_unit = Path("/etc/systemd/system/disclosermm-agent.service")
    user_unit_dir = Path.home() / ".config" / "systemd" / "user"
    try:
        if os.geteuid() == 0:
            system_unit.write_text(unit, encoding="utf-8")
            subprocess.run(["systemctl", "daemon-reload"], check=False)
            subprocess.run(["systemctl", "enable", "--now", "disclosermm-agent.service"], check=False)
            return
    except Exception:
        pass
    user_unit_dir.mkdir(parents=True, exist_ok=True)
    user_unit = user_unit_dir / "disclosermm-agent.service"
    user_unit.write_text(user_unit_text, encoding="utf-8")
    subprocess.run(["systemctl", "--user", "daemon-reload"], check=False)
    subprocess.run(["systemctl", "--user", "enable", "--now", "disclosermm-agent.service"], check=False)


def install_agent(server: str, token: str, uninstall_password_hash: str = "") -> Path:
    """One-time permanent install. Copies binary, saves config, registers autostart."""
    exe = copy_self_to_install_dir()
    cfg = {
        "server": server.rstrip("/"),
        "token": token,
        "installed": True,
        "install_path": str(exe),
    }
    save_config(cfg)
    if uninstall_password_hash:
        set_uninstall_password_hash(uninstall_password_hash)
    if is_windows():
        _register_windows_autostart(exe, server.rstrip("/"), token)
    else:
        _register_linux_autostart(exe, server.rstrip("/"), token)
    return exe


def _remove_windows_autostart() -> None:
    subprocess.run(["schtasks", "/End", "/TN", TASK_NAME], capture_output=True, text=True)
    subprocess.run(["schtasks", "/Delete", "/TN", TASK_NAME, "/F"], capture_output=True, text=True)


def _remove_linux_autostart() -> None:
    subprocess.run(["systemctl", "disable", "--now", "disclosermm-agent.service"], check=False, capture_output=True)
    subprocess.run(["systemctl", "--user", "disable", "--now", "disclosermm-agent.service"], check=False, capture_output=True)
    Path("/etc/systemd/system/disclosermm-agent.service").unlink(missing_ok=True)
    (Path.home() / ".config" / "systemd" / "user" / "disclosermm-agent.service").unlink(missing_ok=True)


def uninstall_agent(password: str) -> None:
    """Remove permanent install only if uninstall password matches."""
    if not verify_uninstall_password(password):
        raise PermissionError(
            "Incorrect uninstall password. Ask your DiscloseRMM administrator."
        )
    if is_windows():
        _remove_windows_autostart()
    else:
        _remove_linux_autostart()
    root = install_root()
    # Remove files except we may be running from inside install dir
    for path in root.iterdir():
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
