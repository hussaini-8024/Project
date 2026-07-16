# DiscloseRMM — legitimate remote management for company-owned PCs

**DiscloseRMM** is a small Remote Monitoring & Management (RMM) stack:

| Component | Role | Build artifact |
|-----------|------|----------------|
| **Server** | Admin console on your Windows management host | `DiscloseRMM-Server.exe` |
| **Agent** | Installed on each managed PC | `DiscloseRMM-Agent.exe` |

## Design rules (non-negotiable)

- **Live view is always disclosed.** The agent shows an always-on-top banner: *REMOTE SESSION ACTIVE — IT is viewing this screen*, and burns the same notice into captured frames.
- **No stealth / hidden monitoring.** That capability is intentionally omitted.
- **Audit log** records logins, shell commands, deploys, live sessions, and agent connect/disconnect.
- Use only on **company-owned** machines with an acceptable-use / monitoring policy employees know about.

## Features

1. **Remote PCs** — enroll agents with a shared token; see online/offline
2. **Groups** — assign PCs; deploy software to a whole group
3. **Software deploy** — register a setup path on the server (or upload), agents download and run
4. **Remote shell** — `cmd`, PowerShell, or bash on a connected agent
5. **Live session** — JPEG screen stream with visible remote banner
6. **Add PC** — enrollment instructions + rotatable token
7. **Network monitor** — ping hosts; mark **live** / **dead**

## Quick start (Python, any OS for the server)

```bash
cd rmm
python -m venv .venv
# Windows: .venv\Scripts\activate
source .venv/bin/activate
pip install -r requirements.txt

# Optional env overrides
export RMM_ADMIN_USER=admin
export RMM_ADMIN_PASSWORD='ChangeMeNow!'
export RMM_ENROLLMENT_TOKEN='enroll-change-me'
export RMM_PORT=8443

python run_server.py
```

Open `http://SERVER_IP:8443` and sign in.

On each remote PC:

```bash
python run_agent.py --server http://SERVER_IP:8443 --token enroll-change-me
```

## Build single-file Windows `.exe` (on a Windows machine)

```bat
cd rmm
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
pyinstaller build\server.spec
pyinstaller build\agent.spec
```

Outputs:

- `dist\DiscloseRMM-Server.exe`
- `dist\DiscloseRMM-Agent.exe`

Run the server on the management host, then enroll agents:

```bat
DiscloseRMM-Agent.exe --server http://192.168.1.10:8443 --token YOUR_TOKEN
```

## Security checklist

- Change default admin password and enrollment token immediately
- Prefer an internal/VPN network; put TLS (reverse proxy) in front for production
- Agents should run under an account that is allowed to install software if you use deploy
- Review **Audit Log** regularly
- Do not use this product to monitor people without authorization

## Layout

```
rmm/
  run_server.py          # server entry
  run_agent.py           # agent entry
  server/app.py          # FastAPI + WebSockets + dashboard
  server/database.py     # SQLite
  server/static/         # admin UI
  agent/agent.py         # agent + visible banner
  shared/                # protocol + config
  build/*.spec           # PyInstaller
  data/                  # created at runtime (DB, packages)
```
