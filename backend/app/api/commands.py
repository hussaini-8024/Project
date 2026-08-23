from __future__ import annotations

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.data.commands import COMMANDS, search
from app.database import get_db
from app.deps import current_user
from app.models import User

router = APIRouter(tags=["commands"])


@router.get("/commands")
def list_commands(
    q: str = "",
    _: User = Depends(current_user),
    _db: Session = Depends(get_db),
) -> dict:
    """Search the offline cybersecurity command reference (all authenticated users)."""
    results = search(q, limit=200)
    categories = sorted({c["category"] for c in COMMANDS})
    return {
        "query": q,
        "count": len(results),
        "total": len(COMMANDS),
        "categories": categories,
        "results": results,
    }
