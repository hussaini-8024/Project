"""
AU-Kamra-IT Loan Cards Management
Desktop application — single-EXE friendly (Flask + local UI).
"""

from __future__ import annotations

import os
import sys
from pathlib import Path


APP_NAME = "AU-Kamra-IT Loan Cards Management"
APP_SHORT = "AU-Kamra Loan Cards"
APP_VERSION = "1.0.0"
HOST = "127.0.0.1"
PORT = 8765


def app_root() -> Path:
    """Directory containing the running app (EXE or source)."""
    if getattr(sys, "frozen", False):
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parent


def resource_path(*parts: str) -> Path:
    """Bundled resources (templates/static) — works with PyInstaller."""
    if getattr(sys, "frozen", False) and hasattr(sys, "_MEIPASS"):
        meipass = Path(sys._MEIPASS)
        packaged = meipass / "au_kamra_loan_cards"
        base = packaged if packaged.exists() else meipass
    else:
        base = Path(__file__).resolve().parent
    return base.joinpath(*parts)


def data_dir() -> Path:
    """Persistent user data next to the EXE (or under ./data in source mode)."""
    if getattr(sys, "frozen", False):
        path = app_root() / "AU_Kamra_Data"
    else:
        path = Path(__file__).resolve().parent / "data"
    path.mkdir(parents=True, exist_ok=True)
    (path / "uploads").mkdir(exist_ok=True)
    (path / "generated").mkdir(exist_ok=True)
    (path / "backups").mkdir(exist_ok=True)
    return path


def db_path() -> Path:
    return data_dir() / "loan_cards.db"
