# AU Labs IT Management

Local Linux VPS hosting panel. Multiple users share one Linux host with **isolated directories**, **storage quotas**, **permissions**, and **independent sessions** — managed from a web panel named **AU Labs IT Management**.

Runs on Linux only. Installs and starts with a single command.

## One-command install

From this repository on a Linux machine:

```bash
bash install.sh
```

Or:

```bash
chmod +x install.sh && ./install.sh
```

The installer will:

1. Detect Linux and package manager  
2. Install Python dependencies  
3. Create an app root (`/opt/aulabs` as root, or `~/.local/share/aulabs` as user)  
4. Create a virtualenv and install packages  
5. Initialize the database and admin account  
6. Install a systemd service when run as root  
7. Start the panel on **http://127.0.0.1:8787**

### After install

| Item | Default |
|------|---------|
| URL | http://127.0.0.1:8787 |
| Admin user | `admin` |
| Admin password | `aulabs-admin` |

Change the admin password after first login.

```bash
aulabs serve      # start panel
aulabs init       # initialize data
aulabs version    # print version
systemctl status aulabs   # when installed as root
```

## What it does

- **Users** — create panel users, each with a private home under the data root  
- **Storage** — per-user quotas, directory trees, usage reporting  
- **Permissions** — grant/revoke capabilities (files, shell, sessions, admin ops)  
- **Sessions** — each user can open separate web/shell working environments on the same OS  
- **Master OS** — host CPU, memory, disk, uptime, and process overview from the panel  
- **Audit** — activity log for user/session/storage actions  

All of this is local: the panel binds to `127.0.0.1` by default for local access.

## Development run (without installer)

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
export AULABS_DATA_ROOT="$(pwd)/data"
python -m aulabs init
python -m aulabs serve
```

Open http://127.0.0.1:8787

## Environment variables

| Variable | Default | Meaning |
|----------|---------|---------|
| `AULABS_HOST` | `127.0.0.1` | Bind address |
| `AULABS_PORT` | `8787` | Bind port |
| `AULABS_DATA_ROOT` | `/opt/aulabs/data` or `./data` | Data + user homes |
| `AULABS_ADMIN_USER` | `admin` | Bootstrap admin |
| `AULABS_ADMIN_PASS` | `aulabs-admin` | Bootstrap password |
| `AULABS_SECRET_KEY` | auto | Session signing key |
| `AULABS_DEFAULT_STORAGE_MB` | `1024` | New user quota |

## Project layout

```
install.sh              # one-command autoinstaller
aulabs/                 # Python application
  app.py                # FastAPI web panel
  services/             # users, storage, sessions, permissions, system
  web/                  # UI templates + static assets
systemd/aulabs.service  # systemd unit template
```

## Requirements

- Linux OS  
- Python 3.10+  
- Root optional (root enables systemd + system packages; user mode still works)

## License

Proprietary — AU Labs.
