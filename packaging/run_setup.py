#!/usr/bin/env python3
"""PyInstaller entry: AU Labs Setup wizard."""
from aulabs.entrypoints import run_setup

if __name__ == "__main__":
    raise SystemExit(run_setup())
