#!/usr/bin/env python3
"""PyInstaller entry: AU Labs Agent."""
from aulabs.entrypoints import run_agent

if __name__ == "__main__":
    raise SystemExit(run_agent())
