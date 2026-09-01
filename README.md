# University Cybersecurity Virtual Lab / Cyber Range

Private **Cyber Range as a Service** for a university campus. Each student receives an isolated laboratory. The platform is **container-first** and uses full KVM/QEMU virtual machines only when a complete operating system or kernel is required.

This repository is a functional management plane: authentication, RBAC, persistent labs, machine lifecycle, a dynamic resource scheduler, quotas, assignments, audit, backups, browser terminal/console gateways, and capacity benchmarking. Development mode uses a mock compute provider so the UI and APIs work without Docker or libvirt. Production attaches Docker and KVM on the workload plane.

**GitHub:** https://github.com/hussaini-8024/Project

---

## Architecture

```text
                         INTERNET
                            |
                         FIREWALL / TLS
                            |
                    FRONTEND / API (management plane)
                            |
                     LAB RESOURCE SCHEDULER
                            |
          +-----------------+-----------------+
          |                 |                 |
       Docker            KVM/QEMU          Network
       Engine             libvirt          Manager
          |                 |                 |
    Containers             VMs          Private labs
```

**Principle:** lightweight container → full VM only if the exercise needs its own kernel.

Logged-in user ≠ active lab ≠ running container ≠ running VM. Hundreds of accounts can exist; only running workloads consume the lab RAM pool.

---

## Demo accounts

| Role | Username | Password |
| --- | --- | --- |
| Super administrator | `admin` | `CyberRange!Admin2026` |
| Instructor | `instructor` | `CyberRange!Teach2026` |
| Lab manager | `labmanager` | `CyberRange!Lab2026` |
| Student (Alex Chen, `STU-000245`) | `student` | `CyberRange!Stud2026` |

Change these immediately on a production host.

---

## Collaboration & study features

- **Announcements & notification bell** — a top-bar bell (all users) polls `GET /api/notifications`
  every ~20s and shows an unread badge. Instructors/admins compose announcements from the bell:
  instructors target a student group, admins can also target *all students*. New assignments also
  notify students. See `docs/API.md`.
- **Command Search** (`/commands`) — an offline catalogue of ~60 common cybersecurity tool commands
  (nmap, hydra, sqlmap, gobuster, john, hashcat, netcat, tcpdump, metasploit, openssl, dig, curl, …)
  with case-insensitive search and copyable commands. Works fully offline.
- **AUKC AI Search** (`/aukc`) — the branded **AU Kamra AI Agent**, an offline book-search engine.
  Administrators upload PDF books; their text is extracted with `pypdf`, stored as searchable
  chunks in the database, and ranked with a local BM25 relevance algorithm. All authenticated
  users search the shared library and get passages cited by book title and page number, with the
  matched terms highlighted. Fully self-contained — **no external AI, no API keys, no outbound
  calls**. Accessible from the **AUKC AI Search** entry in the top menu bar. Max PDF size is set by
  `AUKC_BOOK_MAX_MB` (see `.env.example`).

---

## Quick start (development)

Requires Python 3.12+ and Node 20+.

```bash
# API
cd backend
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
mkdir -p data
uvicorn app.main:app --reload --host 0.0.0.0 --port 18000

# UI (second terminal)
cd frontend
npm install
npm run dev
```

Open http://localhost:18173 and sign in as `student`. Production-style access uses nginx on **18080** (see below).

### Ubuntu on the LAN — dedicated ports 18080 / 18081 / 18000

Do **not** use port 80, 8080, 5173, or 8000 — those collide with other software and with `http://127.0.0.1` in a browser on another PC. This project listens on:

| Service | Port |
| --- | --- |
| Login / UI (nginx) | **18080** |
| Login alternate | **18081** |
| API (uvicorn, also via nginx `/api`) | **18000** |

On the Ubuntu host:

```bash
git clone https://github.com/hussaini-8024/Project.git
cd Project
git checkout cursor/university-cyber-range-a428
chmod +x scripts/install-ubuntu-deps.sh scripts/run-ubuntu-lan.sh
./scripts/install-ubuntu-deps.sh    # packages + build — do this first
./scripts/run-ubuntu-lan.sh         # nginx :18080/:18081 + API :18000
```

`run-ubuntu-lan.sh` also runs the install step, so one command is enough after a successful install. It enables **boot services** so nginx and the API start after reboot and restart if they die (`systemd` `Restart=always` when systemd is PID 1; otherwise `cron` `@reboot` plus a one-minute watchdog).

The UI is the production build served by nginx from `/var/www/cyberrange`.

Then, **on this Ubuntu or any other PC on the same network**, open:

```text
http://<ubuntu-ip>:18080/login
http://<ubuntu-ip>:18081/login
```

Example if the host address is `172.30.0.2` or `172.26.1.3`:

```text
http://172.30.0.2:18080/login
http://172.26.1.3:18080/login
```

Do not open `http://127.0.0.1/login` from another computer — `127.0.0.1` is that computer, not the Ubuntu host.

Ping can succeed while the page does not: ping is ICMP; the browser uses **TCP 18080**. If Ubuntu Firewall (`ufw`) was on, the start script opens 18080, 18081, and 18000.

The UI and API bind `0.0.0.0`. CORS includes RFC1918 origins (`CORS_ALLOW_LAN=true`). Keep `COOKIE_SECURE=false` for plain HTTP. Change `PUBLIC_HOST` in `.env` if your IP is different (`hostname -I`).

Do not expose this development preview to the public internet.

- API docs: http://localhost:18080/docs
- Health: http://localhost:18080/api/health

Development uses SQLite (`backend/data/cyberrange.db`) and `COMPUTE_PROVIDER=auto`.

Linux terminals are **real shells** inside isolated guests (Alpine userspace + Linux network namespaces). Machines in the same student lab share a private **10.0.0.0/8** bridge, so `ping dvwa-target` and `ping 10.0.0.3` work. Other student labs stay unreachable. Administrators can create additional networks and deploy machines onto them.

---

## Docker Compose

```bash
cp .env.example .env
docker compose up --build
```

Postgres, Redis, API, queue worker, and the nginx-hosted UI start together. Set `COMPUTE_PROVIDER=hybrid` on a KVM host after installing Docker and libvirt.

---

## Capacity model (targets, not guarantees)

Configured for a **128 GB RAM / 3.2 TB** campus server with a **16–24 GB host reserve**. The remaining pool is scheduled dynamically.

| Population | Engineering target |
| --- | --- |
| Registered students | 500+ accounts (no compute cost) |
| Logged-in students | 100+ if most are idle |
| Container-heavy active labs | 40–60 |
| Concurrent full VMs | 8–16 |
| Host pressure | stay under 80–85%; block heavy labs above 90% |

The **Capacity Manager** and **load-test suite** (`POST /api/resources/loadtest`) measure safe concurrency from CPU, RAM, storage, IOPS, and latency. Do not hard-code a maximum user count.

---

## Repository layout

```text
backend/          FastAPI services, scheduler, providers, Alembic
frontend/         React + TypeScript + Vite + Tailwind
docs/             Production deployment, API, project report PDF
loadtest/         CLI capacity runner
docker-compose.yml
```

Ansible playbooks that were already in this repository remain under `playbooks/` and `roles/` and are unrelated to the cyber range runtime.

---

## Security

- Students never receive the Docker socket, host root shell, or libvirt management socket.
- Each student lab has its own network namespace / VLAN / bridge.
- Vulnerable templates are labeled **Training Target — Authorized Laboratory Use Only**.
- Testing is restricted to the owning student's isolated environment.
- Audit records are append-only through the API.
- Administrators can enable TOTP MFA.

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for TLS, KVM, storage layout, and multi-node notes.

A full project report (why the range exists, how it was built, software and strategies, and solved installation/configuration questions) is in [docs/University-Cyber-Range-Project-Report.pdf](docs/University-Cyber-Range-Project-Report.pdf). Regenerate it with:

```bash
pip install reportlab
python docs/generate_project_report.py
```
