from __future__ import annotations

import secrets
from datetime import datetime
from sqlalchemy.orm import Session

from app.models import (
    LabNetwork,
    Machine,
    MachineKind,
    MachineStatus,
    MachineTemplate,
    NetworkInterface,
    Role,
    Snapshot,
    StorageVolume,
    StudentLab,
    User,
)
from app.providers import get_provider
from app.security import public_id
from app.services import audit
from app.services.netutil import DEFAULT_LAB_CIDR, next_host, parse_cidr
from app.services.scheduler import evaluate, start_machine


def ensure_lab(db: Session, user: User) -> StudentLab:
    if user.lab:
        return user.lab
    lab = StudentLab(
        public_id=public_id("LAB"),
        student_id=user.id,
        name=f"{user.username} Laboratory",
        status="ready",
        internet_enabled=False,
        config={"isolation": "strict", "plane": "student-workload"},
    )
    db.add(lab)
    db.flush()
    vlan = 1000 + (int(user.public_id.encode().hex()[:4], 16) % 3000)
    net = LabNetwork(
        lab_id=lab.id,
        name=f"net-{user.public_id}",
        cidr=DEFAULT_LAB_CIDR,
        vlan_id=vlan,
        namespace=f"ns-{user.public_id.lower()}",
        isolated=True,
        internet=False,
        bridge=f"br-{user.public_id[-6:].lower()}",
        kind="student",
        created_by=user.id,
    )
    db.add(net)
    db.commit()
    db.refresh(lab)
    return lab


def restore_lab(db: Session, user: User) -> StudentLab:
    lab = ensure_lab(db, user)
    lab.last_restored_at = datetime.utcnow()
    lab.status = "ready"
    db.commit()
    return lab


def next_ip(network: LabNetwork, db: Session) -> str:
    used = {i.ipv4 for i in network.interfaces}
    return next_host(network, used)


def _next_vlan(db: Session) -> int:
    used = {row[0] for row in db.query(LabNetwork.vlan_id).all()}
    for vlan in range(1000, 4095):
        if vlan not in used:
            return vlan
    raise RuntimeError("VLAN pool exhausted")


def resolve_lab(db: Session, lab_id: str | None) -> StudentLab | None:
    if not lab_id:
        return None
    return (
        db.query(StudentLab)
        .filter((StudentLab.id == lab_id) | (StudentLab.public_id == lab_id))
        .first()
    )


def create_network(
    db: Session,
    user: User,
    *,
    name: str,
    cidr: str = DEFAULT_LAB_CIDR,
    lab_id: str | None = None,
    isolated: bool = True,
    internet: bool = False,
    kind: str = "admin",
) -> LabNetwork:
    net = parse_cidr(cidr)
    lab = resolve_lab(db, lab_id)
    if lab_id and not lab:
        raise ValueError("Lab not found")
    if not lab:
        lab = ensure_lab(db, user)
    token = secrets.token_hex(3)
    network = LabNetwork(
        lab_id=lab.id,
        name=name.strip() or f"net-{token}",
        cidr=str(net),
        vlan_id=_next_vlan(db),
        namespace=f"ns-{token}",
        isolated=isolated,
        internet=internet,
        bridge=f"br{token}"[:15],
        kind=kind,
        created_by=user.id,
    )
    db.add(network)
    audit.record(db, user=user, action="network.create", resource=network.name, detail=network.cidr)
    db.commit()
    db.refresh(network)
    return network


def create_machine(
    db: Session,
    user: User,
    *,
    name: str,
    template: MachineTemplate | None,
    kind: MachineKind,
    vcpu: int,
    ram_mb: int,
    disk_gb: int,
    internet: bool,
    isolated: bool,
    ephemeral: bool,
    ip: str,
    network_id: str | None = None,
    owner: User | None = None,
) -> tuple[Machine, dict]:
    owner = owner or user
    lab = ensure_lab(db, owner)
    target_net = None
    if network_id:
        target_net = db.get(LabNetwork, network_id)
        if not target_net:
            raise ValueError("Network not found")
        if target_net.lab_id:
            lab = target_net.lab or lab
    quota_user = user if user.role != Role.STUDENT else owner
    decision = evaluate(db, quota_user, kind=kind, ram_mb=ram_mb, vcpu=vcpu, disk_gb=disk_gb, template=template)
    kind = MachineKind(decision.recommended_kind)
    machine = Machine(
        public_id=public_id("MCH"),
        lab_id=lab.id,
        owner_id=owner.id,
        template_id=template.id if template else None,
        name=name,
        kind=kind,
        status=MachineStatus.QUEUED if decision.queued else MachineStatus.CREATING,
        vcpu=vcpu,
        ram_mb=ram_mb,
        disk_gb=disk_gb,
        internet=internet and lab.internet_enabled,
        isolated=isolated,
        ephemeral=ephemeral,
        queue_position=decision.queue_position,
        queue_reason=decision.reason if decision.queued else "",
        node_id=decision.node_id,
    )
    db.add(machine)
    db.flush()
    attach = target_net or lab.network
    if attach:
        iface = NetworkInterface(
            network_id=attach.id,
            machine_id=machine.id,
            mac="02:cr:" + ":".join(f"{secrets.randbelow(256):02x}" for _ in range(4)),
            ipv4=next_ip(attach, db),
        )
        # mac format fix - keep simple valid-ish
        iface.mac = "02:%02x:%02x:%02x:%02x:%02x" % tuple(secrets.randbelow(256) for _ in range(5))
        db.add(iface)
    vol = StorageVolume(
        owner_id=owner.id,
        lab_id=lab.id,
        machine_id=machine.id,
        name=f"{name}-data",
        category="student",
        size_gb=float(disk_gb),
        persistent=not ephemeral,
        path=f"/var/lib/cyberrange/volumes/{machine.public_id}",
    )
    db.add(vol)

    provider = get_provider()
    image = template.image_ref if template else "ubuntu:22.04"
    net_name = attach.namespace if attach else ""
    if kind == MachineKind.CONTAINER:
        result = provider.create_container(machine.public_id, image, vcpu, ram_mb, net_name)
    else:
        result = provider.create_vm(machine.public_id, image, vcpu, ram_mb, disk_gb, net_name)
    machine.provider_ref = result.ref
    if not result.ok and not decision.queued:
        machine.status = MachineStatus.ERROR
        machine.error_message = result.message
        db.commit()
        return machine, {"decision": decision.__dict__, "provider": result.message}

    if decision.queued:
        db.commit()
        return machine, {"decision": decision.__dict__}
    if not decision.allowed:
        machine.status = MachineStatus.ERROR
        machine.error_message = decision.reason
        db.commit()
        return machine, {"decision": decision.__dict__}

    machine.status = MachineStatus.STOPPED
    db.commit()
    start_machine(db, machine, quota_user)
    audit.record(db, user=user, action="machine.create", resource=machine.public_id, machine=machine.name, ip=ip)
    db.commit()
    return machine, {"decision": decision.__dict__}


def snapshot_machine(db: Session, user: User, machine: Machine, name: str) -> Snapshot:
    qmax = user.quota.max_snapshots if user.quota else 2
    count = db.query(Snapshot).filter(Snapshot.owner_id == user.id).count()
    if count >= qmax:
        raise ValueError(f"Snapshot quota reached ({qmax})")
    provider = get_provider()
    result = provider.snapshot(machine.provider_ref or machine.public_id, name)
    snap = Snapshot(machine_id=machine.id, owner_id=user.id, name=name, size_mb=max(64, machine.disk_gb * 40))
    db.add(snap)
    audit.record(db, user=user, action="snapshot.create", resource=snap.id, machine=machine.name)
    db.commit()
    return snap
