# DiscloseRMM — legitimate remote management for company-owned PCs

**DiscloseRMM** is a Remote Monitoring & Management (RMM) stack delivered as **two Windows `.exe` files**. Managed PCs do **not** need Python or any other packages.

| File | Role |
|------|------|
| `DiscloseRMM-Server.exe` | Admin console on the management PC |
| `DiscloseRMM-Agent.exe` | One-time permanent install on each remote PC |

## Design rules

- Live view always shows a visible banner on the remote PC
- No stealth / hidden monitoring
- Agent installs permanently and **cannot be uninstalled without the uninstall password** set in the server **Settings** panel
- All admin actions are audited

## Windows end-user usage (no Python)

### 1. Build once (developer / IT build machine only)

On a Windows PC that has Python installed:

```bat
cd rmm
build\build_windows.bat
```

Outputs:

- `dist\DiscloseRMM-Server.exe`
- `dist\DiscloseRMM-Agent.exe` (also copied to `bin\`)

Copy those two `.exe` files to USB / share. You never install Python on remote PCs.

### 2. Management server

1. Run `DiscloseRMM-Server.exe` (or `Start-Server.bat`)
2. Open `http://SERVER_IP:8443`
3. Sign in (`admin` / `ChangeMeNow!` — change immediately)
4. Open **Settings** → set a strong **Agent uninstall password**
5. Open **Add / Discover PC**

### 3. Permanent agent install (one time per PC)

**Option A — local install**

1. Copy `DiscloseRMM-Agent.exe` to the remote PC
2. Right-click → **Run as administrator**
3. Enter server URL + enrollment token (GUI), or:

```bat
DiscloseRMM-Agent.exe --install --server http://SERVER_IP:8443 --token YOUR_TOKEN
```

The agent copies itself to `%ProgramData%\DiscloseRMM\`, registers a startup task, and keeps running across reboots.

**Option B — discover on network**

After agents are installed/running, use **Discover agents now** (optional `/24` scan). Discovered hosts appear in the panel automatically.

**Option C — manual push (IP + username + password)**

In **Add / Discover PC**, enter remote IP + Windows admin credentials. The server (must be Windows) copies the agent via admin share and starts install. Requires `DiscloseRMM-Agent.exe` in `bin\` or `dist\` next to the server.

### 4. Uninstall (password required)

On the remote PC:

```bat
DiscloseRMM-Agent.exe --uninstall
```

Enter the uninstall password from the server **Settings** panel.  
Or from the server panel: **Remote PCs → Uninstall agent** (same password).

Without the password, the official uninstall path refuses. (A local Windows administrator can still force-delete files outside this tool — treat the password as organizational control, not a cryptographic lock against device owners.)

## Features

1. Permanent agent install + password-gated uninstall  
2. Auto discovery of agents on the LAN  
3. Manual add via IP / username / password (push install)  
4. Groups + software deploy  
5. Remote cmd / PowerShell / bash  
6. Live session (disclosed banner)  
7. Network live/dead ping monitor  
8. Audit log  

## Defaults (change immediately)

| Item | Default |
|------|---------|
| Admin login | `admin` / `ChangeMeNow!` |
| Enrollment token | `enroll-change-me` |
| Uninstall password | `UninstallMe!` |

Env overrides: `RMM_ADMIN_USER`, `RMM_ADMIN_PASSWORD`, `RMM_ENROLLMENT_TOKEN`, `RMM_UNINSTALL_PASSWORD`, `RMM_PORT`, `RMM_PUBLIC_URL`.

## Dev run (optional, with Python)

```bash
cd rmm
pip install -r requirements.txt
python run_server.py
python run_agent.py --install --server http://127.0.0.1:8443 --token enroll-change-me
```

## Security

- Company-owned machines only, with a disclosed monitoring policy  
- Change all default passwords/tokens before production  
- Prefer internal/VPN networks; put TLS (reverse proxy) in front when exposed  
- Review the Audit Log regularly  
