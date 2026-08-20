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
