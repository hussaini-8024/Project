from __future__ import annotations

from sqlalchemy.orm import Session

from app.models import (
    Announcement,
    AnnouncementScope,
    Group,
    GroupKind,
    Notification,
    Role,
    User,
)


def _student_ids_for_group(db: Session, group: Group) -> list[str]:
    return [m.id for m in group.members if m.role == Role.STUDENT]


def _all_student_ids(db: Session) -> list[str]:
    return [u.id for u in db.query(User).filter(User.role == Role.STUDENT).all()]


def fan_out(
    db: Session,
    *,
    user_ids: list[str],
    title: str,
    body: str = "",
    kind: str = "announcement",
    link: str = "",
    ref_id: str = "",
) -> int:
    """Create one Notification row per target user. Returns the number created."""
    created = 0
    for uid in dict.fromkeys(user_ids):  # de-dupe while preserving order
        db.add(
            Notification(
                user_id=uid,
                title=title[:128],
                body=body,
                kind=kind,
                link=link[:255],
                ref_id=ref_id or "",
            )
        )
        created += 1
    db.flush()
    return created


def announcement_targets(db: Session, announcement: Announcement) -> list[str]:
    """Student user ids that should receive notifications for an announcement."""
    if announcement.scope == AnnouncementScope.ALL.value:
        return _all_student_ids(db)
    if announcement.group_id:
        group = db.get(Group, announcement.group_id)
        if group and group.kind == GroupKind.STUDENT.value:
            return _student_ids_for_group(db, group)
    return []


def notify_announcement(db: Session, announcement: Announcement) -> int:
    """Fan an announcement out to every targeted student."""
    targets = announcement_targets(db, announcement)
    return fan_out(
        db,
        user_ids=targets,
        title=announcement.title,
        body=announcement.body,
        kind=announcement.kind,
        link=f"/announcements#{announcement.public_id}",
        ref_id=announcement.id,
    )


def notify_assignment(db: Session, *, assignment_id: str, title: str, description: str, student_ids: list[str]) -> int:
    """Fan a newly created assignment out to the relevant students."""
    return fan_out(
        db,
        user_ids=student_ids,
        title=f"New assignment: {title}",
        body=description or "A new assignment is available in Exercises.",
        kind="assignment",
        link="/exercises",
        ref_id=assignment_id,
    )
