"""Frozen-aware path helpers for server runtime."""

from __future__ import annotations

import os
import sys
from pathlib import Path


def is_frozen() -> bool:
    return bool(getattr(sys, "frozen", False))


def resource_root() -> Path:
    """Where packaged read-only assets live (static files, bundled agents)."""
    if is_frozen():
        meipass = getattr(sys, "_MEIPASS", None)
        if meipass:
            return Path(meipass)
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parent.parent


def writable_root() -> Path:
    """Where DB, packages, and generated agents can be written."""
    if not is_frozen():
        return Path(__file__).resolve().parent.parent

    exe_dir = Path(sys.executable).resolve().parent
    probe = exe_dir / ".aukamra_write_test"
    try:
        probe.write_text("ok", encoding="utf-8")
        probe.unlink(missing_ok=True)
        return exe_dir
    except Exception:
        pass

    if sys.platform == "win32":
        base = Path(os.environ.get("LOCALAPPDATA") or Path.home())
    else:
        base = Path.home() / ".local" / "share"
    root = base / "AUKamraRemoteManager"
    root.mkdir(parents=True, exist_ok=True)
    return root


def static_dir() -> Path:
    root = resource_root()
    candidates = [
        root / "server" / "static",
        root / "static",
        Path(__file__).resolve().parent / "static",
    ]
    for c in candidates:
        if (c / "index.html").is_file():
            return c
    return candidates[0]
