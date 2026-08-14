# AU-Kamra IT Experts Remote Manager

**Legitimate remote monitoring and management (RMM) for company-owned PCs.**

This project is a full remote-management stack: a **Windows/Linux server dashboard** plus a **permanent background agent** that you install on remote Windows, macOS, and Linux machines. Administrators can see which PCs are online, run remote shell commands, deploy software to groups, watch a **disclosed** live desktop session, ping hosts as live/dead, and enroll new PCs from the panel.

> **Not included (by design):** hidden or stealth monitoring. Live viewing always shows a visible **REMOTE SESSION ACTIVE** banner on the remote PC. All admin actions are written to an audit log.

**GitHub:** https://github.com/hussaini-8024/Project

---

## Demo login (open the dashboard with these)

Use these credentials when you start the server and open the web console:

| Field | Value |
|--------|--------|
| **Username** | `admin` |
| **Password** | `admin123` |
| Dashboard URL | `http://SERVER_IP:8443` |
| Enrollment token | `enroll-change-me` |
| Agent uninstall password | `UninstallMe!` (change this in **Settings**) |

**Username: `admin`**  
**Password: `admin123`**

Open `http://127.0.0.1:8443` (or your server IP on port **8443**), then sign in with **admin** / **admin123**.

---

## What this software does

| Area | What you get |
|------|----------------|
| **Remote PCs** | Fleet list with hostname, user, IP, OS, online/offline, live-view status |
| **Add / Discover** | LAN discovery, manual add by IP + Windows credentials, generate/download agents |
| **Groups** | Assign PCs to groups and deploy software to all members at once |
| **Software** | Register a setup from a local path on the server, or upload an installer, then run it on targets |
| **Remote Shell** | Run `cmd`, PowerShell, or bash on a connected agent (audited) |
| **Live Session** | Live JPEG view of the remote screen with a **visible banner** on that PC |
| **Network Monitor** | Ping hosts and mark them **live** or **dead** |
| **Settings** | Agent uninstall password (required to remove the agent from a PC) |
| **Audit Log** | Logins, deploys, shell commands, live sessions, connect/disconnect |

---

## Two files (after Windows build)

| File | Role |
|------|------|
| `AU-Kamra-Remote-Manager-Server.exe` | Management console. Double-click to start. No Python needed at runtime. |
| `AU-Kamra-Remote-Manager-Agent.exe` | Install once on each remote PC. Runs in the **background**, starts after reboot, stays until uninstalled with the admin password. |

Source layout:

```
rmm/
  run_server.py          # start the dashboard
  run_agent.py           # agent entry
  server/                # FastAPI + WebSockets + UI
  agent/                 # permanent install, discovery, live view
  shared/                # protocol and defaults
  build/build_windows.bat
```

---

## How to run (visitors)

### Option A — Python (any OS, for trying the dashboard)

```bash
cd rmm
pip install -r requirements.txt
python run_server.py
```

Then open **http://127.0.0.1:8443**

Sign in:

- **Username:** `admin`
- **Password:** `admin123`

### Option B — Windows `.exe` (no Python on the PC)

On a Windows build machine:

```bat
cd rmm
build\build_windows.bat
```

Then double-click:

- `dist\AU-Kamra-Remote-Manager-Server.exe`

Open **http://YOUR_PC_IP:8443** and log in with **admin** / **admin123**.

### Install the agent on a remote PC

1. In the dashboard go to **Add / Discover**
2. Click **Generate agent packages** (or **Download** Windows / macOS / Linux)
3. On the remote machine, run the agent **once** as administrator:

```bat
AU-Kamra-Remote-Manager-Agent.exe --install --server http://SERVER_IP:8443 --token enroll-change-me
```

Or double-click the agent `.exe` and enter the server URL and token in the dialog.

The agent then:

- Copies itself under `%ProgramData%\AUKamraRemoteManager\` (Windows)
- Runs **silently in the background** (no CMD window after install)
- Starts automatically after reboot
- Keeps running if you close the installer dialog
- Can be removed **only** with the uninstall password from **Settings** (`AU-Kamra-Remote-Manager-Agent.exe --uninstall`)

---

## How the pieces connect

1. **Server** listens on port **8443** and serves the admin UI.
2. **Agents** enroll with the shared token and keep a WebSocket connection.
3. The panel shows connected PCs automatically (heartbeat + LAN discovery).
4. Live view, shell, and software deploy go over that connection.
5. Live view **always** shows an on-screen banner on the remote PC.

Use this only on **company-owned** machines with a disclosed monitoring policy.

---

## Default values (change for production)

| Item | Default |
|------|---------|
| **Username** | **`admin`** |
| **Password** | **`admin123`** |
| Enrollment token | `enroll-change-me` |
| Agent uninstall password | `UninstallMe!` |
| Port | `8443` |

Environment overrides: `RMM_ADMIN_USER`, `RMM_ADMIN_PASSWORD`, `RMM_ENROLLMENT_TOKEN`, `RMM_UNINSTALL_PASSWORD`, `RMM_PORT`, `RMM_PUBLIC_URL`.

---

## More detail

See [rmm/README.md](rmm/README.md) for build notes, multi-OS agent download, and security checklist.

This repository also contains Ansible/AWX playbooks under `playbooks/` for MS Office 2016 LAN install (separate from the Remote Manager).
