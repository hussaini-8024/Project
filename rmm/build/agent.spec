# Build AU-Kamra Agent .exe (Windows / PyInstaller)

from pathlib import Path

block_cipher = None


def _resolve_rmm_root() -> Path:
    raw = Path(str(SPECPATH)).resolve()
    candidates = []
    if raw.is_file():
        candidates.append(raw.parent.parent)
        candidates.append(raw.parent)
    else:
        candidates.append(raw.parent)
        candidates.append(raw.parent.parent / "rmm")
    cwd = Path.cwd().resolve()
    candidates.extend([cwd, cwd / "rmm", cwd.parent / "rmm"])
    seen = set()
    for c in candidates:
        try:
            key = str(c.resolve())
        except Exception:
            continue
        if key in seen:
            continue
        seen.add(key)
        if (c / "run_agent.py").is_file() and (c / "agent").is_dir():
            return c.resolve()
    raise SystemExit(
        "ERROR: could not locate rmm/run_agent.py from SPECPATH=%r cwd=%r"
        % (str(SPECPATH), str(cwd))
    )


root = _resolve_rmm_root()
run_agent = root / "run_agent.py"
print("agent.spec using root:", root)

a = Analysis(
    [str(run_agent)],
    pathex=[str(root)],
    binaries=[],
    datas=[],
    hiddenimports=[
        "mss",
        "PIL",
        "tkinter",
        "agent.discovery",
        "agent.install_service",
        "shared.protocol",
        "shared.config",
        "websocket",
        "requests",
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=["server", "uvicorn", "fastapi"],
    win_no_prefer_redirects=False,
    win_private_assemblies=False,
    cipher=block_cipher,
    noarchive=False,
)
pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)
exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name="AU-Kamra-Remote-Manager-Agent",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=False,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
)
