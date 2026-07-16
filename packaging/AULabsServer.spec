# -*- mode: python ; coding: utf-8 -*-
"""PyInstaller spec: AULabsServer single-file binary."""
from pathlib import Path

SPECDIR = Path(SPECPATH).resolve()
ROOT = SPECDIR.parent

block_cipher = None

a = Analysis(
    [str(SPECDIR / 'run_server.py')],
    pathex=[str(ROOT)],
    binaries=[],
    datas=[
        (str(ROOT / 'aulabs' / 'web' / 'templates'), 'aulabs/web/templates'),
        (str(ROOT / 'aulabs' / 'web' / 'static'), 'aulabs/web/static'),
    ],
    hiddenimports=[
        'aulabs',
        'aulabs.app',
        'aulabs.__main__',
        'aulabs.api',
        'aulabs.api.auth_routes',
        'aulabs.api.users_routes',
        'aulabs.api.storage_routes',
        'aulabs.api.sessions_routes',
        'aulabs.api.system_routes',
        'aulabs.api.agents_routes',
        'uvicorn.logging',
        'uvicorn.loops',
        'uvicorn.loops.auto',
        'uvicorn.protocols',
        'uvicorn.protocols.http',
        'uvicorn.protocols.http.auto',
        'uvicorn.protocols.websockets',
        'uvicorn.protocols.websockets.auto',
        'uvicorn.lifespan',
        'uvicorn.lifespan.on',
        'multipart',
        'passlib.handlers.bcrypt',
        'bcrypt',
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
    name='AULabsServer',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)
