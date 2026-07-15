"""Storage API."""

from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel

from aulabs.api.deps import get_current_user, require_permission
from aulabs.services.storage import StorageService
from aulabs.services.users import UserService

router = APIRouter()


class MkdirBody(BaseModel):
    name: str
    user_id: int | None = None


@router.get("/summary")
async def storage_summary(user: dict = Depends(require_permission("storage.manage"))):
    users = UserService().list_users()
    return {
        "panel": StorageService().panel_storage_summary(),
        "users": StorageService().usage_all(users),
    }


@router.get("/me")
async def my_storage(user: dict = Depends(get_current_user)):
    return StorageService().usage_for_user(user)


@router.get("/files")
async def list_files(path: str = "", user: dict = Depends(require_permission("files.read"))):
    try:
        return {"path": path, "entries": StorageService().list_files(user, path)}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@router.post("/mkdir")
async def mkdir(body: MkdirBody, actor: dict = Depends(require_permission("files.write"))):
    target = actor
    if body.user_id and body.user_id != actor["id"]:
        if not UserService().has_permission(actor, "storage.manage"):
            raise HTTPException(status_code=403, detail="Cannot create dirs for other users")
        target = UserService().get_user(body.user_id)
        if not target:
            raise HTTPException(status_code=404, detail="User not found")
    try:
        created = StorageService().create_directory(target, body.name, actor=actor["username"])
        return {"ok": True, "directory": created}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
