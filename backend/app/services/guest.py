from __future__ import annotations

from app.models import Machine
from app.providers import get_provider
from app.runtime.linux import GuestSpec, get_runtime


def hostify(name: str) -> str:
    return "".join(ch if ch.isalnum() else "-" for ch in name.lower()).strip("-") or "guest"


def lab_peers(lab) -> list[tuple[str, str]]:
    if not lab or not lab.network:
        return []
    return [(iface.ipv4, hostify(iface.machine.name)) for iface in lab.network.interfaces if iface.machine]


def spec_for(machine: Machine) -> GuestSpec | None:
    lab = machine.lab
    if not lab or not lab.network or not machine.interfaces:
        return None
    iface = machine.interfaces[0]
    return GuestSpec(
        ref=machine.provider_ref or machine.public_id,
        hostname=hostify(machine.name),
        ipv4=iface.ipv4,
        cidr=lab.network.cidr,
        lab_key=lab.network.namespace,
        bridge=lab.network.bridge,
        peers=lab_peers(lab),
        internet=bool(machine.internet and lab.internet_enabled),
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
    lab = machine.lab
    if not lab:
        return
    for peer in lab.machines:
        if peer.id == machine.id or not peer.interfaces:
            continue
        peer_spec = spec_for(peer)
        if not peer_spec:
            continue
        runtime.save_spec(peer_spec)
        if runtime.running(peer_spec.ref):
            runtime.refresh_hosts(peer_spec)
