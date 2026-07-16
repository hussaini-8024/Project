"""Host OS overview for the master management panel."""

from __future__ import annotations

import os
import platform
import socket
import time
from typing import Any

import psutil

from aulabs import __version__
from aulabs.config import get_settings


class SystemService:
    def overview(self) -> dict[str, Any]:
        settings = get_settings()
        boot = psutil.boot_time()
        vm = psutil.virtual_memory()
        disk = psutil.disk_usage(str(settings.data_root))
        load = os.getloadavg() if hasattr(os, "getloadavg") else (0.0, 0.0, 0.0)
        return {
            "panel": {
                "name": settings.app_name,
                "version": __version__,
                "host": settings.host,
                "port": settings.port,
                "data_root": str(settings.data_root),
                "users_root": str(settings.users_root),
            },
            "os": {
                "system": platform.system(),
                "release": platform.release(),
                "version": platform.version(),
                "machine": platform.machine(),
                "hostname": socket.gethostname(),
                "python": platform.python_version(),
            },
            "runtime": {
                "uptime_seconds": int(time.time() - boot),
                "boot_time": boot,
                "load_avg": list(load),
                "cpu_count": psutil.cpu_count() or 1,
                "cpu_percent": psutil.cpu_percent(interval=0.1),
            },
            "memory": {
                "total": vm.total,
                "available": vm.available,
                "used": vm.used,
                "percent": vm.percent,
            },
            "disk": {
                "path": str(settings.data_root),
                "total": disk.total,
                "used": disk.used,
                "free": disk.free,
                "percent": disk.percent,
            },
            "processes": len(psutil.pids()),
            "logged_in_users": self._logged_in_users(),
            "is_root": os.geteuid() == 0 if hasattr(os, "geteuid") else False,
        }

    def _logged_in_users(self) -> list[dict[str, Any]]:
        users: list[dict[str, Any]] = []
        try:
            for u in psutil.users():
                users.append(
                    {
                        "name": u.name,
                        "terminal": u.terminal,
                        "host": u.host,
                        "started": u.started,
                    }
                )
        except Exception:
            pass
        return users
