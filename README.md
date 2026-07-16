# AU Labs IT Management

Local Linux VPS hosting panel with a **VLC-style Setup installer**.  
Install Server and Agent as single-file programs — **no Python, pip, or terminal required**.

## Easy install (recommended)

### Windows (`.exe` like VLC)

1. Build on a Windows machine (one time):
   ```bat
   scripts\build_windows.bat
   ```
2. Open `release\windows\` and double-click **`AULabsSetup.exe`**
3. Next → choose **Server** and/or **Agent** → Install
4. Open http://127.0.0.1:8787 — login `admin` / `aulabs-admin`

Optional: if [Inno Setup](https://jrsoftware.org/isinfo.php) is installed, the same script also produces **`Setup-AULabs.exe`**.

| File | Purpose |
|------|---------|
| `AULabsSetup.exe` | Graphical installer (double-click) |
| `AULabsServer.exe` | Web panel server |
| `AULabsAgent.exe` | Host agent |
| `Setup-AULabs.exe` | Optional Inno Setup wrapper |

### Linux (same flow, single-file binaries)

```bash
bash scripts/build_binaries.sh
# then:
./release/AULabs-linux-*/AULabsSetup
```

Double-click / run **AULabsSetup** → wizard installs Server and Agent into a folder (default `~/AU Labs IT Management` or `/opt/aulabs`).

## What you get

- **Server** — AU Labs IT Management web panel (users, storage, permissions, sessions, master OS)
- **Agent** — connects a host to the panel with heartbeats and local session environments
- **Setup** — install wizard, shortcuts, uninstall script

## Developer run (optional)

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
export AULABS_DATA_ROOT="$(pwd)/data"
python -m aulabs serve          # server
python -m aulabs.agent run      # agent
python -m aulabs.setup_wizard   # setup GUI
```

Classic script install is still available: `bash install.sh`

## Default login

| Item | Value |
|------|-------|
| URL | http://127.0.0.1:8787 |
| User | `admin` |
| Password | `aulabs-admin` |

## Project layout

```
AULabsSetup / AULabsSetup.exe   # built installer
AULabsServer / AULabsServer.exe # built server
AULabsAgent / AULabsAgent.exe   # built agent
packaging/                      # PyInstaller + Inno Setup
scripts/build_binaries.sh       # Linux/macOS single-file build
scripts/build_windows.bat       # Windows .exe build
aulabs/                         # application source
```
