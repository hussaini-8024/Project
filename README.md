# AU-Kamra-IT Loan Cards Management

Professional Windows desktop software for **Air University — Kamra Campus** IT loan cards and inventory.

## Software packages (single EXE each)

| Package | File | Install on |
|---------|------|------------|
| **Server** | `AU-Kamra-IT-Loan-Cards-Server.exe` | Windows server / main PC |
| **Agent** | `AU-Kamra-IT-Agent.exe` | Remote PCs on the same LAN |

Supports **Windows 7 / 8 / 10 / 11**.

## Features

- **Authentication** with role-based access (Administrator assigns roles)
- **Loan cards**: upload PDF/HTML, search, open original file, generate new cards
- **Inventory**: add / edit / delete / allocate with allocation officer
- **Inventory search** by item name, allocation officer, allocated-to, added date, issued date, allocation date
- **Live Online Users dashboard** — who is online and what panel/activity they are on
- **Backup / Restore** (ZIP) and **Import / Export** (JSON, CSV)
- **Network agents** authenticate to the server by LAN IP address

### Default roles

| Role | Access |
|------|--------|
| Administrator | Full access + user/role management |
| Allocation Officer | Inventory manage/allocate + loan cards |
| User | Upload/create/search loan cards, view inventory |
| Viewer | Read-only search |

Default login: **admin** / **admin123** (change after first login)

## Build both EXEs (on Windows)

```bat
scripts\build_windows.bat
```

Outputs:

```text
dist\AU-Kamra-IT-Loan-Cards-Server.exe
dist\AU-Kamra-IT-Agent.exe
```

## Run from source

```bat
python -m pip install -r requirements.txt
python run.py
```

Server listens on all interfaces (`0.0.0.0:8765`) so agents can connect via the PC’s LAN IP.

Agent launcher:

```bat
python agent_run.py
```

Or: `python agent_run.py 192.168.1.50 8765`

## Network setup

1. Run **Server EXE** on the main Windows machine.
2. Allow port **8765** in Windows Firewall if prompted.
3. Note the server LAN IP (shown at startup, e.g. `192.168.1.50`).
4. On each remote PC, run **Agent EXE**, enter the server IP, then sign in with your username/password.
5. Administrators open **Online Users** on the server to see live agent activity.

## Filters

**Loan cards:** name, designation, department, issue date, equipment/item name, tel. extension  

**Inventory:** item name, allocation officer, allocated to, status, added date, issued date, allocation date

## Tests

```bat
python -m unittest discover -s tests -v
```

## Data storage

Next to the Server EXE: `AU_Kamra_Data\` (database, uploads, generated files, backups).
