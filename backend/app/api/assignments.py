from __future__ import annotations

from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import current_user, require_staff
from app.models import (
    Assignment,
    AssignmentStatus,
    AssignmentSubmission,
    MachineKind,
    MachineTemplate,
    Role,
    User,
)
from app.services import audit
from app.services.labs import create_machine

router = APIRouter(tags=["assignments"])


class AssignmentIn(BaseModel):
    title: str
    description: str = ""
    objective: str = ""
    required_templates: list[str] = []
    duration_minutes: int = 120
    course: str = ""
    student_ids: list[str] = []


@router.get("/assignments")
def list_assignments(user: User = Depends(current_user), db: Session = Depends(get_db)) -> list[dict]:
    rows = db.query(Assignment).order_by(Assignment.created_at.desc()).all()
    out = []
    for a in rows:
        sub = None
        if user.role == Role.STUDENT:
            sub = next((s for s in a.submissions if s.student_id == user.id), None)
        out.append(
            {
                "id": a.id,
                "title": a.title,
                "description": a.description,
                "objective": a.objective,
                "required_templates": a.required_templates,
                "duration_minutes": a.duration_minutes,
                "course": a.course,
                "created_at": a.created_at.isoformat(),
                "status": sub.status.value if sub else None,
                "grade": sub.grade if sub else None,
                "submissions": len(a.submissions),
            }
        )
    return out


@router.post("/assignments")
def create_assignment(body: AssignmentIn, user: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    a = Assignment(
        title=body.title,
        description=body.description,
        objective=body.objective,
        required_templates=body.required_templates,
        duration_minutes=body.duration_minutes,
        created_by=user.id,
        course=body.course,
    )
    db.add(a)
    db.flush()
    targets = body.student_ids
    if not targets:
        targets = [u.id for u in db.query(User).filter(User.role == Role.STUDENT).all()]
    for sid in targets:
        db.add(AssignmentSubmission(assignment_id=a.id, student_id=sid))
    audit.record(db, user=user, action="assignment.create", resource=a.title)
    db.commit()
    return {"id": a.id, "title": a.title}


@router.post("/assignments/{assignment_id}/start")
def start_assignment(assignment_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    a = db.get(Assignment, assignment_id)
    if not a:
        raise HTTPException(404, "Assignment not found")
    sub = (
        db.query(AssignmentSubmission)
        .filter(AssignmentSubmission.assignment_id == a.id, AssignmentSubmission.student_id == user.id)
        .first()
    )
    if not sub:
        sub = AssignmentSubmission(assignment_id=a.id, student_id=user.id)
        db.add(sub)
        db.flush()
    created = []
    for slug in a.required_templates:
        tmpl = db.query(MachineTemplate).filter(MachineTemplate.slug == slug).first()
        if not tmpl:
            continue
        m, _ = create_machine(
            db,
            user,
            name=tmpl.name,
            template=tmpl,
            kind=tmpl.recommended_kind,
            vcpu=tmpl.default_vcpu,
            ram_mb=tmpl.default_ram_mb,
            disk_gb=tmpl.default_disk_gb,
            internet=False,
            isolated=True,
            ephemeral=True,
            ip="",
        )
        created.append(m.public_id)
    sub.status = AssignmentStatus.RUNNING
    sub.started_at = datetime.utcnow()
    audit.record(db, user=user, action="assignment.start", resource=a.title)
    db.commit()
    return {"status": sub.status.value, "machines": created}


@router.post("/assignments/{assignment_id}/complete")
def complete_assignment(assignment_id: str, user: User = Depends(current_user), db: Session = Depends(get_db)) -> dict:
    sub = (
        db.query(AssignmentSubmission)
        .filter(AssignmentSubmission.assignment_id == assignment_id, AssignmentSubmission.student_id == user.id)
        .first()
    )
    if not sub:
        raise HTTPException(404, "Submission not found")
    sub.status = AssignmentStatus.COMPLETED
    sub.completed_at = datetime.utcnow()
    db.commit()
    return {"status": sub.status.value}


@router.post("/assignments/{assignment_id}/grade")
def grade(assignment_id: str, body: dict, user: User = Depends(require_staff), db: Session = Depends(get_db)) -> dict:
    sub = (
        db.query(AssignmentSubmission)
        .filter(
            AssignmentSubmission.assignment_id == assignment_id,
            AssignmentSubmission.student_id == body.get("student_id"),
        )
        .first()
    )
    if not sub:
        raise HTTPException(404, "Submission not found")
    sub.grade = str(body.get("grade", ""))
    sub.feedback = str(body.get("feedback", ""))
    sub.status = AssignmentStatus.GRADED
    audit.record(db, user=user, action="assignment.grade", resource=assignment_id)
    db.commit()
    return {"ok": True}


@router.get("/assignments/{assignment_id}/submissions")
def submissions(assignment_id: str, _: User = Depends(require_staff), db: Session = Depends(get_db)) -> list[dict]:
    rows = db.query(AssignmentSubmission).filter(AssignmentSubmission.assignment_id == assignment_id).all()
    return [
        {
            "id": s.id,
            "student_id": s.student_id,
            "status": s.status.value,
            "grade": s.grade,
            "feedback": s.feedback,
            "started_at": s.started_at.isoformat() if s.started_at else None,
            "completed_at": s.completed_at.isoformat() if s.completed_at else None,
        }
        for s in rows
    ]
