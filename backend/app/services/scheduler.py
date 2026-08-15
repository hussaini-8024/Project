from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sqlalchemy.orm import Session

from app.config import get_settings
from app.models import (
    ComputeNode,
    Machine,
    MachineKind,
    MachineStatus,
    MachineTemplate,
    QuotaProfile,
    User,
)
from app.providers import get_provider
from app.services.capacity import occupancy, sample_host


@dataclass
class ScheduleDecision:
    allowed: bool
    queued: bool
    reason: str
    node_id: str | None
    alternatives: list[str]
    wait_seconds: int
    queue_position: int | None
    estimated_ram_mb: int
    estimated_vcpu: int
    estimated_disk_gb: int
    recommended_kind: str


def student_quota(user: User) -> QuotaProfile:
    if user.quota:
        return user.quota
    return QuotaProfile(
        name="default",
        max_containers=3,
        max_running_containers=2,
        max_ram_mb=2048,
        max_vcpu=2,
        max_vms=0,
        max_storage_gb=2,
        max_snapshots=2,
    )


def quota_ok(db: Session, user: User, kind: MachineKind, ram_mb: int, vcpu: int, disk_gb: int) -> str | None:
    q = student_quota(user)
    machines = db.query(Machine).filter(Machine.owner_id == user.id).all()
    running = [m for m in machines if m.status in (MachineStatus.RUNNING, MachineStatus.STARTING, MachineStatus.PAUSED)]
    containers = [m for m in machines if m.kind == MachineKind.CONTAINER]
    vms = [m for m in machines if m.kind == MachineKind.VM]
    if kind == MachineKind.CONTAINER and len(containers) >= q.max_containers:
        return f"Container quota reached ({q.max_containers})"
    if kind == MachineKind.VM and len(vms) >= q.max_vms:
        return f"VM quota reached ({q.max_vms})"
    if kind == MachineKind.CONTAINER and len([m for m in running if m.kind == MachineKind.CONTAINER]) >= q.max_running_containers:
        return f"Running container quota reached ({q.max_running_containers})"
    used_ram = sum(m.ram_mb for m in running) + ram_mb
    used_cpu = sum(m.vcpu for m in running) + vcpu
    used_disk = sum(m.disk_gb for m in machines) + disk_gb
    if used_ram > q.max_ram_mb:
        return f"RAM quota exceeded ({used_ram} > {q.max_ram_mb} MB)"
    if used_cpu > q.max_vcpu:
        return f"vCPU quota exceeded ({used_cpu} > {q.max_vcpu})"
    if used_disk > q.max_storage_gb:
        return f"Storage quota exceeded ({used_disk} > {q.max_storage_gb} GB)"
    return None


def pick_node(db: Session, kind: MachineKind) -> ComputeNode | None:
    nodes = db.query(ComputeNode).filter(ComputeNode.status == "healthy").all()
    if not nodes:
        return None
    scored: list[tuple[float, ComputeNode]] = []
    for n in nodes:
        if kind == MachineKind.VM and not n.kvm_available:
            continue
        if kind == MachineKind.CONTAINER and not n.docker_available:
            continue
        load = 0.0
        for m in db.query(Machine).filter(Machine.node_id == n.id, Machine.status == MachineStatus.RUNNING):
            load += m.ram_mb / max(1, n.ram_mb)
        scored.append((load, n))
    if not scored:
        return None
    scored.sort(key=lambda x: x[0])
    return scored[0][1]


def recommend_kind(template: MachineTemplate | None, requested: MachineKind) -> tuple[MachineKind, list[str]]:
    alts: list[str] = []
    if not template:
        return requested, alts
    if requested == MachineKind.VM and not template.requires_kernel and not template.requires_full_os:
        alts.append(
            f"Use a container for {template.name} instead of a full VM to save RAM and raise student density."
        )
        return MachineKind.CONTAINER, alts
    if template.recommended_kind == MachineKind.CONTAINER and requested == MachineKind.VM:
        alts.append("Platform recommends container-first for this exercise.")
        return MachineKind.CONTAINER, alts
    return requested, alts


def evaluate(
    db: Session,
    user: User,
    *,
    kind: MachineKind,
    ram_mb: int,
    vcpu: int,
    disk_gb: int,
    template: MachineTemplate | None,
) -> ScheduleDecision:
    settings = get_settings()
    recommended, alts = recommend_kind(template, kind)
    kind = recommended
    qerr = quota_ok(db, user, kind, ram_mb, vcpu, disk_gb)
    if qerr:
        return ScheduleDecision(False, False, qerr, None, alts + ["Stop another machine to free quota"], 0, None, ram_mb, vcpu, disk_gb, kind.value)

    host = sample_host()
    occ = occupancy(db)
    remaining_ram = settings.lab_pool_ram_mb - occ["allocated_ram_mb"]
    remaining_disk = settings.lab_pool_storage_gb - occ["allocated_disk_gb"]
    heavy = kind == MachineKind.VM or ram_mb >= 2048

    if remaining_ram < ram_mb:
        return _queue(db, "Insufficient lab-pool RAM", alts, ram_mb, vcpu, disk_gb, kind, heavy)
    if remaining_disk < disk_gb:
        return _queue(db, "Insufficient lab-pool storage", alts, ram_mb, vcpu, disk_gb, kind, heavy)
    if host.level == "block" and heavy:
        alts.append("Start a lightweight container lab instead of a full VM")
        return _queue(db, "Host above emergency threshold — heavy labs are queued", alts, ram_mb, vcpu, disk_gb, kind, heavy)
    if host.level in ("high", "block") and heavy:
        return _queue(db, "High load — queueing resource-intensive request", alts, ram_mb, vcpu, disk_gb, kind, heavy)

    node = pick_node(db, kind)
    if not node:
        return _queue(db, "No healthy compute node can accept this workload", alts, ram_mb, vcpu, disk_gb, kind, heavy)

    return ScheduleDecision(True, False, "Resources available", node.id, alts, 0, None, ram_mb, vcpu, disk_gb, kind.value)


def _queue(db: Session, reason: str, alts: list[str], ram: int, cpu: int, disk: int, kind: MachineKind, heavy: bool) -> ScheduleDecision:
    queued = db.query(Machine).filter(Machine.status == MachineStatus.QUEUED).count()
    pos = queued + 1
    wait = pos * (90 if heavy else 25)
    if kind == MachineKind.VM:
        alts.append("Try DVWA / Juice Shop / WebGoat as containers")
    return ScheduleDecision(False, True, reason, None, alts, wait, pos, ram, cpu, disk, kind.value)


def start_machine(db: Session, machine: Machine, user: User) -> ScheduleDecision:
    template = machine.template
    decision = evaluate(
        db,
        user,
        kind=machine.kind,
        ram_mb=machine.ram_mb,
        vcpu=machine.vcpu,
        disk_gb=machine.disk_gb,
        template=template,
    )
    if decision.queued:
        machine.status = MachineStatus.QUEUED
        machine.queue_position = decision.queue_position
        machine.queue_reason = decision.reason
        db.commit()
        return decision
    if not decision.allowed:
        machine.status = MachineStatus.ERROR
        machine.error_message = decision.reason
        db.commit()
        return decision

    machine.status = MachineStatus.STARTING
    machine.node_id = decision.node_id
    db.commit()
    provider = get_provider()
    result = provider.start(machine.provider_ref or machine.public_id, machine.kind.value)
    if not result.ok:
        machine.status = MachineStatus.ERROR
        machine.error_message = result.message
        db.commit()
        decision.allowed = False
        decision.reason = result.message
        return decision
    machine.status = MachineStatus.RUNNING
    machine.last_started_at = datetime.utcnow()
    machine.queue_position = None
    machine.queue_reason = ""
    db.commit()
    return decision


def drain_queue(db: Session) -> int:
    queued = (
        db.query(Machine)
        .filter(Machine.status == MachineStatus.QUEUED)
        .order_by(Machine.created_at.asc())
        .all()
    )
    started = 0
    for m in queued:
        owner = db.get(User, m.owner_id)
        if not owner:
            continue
        d = start_machine(db, m, owner)
        if d.allowed and not d.queued:
            started += 1
        else:
            break
    return started
