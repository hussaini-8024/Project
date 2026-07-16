# Build single-file Windows Server executable with PyInstaller (run on Windows).
# Prefer build\build_windows.bat which builds the Agent first, then this Server
# with the agent bundled for admin-panel Generate/Download.

import os
from pathlib import Path

block_cipher = None
root = Path(SPECPATH).resolve().parent.parent  # rmm/

datas = [(str(root / 'server' / 'static'), 'server/static')]

# Bundle Windows agent into the server so Generate/Download works without recompiling
agent_candidates = [
    root / 'bin' / 'agents' / 'windows' / 'AU-Kamra-Remote-Manager-Agent.exe',
    root / 'dist' / 'AU-Kamra-Remote-Manager-Agent.exe',
    root / 'bin' / 'AU-Kamra-Remote-Manager-Agent.exe',
]
for agent_path in agent_candidates:
    if agent_path.is_file() and agent_path.stat().st_size > 1024:
        datas.append((str(agent_path), 'bundled_agents/windows'))
        break

a = Analysis(
    [str(root / 'run_server.py')],
    pathex=[str(root)],
    binaries=[],
    datas=datas,
    hiddenimports=['uvicorn.logging', 'uvicorn.loops', 'uvicorn.loops.auto',
                   'uvicorn.protocols', 'uvicorn.protocols.http', 'uvicorn.protocols.http.auto',
                   'uvicorn.protocols.websockets', 'uvicorn.protocols.websockets.auto',
                   'uvicorn.lifespan', 'uvicorn.lifespan.on',
                   'agent.discovery', 'agent.install_service', 'server.remote_push',
                   'server.agent_packages', 'server.database', 'shared.protocol', 'shared.config'],
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
    name='AU-Kamra-Remote-Manager-Server',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
)
