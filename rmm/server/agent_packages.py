"""Multi-platform agent binaries and downloadable install packages."""

from __future__ import annotations

import io
import os
import platform
import shutil
import subprocess
import sys
import time
import zipfile
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent.parent
AGENTS_DIR = ROOT / "bin" / "agents"

PLATFORMS = ("windows", "macos", "linux")

# Preferred native binary names per platform
NATIVE_NAMES = {
    "windows": [
        "AU-Kamra-Remote-Manager-Agent.exe",
        "DiscloseRMM-Agent.exe",
    ],
    "macos": [
        "AU-Kamra-Remote-Manager-Agent",
        "AU-Kamra-Remote-Manager-Agent.app",
    ],
    "linux": [
        "AU-Kamra-Remote-Manager-Agent",
        "DiscloseRMM-Agent",
    ],
}

DOWNLOAD_FILENAMES = {
    "windows": "AU-Kamra-Remote-Manager-Agent-Windows.exe",
    "macos": "AU-Kamra-Remote-Manager-Agent-macOS",
    "linux": "AU-Kamra-Remote-Manager-Agent-Linux",
}

KIT_FILENAMES = {
    "windows": "AU-Kamra-Agent-Windows.zip",
    "macos": "AU-Kamra-Agent-macOS.zip",
    "linux": "AU-Kamra-Agent-Linux.zip",
}


def ensure_dirs() -> None:
    for p in PLATFORMS:
        (AGENTS_DIR / p).mkdir(parents=True, exist_ok=True)


def host_platform() -> str:
    system = platform.system().lower()
    if system == "windows":
        return "windows"
    if system == "darwin":
        return "macos"
    return "linux"


def platform_dir(plat: str) -> Path:
    ensure_dirs()
    return AGENTS_DIR / plat


def find_native_binary(plat: str) -> Path | None:
    """Find a native agent binary for the platform."""
    ensure_dirs()
    d = platform_dir(plat)
    for name in NATIVE_NAMES.get(plat, []):
        candidate = d / name
        if candidate.is_file():
            return candidate
        # .app bundle on mac
        if candidate.is_dir() and plat == "macos":
            return candidate

    # Legacy / dist / bin roots (mainly Windows)
    legacy = [
        ROOT / "dist" / "AU-Kamra-Remote-Manager-Agent.exe",
        ROOT / "dist" / "DiscloseRMM-Agent.exe",
        ROOT / "bin" / "AU-Kamra-Remote-Manager-Agent.exe",
        ROOT / "bin" / "DiscloseRMM-Agent.exe",
        ROOT / "dist" / "AU-Kamra-Remote-Manager-Agent",
        ROOT / "bin" / "AU-Kamra-Remote-Manager-Agent",
    ]
    if plat == host_platform():
        for c in legacy:
            if c.is_file():
                # Mirror into platform folder for consistent downloads
                dest = d / c.name
                if not dest.exists():
                    try:
                        shutil.copy2(c, dest)
                    except Exception:
                        return c
                return dest
    env = os.environ.get("RMM_AGENT_EXE", "")
    if env and Path(env).is_file() and plat == host_platform():
        return Path(env)
    return None


def find_agent_binary(plat: str | None = None) -> Path | None:
    """Backward-compatible helper; defaults to Windows then host."""
    order = [plat] if plat else ["windows", host_platform(), "linux", "macos"]
    seen = set()
    for p in order:
        if not p or p in seen:
            continue
        seen.add(p)
        found = find_native_binary(p)
        if found:
            return found
    return None


def save_uploaded_binary(plat: str, filename: str, data: bytes) -> Path:
    if plat not in PLATFORMS:
        raise ValueError("platform must be windows, macos, or linux")
    ensure_dirs()
    # Normalize storage name
    if plat == "windows":
        name = "AU-Kamra-Remote-Manager-Agent.exe"
    else:
        name = "AU-Kamra-Remote-Manager-Agent"
    dest = platform_dir(plat) / name
    dest.write_bytes(data)
    if plat != "windows":
        dest.chmod(0o755)
    # Keep original name copy too if different
    if filename and Path(filename).name != name:
        (platform_dir(plat) / Path(filename).name).write_bytes(data)
    return dest


def _agent_source_files() -> list[tuple[str, Path]]:
    """Files to include in portable kits."""
    files: list[tuple[str, Path]] = []
    for rel in (
        "run_agent.py",
        "requirements.txt",
        "agent/__init__.py",
        "agent/agent.py",
        "agent/discovery.py",
        "agent/install_service.py",
        "shared/__init__.py",
        "shared/config.py",
        "shared/protocol.py",
    ):
        path = ROOT / rel
        if path.is_file():
            files.append((rel.replace("\\", "/"), path))
    return files


def _install_script_windows(server_placeholder: str = "http://SERVER_IP:8443") -> str:
    return f"""@echo off
setlocal
cd /d "%~dp0"
echo AU-Kamra IT Experts Remote Manager — Windows Agent Install
echo.
if exist "AU-Kamra-Remote-Manager-Agent.exe" (
  echo Installing native agent...
  AU-Kamra-Remote-Manager-Agent.exe --install --server "%~1" --token "%~2"
  if errorlevel 1 exit /b 1
  echo Done.
  exit /b 0
)
echo Native .exe not in this package. Use the admin panel Download for Windows,
echo or run: build\\build_windows.bat on a Windows build PC and upload the agent.
exit /b 1
"""


def _install_script_unix(os_label: str) -> str:
    return f"""#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
SERVER="${{1:-}}"
TOKEN="${{2:-}}"
if [[ -z "$SERVER" || -z "$TOKEN" ]]; then
  echo "Usage: ./install.sh <server_url> <enrollment_token>"
  echo "Example: ./install.sh http://192.168.1.10:8443 enroll-change-me"
  exit 1
fi
echo "AU-Kamra IT Experts Remote Manager — {os_label} Agent Install"
if [[ -x "./AU-Kamra-Remote-Manager-Agent" ]]; then
  echo "Installing native agent binary..."
  ./AU-Kamra-Remote-Manager-Agent --install --server "$SERVER" --token "$TOKEN"
  echo "Done."
  exit 0
fi
# Fallback: Python source install (requires python3)
if ! command -v python3 >/dev/null 2>&1; then
  echo "No native agent binary and python3 not found."
  echo "Download the native agent from the admin panel for {os_label}."
  exit 1
fi
python3 -m venv .venv
# shellcheck disable=SC1091
source .venv/bin/activate
pip install -q -r requirements.txt
python run_agent.py --install --server "$SERVER" --token "$TOKEN"
echo "Done."
"""


def build_kit_zip(plat: str) -> Path:
    """Create/update a downloadable zip kit for the platform."""
    if plat not in PLATFORMS:
        raise ValueError("invalid platform")
    ensure_dirs()
    out = platform_dir(plat) / KIT_FILENAMES[plat]
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
        native = find_native_binary(plat)
        if native and native.is_file():
            arc = NATIVE_NAMES[plat][0]
            zf.write(native, arcname=arc)

        for arc, path in _agent_source_files():
            zf.write(path, arcname=f"src/{arc}")

        if plat == "windows":
            zf.writestr("install.bat", _install_script_windows())
            zf.writestr(
                "README.txt",
                "AU-Kamra Agent — Windows\r\n\r\n"
                "1) Prefer: run AU-Kamra-Remote-Manager-Agent.exe as Administrator\r\n"
                "   AU-Kamra-Remote-Manager-Agent.exe --install --server http://SERVER:8443 --token TOKEN\r\n"
                "2) Or: install.bat http://SERVER:8443 TOKEN\r\n",
            )
        else:
            script = _install_script_unix("macOS" if plat == "macos" else "Linux")
            zf.writestr("install.sh", script)
            # Flat layout for install.sh (avoid duplicating src/ entries)
            written = set()
            for arc, path in _agent_source_files():
                if arc in written:
                    continue
                zf.write(path, arcname=arc)
                written.add(arc)
            zf.writestr(
                "README.txt",
                f"AU-Kamra Agent — {'macOS' if plat == 'macos' else 'Linux'}\n\n"
                "chmod +x install.sh AU-Kamra-Remote-Manager-Agent 2>/dev/null\n"
                "./install.sh http://SERVER:8443 TOKEN\n"
                "Or run the native binary with --install\n",
            )
    out.write_bytes(buf.getvalue())
    return out


def generate_all_packages(try_native_build: bool = True) -> dict[str, Any]:
    """
    Generate download kits for windows/macos/linux.
    Optionally build a native agent for the host OS via PyInstaller.
    """
    ensure_dirs()
    result: dict[str, Any] = {"built_native": None, "kits": {}, "errors": []}

    if try_native_build:
        hp = host_platform()
        try:
            native_path = _try_pyinstaller_build(hp)
            if native_path:
                result["built_native"] = {"platform": hp, "path": str(native_path)}
        except Exception as exc:
            result["errors"].append(f"native build ({hp}): {exc}")

    for plat in PLATFORMS:
        try:
            kit = build_kit_zip(plat)
            native = find_native_binary(plat)
            result["kits"][plat] = {
                "kit": str(kit),
                "kit_size": kit.stat().st_size,
                "native": str(native) if native else None,
                "native_size": native.stat().st_size if native and native.is_file() else 0,
            }
        except Exception as exc:
            result["errors"].append(f"kit ({plat}): {exc}")
    return result


def _try_pyinstaller_build(plat: str) -> Path | None:
    """Build agent for current host OS into bin/agents/<plat>/."""
    if plat != host_platform():
        return None
    # Need pyinstaller
    try:
        import PyInstaller  # noqa: F401
    except ImportError:
        subprocess.run(
            [sys.executable, "-m", "pip", "install", "-q", "pyinstaller"],
            check=False,
            capture_output=True,
        )

    work = ROOT / "build" / f"gen-{plat}-{int(time.time())}"
    work.mkdir(parents=True, exist_ok=True)
    name = "AU-Kamra-Remote-Manager-Agent"
    cmd = [
        sys.executable,
        "-m",
        "PyInstaller",
        "--noconfirm",
        "--clean",
        "--onefile",
        "--name",
        name,
        "--distpath",
        str(platform_dir(plat)),
        "--workpath",
        str(work / "work"),
        "--specpath",
        str(work),
        str(ROOT / "run_agent.py"),
        "--paths",
        str(ROOT),
        "--hidden-import",
        "agent.discovery",
        "--hidden-import",
        "agent.install_service",
        "--hidden-import",
        "shared.config",
        "--hidden-import",
        "shared.protocol",
        "--hidden-import",
        "websocket",
        "--hidden-import",
        "requests",
    ]
    if plat == "windows":
        cmd.extend(["--hidden-import", "mss", "--hidden-import", "PIL"])
    else:
        cmd.extend(["--hidden-import", "mss", "--hidden-import", "PIL"])

    proc = subprocess.run(cmd, cwd=str(ROOT), capture_output=True, text=True, timeout=600)
    if proc.returncode != 0:
        raise RuntimeError((proc.stderr or proc.stdout or "pyinstaller failed")[-2000:])

    out = platform_dir(plat) / (name + (".exe" if plat == "windows" else ""))
    if out.is_file():
        if plat != "windows":
            out.chmod(0o755)
        return out
    # mac may not add extension
    alt = platform_dir(plat) / name
    if alt.is_file():
        alt.chmod(0o755)
        return alt
    return None


def list_platform_status() -> list[dict[str, Any]]:
    ensure_dirs()
    rows = []
    labels = {"windows": "Windows", "macos": "macOS", "linux": "Linux"}
    for plat in PLATFORMS:
        native = find_native_binary(plat)
        kit = platform_dir(plat) / KIT_FILENAMES[plat]
        rows.append(
            {
                "platform": plat,
                "label": labels[plat],
                "native_available": bool(native and (native.is_file() or native.is_dir())),
                "native_path": str(native) if native else "",
                "native_size": native.stat().st_size if native and native.is_file() else 0,
                "kit_available": kit.is_file(),
                "kit_path": str(kit) if kit.is_file() else "",
                "kit_size": kit.stat().st_size if kit.is_file() else 0,
                "download_name": DOWNLOAD_FILENAMES[plat] if native else KIT_FILENAMES[plat],
                "preferred": "native" if native else ("kit" if kit.is_file() else "missing"),
            }
        )
    return rows


def resolve_download(plat: str, prefer: str = "auto") -> tuple[Path, str]:
    """
    Return (path, download_filename) for a platform.
    prefer: auto|native|kit
    """
    if plat not in PLATFORMS:
        raise ValueError("invalid platform")
    native = find_native_binary(plat)
    kit = platform_dir(plat) / KIT_FILENAMES[plat]

    if prefer == "kit":
        if not kit.is_file():
            kit = build_kit_zip(plat)
        return kit, KIT_FILENAMES[plat]

    if prefer == "native":
        if not native:
            raise FileNotFoundError(f"No native agent for {plat}")
        return native, DOWNLOAD_FILENAMES[plat]

    # auto: native first, else kit (generate if needed)
    if native and native.is_file():
        return native, DOWNLOAD_FILENAMES[plat]
    if not kit.is_file():
        kit = build_kit_zip(plat)
    return kit, KIT_FILENAMES[plat]
