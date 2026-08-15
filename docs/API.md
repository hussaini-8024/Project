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
| `/api/assignments` | Instructor exercises |
| `/api/snapshots` | Checkpoints (quota-limited) |
| `/api/resources` `/api/resources/scheduler` `/api/resources/loadtest` | Capacity |
| `/api/audit` `/api/backups` `/api/settings` `/api/activity` | Operations |

## WebSockets

| Path | Purpose |
| --- | --- |
| `/ws/monitoring` | Host telemetry |
| `/ws/events` | Machine state |
| `/ws/terminal/{id}` | Browser terminal gateway |
| `/ws/console/{id}` | Graphical console gateway |

All WebSocket routes require `?token=` (access JWT). Authorization is re-checked against machine ownership.
