from __future__ import annotations

from app.models import Machine
from app.providers import get_provider
from app.runtime.linux import GuestSpec, get_runtime


def hostify(name: str) -> str:
    return "".join(ch if ch.isalnum() else "-" for ch in name.lower()).strip("-") or "guest"


def network_peers(network) -> list[tuple[str, str]]:
    if not network:
        return []
    return [(iface.ipv4, hostify(iface.machine.name)) for iface in network.interfaces if iface.machine]


def spec_for(machine: Machine) -> GuestSpec | None:
    if not machine.interfaces:
        return None
    iface = machine.interfaces[0]
    net = iface.network
    if not net:
        lab = machine.lab
        net = lab.network if lab else None
    if not net:
        return None
    lab = machine.lab
    return GuestSpec(
        ref=machine.provider_ref or machine.public_id,
        hostname=hostify(machine.name),
        ipv4=iface.ipv4,
        cidr=net.cidr,
        lab_key=net.namespace,
        bridge=net.bridge,
        peers=network_peers(net),
        internet=bool(machine.internet and ((lab.internet_enabled if lab else False) or net.internet)),
    )


def provision_guest(machine: Machine) -> None:
    spec = spec_for(machine)
    if not spec:
        return
    runtime = get_runtime()
    runtime.save_spec(spec)
    provider = get_provider()
    if hasattr(provider, "provision"):
        result = provider.provision(spec)
        if not result.ok:
            raise RuntimeError(result.message)
    else:
        runtime.start(spec)
    iface = machine.interfaces[0] if machine.interfaces else None
    net = iface.network if iface else None
    if not net:
        return
    for peer_if in net.interfaces:
        peer = peer_if.machine
        if not peer or peer.id == machine.id:
            continue
        peer_spec = spec_for(peer)
        if not peer_spec:
            continue
        runtime.save_spec(peer_spec)
        if runtime.running(peer_spec.ref):
            runtime.refresh_hosts(peer_spec)
