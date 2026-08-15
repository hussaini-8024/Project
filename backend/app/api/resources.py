from __future__ import annotations

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.config import get_settings
from app.database import get_db
from app.deps import current_user, require_staff
from app.models import AuditLog, ComputeNode, Machine, MachineStatus, Role, User
from app.services.capacity import (
    occupancy,
    persist_sample,
    recommend_concurrency,
    recommend_from_loadtests,
    run_synthetic_load,
    sample_host,
)
from app.services.scheduler import drain_queue

router = APIRouter(tags=["resources"])

LOAD_STEPS = [10, 20, 30, 40, 50, 60, 70, 80, 100]


@router.get("/resources")
def resources(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    host = sample_host()
    persist_sample(db, host)
    occ = occupancy(db)
    rec = recommend_concurrency(db)
    settings = get_settings()
    return {
        "host": host.__dict__,
        "occupancy": occ,
        "capacity": rec,
        "thresholds": {
            "normal": settings.threshold_normal,
            "warning": settings.threshold_warning,
            "high": settings.threshold_high,
            "block": settings.threshold_block,
        },
        "distinction": {
            "logged_in": occ["logged_in"],
            "active_labs": occ["active_labs"],
            "running_containers": occ["running_containers"],
            "running_vms": occ["running_vms"],
        },
        "viewer": user.role.value,
    }


@router.get("/resources/scheduler")
def scheduler_status(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    queued = db.query(Machine).filter(Machine.status == MachineStatus.QUEUED).all()
    nodes = db.query(ComputeNode).all()
    return {
        "queued": [
            {
                "id": m.public_id,
                "name": m.name,
                "kind": m.kind.value,
                "ram_mb": m.ram_mb,
                "position": m.queue_position,
                "reason": m.queue_reason,
            }
            for m in queued
        ],
        "nodes": [
            {
                "id": n.id,
                "name": n.name,
                "status": n.status,
                "ram_mb": n.ram_mb,
                "cpu_cores": n.cpu_cores,
                "storage_gb": n.storage_gb,
                "kvm": n.kvm_available,
                "docker": n.docker_available,
            }
            for n in nodes
        ],
        "drained": drain_queue(db),
    }


@router.post("/resources/loadtest")
def loadtest(body: dict | None = None, _: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    steps = (body or {}).get("steps") or LOAD_STEPS
    reports = [run_synthetic_load(db, int(n)) for n in steps]
    summary = recommend_from_loadtests(db)
    return {"reports": reports, "summary": summary}


@router.get("/resources/loadtest")
def loadtest_summary(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    return recommend_from_loadtests(db)


@router.get("/audit")
def audit_logs(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(AuditLog).order_by(AuditLog.created_at.desc()).limit(200)
    if user.role == Role.STUDENT:
        q = q.filter(AuditLog.user_id == user.id)
    return [
        {
            "id": a.id,
            "timestamp": a.created_at.isoformat(),
            "user": a.username,
            "role": a.role,
            "session_id": a.session_id,
            "lab_id": a.lab_id,
            "ip": a.ip,
            "action": a.action,
            "machine": a.machine,
            "resource": a.resource,
            "result": a.result,
            "detail": a.detail,
        }
        for a in q.all()
    ]


@router.get("/settings")
def settings_get(_: User = Depends(require_staff)) -> dict:
    s = get_settings()
    return {
        "app_name": s.app_name,
        "app_env": s.app_env,
        "compute_provider": s.compute_provider,
        "host_total_ram_mb": s.host_total_ram_mb,
        "host_reserve_ram_mb": s.host_reserve_ram_mb,
        "lab_pool_ram_mb": s.lab_pool_ram_mb,
        "host_total_storage_gb": s.host_total_storage_gb,
        "thresholds": {
            "normal": s.threshold_normal,
            "warning": s.threshold_warning,
            "high": s.threshold_high,
            "block": s.threshold_block,
        },
        "snapshot_max_per_student": s.snapshot_max_per_student,
        "backup_retention_days": s.backup_retention_days,
    }


@router.get("/activity")
def activity(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(AuditLog).filter(AuditLog.user_id == user.id).order_by(AuditLog.created_at.desc()).limit(50)
    return [
        {"timestamp": a.created_at.isoformat(), "action": a.action, "resource": a.resource, "result": a.result}
        for a in q.all()
    ]
