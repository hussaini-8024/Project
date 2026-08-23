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
| `/api/aukc/search` | AU Kamra AI Agent — OpenAI-compatible chat proxy with graceful offline fallback |
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

- `POST /api/aukc/search` body `{ "prompt": "…" }` → always HTTP 200.
- Configure a real ChatGPT key via **`OPENAI_API_KEY`** (or `AUKC_AI_API_KEY`) and, optionally,
  `AUKC_AI_MODEL` (default `gpt-4o-mini`). With a key + working egress: `{ "configured": true, "answer": … }`.
- With no key or blocked egress it degrades gracefully to `{ "configured": false, "answer": <offline guidance>, "error": … }`.
- No API key is ever committed; the key is read from the server environment only.

## WebSockets

| Path | Purpose |
| --- | --- |
| `/ws/monitoring` | Host telemetry |
| `/ws/events` | Machine state |
| `/ws/terminal/{id}` | Browser terminal gateway |
| `/ws/console/{id}` | Graphical console gateway |

All WebSocket routes require `?token=` (access JWT). Authorization is re-checked against machine ownership.
