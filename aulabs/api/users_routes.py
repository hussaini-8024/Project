"""User management API."""

from __future__ import annotations

from typing import Any

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field

from aulabs.api.deps import get_current_user, require_admin, require_permission
from aulabs.services.permissions import PermissionService
from aulabs.services.users import UserService

router = APIRouter()


class CreateUserBody(BaseModel):
    username: str
    password: str = Field(min_length=4)
    display_name: str = ""
    email: str = ""
    storage_quota_mb: int | None = None
    permissions: list[str] | None = None
    role: str = "user"


class UpdateUserBody(BaseModel):
    display_name: str | None = None
    email: str | None = None
    storage_quota_mb: int | None = None
    permissions: list[str] | None = None
    enabled: bool | None = None
    password: str | None = None


class PermissionsBody(BaseModel):
    permissions: list[str]


@router.get("")
async def list_users(user: dict = Depends(require_permission("users.manage"))):
    return {"users": UserService().list_users()}


@router.get("/permissions/catalog")
async def permission_catalog(user: dict = Depends(get_current_user)):
    return {"permissions": PermissionService().catalog()}


@router.get("/{user_id}")
async def get_user(user_id: int, user: dict = Depends(get_current_user)):
    if user["id"] != user_id and user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Forbidden")
    target = UserService().get_user(user_id)
    if not target:
        raise HTTPException(status_code=404, detail="User not found")
    return {"user": target}


@router.post("")
async def create_user(body: CreateUserBody, actor: dict = Depends(require_permission("users.manage"))):
    try:
        perms = None
        if body.permissions is not None:
            perms = PermissionService().validate(body.permissions)
        created = UserService().create_user(
            username=body.username,
            password=body.password,
            display_name=body.display_name,
            email=body.email,
            storage_quota_mb=body.storage_quota_mb,
            permissions=perms,
            role=body.role if actor.get("role") == "admin" else "user",
            actor=actor["username"],
        )
        return {"ok": True, "user": created}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@router.patch("/{user_id}")
async def update_user(
    user_id: int,
    body: UpdateUserBody,
    actor: dict = Depends(require_permission("users.manage")),
):
    try:
        perms = None
        if body.permissions is not None:
            perms = PermissionService().validate(body.permissions)
        updated = UserService().update_user(
            user_id,
            display_name=body.display_name,
            email=body.email,
            storage_quota_mb=body.storage_quota_mb,
            permissions=perms,
            enabled=body.enabled,
            password=body.password,
            actor=actor["username"],
        )
        return {"ok": True, "user": updated}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@router.put("/{user_id}/permissions")
async def set_permissions(
    user_id: int,
    body: PermissionsBody,
    actor: dict = Depends(require_permission("permissions.manage")),
):
    try:
        perms = PermissionService().validate(body.permissions)
        updated = UserService().set_permissions(user_id, perms, actor=actor["username"])
        return {"ok": True, "user": updated, "summary": PermissionService().summarize(updated)}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@router.get("/{user_id}/permissions")
async def get_permissions(user_id: int, actor: dict = Depends(get_current_user)):
    if actor["id"] != user_id and actor.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Forbidden")
    target = UserService().get_user(user_id)
    if not target:
        raise HTTPException(status_code=404, detail="User not found")
    return PermissionService().summarize(target)


@router.delete("/{user_id}")
async def delete_user(
    user_id: int,
    purge_home: bool = False,
    actor: dict = Depends(require_admin),
):
    try:
        UserService().delete_user(user_id, actor=actor["username"], purge_home=purge_home)
        return {"ok": True}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
