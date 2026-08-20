from __future__ import annotations

import enum
from datetime import datetime
from typing import Any
from uuid import uuid4

from sqlalchemy import (
    JSON,
    Boolean,
    DateTime,
    Enum,
    Float,
    ForeignKey,
    Index,
    Integer,
    String,
    Text,
    UniqueConstraint,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


def _uuid() -> str:
    return str(uuid4())


def utcnow() -> datetime:
    return datetime.utcnow()


class Role(str, enum.Enum):
    SUPER_ADMIN = "super_admin"
    ADMINISTRATOR = "administrator"
    INSTRUCTOR = "instructor"
    LAB_MANAGER = "lab_manager"
    STUDENT = "student"


class UserStatus(str, enum.Enum):
    ACTIVE = "active"
    DISABLED = "disabled"
    EXPIRED = "expired"
    PENDING = "pending"


class MachineKind(str, enum.Enum):
    CONTAINER = "container"
    VM = "vm"


class MachineStatus(str, enum.Enum):
    RUNNING = "running"
    STOPPED = "stopped"
    STARTING = "starting"
    STOPPING = "stopping"
    ERROR = "error"
    QUEUED = "queued"
    PAUSED = "paused"
    CREATING = "creating"


class EnvironmentKind(str, enum.Enum):
    CONTAINER = "container"
    VM = "vm"
    PREBUILT = "prebuilt"


class AssignmentStatus(str, enum.Enum):
    NOT_STARTED = "not_started"
    RUNNING = "running"
    COMPLETED = "completed"
    GRADED = "graded"


class IsoStatus(str, enum.Enum):
    PENDING = "pending"
    APPROVED = "approved"
    REJECTED = "rejected"


class GroupKind(str, enum.Enum):
    STUDENT = "student"
    INSTRUCTOR = "instructor"


class InternetPolicy(str, enum.Enum):
    ENABLED = "enabled"
    DISABLED = "disabled"
    UNSET = "unset"


class QuotaProfile(Base):
    __tablename__ = "resource_quotas"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    name: Mapped[str] = mapped_column(String(64), unique=True)
    max_containers: Mapped[int] = mapped_column(Integer, default=3)
    max_running_containers: Mapped[int] = mapped_column(Integer, default=2)
    max_ram_mb: Mapped[int] = mapped_column(Integer, default=2048)
    max_vcpu: Mapped[int] = mapped_column(Integer, default=2)
    max_vms: Mapped[int] = mapped_column(Integer, default=0)
    max_storage_gb: Mapped[int] = mapped_column(Integer, default=2)
    max_snapshots: Mapped[int] = mapped_column(Integer, default=2)
    description: Mapped[str] = mapped_column(String(255), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    users: Mapped[list[User]] = relationship(back_populates="quota")


class Group(Base):
    __tablename__ = "groups"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    public_id: Mapped[str] = mapped_column(String(32), unique=True, index=True)
    name: Mapped[str] = mapped_column(String(128), unique=True)
    kind: Mapped[str] = mapped_column(String(32), default=GroupKind.STUDENT.value)
    description: Mapped[str] = mapped_column(String(255), default="")
    # Policies (see app/services/groups.py for enforcement)
    internet_policy: Mapped[str] = mapped_column(String(16), default=InternetPolicy.DISABLED.value)
    max_machines: Mapped[int | None] = mapped_column(Integer, nullable=True)
    inactivity_alert_days: Mapped[int] = mapped_column(Integer, default=3)
    created_by: Mapped[str | None] = mapped_column(ForeignKey("users.id"), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    members: Mapped[list[User]] = relationship(
        back_populates="group", foreign_keys="User.group_id"
    )


class User(Base):
    __tablename__ = "users"
    __table_args__ = (Index("ix_users_username", "username"),)

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    public_id: Mapped[str] = mapped_column(String(32), unique=True, index=True)
    username: Mapped[str] = mapped_column(String(64), unique=True)
    email: Mapped[str] = mapped_column(String(255), unique=True)
    full_name: Mapped[str] = mapped_column(String(128), default="")
    hashed_password: Mapped[str] = mapped_column(String(255))
    role: Mapped[Role] = mapped_column(Enum(Role), default=Role.STUDENT)
    status: Mapped[UserStatus] = mapped_column(Enum(UserStatus), default=UserStatus.ACTIVE)
    quota_id: Mapped[str | None] = mapped_column(ForeignKey("resource_quotas.id"))
    group_id: Mapped[str | None] = mapped_column(
        ForeignKey("groups.id", ondelete="SET NULL"), nullable=True
    )
    course: Mapped[str] = mapped_column(String(128), default="")
    expires_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    mfa_secret: Mapped[str | None] = mapped_column(String(64), nullable=True)
    mfa_enabled: Mapped[bool] = mapped_column(Boolean, default=False)
    failed_logins: Mapped[int] = mapped_column(Integer, default=0)
    locked_until: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    last_login_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    quota: Mapped[QuotaProfile | None] = relationship(back_populates="users")
    group: Mapped[Group | None] = relationship(
        back_populates="members", foreign_keys="User.group_id"
    )
    lab: Mapped[StudentLab | None] = relationship(back_populates="student", uselist=False)
    sessions: Mapped[list[UserSession]] = relationship(back_populates="user")


class UserSession(Base):
    __tablename__ = "sessions"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    public_id: Mapped[str] = mapped_column(String(32), unique=True, index=True)
    user_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    refresh_token_hash: Mapped[str] = mapped_column(String(128))
    ip: Mapped[str] = mapped_column(String(64), default="")
    user_agent: Mapped[str] = mapped_column(String(255), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    expires_at: Mapped[datetime] = mapped_column(DateTime)
    last_seen_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    revoked: Mapped[bool] = mapped_column(Boolean, default=False)

    user: Mapped[User] = relationship(back_populates="sessions")


class ComputeNode(Base):
    __tablename__ = "compute_nodes"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    name: Mapped[str] = mapped_column(String(64), unique=True)
    hostname: Mapped[str] = mapped_column(String(128), default="localhost")
    role: Mapped[str] = mapped_column(String(32), default="worker")  # controller|worker
    status: Mapped[str] = mapped_column(String(32), default="healthy")
    ram_mb: Mapped[int] = mapped_column(Integer, default=131072)
    cpu_cores: Mapped[int] = mapped_column(Integer, default=32)
    storage_gb: Mapped[int] = mapped_column(Integer, default=3276)
    kvm_available: Mapped[bool] = mapped_column(Boolean, default=True)
    docker_available: Mapped[bool] = mapped_column(Boolean, default=True)
    labels: Mapped[dict[str, Any]] = mapped_column(JSON, default=dict)
    last_heartbeat: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class StudentLab(Base):
    __tablename__ = "student_labs"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    public_id: Mapped[str] = mapped_column(String(40), unique=True, index=True)
    student_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"), unique=True)
    name: Mapped[str] = mapped_column(String(128), default="My Laboratory")
    status: Mapped[str] = mapped_column(String(32), default="ready")
    internet_enabled: Mapped[bool] = mapped_column(Boolean, default=False)
    config: Mapped[dict[str, Any]] = mapped_column(JSON, default=dict)
    last_restored_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, onupdate=utcnow)

    student: Mapped[User] = relationship(back_populates="lab")
    networks: Mapped[list[LabNetwork]] = relationship(back_populates="lab")
    machines: Mapped[list[Machine]] = relationship(back_populates="lab")

    @property
    def network(self) -> LabNetwork | None:
        return self.networks[0] if self.networks else None


class LabNetwork(Base):
    __tablename__ = "networks"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    lab_id: Mapped[str | None] = mapped_column(ForeignKey("student_labs.id", ondelete="SET NULL"), nullable=True)
    name: Mapped[str] = mapped_column(String(64))
    cidr: Mapped[str] = mapped_column(String(32), default="10.0.0.0/8")
    vlan_id: Mapped[int] = mapped_column(Integer)
    namespace: Mapped[str] = mapped_column(String(64))
    isolated: Mapped[bool] = mapped_column(Boolean, default=True)
    internet: Mapped[bool] = mapped_column(Boolean, default=False)
    bridge: Mapped[str] = mapped_column(String(64), default="")
    kind: Mapped[str] = mapped_column(String(32), default="student")  # student | admin
    created_by: Mapped[str | None] = mapped_column(ForeignKey("users.id"), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    lab: Mapped[StudentLab | None] = relationship(back_populates="networks")
    interfaces: Mapped[list[NetworkInterface]] = relationship(back_populates="network")


class MachineTemplate(Base):
    __tablename__ = "machine_templates"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    name: Mapped[str] = mapped_column(String(128), unique=True)
    slug: Mapped[str] = mapped_column(String(64), unique=True)
    environment: Mapped[EnvironmentKind] = mapped_column(Enum(EnvironmentKind))
    recommended_kind: Mapped[MachineKind] = mapped_column(Enum(MachineKind))
    os_family: Mapped[str] = mapped_column(String(64))
    image_ref: Mapped[str] = mapped_column(String(255))
    default_vcpu: Mapped[int] = mapped_column(Integer, default=1)
    default_ram_mb: Mapped[int] = mapped_column(Integer, default=512)
    default_disk_gb: Mapped[int] = mapped_column(Integer, default=2)
    is_vulnerable_target: Mapped[bool] = mapped_column(Boolean, default=False)
    requires_kernel: Mapped[bool] = mapped_column(Boolean, default=False)
    requires_full_os: Mapped[bool] = mapped_column(Boolean, default=False)
    tools: Mapped[list[str]] = mapped_column(JSON, default=list)
    description: Mapped[str] = mapped_column(Text, default="")
    category: Mapped[str] = mapped_column(String(64), default="general")
    approved: Mapped[bool] = mapped_column(Boolean, default=True)
    warning_label: Mapped[str] = mapped_column(String(255), default="")


class Machine(Base):
    __tablename__ = "virtual_machines"
    __table_args__ = (Index("ix_machines_owner_status", "owner_id", "status"),)

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    public_id: Mapped[str] = mapped_column(String(32), unique=True, index=True)
    lab_id: Mapped[str] = mapped_column(ForeignKey("student_labs.id", ondelete="CASCADE"))
    owner_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    template_id: Mapped[str | None] = mapped_column(ForeignKey("machine_templates.id"))
    node_id: Mapped[str | None] = mapped_column(ForeignKey("compute_nodes.id"))
    name: Mapped[str] = mapped_column(String(128))
    kind: Mapped[MachineKind] = mapped_column(Enum(MachineKind))
    status: Mapped[MachineStatus] = mapped_column(Enum(MachineStatus), default=MachineStatus.STOPPED)
    vcpu: Mapped[int] = mapped_column(Integer, default=1)
    ram_mb: Mapped[int] = mapped_column(Integer, default=512)
    disk_gb: Mapped[int] = mapped_column(Integer, default=2)
    provider_ref: Mapped[str] = mapped_column(String(128), default="")
    internet: Mapped[bool] = mapped_column(Boolean, default=False)
    isolated: Mapped[bool] = mapped_column(Boolean, default=True)
    ephemeral: Mapped[bool] = mapped_column(Boolean, default=False)
    queue_position: Mapped[int | None] = mapped_column(Integer, nullable=True)
    queue_reason: Mapped[str] = mapped_column(String(255), default="")
    error_message: Mapped[str] = mapped_column(String(512), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, onupdate=utcnow)
    last_started_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)

    lab: Mapped[StudentLab] = relationship(back_populates="machines")
    template: Mapped[MachineTemplate | None] = relationship()
    node: Mapped[ComputeNode | None] = relationship()
    interfaces: Mapped[list[NetworkInterface]] = relationship(
        back_populates="machine", cascade="all, delete-orphan"
    )
    snapshots: Mapped[list[Snapshot]] = relationship(back_populates="machine")


class NetworkInterface(Base):
    __tablename__ = "network_interfaces"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    network_id: Mapped[str] = mapped_column(ForeignKey("networks.id", ondelete="CASCADE"))
    machine_id: Mapped[str] = mapped_column(ForeignKey("virtual_machines.id", ondelete="CASCADE"))
    mac: Mapped[str] = mapped_column(String(32))
    ipv4: Mapped[str] = mapped_column(String(32))
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    network: Mapped[LabNetwork] = relationship(back_populates="interfaces")
    machine: Mapped[Machine] = relationship(back_populates="interfaces")


class ContainerImage(Base):
    __tablename__ = "container_images"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    name: Mapped[str] = mapped_column(String(128), unique=True)
    tag: Mapped[str] = mapped_column(String(64), default="latest")
    digest: Mapped[str] = mapped_column(String(128), default="")
    size_mb: Mapped[int] = mapped_column(Integer, default=0)
    shared: Mapped[bool] = mapped_column(Boolean, default=True)
    approved: Mapped[bool] = mapped_column(Boolean, default=True)
    description: Mapped[str] = mapped_column(String(255), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class IsoImage(Base):
    __tablename__ = "iso_images"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    name: Mapped[str] = mapped_column(String(128))
    filename: Mapped[str] = mapped_column(String(255))
    size_bytes: Mapped[int] = mapped_column(Integer, default=0)
    sha256: Mapped[str] = mapped_column(String(64), default="")
    status: Mapped[IsoStatus] = mapped_column(Enum(IsoStatus), default=IsoStatus.PENDING)
    uploaded_by: Mapped[str | None] = mapped_column(ForeignKey("users.id"))
    os_family: Mapped[str] = mapped_column(String(64), default="")
    approved_for_students: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class StorageVolume(Base):
    __tablename__ = "storage_volumes"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    owner_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    lab_id: Mapped[str | None] = mapped_column(ForeignKey("student_labs.id"))
    machine_id: Mapped[str | None] = mapped_column(ForeignKey("virtual_machines.id"))
    name: Mapped[str] = mapped_column(String(128))
    category: Mapped[str] = mapped_column(String(32), default="student")
    size_gb: Mapped[float] = mapped_column(Float, default=1)
    persistent: Mapped[bool] = mapped_column(Boolean, default=True)
    path: Mapped[str] = mapped_column(String(255), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class Snapshot(Base):
    __tablename__ = "snapshots"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    machine_id: Mapped[str] = mapped_column(ForeignKey("virtual_machines.id", ondelete="CASCADE"))
    owner_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    name: Mapped[str] = mapped_column(String(128))
    size_mb: Mapped[int] = mapped_column(Integer, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    machine: Mapped[Machine] = relationship(back_populates="snapshots")


class Assignment(Base):
    __tablename__ = "lab_assignments"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    title: Mapped[str] = mapped_column(String(255))
    description: Mapped[str] = mapped_column(Text, default="")
    objective: Mapped[str] = mapped_column(Text, default="")
    required_templates: Mapped[list[str]] = mapped_column(JSON, default=list)
    duration_minutes: Mapped[int] = mapped_column(Integer, default=120)
    created_by: Mapped[str] = mapped_column(ForeignKey("users.id"))
    course: Mapped[str] = mapped_column(String(128), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)

    submissions: Mapped[list[AssignmentSubmission]] = relationship(back_populates="assignment")


class AssignmentSubmission(Base):
    __tablename__ = "assignment_submissions"
    __table_args__ = (UniqueConstraint("assignment_id", "student_id"),)

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    assignment_id: Mapped[str] = mapped_column(ForeignKey("lab_assignments.id", ondelete="CASCADE"))
    student_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    status: Mapped[AssignmentStatus] = mapped_column(
        Enum(AssignmentStatus), default=AssignmentStatus.NOT_STARTED
    )
    grade: Mapped[str | None] = mapped_column(String(16), nullable=True)
    feedback: Mapped[str] = mapped_column(Text, default="")
    started_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)

    assignment: Mapped[Assignment] = relationship(back_populates="submissions")


class AuditLog(Base):
    __tablename__ = "audit_logs"
    __table_args__ = (Index("ix_audit_created", "created_at"),)

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    user_id: Mapped[str | None] = mapped_column(ForeignKey("users.id"), nullable=True)
    username: Mapped[str] = mapped_column(String(64), default="")
    role: Mapped[str] = mapped_column(String(32), default="")
    session_id: Mapped[str] = mapped_column(String(32), default="")
    lab_id: Mapped[str] = mapped_column(String(40), default="")
    ip: Mapped[str] = mapped_column(String(64), default="")
    action: Mapped[str] = mapped_column(String(128))
    resource: Mapped[str] = mapped_column(String(128), default="")
    machine: Mapped[str] = mapped_column(String(128), default="")
    result: Mapped[str] = mapped_column(String(32), default="success")
    detail: Mapped[str] = mapped_column(Text, default="")
    immutable: Mapped[bool] = mapped_column(Boolean, default=True)


class MachineEvent(Base):
    __tablename__ = "machine_events"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    machine_id: Mapped[str] = mapped_column(ForeignKey("virtual_machines.id", ondelete="CASCADE"))
    event: Mapped[str] = mapped_column(String(64))
    detail: Mapped[str] = mapped_column(String(255), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class Backup(Base):
    __tablename__ = "backups"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    kind: Mapped[str] = mapped_column(String(32))  # database, lab, volume, config
    name: Mapped[str] = mapped_column(String(128))
    path: Mapped[str] = mapped_column(String(255), default="")
    size_mb: Mapped[int] = mapped_column(Integer, default=0)
    status: Mapped[str] = mapped_column(String(32), default="completed")
    created_by: Mapped[str | None] = mapped_column(ForeignKey("users.id"))
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)


class Notification(Base):
    __tablename__ = "notifications"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    user_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    title: Mapped[str] = mapped_column(String(128))
    body: Mapped[str] = mapped_column(Text, default="")
    read: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class ResourceSample(Base):
    __tablename__ = "resource_usage"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    node_id: Mapped[str | None] = mapped_column(ForeignKey("compute_nodes.id"))
    cpu_percent: Mapped[float] = mapped_column(Float, default=0)
    ram_percent: Mapped[float] = mapped_column(Float, default=0)
    storage_percent: Mapped[float] = mapped_column(Float, default=0)
    disk_iops: Mapped[float] = mapped_column(Float, default=0)
    net_mbps: Mapped[float] = mapped_column(Float, default=0)
    running_containers: Mapped[int] = mapped_column(Integer, default=0)
    running_vms: Mapped[int] = mapped_column(Integer, default=0)
    queued: Mapped[int] = mapped_column(Integer, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class LoadTestRun(Base):
    __tablename__ = "load_test_runs"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    students: Mapped[int] = mapped_column(Integer)
    report: Mapped[dict[str, Any]] = mapped_column(JSON, default=dict)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)


class SystemSetting(Base):
    __tablename__ = "system_settings"

    key: Mapped[str] = mapped_column(String(64), primary_key=True)
    value: Mapped[dict[str, Any]] = mapped_column(JSON, default=dict)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow, onupdate=utcnow)


class TerminalSession(Base):
    __tablename__ = "terminal_sessions"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    user_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    machine_id: Mapped[str] = mapped_column(ForeignKey("virtual_machines.id", ondelete="CASCADE"))
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    closed_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)


class ConsoleSession(Base):
    __tablename__ = "console_sessions"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=_uuid)
    user_id: Mapped[str] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"))
    machine_id: Mapped[str] = mapped_column(ForeignKey("virtual_machines.id", ondelete="CASCADE"))
    created_at: Mapped[datetime] = mapped_column(DateTime, default=utcnow)
    closed_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
