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

try:
    from server.paths import is_frozen, resource_root, writable_root
except Exception:  # pragma: no cover - early import / tests
    def is_frozen() -> bool:
        return bool(getattr(sys, "frozen", False))

    def resource_root() -> Path:
        return Path(__file__).resolve().parent.parent

    def writable_root() -> Path:
        return Path(__file__).resolve().parent.parent


def project_root() -> Path:
    """Read-only source/bundle root (may be inside _MEIPASS when frozen)."""
    return resource_root()


def agents_dir() -> Path:
    return writable_root() / "bin" / "agents"


# Back-compat
ROOT = resource_root()

PLATFORMS = ("windows", "macos", "linux")

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
        (agents_dir() / p).mkdir(parents=True, exist_ok=True)


def host_platform() -> str:
    system = platform.system().lower()
    if system == "windows":
        return "windows"
    if system == "darwin":
        return "macos"
    return "linux"


def platform_dir(plat: str) -> Path:
    ensure_dirs()
    return agents_dir() / plat


def _meipass() -> Path | None:
    mp = getattr(sys, "_MEIPASS", None)
    return Path(mp) if mp else None


def _server_dir() -> Path:
    return writable_root()


def _candidate_native_paths(plat: str) -> list[Path]:
    """All places we may find a prebuilt agent for plat."""
    names = NATIVE_NAMES.get(plat, [])
    paths: list[Path] = []
    d = platform_dir(plat)
    for name in names:
        paths.append(d / name)

    # Bundled inside frozen server (_MEIPASS)
    mp = _meipass()
    if mp:
        for name in names:
            paths.append(mp / "bundled_agents" / plat / name)
            paths.append(mp / "bin" / "agents" / plat / name)

    # Next to server executable / project roots
    server_dir = _server_dir()
    for name in names:
        paths.append(server_dir / name)
        paths.append(server_dir / "agents" / plat / name)
        paths.append(server_dir / "bin" / "agents" / plat / name)
        paths.append(server_dir / "bundled_agents" / plat / name)

    if plat == "windows" or plat == host_platform():
        for name in names:
            paths.append(project_root() / "dist" / name)
            paths.append(project_root() / "bin" / name)
            paths.append(writable_root() / "dist" / name)
            paths.append(writable_root() / "bin" / name)

    env = os.environ.get("RMM_AGENT_EXE", "")
    if env:
        paths.append(Path(env))
    return paths


def find_native_binary(plat: str) -> Path | None:
    for candidate in _candidate_native_paths(plat):
        try:
            if candidate.is_file():
                return candidate
            if candidate.is_dir() and plat == "macos":
                return candidate
        except Exception:
            continue
    return None


def stage_native_binary(plat: str) -> Path | None:
    """
    Copy a found agent into bin/agents/<plat>/ so downloads & push-install work.
    Returns the staged path (under AGENTS_DIR) or None.
    """
    ensure_dirs()
    existing = platform_dir(plat) / NATIVE_NAMES[plat][0]
    if existing.is_file() and existing.stat().st_size > 1024:
        return existing

    found = find_native_binary(plat)
    if not found or not found.is_file():
        return None

    dest = platform_dir(plat) / NATIVE_NAMES[plat][0]
    # Avoid copying onto itself
    try:
        if found.resolve() == dest.resolve():
            return dest
    except Exception:
        pass
    shutil.copy2(found, dest)
    if plat != "windows":
        try:
            dest.chmod(0o755)
        except Exception:
            pass
    return dest if dest.is_file() else None


def find_agent_binary(plat: str | None = None) -> Path | None:
    order = [plat] if plat else ["windows", host_platform(), "linux", "macos"]
    seen: set[str] = set()
    for p in order:
        if not p or p in seen:
            continue
        seen.add(p)
        staged = stage_native_binary(p)
        if staged:
            return staged
        found = find_native_binary(p)
        if found:
            return found
    return None


def save_uploaded_binary(plat: str, filename: str, data: bytes) -> Path:
    if plat not in PLATFORMS:
        raise ValueError("platform must be windows, macos, or linux")
    ensure_dirs()
    name = NATIVE_NAMES[plat][0]
    dest = platform_dir(plat) / name
    dest.write_bytes(data)
    if plat != "windows":
        dest.chmod(0o755)
    if filename and Path(filename).name != name:
        (platform_dir(plat) / Path(filename).name).write_bytes(data)
    return dest


def _agent_source_files() -> list[tuple[str, Path]]:
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
        path = project_root() / rel
        if path.is_file():
            files.append((rel.replace("\\", "/"), path))
    return files


def _install_script_windows() -> str:
    return """@echo off
setlocal
cd /d "%~dp0"
echo AU-Kamra IT Experts Remote Manager — Windows Agent Install
if "%~1"=="" (
  echo Usage: install.bat http://SERVER:8443 TOKEN
  exit /b 1
)
if exist "AU-Kamra-Remote-Manager-Agent.exe" (
  AU-Kamra-Remote-Manager-Agent.exe --install --server "%~1" --token "%~2"
  exit /b %ERRORLEVEL%
)
echo Native agent .exe missing from this zip.
echo In the admin panel: Generate agent packages, or Upload the Windows agent .exe
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
  exit 1
fi
echo "AU-Kamra IT Experts Remote Manager — {os_label} Agent Install"
if [[ -x "./AU-Kamra-Remote-Manager-Agent" ]]; then
  ./AU-Kamra-Remote-Manager-Agent --install --server "$SERVER" --token "$TOKEN"
  exit 0
fi
if ! command -v python3 >/dev/null 2>&1; then
  echo "No native agent binary and python3 not found."
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
    if plat not in PLATFORMS:
        raise ValueError("invalid platform")
    ensure_dirs()
    # Prefer staged native binary inside platform dir
    stage_native_binary(plat)
    out = platform_dir(plat) / KIT_FILENAMES[plat]
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
        native = None
        staged = platform_dir(plat) / NATIVE_NAMES[plat][0]
        if staged.is_file():
            native = staged
        else:
            native = find_native_binary(plat)

        if native and native.is_file():
            zf.write(native, arcname=NATIVE_NAMES[plat][0])

        if plat == "windows":
            zf.writestr("install.bat", _install_script_windows())
            zf.writestr(
                "README.txt",
                "AU-Kamra Agent — Windows\r\n\r\n"
                "Run as Administrator:\r\n"
                "  AU-Kamra-Remote-Manager-Agent.exe --install --server http://SERVER:8443 --token TOKEN\r\n",
            )
        else:
            zf.writestr("install.sh", _install_script_unix("macOS" if plat == "macos" else "Linux"))
            written: set[str] = set()
            for arc, path in _agent_source_files():
                if arc in written:
                    continue
                zf.write(path, arcname=arc)
                written.add(arc)
            zf.writestr(
                "README.txt",
                f"AU-Kamra Agent — {'macOS' if plat == 'macos' else 'Linux'}\n\n"
                "chmod +x install.sh AU-Kamra-Remote-Manager-Agent 2>/dev/null\n"
                "./install.sh http://SERVER:8443 TOKEN\n",
            )
    out.write_bytes(buf.getvalue())
    return out


def _find_real_python() -> str | None:
    """Return a real Python interpreter path — never the frozen server .exe."""
    if not is_frozen() and sys.executable and Path(sys.executable).is_file():
        # Extra safety: frozen exes are not valid for -m PyInstaller
        name = Path(sys.executable).name.lower()
        if "python" in name:
            return sys.executable

    for candidate in (
        shutil.which("python"),
        shutil.which("python3"),
        shutil.which("py"),
    ):
        if not candidate:
            continue
        # On Windows, `py` is the launcher — OK for -m PyInstaller
        cname = Path(candidate).name.lower()
        if is_frozen() and cname in {
            Path(sys.executable).name.lower(),
            "au-kamra-remote-manager-server.exe",
            "disclosermm-server.exe",
        }:
            continue
        if "server" in cname and cname.endswith(".exe"):
            continue
        return candidate
    return None


def _try_pyinstaller_build(plat: str) -> Path | None:
    """Build agent for current host OS into bin/agents/<plat>/ using real Python only."""
    if plat != host_platform():
        return None

    if is_frozen():
        # Cannot compile from inside the server .exe (sys.executable IS the server).
        staged = stage_native_binary(plat)
        if staged:
            return staged
        raise RuntimeError(
            "Cannot compile agent from the server .exe. "
            "Rebuild with build\\build_windows.bat (agent is bundled), "
            "or place/upload AU-Kamra-Remote-Manager-Agent.exe in the admin panel."
        )

    python = _find_real_python()
    if not python:
        raise RuntimeError("Python interpreter not found for PyInstaller build.")

    # Ensure pyinstaller available for that interpreter
    check = subprocess.run(
        [python, "-c", "import PyInstaller"],
        capture_output=True,
        text=True,
    )
    if check.returncode != 0:
        pip = subprocess.run(
            [python, "-m", "pip", "install", "-q", "pyinstaller"],
            capture_output=True,
            text=True,
        )
        if pip.returncode != 0:
            raise RuntimeError("Failed to install PyInstaller: " + (pip.stderr or pip.stdout)[-500:])

    work = writable_root() / "build" / f"gen-{plat}-{int(time.time())}"
    work.mkdir(parents=True, exist_ok=True)
    name = "AU-Kamra-Remote-Manager-Agent"
    distpath = platform_dir(plat)
    src_root = project_root()
    cmd = [
        python,
        "-m",
        "PyInstaller",
        "--noconfirm",
        "--clean",
        "--onefile",
        "--name",
        name,
        "--distpath",
        str(distpath),
        "--workpath",
        str(work / "work"),
        "--specpath",
        str(work),
        str(src_root / "run_agent.py"),
        "--paths",
        str(src_root),
        "--hidden-import", "agent.discovery",
        "--hidden-import", "agent.install_service",
        "--hidden-import", "shared.config",
        "--hidden-import", "shared.protocol",
        "--hidden-import", "websocket",
        "--hidden-import", "requests",
        "--hidden-import", "mss",
        "--hidden-import", "PIL",
        "--exclude-module", "server",
        "--exclude-module", "uvicorn",
        "--exclude-module", "fastapi",
    ]

    proc = subprocess.run(cmd, cwd=str(src_root), capture_output=True, text=True, timeout=900)
    if proc.returncode != 0:
        err = (proc.stderr or "") + "\n" + (proc.stdout or "")
        # Keep message short and actionable
        tail = err.strip()[-800:]
        raise RuntimeError(f"PyInstaller failed (code {proc.returncode}): {tail}")

    out = distpath / (name + (".exe" if plat == "windows" else ""))
    if out.is_file() and out.stat().st_size > 1024:
        if plat != "windows":
            out.chmod(0o755)
        return out
    alt = distpath / name
    if alt.is_file() and alt.stat().st_size > 1024:
        if plat != "windows":
            alt.chmod(0o755)
        return alt
    raise RuntimeError("PyInstaller finished but agent binary was not created.")


def generate_all_packages(try_native_build: bool = True) -> dict[str, Any]:
    """
    Stage bundled/sidecar agents, optionally compile on this host (source mode only),
    and build download kits for windows/macos/linux.
    """
    ensure_dirs()
    result: dict[str, Any] = {
        "built_native": None,
        "staged": {},
        "kits": {},
        "errors": [],
        "warnings": [],
    }

    # Always stage anything already available (bundled / next to server / dist)
    for plat in PLATFORMS:
        staged = stage_native_binary(plat)
        if staged:
            result["staged"][plat] = str(staged)

    hp = host_platform()
    if try_native_build:
        try:
            if is_frozen():
                # Stage only — never relaunch server exe as "python -m PyInstaller"
                staged = stage_native_binary(hp)
                if staged:
                    result["built_native"] = {
                        "platform": hp,
                        "path": str(staged),
                        "mode": "staged_bundled",
                    }
                else:
                    result["warnings"].append(
                        f"No native {hp} agent found next to the server. "
                        "Upload the agent .exe in this panel, or rebuild with build\\build_windows.bat "
                        "so the agent is bundled inside the server."
                    )
            else:
                native_path = _try_pyinstaller_build(hp)
                if native_path:
                    result["built_native"] = {
                        "platform": hp,
                        "path": str(native_path),
                        "mode": "pyinstaller",
                    }
                    result["staged"][hp] = str(native_path)
        except Exception as exc:
            result["errors"].append(f"native build ({hp}): {exc}")

    for plat in PLATFORMS:
        try:
            # Re-stage before zipping
            stage_native_binary(plat)
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


def list_platform_status() -> list[dict[str, Any]]:
    ensure_dirs()
    # Opportunistically stage bundled binaries so status is accurate
    for plat in PLATFORMS:
        stage_native_binary(plat)

    rows = []
    labels = {"windows": "Windows", "macos": "macOS", "linux": "Linux"}
    for plat in PLATFORMS:
        native = find_native_binary(plat)
        kit = platform_dir(plat) / KIT_FILENAMES[plat]
        native_ok = bool(native and native.is_file() and native.stat().st_size > 1024)
        rows.append(
            {
                "platform": plat,
                "label": labels[plat],
                "native_available": native_ok,
                "native_path": str(native) if native_ok else "",
                "native_size": native.stat().st_size if native_ok else 0,
                "kit_available": kit.is_file() and kit.stat().st_size > 0,
                "kit_path": str(kit) if kit.is_file() else "",
                "kit_size": kit.stat().st_size if kit.is_file() else 0,
                "download_name": DOWNLOAD_FILENAMES[plat] if native_ok else KIT_FILENAMES[plat],
                "preferred": "native" if native_ok else ("kit" if kit.is_file() else "missing"),
            }
        )
    return rows


def resolve_download(plat: str, prefer: str = "auto") -> tuple[Path, str]:
    if plat not in PLATFORMS:
        raise ValueError("invalid platform")
    stage_native_binary(plat)
    native = find_native_binary(plat)
    kit = platform_dir(plat) / KIT_FILENAMES[plat]

    if prefer == "kit":
        if not kit.is_file():
            kit = build_kit_zip(plat)
        return kit, KIT_FILENAMES[plat]

    if prefer == "native":
        if not native or not native.is_file():
            raise FileNotFoundError(
                f"No native agent for {plat}. Click Generate, Upload the .exe, "
                "or rebuild with build\\build_windows.bat"
            )
        return native, DOWNLOAD_FILENAMES[plat]

    if native and native.is_file() and native.stat().st_size > 1024:
        return native, DOWNLOAD_FILENAMES[plat]
    if not kit.is_file() or kit.stat().st_size < 64:
        kit = build_kit_zip(plat)
    return kit, KIT_FILENAMES[plat]
