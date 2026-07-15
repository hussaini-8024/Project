"""CLI entrypoint: python -m aulabs"""

from __future__ import annotations

import argparse
import sys


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="AU Labs IT Management")
    parser.add_argument("command", nargs="?", default="serve", choices=["serve", "version", "init"])
    parser.add_argument("--host", default=None)
    parser.add_argument("--port", type=int, default=None)
    args = parser.parse_args(argv)

    if args.command == "version":
        from aulabs import __app_name__, __version__

        print(f"{__app_name__} {__version__}")
        return 0

    from aulabs.config import get_settings
    from aulabs.services.users import UserService

    settings = get_settings()
    settings.ensure_paths()
    UserService().ensure_admin()

    if args.command == "init":
        print(f"Initialized data root: {settings.data_root}")
        print(f"Users root: {settings.users_root}")
        print(f"Admin user: {settings.admin_username}")
        return 0

    import uvicorn

    host = args.host or settings.host
    port = args.port or settings.port
    print(f"Starting AU Labs IT Management on http://{host}:{port}")
    uvicorn.run(
        "aulabs.app:app",
        host=host,
        port=port,
        reload=False,
        log_level="info",
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
