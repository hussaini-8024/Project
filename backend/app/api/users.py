from __future__ import annotations

from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException, Request, status
from pydantic import BaseModel, EmailStr, Field
from sqlalchemy import func
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import current_user, get_client_ip, require_admin, require_staff
from app.models import Group, QuotaProfile, Role, User, UserSession, UserStatus
from app.security import hash_password, public_id
from app.services import audit
from app.services.groups import add_member, group_summaries, remove_member, student_group_of
from app.services.labs import ensure_lab, purge_user

router = APIRouter(tags=["users"])


class UserCreate(BaseModel):
    username: str = Field(min_length=3, max_length=64)
    email: EmailStr
    full_name: str = ""
    password: str = Field(min_length=10)
    role: Role = Role.STUDENT
    quota_name: str = "Standard"
    course: str = ""
    expires_at: datetime | None = None
    group_ids: list[str] = Field(default_factory=list)


class UserUpdate(BaseModel):
    username: str | None = Field(default=None, min_length=3, max_length=64)
    email: EmailStr | None = None
    full_name: str | None = None
    role: Role | None = None
    status: UserStatus | None = None
    quota_name: str | None = None
    course: str | None = None
    expires_at: datetime | None = None


def _dump(u: User) -> dict:
    groups = group_summaries(u)
    single = student_group_of(u)
    return {
        "id": u.id,
        "public_id": u.public_id,
        "username": u.username,
        "email": u.email,
        "full_name": u.full_name,
        "role": u.role.value,
        "status": u.status.value,
        "course": u.course,
        "quota": u.quota.name if u.quota else None,
        "groups": groups,
        "group_ids": [g["id"] for g in groups],
        "group_id": single.id if single else None,
        "group": single.name if single else None,
        "lab_id": u.lab.public_id if u.lab else None,
        "last_login_at": u.last_login_at.isoformat() if u.last_login_at else None,
        "expires_at": u.expires_at.isoformat() if u.expires_at else None,
        "created_at": u.created_at.isoformat(),
    }


@router.get("/users")
def list_users(
    user: User = Depends(require_staff),
    db: Session = Depends(get_db),
    q: str | None = None,
) -> list[dict]:
    query = db.query(User)
    if user.role == Role.INSTRUCTOR:
        query = query.filter(User.role == Role.STUDENT)
    if q:
        needle = f"%{q.strip().lower()}%"
        query = query.filter(
            func.lower(User.username).like(needle)
            | func.lower(User.full_name).like(needle)
            | func.lower(User.email).like(needle)
        )
    return [_dump(u) for u in query.order_by(User.created_at.desc()).all()]


@router.get("/students")
def list_students(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    return [_dump(u) for u in db.query(User).filter(User.role == Role.STUDENT).all()]


@router.post("/users", status_code=201)
def create_user(body: UserCreate, request: Request, admin: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    if db.query(User).filter((User.username == body.username) | (User.email == body.email)).first():
        raise HTTPException(status.HTTP_409_CONFLICT, "Username or email already exists")
    quota = db.query(QuotaProfile).filter(QuotaProfile.name == body.quota_name).first()
    prefix = {
        Role.STUDENT: "STU",
        Role.INSTRUCTOR: "INS",
        Role.ADMINISTRATOR: "ADM",
        Role.SUPER_ADMIN: "ADM",
        Role.LAB_MANAGER: "LABM",
    }[body.role]
    u = User(
        public_id=public_id(prefix),
        username=body.username,
        email=body.email,
        full_name=body.full_name,
        hashed_password=hash_password(body.password),
        role=body.role,
        quota_id=quota.id if quota else None,
        course=body.course,
        expires_at=body.expires_at,
    )
    db.add(u)
    db.flush()
    if u.role == Role.STUDENT:
        ensure_lab(db, u)
    assign_errors: list[str] = []
    for gid in body.group_ids:
        group = db.query(Group).filter((Group.id == gid) | (Group.public_id == gid)).first()
        if not group:
            assign_errors.append(f"group {gid} not found")
            continue
        err = add_member(db, group, u)
        if err:
            assign_errors.append(err)
    db.flush()
    audit.record(db, user=admin, action="user.create", resource=u.public_id, ip=get_client_ip(request))
    db.commit()
    result = _dump(u)
    if assign_errors:
        result["group_assignment_errors"] = assign_errors
    return result


@router.patch("/users/{user_id}")
def update_user(user_id: str, body: UserUpdate, request: Request, admin: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    u = db.get(User, user_id)
    if not u:
        raise HTTPException(404, "User not found")
    data = body.model_dump(exclude_unset=True)
    if "quota_name" in data:
        q = db.query(QuotaProfile).filter(QuotaProfile.name == data.pop("quota_name")).first()
        u.quota_id = q.id if q else u.quota_id
    new_username = data.get("username")
    if new_username and new_username != u.username:
        if db.query(User).filter(User.username == new_username, User.id != u.id).first():
            raise HTTPException(status.HTTP_409_CONFLICT, "Username already exists")
    new_email = data.get("email")
    if new_email and new_email != u.email:
        if db.query(User).filter(User.email == new_email, User.id != u.id).first():
            raise HTTPException(status.HTTP_409_CONFLICT, "Email already exists")
    for k, v in data.items():
        setattr(u, k, v)
    audit.record(db, user=admin, action="user.update", resource=u.public_id, ip=get_client_ip(request))
    db.commit()
    return _dump(u)


class GroupAssign(BaseModel):
    group_ids: list[str] = Field(default_factory=list)


@router.put("/users/{user_id}/groups")
def set_user_groups(
    user_id: str,
    body: GroupAssign,
    admin: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    """Replace a user's group memberships.

    Instructors may be assigned to many groups; students to at most one (a >1 request
    is rejected). Role must match each group's kind.
    """
    u = db.get(User, user_id)
    if not u:
        raise HTTPException(404, "User not found")
    if u.role == Role.STUDENT and len(body.group_ids) > 1:
        raise HTTPException(400, "Students may belong to at most one group")

    desired: list[Group] = []
    for gid in body.group_ids:
        group = db.query(Group).filter((Group.id == gid) | (Group.public_id == gid)).first()
        if not group:
            raise HTTPException(404, f"Group {gid} not found")
        desired.append(group)

    desired_ids = {g.id for g in desired}
    # Remove memberships no longer wanted.
    for g in list(u.groups):
        if g.id not in desired_ids:
            remove_member(db, g, u)
    db.flush()
    # Add the new ones under the cardinality rules.
    errors: list[str] = []
    for g in desired:
        err = add_member(db, g, u)
        if err:
            errors.append(err)
    if errors:
        db.rollback()
        raise HTTPException(400, "; ".join(errors))
    audit.record(db, user=admin, action="user.groups.set", resource=u.public_id, detail=",".join(desired_ids))
    db.commit()
    db.refresh(u)
    return _dump(u)


@router.post("/users/{user_id}/reset-password")
def reset_password(user_id: str, body: dict, admin: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    u = db.get(User, user_id)
    if not u:
        raise HTTPException(404, "User not found")
    password = body.get("password") or public_id("TMP") + "!x"
    u.hashed_password = hash_password(password)
    audit.record(db, user=admin, action="user.reset_password", resource=u.public_id)
    db.commit()
    return {"ok": True, "temporary_password": password}


@router.post("/users/{user_id}/disable")
def disable_user(user_id: str, admin: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    u = db.get(User, user_id)
    if not u:
        raise HTTPException(404, "User not found")
    u.status = UserStatus.DISABLED
    for s in u.sessions:
        s.revoked = True
    audit.record(db, user=admin, action="user.disable", resource=u.public_id)
    db.commit()
    return _dump(u)


@router.delete("/users/{user_id}")
def delete_user(user_id: str, admin: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    u = db.get(User, user_id)
    if not u:
        raise HTTPException(404, "User not found")
    if u.role == Role.SUPER_ADMIN:
        raise HTTPException(400, "Cannot delete super administrator")
    if u.id == admin.id:
        raise HTTPException(400, "You cannot delete your own account")
    audit.record(db, user=admin, action="user.delete", resource=u.public_id)
    purge_user(db, u)
    db.commit()
    return {"ok": True}


@router.get("/sessions")
def list_sessions(_: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    rows = db.query(UserSession).filter(UserSession.revoked.is_(False)).all()
    return [
        {
            "id": s.id,
            "public_id": s.public_id,
            "user": s.user.username,
            "role": s.user.role.value,
            "ip": s.ip,
            "created_at": s.created_at.isoformat(),
            "last_seen_at": s.last_seen_at.isoformat(),
            "expires_at": s.expires_at.isoformat(),
        }
        for s in rows
    ]


@router.post("/sessions/{session_id}/terminate")
def terminate_session(session_id: str, admin: User = Depends(require_admin), db: Session = Depends(get_db)) -> dict:
    s = db.get(UserSession, session_id)
    if not s:
        raise HTTPException(404, "Session not found")
    s.revoked = True
    audit.record(db, user=admin, action="session.terminate", resource=s.public_id)
    db.commit()
    return {"ok": True}


@router.get("/users/me/usage")
def my_usage(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    from app.models import Machine, MachineStatus

    machines = db.query(Machine).filter(Machine.owner_id == user.id).all()
    running = [m for m in machines if m.status == MachineStatus.RUNNING]
    q = user.quota
    return {
        "machines": len(machines),
        "running": len(running),
        "ram_mb": sum(m.ram_mb for m in running),
        "vcpu": sum(m.vcpu for m in running),
        "disk_gb": sum(m.disk_gb for m in machines),
        "quota": {
            "name": q.name if q else "none",
            "max_ram_mb": q.max_ram_mb if q else 0,
            "max_vcpu": q.max_vcpu if q else 0,
            "max_containers": q.max_containers if q else 0,
            "max_vms": q.max_vms if q else 0,
            "max_storage_gb": q.max_storage_gb if q else 0,
            "max_snapshots": q.max_snapshots if q else 0,
        },
    }
