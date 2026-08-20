from __future__ import annotations

from datetime import datetime, timedelta

from sqlalchemy.orm import Session

from app.models import (
    Group,
    GroupKind,
    InternetPolicy,
    Machine,
    MachineStatus,
    Role,
    StudentLab,
    User,
)
from app.providers import get_provider
from app.services import audit

DEFAULT_INACTIVITY_DAYS = 3
DEFAULT_MACHINE_CAP = 3


def student_group(db: Session, user: User) -> Group | None:
    """Return the student-kind group a user belongs to, if any."""
    if not user.group_id:
        return None
    group = db.get(Group, user.group_id)
    if group and group.kind == GroupKind.STUDENT.value:
        return group
    return None


def group_machine_cap(db: Session, user: User) -> int | None:
    """The per-student machine cap enforced by the user's student group, or None."""
    group = student_group(db, user)
    if group and group.max_machines is not None:
        return group.max_machines
    return None


def apply_internet_policy(db: Session, group: Group) -> int:
    """Apply the group's internet policy to every member's lab and network.

    Returns the number of member labs touched. ``unset`` leaves labs untouched so
    per-student overrides are preserved.
    """
    policy = group.internet_policy or InternetPolicy.DISABLED.value
    if policy == InternetPolicy.UNSET.value:
        return 0
    desired = policy == InternetPolicy.ENABLED.value
    touched = 0
    for member in group.members:
        if member.role != Role.STUDENT:
            continue
        lab = member.lab
        if not lab:
            continue
        lab.internet_enabled = desired
        for net in lab.networks:
            net.internet = desired
        touched += 1
    return touched


def _last_activity(db: Session, user: User) -> datetime:
    """Best-effort "last used" timestamp from login + most recent machine activity."""
    candidates: list[datetime] = []
    if user.last_login_at:
        candidates.append(user.last_login_at)
    machines = db.query(Machine).filter(Machine.owner_id == user.id).all()
    for m in machines:
        candidates.append(m.updated_at)
        if m.last_started_at:
            candidates.append(m.last_started_at)
    if not candidates:
        candidates.append(user.created_at)
    return max(candidates)


def inactivity_alerts(db: Session, group: Group | None = None) -> list[dict]:
    """Students who have not used their account/machines within the alert threshold.

    When ``group`` is given, only its members are evaluated with its threshold.
    Otherwise every student is evaluated using their group's threshold (or the
    platform default when they have no group).
    """
    now = datetime.utcnow()
    if group is not None:
        members = [m for m in group.members if m.role == Role.STUDENT]
        threshold_for = {m.id: group.inactivity_alert_days or DEFAULT_INACTIVITY_DAYS for m in members}
    else:
        members = db.query(User).filter(User.role == Role.STUDENT).all()
        threshold_for = {}
        for m in members:
            g = db.get(Group, m.group_id) if m.group_id else None
            days = (
                g.inactivity_alert_days
                if g and g.inactivity_alert_days
                else DEFAULT_INACTIVITY_DAYS
            )
            threshold_for[m.id] = days

    alerts: list[dict] = []
    for member in members:
        days = threshold_for.get(member.id, DEFAULT_INACTIVITY_DAYS)
        last = _last_activity(db, member)
        idle = now - last
        if idle >= timedelta(days=days):
            g = db.get(Group, member.group_id) if member.group_id else None
            alerts.append(
                {
                    "user_id": member.id,
                    "public_id": member.public_id,
                    "username": member.username,
                    "full_name": member.full_name,
                    "group_id": member.group_id,
                    "group": g.name if g else None,
                    "last_activity": last.isoformat(),
                    "idle_days": round(idle.total_seconds() / 86400, 1),
                    "threshold_days": days,
                }
            )
    alerts.sort(key=lambda a: a["idle_days"], reverse=True)
    return alerts


def running_activity(db: Session) -> dict:
    """Live running-machine breakdown by group and by student for the dashboard."""
    running = (
        db.query(Machine)
        .filter(Machine.status == MachineStatus.RUNNING)
        .all()
    )
    students: dict[str, dict] = {}
    for m in running:
        owner = db.get(User, m.owner_id)
        if not owner or owner.role != Role.STUDENT:
            continue
        entry = students.setdefault(
            owner.id,
            {
                "user_id": owner.id,
                "public_id": owner.public_id,
                "username": owner.username,
                "full_name": owner.full_name,
                "group_id": owner.group_id,
                "running": 0,
                "containers": 0,
                "vms": 0,
            },
        )
        entry["running"] += 1
        if m.kind.value == "container":
            entry["containers"] += 1
        else:
            entry["vms"] += 1

    groups = db.query(Group).filter(Group.kind == GroupKind.STUDENT.value).all()
    by_group: list[dict] = []
    for g in groups:
        member_entries = [students[m.id] for m in g.members if m.id in students]
        by_group.append(
            {
                "group_id": g.id,
                "public_id": g.public_id,
                "name": g.name,
                "members": len([m for m in g.members if m.role == Role.STUDENT]),
                "active_students": len(member_entries),
                "running_machines": sum(e["running"] for e in member_entries),
                "students": member_entries,
            }
        )
    ungrouped = [e for e in students.values() if not e["group_id"]]
    return {
        "total_running": len(running),
        "active_students": len(students),
        "by_group": by_group,
        "ungrouped": ungrouped,
    }


def shutdown_group(db: Session, group: Group, actor: User) -> dict:
    """Stop every RUNNING machine owned by members of a student group.

    Instructor groups are rejected by the caller; instructor-owned machines are never
    touched here because only student members' machines are enumerated.
    """
    provider = get_provider()
    stopped = 0
    errors: list[dict] = []
    for member in group.members:
        if member.role != Role.STUDENT:
            continue
        machines = (
            db.query(Machine)
            .filter(Machine.owner_id == member.id, Machine.status == MachineStatus.RUNNING)
            .all()
        )
        for m in machines:
            try:
                provider.stop(m.provider_ref or m.public_id, m.kind.value)
                m.status = MachineStatus.STOPPED
                stopped += 1
                audit.record(
                    db,
                    user=actor,
                    action="group.machine.stop",
                    resource=m.public_id,
                    machine=m.name,
                    detail=f"group={group.name}",
                )
            except Exception as exc:  # noqa: BLE001 - surface per-machine failures
                errors.append({"machine": m.public_id, "error": str(exc)[:200]})
    return {"stopped": stopped, "errors": errors}
