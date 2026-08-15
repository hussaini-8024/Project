from __future__ import annotations

from sqlalchemy.orm import Session

from app.models import AuditLog, User


def record(
    db: Session,
    *,
    user: User | None,
    action: str,
    resource: str = "",
    machine: str = "",
    result: str = "success",
    detail: str = "",
    ip: str = "",
    session_id: str = "",
    lab_id: str = "",
) -> AuditLog:
    entry = AuditLog(
        user_id=user.id if user else None,
        username=user.username if user else "system",
        role=user.role.value if user else "system",
        session_id=session_id,
        lab_id=lab_id or (user.lab.public_id if user and user.lab else ""),
        ip=ip,
        action=action,
        resource=resource,
        machine=machine,
        result=result,
        detail=detail,
        immutable=True,
    )
    db.add(entry)
    db.flush()
    return entry
