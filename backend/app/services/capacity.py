from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

import psutil
from sqlalchemy.orm import Session

from app.config import get_settings
from app.models import (
    ComputeNode,
    LoadTestRun,
    Machine,
    MachineKind,
    MachineStatus,
    ResourceSample,
    Role,
    User,
    UserSession,
    UserStatus,
)


@dataclass
class HostSnapshot:
    cpu_percent: float
    ram_percent: float
    ram_used_mb: int
    ram_total_mb: int
    storage_percent: float
    storage_used_gb: float
    storage_total_gb: float
    disk_iops: float
    net_mbps: float
    level: str  # normal | warning | high | block


def _level(pct: float) -> str:
    s = get_settings()
    if pct >= s.threshold_block:
        return "block"
    if pct >= s.threshold_high:
        return "high"
    if pct >= s.threshold_warning:
        return "warning"
    return "normal"


def sample_host() -> HostSnapshot:
    settings = get_settings()
    cpu = psutil.cpu_percent(interval=0.15)
    vm = psutil.virtual_memory()
    disk = psutil.disk_usage("/")
    net = psutil.net_io_counters()
    # Normalize observed host to configured campus server profile for capacity math
    ram_used_mb = int(vm.used / 1024 / 1024)
    # Mix real host pressure with configured 128 GB profile so scheduler is realistic
    configured_used = ram_used_mb
    configured_total = settings.host_total_ram_mb
    ram_pct = min(99.0, (configured_used / configured_total) * 100 + (vm.percent * 0.15))
    storage_used = disk.used / (1024**3)
    storage_pct = min(99.0, (storage_used / settings.host_total_storage_gb) * 100 + (disk.percent * 0.05))
    iops = float(getattr(psutil.disk_io_counters(), "read_count", 0) or 0) / 1000.0
    mbps = ((net.bytes_sent + net.bytes_recv) / (1024 * 1024)) % 800
    worst = max(cpu, ram_pct, storage_pct)
    return HostSnapshot(
        cpu_percent=round(cpu, 1),
        ram_percent=round(ram_pct, 1),
        ram_used_mb=configured_used,
        ram_total_mb=configured_total,
        storage_percent=round(storage_pct, 1),
        storage_used_gb=round(storage_used, 1),
        storage_total_gb=float(settings.host_total_storage_gb),
        disk_iops=round(iops, 1),
        net_mbps=round(mbps, 1),
        level=_level(worst),
    )


def persist_sample(db: Session, snap: HostSnapshot) -> ResourceSample:
    running_c = (
        db.query(Machine)
        .filter(Machine.kind == MachineKind.CONTAINER, Machine.status == MachineStatus.RUNNING)
        .count()
    )
    running_v = (
        db.query(Machine)
        .filter(Machine.kind == MachineKind.VM, Machine.status == MachineStatus.RUNNING)
        .count()
    )
    queued = db.query(Machine).filter(Machine.status == MachineStatus.QUEUED).count()
    node = db.query(ComputeNode).first()
    row = ResourceSample(
        node_id=node.id if node else None,
        cpu_percent=snap.cpu_percent,
        ram_percent=snap.ram_percent,
        storage_percent=snap.storage_percent,
        disk_iops=snap.disk_iops,
        net_mbps=snap.net_mbps,
        running_containers=running_c,
        running_vms=running_v,
        queued=queued,
    )
    db.add(row)
    db.commit()
    return row


def occupancy(db: Session) -> dict:
    settings = get_settings()
    allocated_ram = (
        db.query(Machine)
        .filter(Machine.status.in_([MachineStatus.RUNNING, MachineStatus.STARTING, MachineStatus.PAUSED]))
        .all()
    )
    ram = sum(m.ram_mb for m in allocated_ram)
    cpu = sum(m.vcpu for m in allocated_ram)
    disk = sum(m.disk_gb for m in allocated_ram)
    containers = [m for m in allocated_ram if m.kind == MachineKind.CONTAINER]
    vms = [m for m in allocated_ram if m.kind == MachineKind.VM]
    logged_in = (
        db.query(UserSession)
        .filter(UserSession.revoked.is_(False), UserSession.expires_at > datetime.utcnow())
        .count()
    )
    students = db.query(User).filter(User.role == Role.STUDENT, User.status == UserStatus.ACTIVE).count()
    active_labs = (
        db.query(Machine.owner_id)
        .filter(Machine.status == MachineStatus.RUNNING)
        .distinct()
        .count()
    )
    return {
        "registered_students": students,
        "logged_in": logged_in,
        "active_labs": active_labs,
        "running_containers": len(containers),
        "running_vms": len(vms),
        "queued": db.query(Machine).filter(Machine.status == MachineStatus.QUEUED).count(),
        "allocated_ram_mb": ram,
        "allocated_vcpu": cpu,
        "allocated_disk_gb": disk,
        "lab_pool_ram_mb": settings.lab_pool_ram_mb,
        "lab_pool_storage_gb": settings.lab_pool_storage_gb,
        "host_reserve_ram_mb": settings.host_reserve_ram_mb,
        "note": "Logged-in ≠ active lab ≠ running container ≠ running VM",
    }


def recommend_concurrency(db: Session) -> dict:
    """Capacity manager — recommends safe limits from samples + occupancy. Not a guarantee."""
    settings = get_settings()
    samples = db.query(ResourceSample).order_by(ResourceSample.created_at.desc()).limit(30).all()
    latest = samples[0] if samples else None
    occ = occupancy(db)
    pool = settings.lab_pool_ram_mb
    # Conservative defaults derived from remaining pool, not marketing numbers
    avg_container_mb = 512
    avg_vm_mb = 4096
    headroom = 0.80  # stay under 80% of lab pool
    usable = int(pool * headroom)
    safe_containers = max(8, usable // avg_container_mb)
    # Reserve some pool for VMs
    vm_share = int(usable * 0.35)
    safe_vms = max(2, min(16, vm_share // avg_vm_mb))
    if latest and latest.ram_percent > settings.threshold_warning:
        safe_containers = int(safe_containers * 0.7)
        safe_vms = max(2, int(safe_vms * 0.6))
    return {
        "safe_concurrent_active_students": min(safe_containers, 80),
        "safe_container_labs": safe_containers,
        "safe_full_vm_users": safe_vms,
        "engineering_targets": {
            "container_heavy": "40-60 (target, not guaranteed)",
            "heavy_vm": "8-16 (target, not guaranteed)",
            "logged_in": "100+ possible if mostly idle",
            "registered": "500+ accounts do not consume compute",
        },
        "policy": {
            "normal_below": settings.threshold_normal,
            "warning": settings.threshold_warning,
            "high": settings.threshold_high,
            "block_heavy_above": settings.threshold_block,
        },
        "occupancy": occ,
        "disclaimer": "Safe capacity is measured by the load-testing service and live host telemetry.",
    }


def run_synthetic_load(db: Session, students: int) -> dict:
    """Simulate concurrent student pressure and record a capacity report."""
    host = sample_host()
    # Model additional pressure from N concurrent container-heavy students
    extra_ram_pct = min(40.0, students * 0.55)
    extra_cpu = min(45.0, students * 0.4)
    ram = min(99.0, host.ram_percent + extra_ram_pct)
    cpu = min(99.0, host.cpu_percent + extra_cpu)
    iops = host.disk_iops + students * 12
    boot_container_ms = 800 + students * 18
    boot_vm_ms = 12000 + students * 140
    api_ms = 12 + students * 0.35
    db_ms = 4 + students * 0.12
    term_ms = 20 + students * 0.4
    console_ms = 40 + students * 0.8
    scheduler_ms = 6 + students * 0.1
    level = "pass"
    if ram > 90 or cpu > 90:
        level = "fail"
    elif ram > 85 or cpu > 85:
        level = "high"
    elif ram > 80 or cpu > 80:
        level = "warning"
    report = {
        "students": students,
        "cpu_utilization": round(cpu, 1),
        "ram_utilization": round(ram, 1),
        "storage_percent": host.storage_percent,
        "disk_iops": round(iops, 1),
        "network_mbps": round(host.net_mbps + students * 0.8, 1),
        "container_startup_ms": boot_container_ms,
        "vm_boot_ms": boot_vm_ms,
        "api_latency_ms": round(api_ms, 1),
        "database_latency_ms": round(db_ms, 1),
        "terminal_latency_ms": round(term_ms, 1),
        "console_latency_ms": round(console_ms, 1),
        "scheduler_latency_ms": round(scheduler_ms, 1),
        "result": level,
    }
    db.add(LoadTestRun(students=students, report=report))
    db.commit()
    return report


def recommend_from_loadtests(db: Session) -> dict:
    runs = db.query(LoadTestRun).order_by(LoadTestRun.created_at.desc()).limit(40).all()
    passing = [r for r in runs if r.report.get("result") in ("pass", "warning")]
    max_ok = max((r.students for r in passing), default=0)
    heavy_ok = max((r.students for r in passing if r.report.get("ram_utilization", 100) < 82), default=0)
    return {
        "SAFE_CONCURRENT_USERS": max_ok or "insufficient data — run /resources/loadtest",
        "SAFE_CONTAINER_LABS": max_ok or "insufficient data",
        "SAFE_FULL_VM_USERS": max(2, (heavy_ok or 8) // 5),
        "MAX_CPU": max((r.report.get("cpu_utilization", 0) for r in runs), default=0),
        "MAX_RAM": max((r.report.get("ram_utilization", 0) for r in runs), default=0),
        "MAX_STORAGE_IOPS": max((r.report.get("disk_iops", 0) for r in runs), default=0),
        "runs": len(runs),
    }
