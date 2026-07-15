"""System / master OS management API."""

from __future__ import annotations

from fastapi import APIRouter, Depends

from aulabs.api.deps import require_permission
from aulabs.database import get_db
from aulabs.services.system import SystemService

router = APIRouter()


@router.get("/overview")
async def overview(user: dict = Depends(require_permission("system.manage"))):
    return SystemService().overview()


@router.get("/audit")
async def audit_log(limit: int = 100, user: dict = Depends(require_permission("audit.view"))):
    limit = max(1, min(limit, 500))
    rows = get_db().fetchall(
        "SELECT * FROM audit_log ORDER BY id DESC LIMIT ?",
        (limit,),
    )
    return {"entries": rows}
