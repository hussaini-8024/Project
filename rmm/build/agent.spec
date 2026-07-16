# Build single-file Windows agent executable with PyInstaller (run on Windows).
# Usage (from rmm/):
#   pyinstaller build/agent.spec

block_cipher = None

a = Analysis(
    ['../run_agent.py'],
    pathex=['..'],
    binaries=[],
    datas=[],
    hiddenimports=['mss', 'PIL', 'tkinter'],
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
    name='DiscloseRMM-Agent',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
)
