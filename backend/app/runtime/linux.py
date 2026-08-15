"""Isolated Linux guests: Alpine rootfs + netns + lab bridge.

Each student lab gets a Linux bridge. Each machine gets a network namespace
and a veth pair on that bridge, so `ping` between lab machines works.
Labs do not share bridges, so Lab A cannot reach Lab B.
"""

from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import tarfile
import urllib.request
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path

from app.config import get_settings
from app.services.netutil import addr as cidr_addr
from app.services.netutil import gateway_ip

ALPINE_URL = (
    "https://dl-cdn.alpinelinux.org/alpine/v3.21/releases/x86_64/"
    "alpine-minirootfs-3.21.3-x86_64.tar.gz"
)


def _safe(value: str, length: int = 15) -> str:
    cleaned = re.sub(r"[^A-Za-z0-9]", "", value)
    return (cleaned or "guest")[:length]


def _run(args: list[str], check: bool = True) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, check=check, capture_output=True, text=True)


def _sudo(args: list[str], check: bool = True) -> subprocess.CompletedProcess[str]:
    return _run(["sudo", "-n", *args], check=check)


def _hostify(name: str) -> str:
    return re.sub(r"[^a-z0-9-]", "-", name.lower()).strip("-") or "guest"


@dataclass
class GuestSpec:
    ref: str
    hostname: str
    ipv4: str
    cidr: str
    lab_key: str
    bridge: str
    peers: list[tuple[str, str]]
    internet: bool = False


class LinuxRuntime:
    def __init__(self) -> None:
        storage = Path(get_settings().storage_root).resolve()
        root = storage.parent / "runtime" if storage.name == "storage" else storage / "runtime"
        self.root = root
        self.alpine = root / "alpine"
        self.guests = root / "guests"
        self.root.mkdir(parents=True, exist_ok=True)
        self.guests.mkdir(parents=True, exist_ok=True)

    def ensure_rootfs(self) -> Path:
        marker = self.alpine / "bin" / "busybox"
        if marker.exists():
            return self.alpine
        self.alpine.mkdir(parents=True, exist_ok=True)
        tarball = self.root / "alpine-minirootfs.tar.gz"
        if not tarball.exists():
            urllib.request.urlretrieve(ALPINE_URL, tarball)
        with tarfile.open(tarball, "r:gz") as tar:
            tar.extractall(self.alpine)
        return self.alpine

    def _guest_dir(self, ref: str) -> Path:
        path = self.guests / _safe(ref, 40)
        path.mkdir(parents=True, exist_ok=True)
        return path

    def _paths(self, ref: str) -> dict[str, Path]:
        base = self._guest_dir(ref)
        return {
            "base": base,
            "upper": base / "upper",
            "work": base / "work",
            "merged": base / "merged",
            "spec": base / "spec.json",
        }

    def save_spec(self, spec: GuestSpec) -> None:
        paths = self._paths(spec.ref)
        paths["spec"].write_text(
            json.dumps(
                {
                    "ref": spec.ref,
                    "hostname": spec.hostname,
                    "ipv4": spec.ipv4,
                    "cidr": spec.cidr,
                    "lab_key": spec.lab_key,
                    "bridge": spec.bridge,
                    "peers": spec.peers,
                    "internet": spec.internet,
                },
                indent=2,
            )
        )

    def load_spec(self, ref: str) -> GuestSpec | None:
        spec_path = self._paths(ref)["spec"]
        if not spec_path.exists():
            return None
        data = json.loads(spec_path.read_text())
        return GuestSpec(
            ref=data["ref"],
            hostname=data["hostname"],
            ipv4=data["ipv4"],
            cidr=data["cidr"],
            lab_key=data["lab_key"],
            bridge=data["bridge"],
            peers=[tuple(p) for p in data.get("peers", [])],
            internet=bool(data.get("internet")),
        )

    def ns_name(self, ref: str) -> str:
        return f"cr{_safe(ref, 12)}"

    def veth_name(self, ref: str) -> str:
        return f"v{_safe(ref, 14)}"

    def bridge_name(self, lab_key: str, configured: str) -> str:
        raw = configured or lab_key
        return _safe(raw, 15)

    def _bridge_up(self, bridge: str, gateway: str, cidr: str) -> None:
        exists = _sudo(["ip", "link", "show", bridge], check=False)
        if exists.returncode != 0:
            _sudo(["ip", "link", "add", bridge, "type", "bridge"])
        # Bridged lab traffic must not be filtered as routed iptables FORWARD.
        for key in (
            "net.bridge.bridge-nf-call-iptables",
            "net.bridge.bridge-nf-call-ip6tables",
            "net.bridge.bridge-nf-call-arptables",
        ):
            _sudo(["sysctl", "-w", f"{key}=0"], check=False)
        _sudo(["ip", "link", "set", bridge, "type", "bridge", "forward_delay", "0"], check=False)
        _sudo(["ip", "link", "set", bridge, "up"])
        shown = _sudo(["ip", "-4", "addr", "show", "dev", bridge], check=False)
        if gateway not in (shown.stdout or ""):
            _sudo(["ip", "addr", "add", cidr_addr(gateway, cidr), "dev", bridge], check=False)

    def _prepare_overlay(self, ref: str, spec: GuestSpec) -> Path:
        self.ensure_rootfs()
        paths = self._paths(ref)
        for key in ("upper", "work", "merged"):
            paths[key].mkdir(parents=True, exist_ok=True)
        merged = paths["merged"]
        if not (merged / "bin" / "busybox").exists():
            # Workspace filesystems here cannot host overlay upperdirs, so each
            # guest gets a small private copy of the shared Alpine rootfs.
            _sudo(["cp", "-a", str(self.alpine) + "/.", str(merged)])
        self._write_guest_files(merged, spec)
        for fs, target in (("proc", "proc"), ("sysfs", "sys"), ("tmpfs", "tmp"), ("tmpfs", "run")):
            dest = merged / target
            dest.mkdir(exist_ok=True)
            _sudo(["mount", "-t", fs, fs, str(dest)], check=False)
        dev = merged / "dev"
        dev.mkdir(exist_ok=True)
        for node in ("null", "zero", "urandom", "random", "tty"):
            src = Path("/dev") / node
            dst = dev / node
            if src.exists():
                if not dst.exists():
                    _sudo(["touch", str(dst)], check=False)
                _sudo(["mount", "--bind", str(src), str(dst)], check=False)
        return merged

    def _write_guest_files(self, merged: Path, spec: GuestSpec) -> None:
        hostname = _hostify(spec.hostname)
        gateway = gateway_ip(spec.cidr)
        hosts = [
            "127.0.0.1\tlocalhost",
            f"{spec.ipv4}\t{hostname}",
            f"{gateway}\tlab-gateway",
        ]
        for ip, name in spec.peers:
            hosts.append(f"{ip}\t{_hostify(name)}")
        etc = merged / "etc"
        _sudo(["mkdir", "-p", str(etc)])
        tmp = self._guest_dir(spec.ref) / "etc-stage"
        tmp.mkdir(exist_ok=True)
        (tmp / "hostname").write_text(hostname + "\n")
        (tmp / "hosts").write_text("\n".join(hosts) + "\n")
        (tmp / "resolv.conf").write_text("nameserver 1.1.1.1\n" if spec.internet else "")
        (tmp / "motd").write_text(
            "University Cyber Range — isolated student laboratory\n"
            "Linux commands run inside this guest. Ping other machines on your lab network.\n"
            "Do not attempt to reach other student labs or the virtualization host.\n"
        )
        (tmp / "profile").write_text(
            f"export HOME=/root\nexport TERM=xterm-256color\n"
            f"export PS1='\\u@{hostname}:\\w\\$ '\n"
            "export PATH=/usr/sbin:/usr/bin:/sbin:/bin\n"
            "hostname $(cat /etc/hostname) 2>/dev/null || true\n"
            "cd /root 2>/dev/null || cd /\n"
            "cat /etc/motd 2>/dev/null || true\n"
        )
        for name in ("hostname", "hosts", "resolv.conf", "motd", "profile"):
            _sudo(["cp", str(tmp / name), str(etc / name)])
        # busybox applets: ensure ping/ash exist as names
        _sudo(["chroot", str(merged), "/bin/busybox", "--install", "-s"], check=False)

    def refresh_hosts(self, spec: GuestSpec) -> None:
        self.save_spec(spec)
        merged = self._paths(spec.ref)["merged"]
        if (merged / "etc").exists():
            self._write_guest_files(merged, spec)

    def _live_addr(self, ref: str) -> str:
        shown = _sudo(
            ["ip", "netns", "exec", self.ns_name(ref), "ip", "-4", "addr", "show", "dev", "eth0"],
            check=False,
        )
        return shown.stdout or ""

    def start(self, spec: GuestSpec) -> None:
        self.save_spec(spec)
        if self.running(spec.ref):
            live = self._live_addr(spec.ref)
            if cidr_addr(spec.ipv4, spec.cidr) in live:
                self.refresh_hosts(spec)
                return
            self.stop(spec.ref)
        bridge = self.bridge_name(spec.lab_key, spec.bridge)
        gateway = gateway_ip(spec.cidr)
        self._bridge_up(bridge, gateway, spec.cidr)
        ns = self.ns_name(spec.ref)
        veth = self.veth_name(spec.ref)
        _sudo(["ip", "netns", "delete", ns], check=False)
        _sudo(["ip", "link", "delete", veth], check=False)
        _sudo(["ip", "netns", "add", ns])
        _sudo(["ip", "link", "add", veth, "type", "veth", "peer", "name", "eth0", "netns", ns])
        _sudo(["ip", "link", "set", veth, "master", bridge])
        _sudo(["ip", "link", "set", veth, "up"])
        _sudo(["ip", "netns", "exec", ns, "ip", "link", "set", "lo", "up"])
        _sudo(["ip", "netns", "exec", ns, "ip", "link", "set", "eth0", "up"])
        _sudo(["ip", "netns", "exec", ns, "ip", "addr", "flush", "dev", "eth0"], check=False)
        _sudo(["ip", "netns", "exec", ns, "ip", "addr", "add", cidr_addr(spec.ipv4, spec.cidr), "dev", "eth0"])
        if spec.internet:
            _sudo(["ip", "netns", "exec", ns, "ip", "route", "add", "default", "via", gateway], check=False)
        self._prepare_overlay(spec.ref, spec)

    def stop(self, ref: str) -> None:
        ns = self.ns_name(ref)
        veth = self.veth_name(ref)
        paths = self._paths(ref)
        merged = paths["merged"]
        if merged.exists():
            for sub in ("proc", "sys", "tmp", "run", "dev/null", "dev/zero", "dev/urandom", "dev/random", "dev/tty"):
                _sudo(["umount", "-l", str(merged / sub)], check=False)
            _sudo(["umount", "-l", str(merged)], check=False)
        _sudo(["ip", "link", "delete", veth], check=False)
        _sudo(["ip", "netns", "delete", ns], check=False)

    def delete(self, ref: str) -> None:
        self.stop(ref)
        shutil.rmtree(self._guest_dir(ref), ignore_errors=True)

    def running(self, ref: str) -> bool:
        ns = self.ns_name(ref)
        result = _sudo(["ip", "netns", "list"], check=False)
        return ns in (result.stdout or "")

    def shell_command(self, ref: str) -> list[str]:
        spec = self.load_spec(ref)
        if not spec:
            raise FileNotFoundError(f"guest {ref} has no spec")
        if not self.running(ref):
            self.start(spec)
        merged = self._paths(ref)["merged"]
        if not (merged / "bin" / "busybox").exists():
            self._prepare_overlay(ref, spec)
        ns = self.ns_name(ref)
        hostname = _hostify(spec.hostname)
        return [
            "sudo",
            "-n",
            "ip",
            "netns",
            "exec",
            ns,
            "env",
            f"HOSTNAME={hostname}",
            "TERM=linux",
            "HOME=/root",
            "PATH=/usr/sbin:/usr/bin:/sbin:/bin",
            "PS1=" + f"root@{hostname}:\\w\\$ ",
            "unshare",
            "--uts",
            "chroot",
            str(merged),
            "/bin/busybox",
            "ash",
            "-l",
        ]


@lru_cache
def get_runtime() -> LinuxRuntime:
    return LinuxRuntime()
