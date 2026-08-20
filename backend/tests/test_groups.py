from __future__ import annotations

from datetime import datetime, timedelta

import pytest

from app.models import (
    Group,
    GroupKind,
    GroupMembership,
    InternetPolicy,
    MachineKind,
    MachineStatus,
    QuotaProfile,
    Role,
    User,
    UserStatus,
)
from app.security import hash_password, public_id
from app.services.groups import (
    add_member,
    apply_internet_policy,
    inactivity_alerts,
    running_activity,
    shutdown_group,
    student_group_of,
)
from app.services.labs import create_machine, ensure_lab


def _join(db, group: Group, user: User) -> None:
    """Directly insert a membership row (bypasses role rules for scenario setup)."""
    db.add(GroupMembership(user_id=user.id, group_id=group.id))
    if user.role == Role.STUDENT:
        user.group_id = group.id
    db.commit()
    db.refresh(group)
    db.refresh(user)


def _quota(db) -> QuotaProfile:
    q = QuotaProfile(
        name="Standard",
        max_containers=10,
        max_running_containers=10,
        max_ram_mb=65536,
        max_vcpu=64,
        max_vms=5,
        max_storage_gb=500,
        max_snapshots=5,
    )
    db.add(q)
    db.flush()
    return q


def _student(db, username: str, quota: QuotaProfile) -> User:
    u = User(
        public_id=public_id("STU"),
        username=username,
        email=f"{username}@students.test",
        full_name=username.title(),
        hashed_password=hash_password("CyberRange!Test2026"),
        role=Role.STUDENT,
        status=UserStatus.ACTIVE,
        quota_id=quota.id,
    )
    db.add(u)
    db.flush()
    ensure_lab(db, u)
    return u


def _admin(db) -> User:
    u = User(
        public_id=public_id("ADM"),
        username="admin",
        email="admin@test",
        full_name="Admin",
        hashed_password=hash_password("CyberRange!Test2026"),
        role=Role.ADMINISTRATOR,
        status=UserStatus.ACTIVE,
    )
    db.add(u)
    db.flush()
    return u


def _instructor(db, username: str) -> User:
    u = User(
        public_id=public_id("INS"),
        username=username,
        email=f"{username}@faculty.test",
        full_name=username.title(),
        hashed_password=hash_password("CyberRange!Test2026"),
        role=Role.INSTRUCTOR,
        status=UserStatus.ACTIVE,
    )
    db.add(u)
    db.flush()
    return u


def _group(db, kind=GroupKind.STUDENT, **kwargs) -> Group:
    g = Group(
        public_id=public_id("GRP"),
        name=kwargs.pop("name", "Test Group"),
        kind=kind.value,
        **kwargs,
    )
    db.add(g)
    db.flush()
    return g


def _create(db, actor, owner, name="m"):
    return create_machine(
        db,
        actor,
        owner=owner,
        name=name,
        template=None,
        kind=MachineKind.CONTAINER,
        vcpu=1,
        ram_mb=256,
        disk_gb=1,
        internet=False,
        isolated=True,
        ephemeral=False,
        ip="127.0.0.1",
    )


def _make_machine(db, actor, owner, name="m", running=True):
    machine, _ = _create(db, actor, owner, name=name)
    machine.status = MachineStatus.RUNNING if running else MachineStatus.STOPPED
    db.commit()
    return machine


def test_group_create_and_add_member(db):
    quota = _quota(db)
    student = _student(db, "alice", quota)
    group = _group(db, name="Cohort A")
    _join(db, group, student)
    assert group.members == [student]
    assert len([m for m in group.members if m.role == Role.STUDENT]) == 1


def test_machine_cap_enforced(db):
    quota = _quota(db)
    student = _student(db, "bob", quota)
    group = _group(db, name="Capped", max_machines=3)
    _join(db, group, student)

    # Student creates their own machines (quota path enforced, no staff bypass).
    for i in range(3):
        machine, _ = _create(db, student, student, name=f"m{i}")
        assert machine.status != MachineStatus.ERROR

    # The 4th is rejected by the group cap and lands in ERROR with a clear reason.
    fourth, _ = _create(db, student, student, name="m4")
    assert fourth.status == MachineStatus.ERROR
    assert "Group machine limit reached (3)" in fourth.error_message

    # Staff deploys bypass the cap (ignore_quota path).
    admin = _admin(db)
    staff_machine, _ = _create(db, admin, student, name="staff")
    assert staff_machine.status != MachineStatus.ERROR


def test_internet_policy_applies_to_member_labs(db):
    quota = _quota(db)
    s1 = _student(db, "carol", quota)
    s2 = _student(db, "dave", quota)
    group = _group(db, name="Net", internet_policy=InternetPolicy.ENABLED.value)
    _join(db, group, s1)
    _join(db, group, s2)

    touched = apply_internet_policy(db, group)
    db.commit()
    assert touched == 2
    for s in (s1, s2):
        assert s.lab.internet_enabled is True
        assert all(n.internet for n in s.lab.networks)

    group.internet_policy = InternetPolicy.DISABLED.value
    apply_internet_policy(db, group)
    db.commit()
    assert s1.lab.internet_enabled is False
    assert all(not n.internet for n in s1.lab.networks)


def test_group_shutdown_only_stops_that_group(db):
    quota = _quota(db)
    admin = _admin(db)
    in_group = _student(db, "erin", quota)
    outsider = _student(db, "frank", quota)
    group = _group(db, name="Shut")
    _join(db, group, in_group)

    m_in = _make_machine(db, admin, in_group, name="in", running=True)
    m_out = _make_machine(db, admin, outsider, name="out", running=True)

    result = shutdown_group(db, group, admin)
    db.commit()
    assert result["stopped"] == 1
    db.refresh(m_in)
    db.refresh(m_out)
    assert m_in.status == MachineStatus.STOPPED
    assert m_out.status == MachineStatus.RUNNING


def test_group_shutdown_protects_instructor_machines(db):
    quota = _quota(db)
    admin = _admin(db)
    instructor = User(
        public_id=public_id("INS"),
        username="prof",
        email="prof@test",
        full_name="Prof",
        hashed_password=hash_password("CyberRange!Test2026"),
        role=Role.INSTRUCTOR,
        status=UserStatus.ACTIVE,
    )
    db.add(instructor)
    db.flush()
    # Instructor accidentally in a student group; their machines must be untouched.
    group = _group(db, name="Mixed")
    _join(db, group, instructor)
    m = _make_machine(db, admin, instructor, name="prof-vm", running=True)

    result = shutdown_group(db, group, admin)
    db.commit()
    assert result["stopped"] == 0
    db.refresh(m)
    assert m.status == MachineStatus.RUNNING


def test_inactivity_alert_computation(db):
    quota = _quota(db)
    active = _student(db, "grace", quota)
    idle = _student(db, "heidi", quota)
    active.last_login_at = datetime.utcnow()
    idle.last_login_at = datetime.utcnow() - timedelta(days=5)
    group = _group(db, name="Idle", inactivity_alert_days=3)
    _join(db, group, active)
    _join(db, group, idle)

    alerts = inactivity_alerts(db, group)
    usernames = {a["username"] for a in alerts}
    assert "heidi" in usernames
    assert "grace" not in usernames


def test_running_activity_by_group(db):
    quota = _quota(db)
    admin = _admin(db)
    s1 = _student(db, "ivan", quota)
    group = _group(db, name="Live")
    _join(db, group, s1)
    _make_machine(db, admin, s1, name="live1", running=True)

    activity = running_activity(db)
    assert activity["total_running"] == 1
    assert activity["active_students"] == 1
    live_group = next(g for g in activity["by_group"] if g["name"] == "Live")
    assert live_group["active_students"] == 1
    assert live_group["running_machines"] == 1


def test_student_limited_to_one_group(db):
    quota = _quota(db)
    student = _student(db, "sam", quota)
    g1 = _group(db, name="G1")
    g2 = _group(db, name="G2")

    assert add_member(db, g1, student) is None
    db.commit()
    db.refresh(student)

    err = add_member(db, g2, student)
    assert err is not None
    assert "one group" in err
    db.commit()
    db.refresh(student)

    assert {g.name for g in student.groups} == {"G1"}
    assert student_group_of(student).name == "G1"


def test_instructor_allowed_in_multiple_groups(db):
    instructor = _instructor(db, "prof2")
    f1 = _group(db, kind=GroupKind.INSTRUCTOR, name="F1")
    f2 = _group(db, kind=GroupKind.INSTRUCTOR, name="F2")

    assert add_member(db, f1, instructor) is None
    assert add_member(db, f2, instructor) is None
    db.commit()
    db.refresh(instructor)

    assert {g.name for g in instructor.groups} == {"F1", "F2"}
    assert {m.username for m in f1.members} == {"prof2"}
    assert {m.username for m in f2.members} == {"prof2"}


def test_role_must_match_group_kind(db):
    quota = _quota(db)
    student = _student(db, "mismatch", quota)
    instructor_group = _group(db, kind=GroupKind.INSTRUCTOR, name="FacultyX")
    err = add_member(db, instructor_group, student)
    assert err is not None
    assert "requires instructor" in err


def test_member_listing_via_join_table(db):
    quota = _quota(db)
    s1 = _student(db, "m1", quota)
    s2 = _student(db, "m2", quota)
    group = _group(db, name="Listing")

    assert add_member(db, group, s1) is None
    assert add_member(db, group, s2) is None
    db.commit()
    db.refresh(group)
    assert {m.username for m in group.members} == {"m1", "m2"}

    from app.services.groups import remove_member

    assert remove_member(db, group, s1) is True
    db.commit()
    db.refresh(group)
    assert {m.username for m in group.members} == {"m2"}
    # s1 should no longer report the group
    db.refresh(s1)
    assert student_group_of(s1) is None
