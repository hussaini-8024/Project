"""Push AU-Kamra agent to a remote Windows PC using admin credentials."""

from __future__ import annotations

import platform
import subprocess
import time
from pathlib import Path

REMOTE_DIR_NAME = "AUKamraRemoteManager"
AGENT_EXE_NAME = "AU-Kamra-Remote-Manager-Agent.exe"
TASK_NAME = "AU-Kamra Remote Manager Agent Install"


def find_agent_binary() -> Path | None:
    """Locate Windows agent binary for remote push-install."""
    from server.agent_packages import find_agent_binary as _find

    return _find("windows")


def push_agent_windows(
    ip: str,
    username: str,
    password: str,
    server_url: str,
    enrollment_token: str,
    agent_exe: Path,
) -> tuple[bool, str]:
    """
    Copy agent via admin share and create a one-shot scheduled task.
    Requires Windows server host, admin share access, and firewall allowing SMB/RPC.
    """
    if platform.system().lower() != "windows":
        return (
            False,
            "Remote push install requires the Server running on Windows "
            "(admin shares + schtasks). Copy/download the agent manually otherwise.",
        )

    drive = "Z:"
    net_use = subprocess.run(
        ["net", "use", drive, rf"\\{ip}\ADMIN$", password, f"/user:{username}"],
        capture_output=True,
        text=True,
    )
    if net_use.returncode == 0:
        remote_dir_local = drive + rf"\{REMOTE_DIR_NAME}"
        remote_exe_unc = rf"\\{ip}\ADMIN$\{REMOTE_DIR_NAME}\{AGENT_EXE_NAME}"
    else:
        net_use = subprocess.run(
            ["net", "use", drive, rf"\\{ip}\C$", password, f"/user:{username}"],
            capture_output=True,
            text=True,
        )
        if net_use.returncode != 0:
            return False, f"Cannot connect to admin share: {net_use.stderr or net_use.stdout}"
        remote_dir_local = drive + rf"\ProgramData\{REMOTE_DIR_NAME}"
        remote_exe_unc = rf"\\{ip}\C$\ProgramData\{REMOTE_DIR_NAME}\{AGENT_EXE_NAME}"

    try:
        Path(remote_dir_local).mkdir(parents=True, exist_ok=True)
        dest = Path(remote_dir_local) / AGENT_EXE_NAME
        dest.write_bytes(agent_exe.read_bytes())
        cmd_path = Path(remote_dir_local) / "install_once.cmd"
        cmd_path.write_text(
            f'@echo off\r\n"{dest.name}" --install --server "{server_url}" --token "{enrollment_token}"\r\n',
            encoding="utf-8",
        )
        tr = f'"{remote_exe_unc}" --install --server "{server_url}" --token "{enrollment_token}"'
        create = subprocess.run(
            [
                "schtasks", "/Create", "/S", ip, "/U", username, "/P", password,
                "/TN", TASK_NAME, "/TR", tr, "/SC", "ONCE",
                "/ST", time.strftime("%H:%M", time.localtime(time.time() + 60)),
                "/RU", "SYSTEM", "/RL", "HIGHEST", "/F",
            ],
            capture_output=True,
            text=True,
        )
        if create.returncode != 0:
            wmic = subprocess.run(
                [
                    "wmic", "/node:" + ip, "/user:" + username, "/password:" + password,
                    "process", "call", "create", tr,
                ],
                capture_output=True,
                text=True,
            )
            if wmic.returncode != 0:
                return False, f"Copied agent but failed to start install: {create.stderr or wmic.stderr}"
            return True, "Agent copied; install started via WMI."
        run = subprocess.run(
            ["schtasks", "/Run", "/S", ip, "/U", username, "/P", password, "/TN", TASK_NAME],
            capture_output=True,
            text=True,
        )
        if run.returncode != 0:
            return True, f"Agent copied; task created but run returned: {run.stderr or run.stdout}"
        return True, "Agent copied and install task started on remote PC."
    except Exception as exc:
        return False, str(exc)
    finally:
        subprocess.run(["net", "use", drive, "/delete", "/y"], capture_output=True, text=True)
