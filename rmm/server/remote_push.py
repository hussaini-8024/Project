"""Push DiscloseRMM-Agent.exe to a remote Windows PC using admin credentials."""

from __future__ import annotations

import os
import platform
import subprocess
import time
from pathlib import Path


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
    Copy agent to \\\\IP\\ADMIN$\\DiscloseRMM and create a startup scheduled task.
    Requires Windows server host, admin share access, and firewall allowing SMB/RPC.
    """
    if platform.system().lower() != "windows":
        return (
            False,
            "Remote push install requires DiscloseRMM-Server.exe running on Windows "
            "(uses admin shares + schtasks). On this host, copy the agent manually or use discovery after local install.",
        )

    remote_dir = rf"\\{ip}\ADMIN$\DiscloseRMM"
    remote_exe = remote_dir + r"\DiscloseRMM-Agent.exe"
    # Map admin share
    drive = "Z:"
    net_use = subprocess.run(
        ["net", "use", drive, rf"\\{ip}\ADMIN$", password, f"/user:{username}"],
        capture_output=True,
        text=True,
    )
    if net_use.returncode != 0:
        # try IPC$ / C$
        net_use = subprocess.run(
            ["net", "use", drive, rf"\\{ip}\C$", password, f"/user:{username}"],
            capture_output=True,
            text=True,
        )
        if net_use.returncode != 0:
            return False, f"Cannot connect to admin share: {net_use.stderr or net_use.stdout}"
        remote_dir_local = drive + r"\ProgramData\DiscloseRMM"
        remote_exe_unc = rf"\\{ip}\C$\ProgramData\DiscloseRMM\DiscloseRMM-Agent.exe"
    else:
        remote_dir_local = drive + r"\DiscloseRMM"
        remote_exe_unc = remote_exe

    try:
        Path(remote_dir_local).mkdir(parents=True, exist_ok=True)
        dest = Path(remote_dir_local) / "DiscloseRMM-Agent.exe"
        dest.write_bytes(agent_exe.read_bytes())
        # Write a tiny install launcher cmd
        cmd_path = Path(remote_dir_local) / "install_once.cmd"
        cmd_path.write_text(
            f'@echo off\r\n"{dest.name}" --install --server "{server_url}" --token "{enrollment_token}"\r\n',
            encoding="utf-8",
        )
        # Create remote scheduled task to run install once
        tr = f'"{remote_exe_unc}" --install --server "{server_url}" --token "{enrollment_token}"'
        create = subprocess.run(
            [
                "schtasks",
                "/Create",
                "/S",
                ip,
                "/U",
                username,
                "/P",
                password,
                "/TN",
                "DiscloseRMM Agent Install",
                "/TR",
                tr,
                "/SC",
                "ONCE",
                "/ST",
                time.strftime("%H:%M", time.localtime(time.time() + 60)),
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
            # Fallback: run via wmic process call create
            wmic = subprocess.run(
                [
                    "wmic",
                    "/node:" + ip,
                    "/user:" + username,
                    "/password:" + password,
                    "process",
                    "call",
                    "create",
                    tr,
                ],
                capture_output=True,
                text=True,
            )
            if wmic.returncode != 0:
                return False, f"Copied agent but failed to start install: {create.stderr or wmic.stderr}"
            return True, "Agent copied; install started via WMI."
        run = subprocess.run(
            [
                "schtasks",
                "/Run",
                "/S",
                ip,
                "/U",
                username,
                "/P",
                password,
                "/TN",
                "DiscloseRMM Agent Install",
            ],
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


def write_agent_stub_for_dev(dest: Path) -> Path:
    """When no .exe exists yet, note path for admin (dev helper)."""
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(
        "Place DiscloseRMM-Agent.exe here after building with build\\build_windows.bat\n",
        encoding="utf-8",
    )
    return dest
