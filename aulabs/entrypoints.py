"""Frozen / CLI entrypoints for PyInstaller single-file builds."""

from __future__ import annotations

import multiprocessing
import os
import sys
from pathlib import Path


def _load_env_file(path: Path) -> None:
    if not path.exists():
        return
    try:
        for line in path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            key = key.strip()
            value = value.strip().strip('"').strip("'")
            # Installed config wins over empty defaults, but explicit process env wins.
            if key and key not in os.environ:
                os.environ[key] = value
    except OSError:
        pass


def _prepare_frozen_env() -> None:
    """Ensure data paths work when running as a bundled executable."""
    if getattr(sys, "frozen", False):
        exe_dir = Path(sys.executable).resolve().parent
        # Prefer install root (…/bin/AULabsServer → …/aulabs.env)
        for candidate in (exe_dir / "aulabs.env", exe_dir.parent / "aulabs.env"):
            _load_env_file(candidate)
        os.environ.setdefault("AULABS_DATA_ROOT", str(exe_dir.parent / "data"))
        meipass = getattr(sys, "_MEIPASS", None)
        if meipass and meipass not in sys.path:
            sys.path.insert(0, meipass)


def run_server(argv: list[str] | None = None) -> int:
    _prepare_frozen_env()
    multiprocessing.freeze_support()
    from aulabs.__main__ import main

    return main(argv)


def run_agent(argv: list[str] | None = None) -> int:
    _prepare_frozen_env()
    multiprocessing.freeze_support()
    from aulabs.agent import main

    return main(argv)


def run_setup(argv: list[str] | None = None) -> int:
    multiprocessing.freeze_support()
    from aulabs.setup_wizard import main

    return main(argv)


if __name__ == "__main__":
    raise SystemExit(run_server())
