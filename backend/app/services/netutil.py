from __future__ import annotations

from ipaddress import IPv4Address, IPv4Network

from app.models import LabNetwork

DEFAULT_LAB_CIDR = "10.0.0.0/8"


def parse_cidr(cidr: str) -> IPv4Network:
    net = IPv4Network(cidr, strict=False)
    if net.prefixlen < 8:
        raise ValueError("Prefix length must be at least /8")
    if net.prefixlen > 30:
        raise ValueError("Prefix length must be /30 or larger")
    if net.network_address == IPv4Address("0.0.0.0"):
        raise ValueError("0.0.0.0/8 is reserved; use 10.0.0.0/8 for the private lab range")
    if net.network_address == IPv4Address("127.0.0.0"):
        raise ValueError("127.0.0.0/8 is loopback and cannot be used as a lab network")
    return net


def gateway_ip(cidr: str) -> str:
    net = IPv4Network(cidr, strict=False)
    return str(next(net.hosts()))


def prefix_len(cidr: str) -> int:
    return IPv4Network(cidr, strict=False).prefixlen


def addr(ip: str, cidr: str) -> str:
    return f"{ip}/{prefix_len(cidr)}"


def next_host(network: LabNetwork, used: set[str]) -> str:
    net = IPv4Network(network.cidr, strict=False)
    gateway = gateway_ip(network.cidr)
    reserved = used | {str(net.network_address), str(net.broadcast_address), gateway}
    for host in net.hosts():
        ip = str(host)
        if ip not in reserved:
            return ip
    raise RuntimeError("Lab network exhausted")


def readdress_slash8(network: LabNetwork, cidr: str = DEFAULT_LAB_CIDR) -> list[tuple[str, str]]:
    """Move a student network onto the private /8. Keeps the last-octet host number when possible."""
    changes: list[tuple[str, str]] = []
    network.cidr = cidr
    gateway = IPv4Address(gateway_ip(cidr))
    taken = {str(gateway)}
    for idx, iface in enumerate(network.interfaces):
        try:
            host_num = int(IPv4Address(iface.ipv4)) & 0xFF
            if host_num < 2:
                host_num = 2 + idx
        except ValueError:
            host_num = 2 + idx
        candidate = IPv4Address(int(gateway) + host_num - 1)
        while str(candidate) in taken:
            candidate = IPv4Address(int(candidate) + 1)
        new_ip = str(candidate)
        taken.add(new_ip)
        if iface.ipv4 != new_ip:
            changes.append((iface.ipv4, new_ip))
        iface.ipv4 = new_ip
    return changes
