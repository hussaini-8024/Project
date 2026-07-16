# -*- mode: python ; coding: utf-8 -*-
"""PyInstaller spec: AULabsSetup — VLC-style installer embedding Server + Agent."""

import os
from pathlib import Path

block_cipher = None
ROOT = Path(SPECPATH).resolve().parent
DIST = ROOT / 'dist'

payload = []
for name in ('AULabsServer', 'AULabsAgent'):
    for candidate in (DIST / name, DIST / f'{name}.exe'):
        if candidate.exists():
            payload.append((str(candidate), 'payload'))
            break

a = Analysis(
    ['packaging/run_setup.py'],
    pathex=['.'],
    binaries=[],
    datas=payload + [
        ('aulabs', 'aulabs'),
        ('requirements.txt', '.'),
        ('README.md', '.'),
    ],
    hiddenimports=[
        'aulabs',
        'aulabs.setup_wizard',
        'tkinter',
        '_tkinter',
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
    name='AULabsSetup',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,  # windowed installer like VLC setup
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)
