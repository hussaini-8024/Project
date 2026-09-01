from __future__ import annotations

import os

os.environ["COMPUTE_PROVIDER"] = "mock"
os.environ.setdefault("STORAGE_ROOT", "/tmp/cyberrange-test-storage")

import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

from app.config import get_settings
from app.database import Base

get_settings.cache_clear()


@pytest.fixture(autouse=True)
def _no_guest_provisioning(monkeypatch):
    """Keep tests off the real Linux runtime (netns/ip/sudo)."""
    monkeypatch.setattr("app.services.guest.provision_guest", lambda machine: None)
    yield


@pytest.fixture()
def db(tmp_path):
    engine = create_engine(
        f"sqlite:///{tmp_path / 'test.db'}",
        connect_args={"check_same_thread": False},
    )
    Base.metadata.create_all(bind=engine)
    Session = sessionmaker(bind=engine, autoflush=False, autocommit=False)
    session = Session()
    try:
        yield session
    finally:
        session.close()
        engine.dispose()


@pytest.fixture()
def api(tmp_path):
    """FastAPI TestClient backed by an isolated sqlite DB and seeded demo users.

    The app's ``get_db`` dependency is overridden so requests hit this test DB.
    The TestClient is not used as a context manager, so the app lifespan (which
    would seed the real dev DB) does not run.
    """
    from fastapi.testclient import TestClient

    from app.database import get_db
    from app.main import app
    from app.models import (
        Group,
        GroupKind,
        GroupMembership,
        Role,
        User,
        UserStatus,
    )
    from app.security import hash_password, public_id

    engine = create_engine(
        f"sqlite:///{tmp_path / 'api.db'}",
        connect_args={"check_same_thread": False},
    )
    Base.metadata.create_all(bind=engine)
    Session = sessionmaker(bind=engine, autoflush=False, autocommit=False)

    def _mk(username, role, pw="CyberRange!Test2026", prefix="USR"):
        return User(
            public_id=public_id(prefix),
            username=username,
            email=f"{username}@test.local",
            full_name=username.title(),
            hashed_password=hash_password(pw),
            role=role,
            status=UserStatus.ACTIVE,
        )

    seed_session = Session()
    admin = _mk("admin", Role.ADMINISTRATOR, prefix="ADM")
    instructor = _mk("instructor", Role.INSTRUCTOR, prefix="INS")
    student = _mk("student", Role.STUDENT, prefix="STU")
    student2 = _mk("jordan", Role.STUDENT, prefix="STU")
    seed_session.add_all([admin, instructor, student, student2])
    seed_session.flush()
    group = Group(
        public_id=public_id("GRP"),
        name="CYB-301 Students",
        kind=GroupKind.STUDENT.value,
        created_by=admin.id,
    )
    seed_session.add(group)
    seed_session.flush()
    seed_session.add(GroupMembership(user_id=student.id, group_id=group.id))
    student.group_id = group.id
    ids = {
        "admin": admin.id,
        "instructor": instructor.id,
        "student": student.id,
        "student2": student2.id,
        "group": group.id,
    }
    seed_session.commit()
    seed_session.close()

    def _get_db():
        s = Session()
        try:
            yield s
        finally:
            s.close()

    app.dependency_overrides[get_db] = _get_db
    client = TestClient(app)
    client.ids = ids  # type: ignore[attr-defined]

    def login(username, password="CyberRange!Test2026"):
        res = client.post("/api/auth/login", json={"username": username, "password": password})
        assert res.status_code == 200, res.text
        return res.json()["access_token"]

    def auth_headers(username):
        return {"Authorization": f"Bearer {login(username)}"}

    client.login = login  # type: ignore[attr-defined]
    client.auth_headers = auth_headers  # type: ignore[attr-defined]

    try:
        yield client
    finally:
        app.dependency_overrides.pop(get_db, None)
        engine.dispose()
