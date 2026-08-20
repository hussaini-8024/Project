from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException, Request, status
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import get_client_ip, require_admin, require_staff
from app.models import (
    Group,
    GroupKind,
    GroupMembership,
    InternetPolicy,
    MachineKind,
    MachineStatus,
    MachineTemplate,
    Role,
    User,
)
from app.security import public_id
from app.services import audit
from app.services.groups import (
    add_member,
    apply_internet_policy,
    inactivity_alerts,
    remove_member,
    running_activity,
    shutdown_group,
)
from app.services.labs import create_machine

router = APIRouter(tags=["groups"])

VALID_KINDS = {GroupKind.STUDENT.value, GroupKind.INSTRUCTOR.value}
VALID_INTERNET = {p.value for p in InternetPolicy}


class GroupCreate(BaseModel):
    name: str = Field(min_length=2, max_length=128)
    kind: str = GroupKind.STUDENT.value
    description: str = ""


class GroupUpdate(BaseModel):
    name: str | None = Field(default=None, min_length=2, max_length=128)
    description: str | None = None


class PolicyUpdate(BaseModel):
    internet_policy: str | None = None
    max_machines: int | None = None
    inactivity_alert_days: int | None = None
    clear_max_machines: bool = False


class MembersAdd(BaseModel):
    user_id: str | None = None
    username: str | None = None
    user_ids: list[str] = Field(default_factory=list)
    usernames: list[str] = Field(default_factory=list)


class GroupDeploy(BaseModel):
    name: str = "Group machine"
    template_slug: str | None = None
    environment: str = "container"  # container | vm
    vcpu: int | None = None
    ram_mb: int | None = None
    disk_gb: int | None = None
    internet: bool = False
    ephemeral: bool = False


def _member_summary(u: User) -> dict:
    return {
        "id": u.id,
        "public_id": u.public_id,
        "username": u.username,
        "full_name": u.full_name,
        "role": u.role.value,
        "status": u.status.value,
        "lab_id": u.lab.public_id if u.lab else None,
    }


def _dump(group: Group) -> dict:
    members = list(group.members)
    return {
        "id": group.id,
        "public_id": group.public_id,
        "name": group.name,
        "kind": group.kind,
        "description": group.description,
        "internet_policy": group.internet_policy,
        "max_machines": group.max_machines,
        "inactivity_alert_days": group.inactivity_alert_days,
        "created_by": group.created_by,
        "created_at": group.created_at.isoformat(),
        "member_count": len(members),
        "members": [_member_summary(m) for m in members],
    }


def _resolve(db: Session, group_id: str) -> Group:
    group = (
        db.query(Group)
        .filter((Group.id == group_id) | (Group.public_id == group_id))
        .first()
    )
    if not group:
        raise HTTPException(404, "Group not found")
    return group


@router.get("/groups")
def list_groups(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    return [_dump(g) for g in db.query(Group).order_by(Group.created_at.desc()).all()]


@router.get("/groups/alerts")
def group_alerts(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    return inactivity_alerts(db)


@router.post("/groups", status_code=201)
def create_group(
    body: GroupCreate,
    request: Request,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    if body.kind not in VALID_KINDS:
        raise HTTPException(400, "kind must be 'student' or 'instructor'")
    if db.query(Group).filter(Group.name == body.name).first():
        raise HTTPException(status.HTTP_409_CONFLICT, "Group name already exists")
    group = Group(
        public_id=public_id("GRP"),
        name=body.name,
        kind=body.kind,
        description=body.description,
        created_by=admin.id,
    )
    db.add(group)
    db.flush()
    audit.record(db, user=admin, action="group.create", resource=group.public_id, detail=group.name, ip=get_client_ip(request))
    db.commit()
    return _dump(group)


@router.get("/groups/{group_id}")
def get_group(group_id: str, _: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    return _dump(_resolve(db, group_id))


@router.patch("/groups/{group_id}")
def update_group(
    group_id: str,
    body: GroupUpdate,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    group = _resolve(db, group_id)
    data = body.model_dump(exclude_unset=True)
    if "name" in data and data["name"] != group.name:
        if db.query(Group).filter(Group.name == data["name"], Group.id != group.id).first():
            raise HTTPException(status.HTTP_409_CONFLICT, "Group name already exists")
    for k, v in data.items():
        setattr(group, k, v)
    audit.record(db, user=admin, action="group.update", resource=group.public_id)
    db.commit()
    return _dump(group)


@router.delete("/groups/{group_id}")
def delete_group(
    group_id: str,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
    detach: bool = False,
) -> dict:
    group = _resolve(db, group_id)
    memberships = db.query(GroupMembership).filter(GroupMembership.group_id == group.id).all()
    if memberships and not detach:
        raise HTTPException(
            400,
            f"Group has {len(memberships)} member(s). Detach members first or pass ?detach=true.",
        )
    for row in memberships:
        u = db.get(User, row.user_id)
        if u and u.group_id == group.id:
            u.group_id = None
        db.delete(row)
    audit.record(db, user=admin, action="group.delete", resource=group.public_id, detail=group.name)
    db.delete(group)
    db.commit()
    return {"ok": True, "detached": len(memberships)}


@router.post("/groups/{group_id}/members")
def add_members(
    group_id: str,
    body: MembersAdd,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    group = _resolve(db, group_id)
    identifiers: list[str] = []
    if body.user_id:
        identifiers.append(body.user_id)
    if body.username:
        identifiers.append(body.username)
    identifiers.extend(body.user_ids)
    identifiers.extend(body.usernames)
    if not identifiers:
        raise HTTPException(400, "Provide user_id(s) or username(s) to add")

    added: list[dict] = []
    errors: list[dict] = []
    for ident in identifiers:
        u = (
            db.query(User)
            .filter((User.id == ident) | (User.username == ident) | (User.public_id == ident))
            .first()
        )
        if not u:
            errors.append({"identifier": ident, "error": "User not found"})
            continue
        err = add_member(db, group, u)
        if err:
            errors.append({"identifier": ident, "error": err})
            continue
        added.append(_member_summary(u))
    db.flush()
    db.refresh(group)
    # Re-apply the group's internet policy so newly added members inherit it.
    applied = apply_internet_policy(db, group)
    audit.record(
        db,
        user=admin,
        action="group.members.add",
        resource=group.public_id,
        detail=f"added={len(added)} internet_applied={applied}",
    )
    db.commit()
    db.refresh(group)
    return {"added": added, "errors": errors, "group": _dump(group)}


@router.delete("/groups/{group_id}/members/{user_id}")
def remove_member(
    group_id: str,
    user_id: str,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    group = _resolve(db, group_id)
    u = (
        db.query(User)
        .filter((User.id == user_id) | (User.username == user_id) | (User.public_id == user_id))
        .first()
    )
    if not u or not remove_member(db, group, u):
        raise HTTPException(404, "User is not a member of this group")
    audit.record(db, user=admin, action="group.members.remove", resource=group.public_id, detail=u.username)
    db.commit()
    db.refresh(group)
    return {"ok": True, "group": _dump(group)}


@router.patch("/groups/{group_id}/policies")
def update_policies(
    group_id: str,
    body: PolicyUpdate,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    group = _resolve(db, group_id)
    if body.internet_policy is not None:
        if body.internet_policy not in VALID_INTERNET:
            raise HTTPException(400, "internet_policy must be enabled | disabled | unset")
        group.internet_policy = body.internet_policy
    if body.clear_max_machines:
        group.max_machines = None
    elif body.max_machines is not None:
        if body.max_machines < 1:
            raise HTTPException(400, "max_machines must be >= 1")
        group.max_machines = body.max_machines
    if body.inactivity_alert_days is not None:
        if body.inactivity_alert_days < 1:
            raise HTTPException(400, "inactivity_alert_days must be >= 1")
        group.inactivity_alert_days = body.inactivity_alert_days
    db.flush()
    applied = apply_internet_policy(db, group)
    audit.record(
        db,
        user=admin,
        action="group.policies",
        resource=group.public_id,
        detail=(
            f"internet={group.internet_policy} max_machines={group.max_machines} "
            f"inactivity_days={group.inactivity_alert_days} labs_applied={applied}"
        ),
    )
    db.commit()
    db.refresh(group)
    return {"group": _dump(group), "internet_labs_applied": applied}


@router.post("/groups/{group_id}/shutdown")
def shutdown(
    group_id: str,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    group = _resolve(db, group_id)
    if group.kind != GroupKind.STUDENT.value:
        raise HTTPException(400, "Group-wide shutdown is only allowed for student groups; instructor machines are protected")
    result = shutdown_group(db, group, admin)
    audit.record(
        db,
        user=admin,
        action="group.shutdown",
        resource=group.public_id,
        detail=f"stopped={result['stopped']}",
    )
    db.commit()
    from app.services.scheduler import drain_queue

    drain_queue(db)
    return {"group": group.name, **result}


def _kind_for(environment: str, tmpl: MachineTemplate | None) -> MachineKind:
    kind = MachineKind.VM if environment == "vm" else MachineKind.CONTAINER
    if tmpl:
        if tmpl.requires_full_os or tmpl.requires_kernel:
            kind = MachineKind.VM
        elif environment == "vm":
            kind = MachineKind.CONTAINER  # container-first
        else:
            kind = tmpl.recommended_kind
    return kind


@router.post("/groups/{group_id}/deploy")
def deploy(
    group_id: str,
    body: GroupDeploy,
    request: Request,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    group = _resolve(db, group_id)
    if group.kind != GroupKind.STUDENT.value:
        raise HTTPException(400, "Group deploy targets student groups")
    tmpl = None
    if body.template_slug:
        tmpl = db.query(MachineTemplate).filter(MachineTemplate.slug == body.template_slug).first()
        if not tmpl:
            raise HTTPException(404, "Template not found")
    kind = _kind_for(body.environment, tmpl)
    results: list[dict] = []
    created = 0
    students = [m for m in group.members if m.role == Role.STUDENT]
    for member in students:
        try:
            machine, meta = create_machine(
                db,
                admin,  # staff actor -> bypasses student quota (ignore_quota path)
                owner=member,
                name=body.name or (tmpl.name if tmpl else "Group machine"),
                template=tmpl,
                kind=kind,
                vcpu=body.vcpu or (tmpl.default_vcpu if tmpl else 1),
                ram_mb=body.ram_mb or (tmpl.default_ram_mb if tmpl else 512),
                disk_gb=body.disk_gb or (tmpl.default_disk_gb if tmpl else 2),
                internet=body.internet,
                isolated=True,
                ephemeral=body.ephemeral,
                ip=get_client_ip(request),
            )
            created += 1
            results.append(
                {
                    "student": member.username,
                    "public_id": member.public_id,
                    "machine": machine.public_id,
                    "status": machine.status.value,
                }
            )
        except Exception as exc:  # noqa: BLE001 - report per-student failures
            results.append({"student": member.username, "public_id": member.public_id, "error": str(exc)[:200]})
    audit.record(
        db,
        user=admin,
        action="group.deploy",
        resource=group.public_id,
        detail=f"template={body.template_slug} created={created}/{len(students)}",
    )
    db.commit()
    return {"group": group.name, "created": created, "total": len(students), "results": results}


@router.post("/groups/{group_id}/start")
def start_group(
    group_id: str,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    """Start every stopped machine owned by members of a student group."""
    from app.models import Machine
    from app.services.scheduler import start_machine

    group = _resolve(db, group_id)
    if group.kind != GroupKind.STUDENT.value:
        raise HTTPException(400, "Group-wide start is only allowed for student groups")
    started = 0
    results: list[dict] = []
    for member in group.members:
        if member.role != Role.STUDENT:
            continue
        machines = (
            db.query(Machine)
            .filter(
                Machine.owner_id == member.id,
                Machine.status.in_([MachineStatus.STOPPED, MachineStatus.QUEUED]),
            )
            .all()
        )
        for m in machines:
            d = start_machine(db, m, admin, ignore_quota=True)
            if d.allowed and not d.queued:
                started += 1
            results.append({"student": member.username, "machine": m.public_id, "status": m.status.value})
    audit.record(db, user=admin, action="group.start", resource=group.public_id, detail=f"started={started}")
    db.commit()
    return {"group": group.name, "started": started, "results": results}


@router.get("/admin/activity")
def admin_activity(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    activity = running_activity(db)
    activity["inactivity_alerts"] = inactivity_alerts(db)
    return activity


@router.get("/admin/inactivity-alerts")
def admin_inactivity(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    return inactivity_alerts(db)
