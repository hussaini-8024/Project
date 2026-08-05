#!/usr/bin/env python3
"""
UBNT Dish IP Discovery Tool
===========================
Discover Ubiquiti (UBNT) airMAX / dish radios on the local network
even when you do not know their IP address.

Uses the Ubiquiti Discovery Protocol (UDP port 10001) plus optional
HTTP checks against common default IPs.

Usage:
  python3 ubnt_discover.py              # discover on local LAN
  python3 ubnt_discover.py --timeout 8  # wait longer for replies
  python3 ubnt_discover.py --json       # machine-readable output
  python3 ubnt_discover.py --check-defaults  # also probe common IPs
"""

from __future__ import annotations

import argparse
import json
import socket
import struct
import sys
import time
from dataclasses import asdict, dataclass, field
from typing import Dict, List, Optional, Set, Tuple

# Ubiquiti discovery field types (TLV)
FIELD_MAC = 0x01
FIELD_MAC_AND_IP = 0x02
FIELD_FIRMWARE = 0x03
FIELD_UPTIME = 0x0A
FIELD_HOSTNAME = 0x0B
FIELD_MODEL_SHORT = 0x0C
FIELD_ESSID = 0x0D
FIELD_MODEL_FULL = 0x14

# Discovery request payloads (v1 classic airMAX, v2 newer devices)
REQUEST_V1 = bytes([0x01, 0x00, 0x00, 0x00])
REQUEST_V2 = bytes([0x02, 0x08, 0x00, 0x00])

DISCOVERY_PORT = 10001
ALT_PORTS = (10001, 5678)

# Common factory-default / field IPs for Ubiquiti dishes & radios
COMMON_DEFAULT_IPS = (
    "192.168.1.20",   # classic airMAX default
    "192.168.1.1",
    "192.168.0.1",
    "192.168.1.30",
    "192.168.10.1",
)


@dataclass
class UbntDevice:
    """Discovered Ubiquiti device."""

    ip: str
    mac: str = ""
    hostname: str = ""
    model: str = ""
    model_short: str = ""
    firmware: str = ""
    essid: str = ""
    uptime_seconds: Optional[int] = None
    source: str = "discovery"  # discovery | default-probe
    extra_ips: List[str] = field(default_factory=list)

    @property
    def display_model(self) -> str:
        return self.model or self.model_short or "Ubiquiti device"

    def to_dict(self) -> dict:
        d = asdict(self)
        d["display_model"] = self.display_model
        return d


def _fmt_mac(raw: bytes) -> str:
    if len(raw) < 6:
        return raw.hex(":")
    return ":".join(f"{b:02X}" for b in raw[:6])


def _fmt_ip(raw: bytes) -> str:
    if len(raw) < 4:
        return ""
    return ".".join(str(b) for b in raw[:4])


def _decode_str(raw: bytes) -> str:
    return raw.decode("utf-8", errors="replace").rstrip("\x00").strip()


def parse_discovery_payload(payload: bytes, reply_ip: str) -> Optional[UbntDevice]:
    """Parse a Ubiquiti discovery reply into a UbntDevice."""
    if len(payload) < 4:
        return None

    # Accept v1 (01 00 00) and v2-style replies
    if payload[0] not in (0x01, 0x02):
        return None

    device = UbntDevice(ip=reply_ip)
    offset = 4  # skip signature + length/header bytes

    # Some replies use a 4-byte header where byte 3 is remaining length;
    # others embed TLVs starting at offset 4 regardless.
    while offset + 3 <= len(payload):
        ftype = payload[offset]
        flen = struct.unpack("!H", payload[offset + 1 : offset + 3])[0]
        offset += 3
        if flen < 0 or offset + flen > len(payload):
            break
        data = payload[offset : offset + flen]
        offset += flen

        if ftype == FIELD_MAC and len(data) >= 6:
            device.mac = _fmt_mac(data)
        elif ftype == FIELD_MAC_AND_IP and len(data) >= 10:
            mac = _fmt_mac(data[0:6])
            ip = _fmt_ip(data[6:10])
            if not device.mac:
                device.mac = mac
            if ip and ip != device.ip:
                if ip not in device.extra_ips:
                    device.extra_ips.append(ip)
                # Prefer the interface IP from the TLV when reply came from broadcast
                if device.ip in ("255.255.255.255", "0.0.0.0") or not device.ip:
                    device.ip = ip
                elif not device.ip:
                    device.ip = ip
            elif ip and not device.ip:
                device.ip = ip
            # If we only have reply_ip as broadcast-ish, use TLV IP
            if ip and device.ip == reply_ip and reply_ip.startswith("255."):
                device.ip = ip
            if ip and device.source == "discovery" and not device.extra_ips:
                # Keep primary IP as the TLV IP when available (more accurate)
                if reply_ip and reply_ip != ip and reply_ip not in device.extra_ips:
                    # Prefer TLV IP as the device IP (management address)
                    if device.ip == reply_ip:
                        device.ip = ip
        elif ftype == FIELD_FIRMWARE:
            device.firmware = _decode_str(data)
        elif ftype == FIELD_UPTIME and len(data) >= 4:
            device.uptime_seconds = struct.unpack("!I", data[:4])[0]
        elif ftype == FIELD_HOSTNAME:
            device.hostname = _decode_str(data)
        elif ftype == FIELD_MODEL_SHORT:
            device.model_short = _decode_str(data)
        elif ftype == FIELD_ESSID:
            device.essid = _decode_str(data)
        elif ftype == FIELD_MODEL_FULL:
            device.model = _decode_str(data)

    # Prefer TLV IP when we captured one in extra_ips and ip looks like sender only
    if device.extra_ips and device.ip == reply_ip:
        # Keep reply_ip as primary (how we reached them) — it's usually correct
        pass

    # Must have at least an IP
    if not device.ip or device.ip.startswith("255."):
        if device.extra_ips:
            device.ip = device.extra_ips[0]
        else:
            return None

    return device


def discover_devices(
    timeout: float = 5.0,
    broadcast_addr: str = "255.255.255.255",
    retries: int = 2,
) -> List[UbntDevice]:
    """
    Broadcast Ubiquiti discovery packets and collect replies.

    Works for PowerBeam, NanoBeam, LiteBeam, Rocket, airFiber, etc.
    when the PC is on the same L2 network (or directly linked).
    """
    found: Dict[str, UbntDevice] = {}  # key = mac or ip
    end_time = time.time() + timeout

    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        sock.bind(("", 0))
    except OSError:
        pass
    sock.settimeout(0.4)

    payloads = [REQUEST_V1, REQUEST_V2]
    send_count = 0

    try:
        while time.time() < end_time:
            # Re-send discovery a few times (UDP is unreliable)
            if send_count < retries + 1 or (send_count < retries * 3 and time.time() < end_time - 1):
                for port in ALT_PORTS:
                    for payload in payloads:
                        try:
                            sock.sendto(payload, (broadcast_addr, port))
                        except OSError:
                            continue
                send_count += 1

            try:
                data, addr = sock.recvfrom(4096)
            except socket.timeout:
                continue
            except OSError:
                break

            reply_ip = addr[0]
            device = parse_discovery_payload(data, reply_ip)
            if not device:
                continue

            key = device.mac.upper() if device.mac else device.ip
            if key in found:
                # Merge missing fields
                existing = found[key]
                for attr in ("hostname", "model", "model_short", "firmware", "essid"):
                    if not getattr(existing, attr) and getattr(device, attr):
                        setattr(existing, attr, getattr(device, attr))
                for ip in device.extra_ips:
                    if ip not in existing.extra_ips and ip != existing.ip:
                        existing.extra_ips.append(ip)
            else:
                found[key] = device
    finally:
        sock.close()

    return sorted(found.values(), key=lambda d: d.ip)


def probe_default_ips(timeout: float = 1.5) -> List[UbntDevice]:
    """
    Probe common Ubiquiti factory-default IPs over TCP 80/443.
    Useful when discovery is blocked but the dish is on default settings.
    """
    results: List[UbntDevice] = []
    for ip in COMMON_DEFAULT_IPS:
        for port in (80, 443):
            try:
                s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                s.settimeout(timeout)
                s.connect((ip, port))
                s.close()
                results.append(
                    UbntDevice(
                        ip=ip,
                        model="Possible Ubiquiti (default IP responding)",
                        source="default-probe",
                        hostname=f"port {port} open",
                    )
                )
                break
            except OSError:
                continue
    return results


def format_table(devices: List[UbntDevice]) -> str:
    if not devices:
        return "No Ubiquiti devices found."

    headers = ("IP ADDRESS", "MODEL", "HOSTNAME", "MAC", "FIRMWARE", "ESSID")
    rows = []
    for d in devices:
        rows.append(
            (
                d.ip,
                d.display_model[:28],
                (d.hostname or "-")[:20],
                d.mac or "-",
                (d.firmware or "-")[:24],
                (d.essid or "-")[:18],
            )
        )

    widths = [len(h) for h in headers]
    for row in rows:
        for i, cell in enumerate(row):
            widths[i] = max(widths[i], len(cell))

    def fmt_row(cols: Tuple[str, ...]) -> str:
        return "  ".join(c.ljust(widths[i]) for i, c in enumerate(cols))

    lines = [
        f"Found {len(devices)} Ubiquiti device(s):",
        "",
        fmt_row(headers),
        "  ".join("-" * w for w in widths),
    ]
    for row in rows:
        lines.append(fmt_row(row))
    lines.append("")
    lines.append("Tip: Open http://<IP> or https://<IP> in a browser (default login often ubnt/ubnt).")
    return "\n".join(lines)


def run_discovery(
    timeout: float = 5.0,
    check_defaults: bool = False,
    broadcast_addr: str = "255.255.255.255",
) -> List[UbntDevice]:
    devices = discover_devices(timeout=timeout, broadcast_addr=broadcast_addr)
    if check_defaults:
        seen: Set[str] = {d.ip for d in devices}
        for probe in probe_default_ips():
            if probe.ip not in seen:
                devices.append(probe)
                seen.add(probe.ip)
    return devices


def main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(
        description="Discover Ubiquiti (UBNT) dish / airMAX device IP addresses on the LAN.",
        epilog="Connect your PC to the same network (or directly to the dish) and run this tool.",
    )
    parser.add_argument(
        "-t",
        "--timeout",
        type=float,
        default=5.0,
        help="Seconds to wait for discovery replies (default: 5)",
    )
    parser.add_argument(
        "-b",
        "--broadcast",
        default="255.255.255.255",
        help="Broadcast address (default: 255.255.255.255)",
    )
    parser.add_argument(
        "--check-defaults",
        action="store_true",
        help="Also probe common factory-default IPs (192.168.1.20, etc.)",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="Print results as JSON",
    )
    args = parser.parse_args(argv)

    print("Scanning for Ubiquiti dishes / radios...", file=sys.stderr)
    print(f"  Protocol : UDP discovery port {DISCOVERY_PORT}", file=sys.stderr)
    print(f"  Timeout  : {args.timeout}s", file=sys.stderr)
    if args.check_defaults:
        print("  Also probing common default IPs...", file=sys.stderr)
    print(file=sys.stderr)

    devices = run_discovery(
        timeout=args.timeout,
        check_defaults=args.check_defaults,
        broadcast_addr=args.broadcast,
    )

    if args.json:
        print(json.dumps([d.to_dict() for d in devices], indent=2))
    else:
        print(format_table(devices))
        if not devices:
            print(
                "\nTroubleshooting:\n"
                "  1. Connect PC to the same LAN / switch as the dish (or direct Ethernet).\n"
                "  2. Disable VPN temporarily.\n"
                "  3. Set a static IP like 192.168.1.100/24 and retry with --check-defaults.\n"
                "  4. Try: python3 ubnt_discover.py --timeout 10 --check-defaults\n"
            )
            return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
