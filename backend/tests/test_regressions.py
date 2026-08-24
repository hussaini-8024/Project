"""Regression tests for bugs found while bringing the range fully live.

* ``GET /api/audit`` used to 500 for students because ``filter()`` was called
  after ``limit()`` on the query.
* Staff starting a machine via ``POST /api/machines/{id}/start`` used to be
  bound by the default (tiny) student quota, so admins could not restart
  machines. Staff starts must bypass the student quota, like create/deploy,
  while student-initiated starts stay quota-bound.
"""

from __future__ import annotations

from app.api.labs import start as start_endpoint
from app.models import (
    ComputeNode,
    MachineKind,
    MachineStatus,
    QuotaProfile,
    Role,
    User,
    UserStatus,
)
from app.security import hash_password, public_id
from app.services.labs import create_machine, ensure_lab


def _node(db):
    node = ComputeNode(
        name="node-test",
        hostname="node-test.local",
        role="controller+worker",
        status="healthy",
        ram_mb=131072,
        cpu_cores=32,
        storage_gb=3276,
        kvm_available=True,
        docker_available=True,
    )
    db.add(node)
    db.flush()
    return node


def _admin(db):
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


def _student(db, name="alex", max_storage_gb=2):
    quota = QuotaProfile(
        name=f"q-{name}",
        max_containers=5,
        max_running_containers=5,
        max_ram_mb=8192,
        max_vcpu=8,
        max_vms=1,
        max_storage_gb=max_storage_gb,
        max_snapshots=2,
    )
    db.add(quota)
    db.flush()
    u = User(
        public_id=public_id("STU"),
        username=name,
        email=f"{name}@test",
        full_name=name.title(),
        hashed_password=hash_password("CyberRange!Test2026"),
        role=Role.STUDENT,
        status=UserStatus.ACTIVE,
        quota_id=quota.id,
    )
    db.add(u)
    db.flush()
    ensure_lab(db, u)
    return u


def _make(db, actor, owner, disk_gb):
    m, _ = create_machine(
        db,
        actor,
        owner=owner,
        name="reg",
        template=None,
        kind=MachineKind.CONTAINER,
        vcpu=1,
        ram_mb=256,
        disk_gb=disk_gb,
        internet=False,
        isolated=True,
        ephemeral=False,
        ip="127.0.0.1",
    )
    return m


def test_student_audit_endpoint_is_scoped_and_ok(api):
    """A student can read their own audit trail without a 500 (filter before limit)."""
    res = api.get("/api/audit", headers=api.auth_headers("student"))
    assert res.status_code == 200, res.text
    rows = res.json()
    assert isinstance(rows, list)
    assert all(r["user"] == "student" for r in rows)


def test_staff_start_bypasses_student_quota(db):
    """Admin-initiated starts ignore the (tiny default) student quota.

    The machine requests 3 GB which exceeds the student's 2 GB storage quota.
    A staff deploy creates it RUNNING; after a stop, an admin start must bring
    it back to RUNNING rather than ERROR.
    """
    _node(db)
    admin = _admin(db)
    student = _student(db, "alex", max_storage_gb=2)

    machine = _make(db, admin, student, disk_gb=3)
    assert machine.status == MachineStatus.RUNNING, machine.error_message

    machine.status = MachineStatus.STOPPED
    db.commit()

    resp = start_endpoint(machine.public_id, user=admin, db=db)
    assert resp["machine"]["status"] == "running", resp["machine"]
    assert not resp["machine"]["error"]


def test_student_start_still_enforces_quota(db):
    """Student-initiated starts remain quota-bound (fix must not weaken this)."""
    _node(db)
    admin = _admin(db)
    student = _student(db, "sam", max_storage_gb=2)

    # Staff creates an over-quota (3 GB) machine for the student, then it stops.
    machine = _make(db, admin, student, disk_gb=3)
    machine.status = MachineStatus.STOPPED
    db.commit()

    # The student starting it themselves is rejected by their own quota.
    resp = start_endpoint(machine.public_id, user=student, db=db)
    assert resp["machine"]["status"] != "running"
    assert "quota" in (resp["machine"]["error"] or "").lower()
