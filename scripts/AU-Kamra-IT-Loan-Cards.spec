# -*- mode: python ; coding: utf-8 -*-
# PyInstaller spec for AU-Kamra-IT Loan Cards Management
# Run on Windows: pyinstaller scripts/AU-Kamra-IT-Loan-Cards.spec

import sys
from pathlib import Path

block_cipher = None
root = Path(SPECPATH).resolve().parent

datas = [
    (str(root / "au_kamra_loan_cards" / "templates"), "au_kamra_loan_cards/templates"),
    (str(root / "au_kamra_loan_cards" / "static"), "au_kamra_loan_cards/static"),
    (str(root / "au_kamra_loan_cards" / "samples"), "au_kamra_loan_cards/samples"),
]

a = Analysis(
    [str(root / "run.py")],
    pathex=[str(root)],
    binaries=[],
    datas=datas,
    hiddenimports=["flask", "bs4", "lxml", "pypdf", "reportlab", "webview"],
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
    name="AU-Kamra-IT-Loan-Cards",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,
    disable_windowed_traceback=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)
