"""Offline relevance search over admin-uploaded PDF book chunks.

Implements a self-contained BM25 ranking in pure Python — no external AI, no
network calls. Tokens are lowercased word characters with a small stopword list
removed. Each :class:`~app.models.BookChunk` is treated as a document; the top
ranked chunks are returned with an HTML-safe, highlighted snippet.
"""

from __future__ import annotations

import math
import re
from collections import Counter
from dataclasses import dataclass

from sqlalchemy.orm import Session

from app.models import BookChunk, BookDocument

# Small, generic English stopword list. Kept short on purpose so domain terms
# (e.g. "scan", "port") are never discarded.
STOPWORDS: frozenset[str] = frozenset(
    {
        "a", "an", "and", "are", "as", "at", "be", "but", "by", "for", "from",
        "how", "in", "into", "is", "it", "its", "of", "on", "or", "our", "that",
        "the", "their", "then", "there", "these", "they", "this", "to", "was",
        "were", "what", "when", "where", "which", "who", "will", "with", "you",
        "your",
    }
)

_TOKEN_RE = re.compile(r"[a-z0-9]+")
_RAW_TOKEN_RE = re.compile(r"[A-Za-z0-9]+")

# BM25 tuning parameters.
_K1 = 1.5
_B = 0.75

_SNIPPET_RADIUS = 220  # chars of context on either side of the first match
_MAX_SNIPPET = 600


def tokenize(text: str) -> list[str]:
    """Lowercase word tokens with stopwords removed."""
    return [t for t in _TOKEN_RE.findall(text.lower()) if t not in STOPWORDS]


@dataclass
class SearchResult:
    book_id: str
    book_title: str
    page_number: int
    snippet: str
    score: float


def _escape(text: str) -> str:
    return (
        text.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
        .replace("'", "&#39;")
    )


def _build_snippet(content: str, query_terms: set[str]) -> str:
    """Return an HTML-safe snippet centered on the first matched term.

    The whole passage is HTML-escaped; only ``<mark>`` tags we insert ourselves
    wrap the matching tokens, so the result is safe to render as markup.
    """
    matches = list(_RAW_TOKEN_RE.finditer(content))
    match_spans = [
        (m.start(), m.end()) for m in matches if m.group(0).lower() in query_terms
    ]

    if match_spans:
        first = match_spans[0][0]
        start = max(0, first - _SNIPPET_RADIUS)
    else:
        start = 0
    end = min(len(content), start + _MAX_SNIPPET)

    prefix = "…" if start > 0 else ""
    suffix = "…" if end < len(content) else ""

    out: list[str] = []
    i = start
    highlight = [s for s in match_spans if s[1] > start and s[0] < end]
    hi_idx = 0
    while i < end:
        if hi_idx < len(highlight) and i == highlight[hi_idx][0]:
            s, e = highlight[hi_idx]
            e = min(e, end)
            out.append("<mark>")
            out.append(_escape(content[s:e]))
            out.append("</mark>")
            i = e
            hi_idx += 1
        else:
            nxt = highlight[hi_idx][0] if hi_idx < len(highlight) else end
            out.append(_escape(content[i:nxt]))
            i = nxt
    body = "".join(out).strip()
    return f"{prefix}{body}{suffix}"


def search(db: Session, query: str, limit: int = 10) -> list[SearchResult]:
    """BM25 ranking of all book chunks for ``query``.

    Returns up to ``limit`` results ordered by descending relevance. Chunks that
    contain more distinct query terms (higher coverage) rank above single-term
    matches thanks to summed term weights plus a small coverage boost.
    """
    q_terms = list(dict.fromkeys(tokenize(query)))
    if not q_terms:
        return []
    q_set = set(q_terms)

    chunks = db.query(BookChunk).all()
    if not chunks:
        return []

    tokenized: list[tuple[BookChunk, list[str]]] = [
        (c, tokenize(c.content or "")) for c in chunks
    ]
    n_docs = len(tokenized)
    avgdl = sum(len(toks) for _, toks in tokenized) / n_docs if n_docs else 0.0

    # Document frequency per query term (over chunks).
    df: Counter[str] = Counter()
    for _, toks in tokenized:
        present = set(toks) & q_set
        for term in present:
            df[term] += 1

    idf = {
        term: math.log(1 + (n_docs - df[term] + 0.5) / (df[term] + 0.5))
        for term in q_terms
        if df[term] > 0
    }
    if not idf:
        return []

    titles: dict[str, str] = {
        b.id: b.title for b in db.query(BookDocument).all()
    }

    scored: list[SearchResult] = []
    for chunk, toks in tokenized:
        if not toks:
            continue
        tf = Counter(toks)
        dl = len(toks)
        score = 0.0
        matched = 0
        for term in q_terms:
            f = tf.get(term, 0)
            if f == 0 or term not in idf:
                continue
            matched += 1
            denom = f + _K1 * (1 - _B + _B * dl / avgdl) if avgdl else f + _K1
            score += idf[term] * (f * (_K1 + 1)) / denom
        if matched == 0:
            continue
        # Coverage boost: reward passages that hit more of the query terms.
        score *= 1 + 0.25 * (matched - 1)
        scored.append(
            SearchResult(
                book_id=chunk.book_id,
                book_title=titles.get(chunk.book_id, "Untitled"),
                page_number=chunk.page_number,
                snippet=_build_snippet(chunk.content or "", q_set),
                score=round(score, 4),
            )
        )

    scored.sort(key=lambda r: r.score, reverse=True)
    return scored[:limit]


def extract_chunks(pdf_bytes: bytes) -> list[tuple[int, str]]:
    """Extract per-page passages from a PDF.

    Returns a list of ``(page_number, content)`` tuples. Long pages are split
    into ~800 char passages (on paragraph/word boundaries) so results can cite a
    precise location and show a readable snippet. Raises ``ValueError`` if the
    PDF cannot be read.
    """
    from io import BytesIO

    from pypdf import PdfReader
    from pypdf.errors import PdfReadError

    try:
        reader = PdfReader(BytesIO(pdf_bytes))
        pages = reader.pages
    except (PdfReadError, Exception) as exc:  # noqa: BLE001
        raise ValueError(f"Could not read PDF: {exc}") from exc

    chunks: list[tuple[int, str]] = []
    for page_index, page in enumerate(pages, start=1):
        try:
            text = page.extract_text() or ""
        except Exception:  # noqa: BLE001 - skip unreadable page, keep others
            text = ""
        text = re.sub(r"[ \t]+", " ", text).strip()
        if not text:
            continue
        for passage in _split_passage(text):
            chunks.append((page_index, passage))
    return chunks


def _split_passage(text: str, target: int = 800, hard_max: int = 1200) -> list[str]:
    """Split a page into readable passages of roughly ``target`` chars."""
    if len(text) <= hard_max:
        return [text]
    words = text.split(" ")
    passages: list[str] = []
    current: list[str] = []
    length = 0
    for word in words:
        current.append(word)
        length += len(word) + 1
        if length >= target:
            passages.append(" ".join(current))
            current = []
            length = 0
    if current:
        passages.append(" ".join(current))
    return passages
