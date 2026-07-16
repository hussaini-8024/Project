# Build single-file Windows agent executable with PyInstaller (run on Windows).
# Usage (from rmm/):
#   pyinstaller build/agent.spec

block_cipher = None

a = Analysis(
    ['../run_agent.py'],
    pathex=['..'],
    binaries=[],
    datas=[],
    hiddenimports=['mss', 'PIL', 'tkinter', 'agent.discovery', 'agent.install_service',
                   'shared.protocol', 'shared.config', 'websocket', 'requests'],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=['server', 'uvicorn', 'fastapi', 'pydantic'],
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
    name='AU-Kamra-Remote-Manager-Agent',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
)
