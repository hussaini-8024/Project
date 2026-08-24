# API surface

OpenAPI is generated at runtime: `/docs` and `/openapi.json`.

## REST

| Prefix | Purpose |
| --- | --- |
| `/api/auth` | Login, refresh, logout, MFA, password |
| `/api/users` `/api/students` `/api/sessions` | RBAC user administration |
| `/api/labs` | Persistent student laboratories |
| `/api/machines` `/api/vms` `/api/containers` | Lifecycle |
| `/api/templates` `/api/images` `/api/isos` | Catalog |
| `/api/networks` `POST /api/networks` `POST /api/networks/{id}/deploy` | Private /8 labs; staff create and deploy |
| `/api/assignments` | Instructor exercises (creating one notifies students) |
| `/api/announcements` | Post/list announcements (staff post; students read their group + global) |
| `/api/notifications` `/api/notifications/{id}/read` `/api/notifications/read-all` | Per-user notification feed + unread count |
| `/api/commands?q=` | Offline cybersecurity command reference search (all authenticated users) |
| `/api/aukc/search` | AU Kamra AI Agent — offline BM25 search over uploaded PDF books (all users) |
| `/api/aukc/books` | List the PDF book library (all users); upload a PDF (admin only) |
| `/api/aukc/books/{id}` | Delete a book, its chunks and stored file (admin only) |
| `/api/snapshots` | Checkpoints (quota-limited) |
| `/api/resources` `/api/resources/scheduler` `/api/resources/loadtest` | Capacity |
| `/api/audit` `/api/backups` `/api/settings` `/api/activity` | Operations |

### Announcements & notifications

- `POST /api/announcements` — staff only. Instructors/lab managers may target a **student group**
  (`scope=group`, `group_id=…`); administrators may additionally target **all students** (`scope=all`).
  On create, a `Notification` row fans out to every targeted student. Students receive `403`.
- `GET /api/announcements` — students see their group's + global announcements; staff see global + their own.
- `GET /api/notifications` — `{ unread, items[] }`, newest first. New assignments appear here too.
- The top-bar bell polls `GET /api/notifications` (~20s) and shows the unread badge.

### AUKC AI Search (AU Kamra AI Agent)

Offline PDF book-library search. **No external AI, no API keys, no outbound calls.**

- `POST /api/aukc/books` — **admin only**, multipart form (`file` = PDF, `title` optional). Text is
  extracted per page with `pypdf` and stored as searchable `BookChunk` rows; the original PDF is
  saved under `<storage_root>/books/`. Rejects non-PDF / unreadable / oversized files with `400`.
  Returns the created book summary.
- `GET /api/aukc/books` — list the library (`id, title, filename, page_count, size_bytes,
  uploaded_at`). Readable by all authenticated users.
- `DELETE /api/aukc/books/{id}` — **admin only**; removes the book, its chunks (CASCADE) and the
  stored file.
- `POST /api/aukc/search` body `{ "query": "…", "limit"?: 10 }` → always HTTP 200. Runs a local
  **BM25** relevance ranking over every book's chunks and returns
  `{ "results": [{ book_id, book_title, page_number, snippet, score }], "message": "" }`. The
  `snippet` is HTML-safe with matched terms wrapped in `<mark>` for highlighting, ranked so that the
  most relevant book/page appears first (results may span multiple books).
- Graceful states (still HTTP 200): empty library → `{ "results": [], "message": "No books in the
  library yet. An administrator can upload PDFs." }`; no matches →
  `{ "results": [], "message": "No relevant passages found." }`.
- Max PDF size is controlled by `AUKC_BOOK_MAX_MB` (default 50).

## WebSockets

| Path | Purpose |
| --- | --- |
| `/ws/monitoring` | Host telemetry |
| `/ws/events` | Machine state |
| `/ws/terminal/{id}` | Browser terminal gateway |
| `/ws/console/{id}` | Graphical console gateway |

All WebSocket routes require `?token=` (access JWT). Authorization is re-checked against machine ownership.
