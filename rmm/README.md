# AU-Kamra IT Experts Remote Manager

**GitHub user:** [faisal3537khan-max](https://github.com/faisal3537khan-max)

# Username and password (shown to every visitor)

```
Username:  admin
Password:  admin123
```

| | |
|---|---|
| **Username** | **admin** |
| **Password** | **admin123** |

The dashboard login page also displays **admin** / **admin123** in plain text so visitors do not have to guess.

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
3. Open **Add / Discover PC** → **Generate agent packages**
4. **Download** the Windows / macOS / Linux agent
5. On the target machine, run with `--install --server http://SERVER_IP:8443 --token <token>`

**Important (Windows .exe):** rebuild with `build\build_windows.bat` so the Agent is built **first** and bundled into the Server.

### Agent behavior (Windows)
- Double-click `AU-Kamra-Remote-Manager-Agent.exe` once (Run as administrator)
- It installs permanently under `%ProgramData%\AUKamraRemoteManager\`
- Runs **in the background** (no CMD window)
- Auto-starts after reboot (Scheduled Task + Run key)
- Closing dialogs/windows does **not** stop it
- Remove only with: `AU-Kamra-Remote-Manager-Agent.exe --uninstall` + admin password

### Dashboard login (visitors)

**Username:** `admin`  
**Password:** `admin123`  

Open `http://SERVER_IP:8443` after starting the server.

| Item | Default |
|------|---------|
| **Username** | **`admin`** |
| **Password** | **`admin123`** |
| Enrollment token | `enroll-change-me` |
| Uninstall password | `UninstallMe!` |
