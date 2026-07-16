"""Auth and session helpers for API routes."""

from __future__ import annotations

from typing import Any, Callable

from fastapi import Depends, HTTPException, Request

from aulabs.services.users import UserService


def get_current_user(request: Request) -> dict[str, Any]:
    user = request.session.get("user")
    if not user:
        raise HTTPException(status_code=401, detail="Not authenticated")
    # Refresh from DB so permissions stay current
    fresh = UserService().get_user(user["id"])
    if not fresh or not fresh.get("enabled"):
        request.session.clear()
        raise HTTPException(status_code=401, detail="Account disabled or missing")
    request.session["user"] = fresh
    return fresh


def require_permission(permission: str) -> Callable:
    def _dep(user: dict[str, Any] = Depends(get_current_user)) -> dict[str, Any]:
        if not UserService().has_permission(user, permission):
            raise HTTPException(status_code=403, detail=f"Missing permission: {permission}")
        return user

    return _dep


def require_admin(user: dict[str, Any] = Depends(get_current_user)) -> dict[str, Any]:
    if user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin required")
    return user
