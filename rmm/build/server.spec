# Build AU-Kamra Server .exe (Windows / PyInstaller)
# SPECPATH may be the .spec file path OR its directory depending on PyInstaller version.

from pathlib import Path

block_cipher = None


def _resolve_rmm_root() -> Path:
    raw = Path(str(SPECPATH)).resolve()
    candidates = []
    if raw.is_file():
        # .../rmm/build/server.spec -> parents: build, rmm
        candidates.append(raw.parent.parent)
        candidates.append(raw.parent)
    else:
        # .../rmm/build -> parent rmm
        candidates.append(raw.parent)
        candidates.append(raw.parent.parent / "rmm")
    # CWD fallbacks (build_windows.bat cds into rmm/)
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
        if (c / "run_server.py").is_file() and (c / "server").is_dir():
            return c.resolve()
    raise SystemExit(
        "ERROR: could not locate rmm/run_server.py from SPECPATH=%r cwd=%r"
        % (str(SPECPATH), str(cwd))
    )


root = _resolve_rmm_root()
run_server = root / "run_server.py"
print("server.spec using root:", root)

datas = [(str(root / "server" / "static"), "server/static")]

agent_candidates = [
    root / "bin" / "agents" / "windows" / "AU-Kamra-Remote-Manager-Agent.exe",
    root / "dist" / "AU-Kamra-Remote-Manager-Agent.exe",
    root / "bin" / "AU-Kamra-Remote-Manager-Agent.exe",
]
for agent_path in agent_candidates:
    if agent_path.is_file() and agent_path.stat().st_size > 1024:
        datas.append((str(agent_path), "bundled_agents/windows"))
        print("Bundling agent:", agent_path)
        break
else:
    print("WARNING: No agent .exe found to bundle. Generate/Download will need Upload.")

a = Analysis(
    [str(run_server)],
    pathex=[str(root)],
    binaries=[],
    datas=datas,
    hiddenimports=[
        "uvicorn.logging",
        "uvicorn.loops",
        "uvicorn.loops.auto",
        "uvicorn.protocols",
        "uvicorn.protocols.http",
        "uvicorn.protocols.http.auto",
        "uvicorn.protocols.websockets",
        "uvicorn.protocols.websockets.auto",
        "uvicorn.lifespan",
        "uvicorn.lifespan.on",
        "agent.discovery",
        "agent.install_service",
        "server.remote_push",
        "server.agent_packages",
        "server.database",
        "server.paths",
        "shared.protocol",
        "shared.config",
        "multipart",
        "email.mime.multipart",
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
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
    name="AU-Kamra-Remote-Manager-Server",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=False,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
)
