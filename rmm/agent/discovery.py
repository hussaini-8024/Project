"""LAN discovery beacon + probe listener for agents."""

from __future__ import annotations

import json
import socket
import threading
import time
from typing import Any, Callable

from shared import config


def _local_ip() -> str:
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "0.0.0.0"


class DiscoveryBeacon:
    """Broadcast agent presence so the server panel can auto-discover it."""

    def __init__(self, info_provider: Callable[[], dict[str, Any]]) -> None:
        self.info_provider = info_provider
        self._stop = threading.Event()
        self._threads: list[threading.Thread] = []

    def start(self) -> None:
        self._stop.clear()
        t1 = threading.Thread(target=self._broadcast_loop, daemon=True)
        t2 = threading.Thread(target=self._probe_server, daemon=True)
        self._threads = [t1, t2]
        t1.start()
        t2.start()

    def stop(self) -> None:
        self._stop.set()

    def _payload(self) -> bytes:
        data = self.info_provider()
        data["magic"] = config.DISCOVERY_MAGIC
        data["ip"] = data.get("ip") or _local_ip()
        return json.dumps(data).encode("utf-8")

    def _broadcast_loop(self) -> None:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
        while not self._stop.is_set():
            try:
                sock.sendto(self._payload(), ("255.255.255.255", config.DISCOVERY_UDP_PORT))
            except Exception:
                pass
            self._stop.wait(config.DISCOVERY_INTERVAL_SECONDS)
        sock.close()

    def _probe_server(self) -> None:
        """Tiny TCP probe server: server scans subnet and reads one JSON line."""
        srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        try:
            srv.bind(("0.0.0.0", config.DISCOVERY_TCP_PORT))
            srv.listen(5)
            srv.settimeout(1.0)
        except Exception:
            return
        while not self._stop.is_set():
            try:
                conn, _addr = srv.accept()
            except socket.timeout:
                continue
            except Exception:
                break
            try:
                conn.sendall(self._payload() + b"\n")
            except Exception:
                pass
            finally:
                try:
                    conn.close()
                except Exception:
                    pass
        try:
            srv.close()
        except Exception:
            pass


def listen_udp_beacons(duration_seconds: float = 5.0) -> list[dict[str, Any]]:
    """Server-side: collect UDP discovery beacons for a short window."""
    found: dict[str, dict[str, Any]] = {}
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        sock.bind(("0.0.0.0", config.DISCOVERY_UDP_PORT))
    except OSError:
        # Already bound; still try
        pass
    sock.settimeout(0.5)
    deadline = time.time() + duration_seconds
    while time.time() < deadline:
        try:
            raw, addr = sock.recvfrom(65535)
            data = json.loads(raw.decode("utf-8"))
            if data.get("magic") != config.DISCOVERY_MAGIC:
                continue
            data["ip"] = data.get("ip") or addr[0]
            key = data.get("agent_id") or data["ip"]
            found[str(key)] = data
        except socket.timeout:
            continue
        except Exception:
            continue
    sock.close()
    return list(found.values())


def probe_host(ip: str, timeout: float = 0.6) -> dict[str, Any] | None:
    try:
        sock = socket.create_connection((ip, config.DISCOVERY_TCP_PORT), timeout=timeout)
        sock.settimeout(timeout)
        raw = b""
        while b"\n" not in raw and len(raw) < 8192:
            chunk = sock.recv(1024)
            if not chunk:
                break
            raw += chunk
        sock.close()
        data = json.loads(raw.decode("utf-8").strip())
        if data.get("magic") != config.DISCOVERY_MAGIC:
            return None
        data["ip"] = data.get("ip") or ip
        return data
    except Exception:
        return None
