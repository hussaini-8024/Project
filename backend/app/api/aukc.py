"""AU Kamra AI Agent — offline PDF book-library search.

Admins upload PDF books; their text is extracted with ``pypdf`` and stored as
searchable chunks. All authenticated users can run a local BM25 relevance search
over every book. There are no external AI calls and no API keys anywhere.
"""

from __future__ import annotations

import logging
from pathlib import Path

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.config import get_settings
from app.database import get_db
from app.deps import current_user, require_admin
from app.models import BookChunk, BookDocument, User
from app.security import public_id
from app.services import audit, booksearch

router = APIRouter(tags=["aukc"])
logger = logging.getLogger("aukc")

_PDF_CONTENT_TYPES = {"application/pdf", "application/x-pdf", "application/octet-stream"}


def _books_dir() -> Path:
    settings = get_settings()
    d = Path(settings.storage_root) / "books"
    d.mkdir(parents=True, exist_ok=True)
    return d


def _book_summary(book: BookDocument) -> dict:
    return {
        "id": book.id,
        "public_id": book.public_id,
        "title": book.title,
        "filename": book.filename,
        "page_count": book.page_count,
        "size_bytes": book.size_bytes,
        "uploaded_at": book.created_at.isoformat(),
    }


@router.get("/aukc/books")
def list_books(
    _: User = Depends(current_user), db: Session = Depends(get_db)
) -> list[dict]:
    """List the book library. Readable by all authenticated users."""
    books = db.query(BookDocument).order_by(BookDocument.created_at.desc()).all()
    return [_book_summary(b) for b in books]


@router.post("/aukc/books")
async def upload_book(
    file: UploadFile = File(...),
    title: str = Form(""),
    user: User = Depends(require_admin),
    db: Session = Depends(get_db),
) -> dict:
    """Admin-only PDF upload. Extracts text per page into searchable chunks."""
    settings = get_settings()
    filename = file.filename or "book.pdf"
    ext_ok = filename.lower().endswith(".pdf")
    type_ok = (file.content_type or "").lower() in _PDF_CONTENT_TYPES
    if not ext_ok and not type_ok:
        raise HTTPException(400, "Only PDF files are supported.")

    data = await file.read()
    max_bytes = settings.aukc_book_max_mb * 1024 * 1024
    if len(data) == 0:
        raise HTTPException(400, "Uploaded file is empty.")
    if len(data) > max_bytes:
        raise HTTPException(400, f"PDF exceeds the {settings.aukc_book_max_mb} MB limit.")

    try:
        chunks = booksearch.extract_chunks(data)
    except ValueError as exc:
        raise HTTPException(400, str(exc)) from exc
    if not chunks:
        raise HTTPException(
            400, "No readable text found in the PDF (it may be scanned images only)."
        )

    page_count = max(page for page, _ in chunks)
    clean_title = (title or "").strip() or Path(filename).stem

    book = BookDocument(
        public_id=public_id("BOK"),
        title=clean_title[:255],
        filename=Path(filename).name[:255],
        uploaded_by=user.id,
        size_bytes=len(data),
        page_count=page_count,
    )
    db.add(book)
    db.flush()

    for page_number, content in chunks:
        db.add(BookChunk(book_id=book.id, page_number=page_number, content=content))

    dest = _books_dir() / f"{book.public_id}.pdf"
    dest.write_bytes(data)

    audit.record(db, user=user, action="aukc.book.upload", resource=book.title)
    db.commit()
    db.refresh(book)
    return _book_summary(book)


@router.delete("/aukc/books/{book_id}")
def delete_book(
    book_id: str, user: User = Depends(require_admin), db: Session = Depends(get_db)
) -> dict:
    """Admin-only. Deletes the book, its chunks (CASCADE) and the stored file."""
    book = db.get(BookDocument, book_id)
    if not book:
        raise HTTPException(404, "Book not found")
    stored = _books_dir() / f"{book.public_id}.pdf"
    title = book.title
    db.delete(book)
    audit.record(db, user=user, action="aukc.book.delete", resource=title)
    db.commit()
    try:
        stored.unlink(missing_ok=True)
    except OSError as exc:  # pragma: no cover - filesystem edge case
        logger.warning("Could not delete stored PDF %s: %s", stored, exc)
    return {"ok": True}


class AukcSearchIn(BaseModel):
    query: str = Field(min_length=1, max_length=4000)
    limit: int = Field(default=10, ge=1, le=50)


@router.post("/aukc/search")
def aukc_search(
    body: AukcSearchIn,
    _: User = Depends(current_user),
    db: Session = Depends(get_db),
) -> dict:
    """Offline BM25 search across all uploaded books. Always HTTP 200.

    Graceful states: an empty library or a query that matches nothing both
    return an empty result list with a friendly message.
    """
    if db.query(BookDocument.id).first() is None:
        return {
            "results": [],
            "message": "No books in the library yet. An administrator can upload PDFs.",
        }

    results = booksearch.search(db, body.query, limit=body.limit)
    if not results:
        return {"results": [], "message": "No relevant passages found."}

    return {
        "results": [
            {
                "book_id": r.book_id,
                "book_title": r.book_title,
                "page_number": r.page_number,
                "snippet": r.snippet,
                "score": r.score,
            }
            for r in results
        ],
        "message": "",
    }
