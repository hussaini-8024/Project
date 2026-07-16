# Build single-file Windows executables with PyInstaller (run on Windows).
# Usage (from rmm/):
#   pip install -r requirements.txt
#   pyinstaller build/server.spec
#   pyinstaller build/agent.spec

block_cipher = None

a = Analysis(
    ['../run_server.py'],
    pathex=['..'],
    binaries=[],
    datas=[('../server/static', 'server/static')],
    hiddenimports=['uvicorn.logging', 'uvicorn.loops', 'uvicorn.loops.auto',
                   'uvicorn.protocols', 'uvicorn.protocols.http', 'uvicorn.protocols.http.auto',
                   'uvicorn.protocols.websockets', 'uvicorn.protocols.websockets.auto',
                   'uvicorn.lifespan', 'uvicorn.lifespan.on'],
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
    name='DiscloseRMM-Server',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
)
