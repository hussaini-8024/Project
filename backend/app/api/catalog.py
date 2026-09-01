from __future__ import annotations

import hashlib
from pathlib import Path

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.config import get_settings
from app.database import get_db
from app.deps import current_user, require_admin, require_staff
from app.models import (
    Backup,
    ContainerImage,
    IsoImage,
    IsoStatus,
    LabNetwork,
    MachineKind,
    MachineTemplate,
    QuotaProfile,
    Role,
    Snapshot,
    StorageVolume,
    User,
)
from app.services import audit
from app.services.labs import create_machine, create_network, snapshot_machine
from app.services.netutil import DEFAULT_LAB_CIDR
from app.api.labs import _machine, _owned

router = APIRouter(tags=["catalog"])


@router.get("/images")
def images(_: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    return [
        {
            "id": i.id,
            "name": i.name,
            "tag": i.tag,
            "size_mb": i.size_mb,
            "shared": i.shared,
            "approved": i.approved,
            "description": i.description,
        }
        for i in db.query(ContainerImage).all()
    ]


@router.get("/isos")
def isos(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(IsoImage)
    if user.role.value == "student":
        q = q.filter(IsoImage.approved_for_students.is_(True), IsoImage.status == IsoStatus.APPROVED)
    return [
        {
            "id": i.id,
            "name": i.name,
            "filename": i.filename,
            "size_bytes": i.size_bytes,
            "sha256": i.sha256,
            "status": i.status.value,
            "os_family": i.os_family,
            "approved_for_students": i.approved_for_students,
            "created_at": i.created_at.isoformat(),
        }
        for i in q.all()
    ]


@router.post("/isos")
async def upload_iso(
    file: UploadFile = File(...),
    user: User = Depends(require_staff),
    db: Session = Depends(get_db),
) -> dict:
    settings = get_settings()
    dest_dir = Path(settings.storage_root) / "isos"
    dest_dir.mkdir(parents=True, exist_ok=True)
    data = await file.read()
    max_bytes = settings.iso_max_gb * 1024 * 1024 * 1024
    if len(data) > max_bytes:
        raise HTTPException(400, f"ISO exceeds {settings.iso_max_gb} GB limit")
    digest = hashlib.sha256(data).hexdigest()
    path = dest_dir / (file.filename or f"{digest}.iso")
    path.write_bytes(data)
    iso = IsoImage(
        name=file.filename or "upload.iso",
        filename=path.name,
        size_bytes=len(data),
        sha256=digest,
        status=IsoStatus.PENDING,
        uploaded_by=user.id,
        os_family="unknown",
        approved_for_students=False,
    )
    db.add(iso)
    audit.record(db, user=user, action="iso.upload", resource=iso.filename)
    db.commit()
    return {"id": iso.id, "sha256": digest, "size_bytes": len(data), "status": iso.status.value}


@router.post("/isos/{iso_id}/approve")
def approve_iso(iso_id: str, user: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    iso = db.get(IsoImage, iso_id)
    if not iso:
        raise HTTPException(404, "ISO not found")
    iso.status = IsoStatus.APPROVED
    iso.approved_for_students = True
    audit.record(db, user=user, action="iso.approve", resource=iso.filename)
    db.commit()
    return {"ok": True}


@router.delete("/isos/{iso_id}")
def delete_iso(iso_id: str, user: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    iso = db.get(IsoImage, iso_id)
    if not iso:
        raise HTTPException(404, "ISO not found")
    audit.record(db, user=user, action="iso.delete", resource=iso.filename)
    db.delete(iso)
    db.commit()
    return {"ok": True}


def _network(n: LabNetwork) -> dict:
    return {
        "id": n.id,
        "name": n.name,
        "cidr": n.cidr,
        "vlan_id": n.vlan_id,
        "namespace": n.namespace,
        "isolated": n.isolated,
        "internet": n.internet,
        "bridge": n.bridge,
        "kind": n.kind,
        "created_by": n.created_by,
        "lab_id": n.lab.public_id if n.lab else None,
        "lab_name": n.lab.name if n.lab else None,
        "interfaces": [
            {
                "ip": i.ipv4,
                "mac": i.mac,
                "machine": i.machine.name if i.machine else None,
                "machine_id": i.machine.id if i.machine else None,
            }
            for i in n.interfaces
        ],
    }


@router.get("/networks")
def networks(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(LabNetwork)
    if user.role == Role.STUDENT:
        if not user.lab:
            return []
        q = q.filter(LabNetwork.lab_id == user.lab.id)
    return [_network(n) for n in q.order_by(LabNetwork.created_at.desc()).all()]


class NetworkCreate(BaseModel):
    name: str
    cidr: str = DEFAULT_LAB_CIDR
    lab_id: str | None = None
    isolated: bool = True
    internet: bool = False


@router.post("/networks")
def add_network(body: NetworkCreate, user: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    network = create_network(
        db,
        user,
        name=body.name,
        cidr=body.cidr or DEFAULT_LAB_CIDR,
        lab_id=body.lab_id,
        isolated=body.isolated,
        internet=body.internet,
        kind="admin",
    )
    return _network(network)


class NetworkDeploy(BaseModel):
    template_slug: str
    name: str | None = None
    owner_id: str | None = None
    environment: str = "container"
    internet: bool = False
    isolated: bool = True
    ephemeral: bool = False


@router.post("/networks/{network_id}/deploy")
def deploy_on_network(
    network_id: str,
    body: NetworkDeploy,
    user: User = Depends(require_staff),
    db: Session = Depends(get_db),
) -> dict:
    network = db.get(LabNetwork, network_id)
    if not network:
        raise HTTPException(404, "Network not found")
    tmpl = db.query(MachineTemplate).filter(MachineTemplate.slug == body.template_slug).first()
    if not tmpl:
        raise HTTPException(404, "Template not found")
    owner = user
    if body.owner_id:
        owner = (
            db.query(User)
            .filter((User.id == body.owner_id) | (User.public_id == body.owner_id) | (User.username == body.owner_id))
            .first()
        )
        if not owner:
            raise HTTPException(404, "Owner not found")
    elif network.lab and network.lab.student:
        owner = network.lab.student
    kind = MachineKind.VM if body.environment == "vm" or tmpl.requires_full_os or tmpl.requires_kernel else tmpl.recommended_kind
    machine, meta = create_machine(
        db,
        user,
        name=body.name or tmpl.name,
        template=tmpl,
        kind=kind,
        vcpu=tmpl.default_vcpu,
        ram_mb=tmpl.default_ram_mb,
        disk_gb=tmpl.default_disk_gb,
        internet=body.internet,
        isolated=body.isolated,
        ephemeral=body.ephemeral,
        ip="",
        network_id=network.id,
        owner=owner,
    )
    return {"machine": _machine(machine), **meta}


@router.delete("/networks/{network_id}")
def delete_network(network_id: str, user: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    network = db.get(LabNetwork, network_id)
    if not network:
        raise HTTPException(404, "Network not found")
    if network.kind != "admin":
        raise HTTPException(400, "Student default networks cannot be deleted")
    if network.interfaces:
        raise HTTPException(400, "Detach or delete machines on this network first")
    audit.record(db, user=user, action="network.delete", resource=network.name, detail=network.cidr)
    db.delete(network)
    db.commit()
    return {"ok": True}


@router.get("/storage")
def storage(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    settings = get_settings()
    vols = db.query(StorageVolume).all()
    isos = db.query(IsoImage).all()
    snaps = db.query(Snapshot).all()
    backups = db.query(Backup).all()
    student = sum(v.size_gb for v in vols if v.category == "student")
    docker = 12.4 + sum(0.05 for v in vols)  # shared images dominate
    vms = sum(v.size_gb * 0.35 for v in vols)  # thin provision estimate
    iso_gb = sum(i.size_bytes for i in isos) / (1024**3)
    backup_gb = sum(b.size_mb for b in backups) / 1024
    used = student + docker + vms + iso_gb + backup_gb + 40
    total = settings.host_total_storage_gb
    return {
        "total_gb": total,
        "used_gb": round(used, 1),
        "available_gb": round(total - used, 1),
        "used_percent": round(used / total * 100, 1),
        "categories": {
            "host_application": 40,
            "docker_images": round(docker, 1),
            "vms": round(vms, 1),
            "students": round(student, 1),
            "isos": round(iso_gb, 1),
            "backups": round(backup_gb, 1),
            "logs": 8,
        },
        "volumes": [
            {
                "id": v.id,
                "name": v.name,
                "size_gb": v.size_gb,
                "persistent": v.persistent,
                "category": v.category,
                "owner_id": v.owner_id,
            }
            for v in vols
            if user.role.value != "student" or v.owner_id == user.id
        ],
        "snapshot_count": len(snaps),
    }


@router.get("/quotas")
def quotas(_: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    return [
        {
            "id": q.id,
            "name": q.name,
            "max_containers": q.max_containers,
            "max_running_containers": q.max_running_containers,
            "max_ram_mb": q.max_ram_mb,
            "max_vcpu": q.max_vcpu,
            "max_vms": q.max_vms,
            "max_storage_gb": q.max_storage_gb,
            "max_snapshots": q.max_snapshots,
            "description": q.description,
        }
        for q in db.query(QuotaProfile).all()
    ]


class QuotaIn(BaseModel):
    name: str
    max_containers: int
    max_running_containers: int
    max_ram_mb: int
    max_vcpu: int
    max_vms: int
    max_storage_gb: int
    max_snapshots: int
    description: str = ""


@router.post("/quotas")
def create_quota(body: QuotaIn, user: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    q = QuotaProfile(**body.model_dump())
    db.add(q)
    audit.record(db, user=user, action="quota.create", resource=q.name)
    db.commit()
    return {"id": q.id, "name": q.name}


@router.patch("/quotas/{quota_id}")
def update_quota(quota_id: str, body: QuotaIn, user: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    q = db.get(QuotaProfile, quota_id)
    if not q:
        raise HTTPException(404, "Quota not found")
    for k, v in body.model_dump().items():
        setattr(q, k, v)
    audit.record(db, user=user, action="quota.update", resource=q.name)
    db.commit()
    return {"ok": True}


@router.get("/snapshots")
def snapshots(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(Snapshot)
    if user.role.value == "student":
        q = q.filter(Snapshot.owner_id == user.id)
    return [
        {
            "id": s.id,
            "name": s.name,
            "machine": s.machine.name,
            "size_mb": s.size_mb,
            "created_at": s.created_at.isoformat(),
        }
        for s in q.all()
    ]


@router.post("/machines/{machine_id}/snapshots")
def create_snapshot(machine_id: str, body: dict, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    m = _owned(db, user, machine_id)
    try:
        snap = snapshot_machine(db, user, m, body.get("name") or f"Checkpoint {m.name}")
    except ValueError as exc:
        raise HTTPException(400, str(exc)) from exc
    return {"id": snap.id, "name": snap.name, "size_mb": snap.size_mb}


@router.post("/snapshots/{snap_id}/restore")
def restore_snapshot(snap_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    s = db.get(Snapshot, snap_id)
    if not s or (user.role.value == "student" and s.owner_id != user.id):
        raise HTTPException(404, "Snapshot not found")
    from app.providers import get_provider

    get_provider().restore(s.machine.provider_ref or s.machine.public_id, s.name)
    audit.record(db, user=user, action="snapshot.restore", resource=s.id, machine=s.machine.name)
    db.commit()
    return {"ok": True}


@router.get("/backups")
def backups(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    return [
        {
            "id": b.id,
            "kind": b.kind,
            "name": b.name,
            "path": b.path,
            "size_mb": b.size_mb,
            "status": b.status,
            "created_at": b.created_at.isoformat(),
            "expires_at": b.expires_at.isoformat() if b.expires_at else None,
        }
        for b in db.query(Backup).order_by(Backup.created_at.desc()).all()
    ]


@router.post("/backups")
def create_backup(body: dict, user: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    from datetime import datetime, timedelta
    from app.config import get_settings

    kind = body.get("kind", "database")
    b = Backup(
        kind=kind,
        name=body.get("name") or f"{kind}-{datetime.utcnow().strftime('%Y%m%d%H%M')}",
        path=f"/var/backups/cyberrange/{kind}.bak",
        size_mb=32,
        status="completed",
        created_by=user.id,
        expires_at=datetime.utcnow() + timedelta(days=get_settings().backup_retention_days),
    )
    db.add(b)
    audit.record(db, user=user, action="backup.create", resource=b.name)
    db.commit()
    return {"id": b.id, "name": b.name, "status": b.status}


@router.post("/backups/{backup_id}/restore")
def restore_backup(backup_id: str, user: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    b = db.get(Backup, backup_id)
    if not b:
        raise HTTPException(404, "Backup not found")
    audit.record(db, user=user, action="backup.restore", resource=b.name)
    db.commit()
    return {"ok": True, "restored": b.kind, "note": "Metadata restore recorded. Production restore runs via documented playbooks."}
