"""Session management API."""

from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field

from aulabs.api.deps import get_current_user, require_permission
from aulabs.services.sessions import SessionService
from aulabs.services.users import UserService

router = APIRouter()


class CreateSessionBody(BaseModel):
    session_type: str = "web"
    working_dir: str | None = None
    user_id: int | None = None


class RunCommandBody(BaseModel):
    command: str = Field(min_length=1, max_length=4000)
    session_id: str | None = None


@router.get("")
async def list_sessions(user: dict = Depends(get_current_user)):
    svc = SessionService()
    if user.get("role") == "admin":
        return {"sessions": svc.list_sessions()}
    return {"sessions": svc.list_sessions(user["id"])}


@router.post("")
async def create_session(
    body: CreateSessionBody,
    actor: dict = Depends(require_permission("session.create")),
):
    target = actor
    if body.user_id and body.user_id != actor["id"]:
        if actor.get("role") != "admin":
            raise HTTPException(status_code=403, detail="Admin required")
        target = UserService().get_user(body.user_id)
        if not target:
            raise HTTPException(status_code=404, detail="User not found")
    if body.session_type == "shell" and not UserService().has_permission(actor, "shell.access"):
        raise HTTPException(status_code=403, detail="Missing permission: shell.access")
    try:
        session = SessionService().create_session(
            target,
            session_type=body.session_type,
            working_dir=body.working_dir,
            actor=actor["username"],
        )
        return {"ok": True, "session": session}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@router.post("/run")
async def run_command(
    body: RunCommandBody,
    user: dict = Depends(require_permission("shell.access")),
):
    try:
        result = SessionService().run_command(user, body.command, session_id=body.session_id)
        return result
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@router.post("/{session_id}/terminate")
async def terminate_session(session_id: str, user: dict = Depends(get_current_user)):
    session = SessionService().get_session(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")
    if session["user_id"] != user["id"] and user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Forbidden")
    return {"ok": True, "session": SessionService().terminate(session_id, actor=user["username"])}


@router.get("/{session_id}")
async def get_session(session_id: str, user: dict = Depends(get_current_user)):
    session = SessionService().get_session(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")
    if session["user_id"] != user["id"] and user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Forbidden")
    return {"session": session}
