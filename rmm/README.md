# AU-Kamra IT Experts Remote Manager

Legitimate remote management for company-owned PCs — delivered as **two Windows `.exe` files**. Managed PCs do **not** need Python.

| File | Role |
|------|------|
| `AU-Kamra-Remote-Manager-Server.exe` | Admin dashboard on the management PC |
| `AU-Kamra-Remote-Manager-Agent.exe` | One-time permanent install on each remote PC |

## Dashboard

The web console features a professional animated UI: branded login, icon sidebar, KPI stats, glass panels, and smooth page transitions — product name **AU-Kamra IT Experts Remote Manager**.

## Design rules

- Live view always shows a visible banner on the remote PC
- No stealth / hidden monitoring
- Agent installs permanently; uninstall requires admin password from **Settings**
- All admin actions are audited

## Windows end-user usage (no Python)

### Build once (IT build machine)

```bat
cd rmm
build\build_windows.bat
```

### Run

1. `AU-Kamra-Remote-Manager-Server.exe` → open `http://SERVER_IP:8443`
2. Sign in, set uninstall password under **Settings**
3. On each PC (Run as admin once): `AU-Kamra-Remote-Manager-Agent.exe`

See full feature docs in previous sections of this project (discovery, groups, deploy, shell, live view, network monitor).

### Defaults (change immediately)

| Item | Default |
|------|---------|
| Admin login | `admin` / `ChangeMeNow!` |
| Enrollment token | `enroll-change-me` |
| Uninstall password | `UninstallMe!` |
