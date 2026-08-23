from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import ADMIN_ROLES, current_user, require_staff
from app.models import (
    Announcement,
    AnnouncementKind,
    AnnouncementScope,
    Group,
    GroupKind,
    Notification,
    Role,
    User,
)
from app.security import public_id
from app.services import audit
from app.services.groups import student_group_of
from app.services.notifications import notify_announcement

router = APIRouter(tags=["announcements"])


class AnnouncementIn(BaseModel):
    title: str = Field(min_length=2, max_length=255)
    body: str = ""
    kind: str = AnnouncementKind.ANNOUNCEMENT.value
    scope: str = AnnouncementScope.GROUP.value
    group_id: str | None = None


def _dump(db: Session, a: Announcement) -> dict:
    author = db.get(User, a.author_id) if a.author_id else None
    group = db.get(Group, a.group_id) if a.group_id else None
    return {
        "id": a.id,
        "public_id": a.public_id,
        "title": a.title,
        "body": a.body,
        "kind": a.kind,
        "scope": a.scope,
        "group_id": a.group_id,
        "group": group.name if group else None,
        "author_id": a.author_id,
        "author": author.full_name or author.username if author else "System",
        "created_at": a.created_at.isoformat(),
    }


@router.post("/announcements", status_code=201)
def create_announcement(
    body: AnnouncementIn,
    user: User = Depends(require_staff),
    db: Session = Depends(get_db),
) -> dict:
    is_admin = user.role in ADMIN_ROLES
    scope = body.scope
    if scope not in {s.value for s in AnnouncementScope}:
        raise HTTPException(400, "scope must be 'group' or 'all'")
    if body.kind not in {k.value for k in AnnouncementKind}:
        raise HTTPException(400, "kind must be 'announcement' or 'assignment'")

    group_id: str | None = None
    if scope == AnnouncementScope.ALL.value:
        if not is_admin:
            raise HTTPException(403, "Only administrators may announce to all students")
    else:
        if not body.group_id:
            raise HTTPException(400, "group_id is required for a group-scoped announcement")
        group = (
            db.query(Group)
            .filter((Group.id == body.group_id) | (Group.public_id == body.group_id))
            .first()
        )
        if not group:
            raise HTTPException(404, "Group not found")
        if group.kind != GroupKind.STUDENT.value:
            raise HTTPException(400, "Announcements may only target student groups")
        group_id = group.id

    ann = Announcement(
        public_id=public_id("ANN"),
        author_id=user.id,
        title=body.title,
        body=body.body,
        kind=body.kind,
        scope=scope,
        group_id=group_id,
    )
    db.add(ann)
    db.flush()
    delivered = notify_announcement(db, ann)
    audit.record(
        db,
        user=user,
        action="announcement.create",
        resource=ann.public_id,
        detail=f"scope={scope} delivered={delivered}",
    )
    db.commit()
    return {**_dump(db, ann), "delivered": delivered}


@router.get("/announcements")
def list_announcements(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    query = db.query(Announcement).order_by(Announcement.created_at.desc())
    if user.role in ADMIN_ROLES:
        rows = query.all()
    elif user.role == Role.STUDENT:
        group = student_group_of(user)
        gid = group.id if group else None
        rows = [
            a
            for a in query.all()
            if a.scope == AnnouncementScope.ALL.value or (gid and a.group_id == gid)
        ]
    else:
        # Instructors / lab managers see global announcements + ones they authored.
        rows = [
            a
            for a in query.all()
            if a.scope == AnnouncementScope.ALL.value or a.author_id == user.id
        ]
    return [_dump(db, a) for a in rows]


@router.get("/notifications")
def list_notifications(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    rows = (
        db.query(Notification)
        .filter(Notification.user_id == user.id)
        .order_by(Notification.created_at.desc())
        .limit(100)
        .all()
    )
    unread = sum(1 for n in rows if not n.read)
    return {
        "unread": unread,
        "items": [
            {
                "id": n.id,
                "title": n.title,
                "body": n.body,
                "kind": n.kind,
                "link": n.link,
                "ref_id": n.ref_id,
                "read": n.read,
                "created_at": n.created_at.isoformat(),
            }
            for n in rows
        ],
    }


@router.post("/notifications/{notification_id}/read")
def mark_read(notification_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    n = db.get(Notification, notification_id)
    if not n or n.user_id != user.id:
        raise HTTPException(404, "Notification not found")
    n.read = True
    db.commit()
    return {"ok": True, "id": n.id, "read": True}


@router.post("/notifications/read-all")
def mark_all_read(user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    rows = (
        db.query(Notification)
        .filter(Notification.user_id == user.id, Notification.read.is_(False))
        .all()
    )
    for n in rows:
        n.read = True
    db.commit()
    return {"ok": True, "updated": len(rows)}
