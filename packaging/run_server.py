#!/usr/bin/env python3
"""PyInstaller entry: AU Labs Server."""
from aulabs.entrypoints import run_server

if __name__ == "__main__":
    raise SystemExit(run_server())
