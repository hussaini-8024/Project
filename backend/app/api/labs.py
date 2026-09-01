from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import current_user, require_staff
from app.models import (
    LabNetwork,
    Machine,
    MachineKind,
    MachineStatus,
    MachineTemplate,
    Role,
    StudentLab,
    User,
)
from app.services import audit
from app.services.labs import create_machine, ensure_lab, purge_machine, restore_lab
from app.services.scheduler import start_machine
from app.providers import get_provider

router = APIRouter(tags=["labs"])


def _machine(m: Machine) -> dict:
    return {
        "id": m.id,
        "public_id": m.public_id,
        "name": m.name,
        "kind": m.kind.value,
        "status": m.status.value,
        "vcpu": m.vcpu,
        "ram_mb": m.ram_mb,
        "disk_gb": m.disk_gb,
        "internet": m.internet,
        "isolated": m.isolated,
        "ephemeral": m.ephemeral,
        "template": m.template.slug if m.template else None,
        "template_name": m.template.name if m.template else None,
        "vulnerable": bool(m.template and m.template.is_vulnerable_target),
        "warning_label": m.template.warning_label if m.template else "",
        "ip": m.interfaces[0].ipv4 if m.interfaces else None,
        "mac": m.interfaces[0].mac if m.interfaces else None,
        "network_id": m.interfaces[0].network_id if m.interfaces else None,
        "cidr": m.interfaces[0].network.cidr if m.interfaces and m.interfaces[0].network else None,
        "queue_position": m.queue_position,
        "queue_reason": m.queue_reason,
        "error": m.error_message,
        "node": m.node.name if m.node else None,
        "created_at": m.created_at.isoformat(),
    }


def _network_brief(n) -> dict:
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
    }


def _lab(lab: StudentLab) -> dict:
    nets = [_network_brief(n) for n in lab.networks]
    return {
        "id": lab.id,
        "public_id": lab.public_id,
        "name": lab.name,
        "status": lab.status,
        "internet_enabled": lab.internet_enabled,
        "student": lab.student.username,
        "student_public_id": lab.student.public_id,
        "network": nets[0] if nets else None,
        "networks": nets,
        "machines": [_machine(m) for m in lab.machines],
        "last_restored_at": lab.last_restored_at.isoformat() if lab.last_restored_at else None,
    }


@router.get("/labs/me")
def my_lab(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    lab = restore_lab(db, user) if user.role == Role.STUDENT else ensure_lab(db, user)
    return _lab(lab)


@router.get("/labs")
def list_labs(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    return [_lab(l) for l in db.query(StudentLab).all()]


@router.post("/labs/me/internet")
def set_internet(body: dict, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    lab = ensure_lab(db, user)
    if user.role == Role.STUDENT:
        raise HTTPException(403, "Internet access is controlled by staff")
    lab.internet_enabled = bool(body.get("enabled"))
    if lab.network:
        lab.network.internet = lab.internet_enabled
    audit.record(db, user=user, action="lab.internet", resource=lab.public_id, detail=str(lab.internet_enabled))
    db.commit()
    return _lab(lab)


@router.post("/labs/{lab_id}/internet")
def admin_internet(lab_id: str, body: dict, user: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    lab = db.query(StudentLab).filter((StudentLab.id == lab_id) | (StudentLab.public_id == lab_id)).first()
    if not lab:
        raise HTTPException(404, "Lab not found")
    lab.internet_enabled = bool(body.get("enabled"))
    if lab.network:
        lab.network.internet = lab.internet_enabled
    audit.record(db, user=user, action="lab.internet", resource=lab.public_id)
    db.commit()
    return _lab(lab)


@router.post("/labs/{lab_id}/reset")
def reset_lab(lab_id: str, user: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    lab = db.query(StudentLab).filter((StudentLab.id == lab_id) | (StudentLab.public_id == lab_id)).first()
    if not lab:
        raise HTTPException(404, "Lab not found")
    provider = get_provider()
    for m in lab.machines:
        provider.stop(m.provider_ref or m.public_id, m.kind.value)
        m.status = MachineStatus.STOPPED
    lab.status = "ready"
    audit.record(db, user=user, action="lab.reset", resource=lab.public_id)
    db.commit()
    return _lab(lab)


class MachineCreate(BaseModel):
    name: str
    template_slug: str | None = None
    environment: str = "container"  # container | vm | prebuilt
    vcpu: int | None = None
    ram_mb: int | None = None
    disk_gb: int | None = None
    internet: bool = False
    isolated: bool = True
    ephemeral: bool = False
    network_id: str | None = None


PREBUILT_MEMBERS = {
    "lab-webapp": ["kali", "dvwa", "juice-shop", "webgoat"],
    "lab-network": ["kali", "ubuntu", "metasploitable"],
    "lab-windows": ["kali", "windows"],
    "lab-linux": ["kali", "ubuntu", "debian"],
    "lab-defense": ["ubuntu", "wazuh"],
}


def _usable_network(db: Session, user: User, network_id: str | None) -> str | None:
    if not network_id:
        return None
    net = db.get(LabNetwork, network_id)
    if not net:
        raise HTTPException(404, "Network not found")
    if user.role == Role.STUDENT:
        lab = ensure_lab(db, user)
        if net.lab_id != lab.id:
            raise HTTPException(403, "Network is outside this student laboratory")
    return net.id


@router.post("/machines")
def create(body: MachineCreate, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    tmpl = None
    network_id = _usable_network(db, user, body.network_id)
    if body.template_slug:
        tmpl = db.query(MachineTemplate).filter(MachineTemplate.slug == body.template_slug).first()
        if not tmpl:
            raise HTTPException(404, "Template not found")
    if body.environment == "prebuilt" and tmpl:
        created = []
        for slug in PREBUILT_MEMBERS.get(tmpl.slug, []):
            member = db.query(MachineTemplate).filter(MachineTemplate.slug == slug).one()
            m, meta = create_machine(
                db,
                user,
                name=member.name,
                template=member,
                kind=member.recommended_kind,
                vcpu=member.default_vcpu,
                ram_mb=member.default_ram_mb,
                disk_gb=member.default_disk_gb,
                internet=body.internet,
                isolated=True,
                ephemeral=body.ephemeral,
                ip="",
                network_id=network_id,
            )
            created.append(_machine(m))
        return {"machines": created, "scenario": tmpl.slug}
    kind = MachineKind.VM if body.environment == "vm" else MachineKind.CONTAINER
    if tmpl:
        kind = tmpl.recommended_kind if body.environment != "vm" or not tmpl.requires_full_os else MachineKind.VM
        if tmpl.requires_full_os or tmpl.requires_kernel:
            kind = MachineKind.VM
        elif body.environment == "vm" and not tmpl.requires_full_os:
            kind = MachineKind.CONTAINER  # container-first
    m, meta = create_machine(
        db,
        user,
        name=body.name or (tmpl.name if tmpl else "machine"),
        template=tmpl,
        kind=kind,
        vcpu=body.vcpu or (tmpl.default_vcpu if tmpl else 1),
        ram_mb=body.ram_mb or (tmpl.default_ram_mb if tmpl else 512),
        disk_gb=body.disk_gb or (tmpl.default_disk_gb if tmpl else 2),
        internet=body.internet,
        isolated=body.isolated,
        ephemeral=body.ephemeral,
        ip="",
        network_id=network_id,
    )
    return {"machine": _machine(m), **meta}


@router.get("/machines")
def list_machines(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(Machine)
    if user.role == Role.STUDENT:
        q = q.filter(Machine.owner_id == user.id)
    return [_machine(m) for m in q.order_by(Machine.created_at.desc()).all()]


@router.get("/machines/{machine_id}")
def get_machine(machine_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    m = _owned(db, user, machine_id)
    return _machine(m)


def _owned(db: Session, user: User, machine_id: str) -> Machine:
    m = db.query(Machine).filter((Machine.id == machine_id) | (Machine.public_id == machine_id)).first()
    if not m:
        raise HTTPException(404, "Machine not found")
    if user.role == Role.STUDENT and m.owner_id != user.id:
        raise HTTPException(403, "Machines are isolated to the owning student")
    return m


@router.post("/machines/{machine_id}/start")
def start(machine_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    m = _owned(db, user, machine_id)
    # Staff-initiated starts are not bound by student quota profiles (consistent
    # with create/deploy/group-start). Students remain bound by their own quota.
    d = start_machine(db, m, user, ignore_quota=user.role != Role.STUDENT)
    audit.record(db, user=user, action="machine.start", resource=m.public_id, machine=m.name, result="queued" if d.queued else "success")
    db.commit()
    return {"machine": _machine(m), "decision": d.__dict__}


@router.post("/machines/{machine_id}/stop")
def stop(machine_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    m = _owned(db, user, machine_id)
    get_provider().stop(m.provider_ref or m.public_id, m.kind.value)
    m.status = MachineStatus.STOPPED
    audit.record(db, user=user, action="machine.stop", resource=m.public_id, machine=m.name)
    db.commit()
    from app.services.scheduler import drain_queue

    drain_queue(db)
    return _machine(m)


@router.post("/machines/{machine_id}/restart")
def restart(machine_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    stop(machine_id, user, db)
    return start(machine_id, user, db)


@router.post("/machines/{machine_id}/pause")
def pause(machine_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    m = _owned(db, user, machine_id)
    get_provider().pause(m.provider_ref or m.public_id, m.kind.value)
    m.status = MachineStatus.PAUSED
    db.commit()
    return _machine(m)


@router.delete("/machines/{machine_id}")
def delete_machine(machine_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    m = _owned(db, user, machine_id)
    audit.record(db, user=user, action="machine.delete", resource=m.public_id, machine=m.name)
    purge_machine(db, m)
    db.commit()
    return {"ok": True}


@router.get("/templates")
def templates(db: Session = Depends(get_db), _: User = Depends(current_user)) -> list[dict]:
    rows = db.query(MachineTemplate).filter(MachineTemplate.approved.is_(True)).all()
    return [
        {
            "id": t.id,
            "name": t.name,
            "slug": t.slug,
            "environment": t.environment.value,
            "recommended_kind": t.recommended_kind.value,
            "os_family": t.os_family,
            "default_vcpu": t.default_vcpu,
            "default_ram_mb": t.default_ram_mb,
            "default_disk_gb": t.default_disk_gb,
            "is_vulnerable_target": t.is_vulnerable_target,
            "requires_kernel": t.requires_kernel,
            "requires_full_os": t.requires_full_os,
            "tools": t.tools,
            "description": t.description,
            "category": t.category,
            "warning_label": t.warning_label,
            "container_first": not t.requires_full_os and not t.requires_kernel,
        }
        for t in rows
    ]


@router.get("/vms")
def vms(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(Machine).filter(Machine.kind == MachineKind.VM)
    if user.role == Role.STUDENT:
        q = q.filter(Machine.owner_id == user.id)
    return [_machine(m) for m in q.all()]


@router.get("/containers")
def containers(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    q = db.query(Machine).filter(Machine.kind == MachineKind.CONTAINER)
    if user.role == Role.STUDENT:
        q = q.filter(Machine.owner_id == user.id)
    return [_machine(m) for m in q.all()]
