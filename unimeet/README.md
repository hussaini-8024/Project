# UniMeet

A complete university classroom system for daily use: sign-in by student / teacher / registrar ID, courses, enrollment, LiveKit video, attendance, Smart 720p, audio priority, and lecture AI.

UniMeet does **not** rebuild WebRTC. Video rides on [LiveKit](https://livekit.io) (SFU). Your project work is the university system around it: authorization, attendance, and a network-adaptive quality controller.

```
Student browser → UniMeet backend → LiveKit token (only if enrolled + class active) → LiveKit → WebRTC
```

## What to install on a laptop

| Software | What it is for |
| --- | --- |
| Visual Studio Code | Write the code |
| Node.js 20+ and npm | React frontend and Express API |
| Git / GitHub | Save the project |
| PostgreSQL | Students, courses, attendance |
| pgAdmin (optional) | Inspect the database visually |
| Docker Desktop (optional) | Run LiveKit and Postgres with one command |
| LiveKit Server | Video, audio, screen share, participants |
| Chrome | Camera, microphone, WebRTC |
| Postman (optional) | Call `/api` by hand |

You do not need Zoom, Meet, or any paid meeting API for development.

## Repository layout

```
unimeet/
├── frontend/     React + TypeScript + Vite
├── backend/      Node.js + Express + TypeScript
├── database/     PostgreSQL schema
├── livekit/      How to run the SFU
├── docker-compose.yml
└── scripts/
```

## Exact order to run it

1. Install Node.js, PostgreSQL, Git, and Chrome.
2. Create the database:

```bash
sudo -u postgres psql -c "CREATE USER unimeet WITH PASSWORD 'unimeet_dev_password';"
sudo -u postgres psql -c "CREATE DATABASE unimeet OWNER unimeet;"
```

3. Install and start LiveKit:

```bash
curl -sSL https://get.livekit.io | bash   # Linux
livekit-server --dev --bind 0.0.0.0
```

Or `docker compose up livekit`.

4. Backend:

```bash
cd unimeet/backend
cp .env.example .env
npm install
npm run db:reset
npm run dev
```

5. Frontend (second terminal):

```bash
cd unimeet/frontend
npm install
npm run dev
```

6. Open http://localhost:5173

### Demo accounts (password `UniMeet@2026`)

| Role | ID | Name | Notes |
| --- | --- | --- | --- |
| Student | `STU-1001` | Ali Khan | Enrolled in Database Systems |
| Student | `STU-1002` | Ahmed Raza | Enrolled |
| Student | `STU-1003` | Sara Malik | Enrolled |
| Student | `STU-1004` | John Smith | **Not** enrolled — JOIN CLASS is denied |
| Teacher | `TCH-2001` | Dr. Ahmed | Teaches CS-501 |
| Admin | `ADM-3001` | Registrar Office | Creates courses and enrollments |

First milestone: open UniMeet in two Chrome windows, sign in as Ali and Ahmed (or Ali and Dr. Ahmed), join the live Database Systems class, and see/hear each other.

## Features built

1. **Login** — student / teacher / admin IDs, JWT cookie + bearer token.
2. **Role dashboards** — `/student-dashboard`, `/teacher-dashboard`, `/admin-dashboard`.
3. **Courses + enrollment** — registrar wizard: BSCS → semester → section → course → teacher → students.
4. **JOIN CLASS security** — backend checks login, role, enrollment, and “class is active” before minting a LiveKit token. John gets `You are not enrolled in this course.`
5. **Classroom** — camera, mic, participants, screen share, chat via LiveKit React components.
6. **Attendance** — join/leave timestamps, duration, present / partial / insufficient.
7. **Network monitor** — latency, loss, jitter, bitrate, FPS, resolution.
8. **Smart 720p** — when the path is weak, hold 720p and cut FPS/bitrate before dropping to 480p.
9. **Audio priority** — pause video and keep the lecture audible on a critical path.
10. **AI last** — summary, MCQ quiz, study assistant from the lecture transcript. Uses OpenAI if `OPENAI_API_KEY` is set; otherwise a local teaching engine.

## Poor-internet test (FYP research)

In the classroom, use **Simulate** on the top bar:

| Simulated path | Expected decision with Smart Quality ON |
| --- | --- |
| Excellent | 1080p / 30 FPS |
| Good | 720p / 30 FPS |
| Poor | Smart 720p — keep 720p, reduce FPS/bitrate |
| Very poor | 480p last resort |
| Critical | Audio priority |

Measure latency, loss, bitrate, FPS, resolution, audio interruptions, and reconnect time. Compare the same Chrome Network throttling profile against Google Meet or Zoom.

## API map

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/auth/login` | Sign in |
| GET | `/api/dashboard` | Role home data |
| GET/POST | `/api/courses` | List / create |
| POST | `/api/enrollments` | Admin enroll |
| POST | `/api/livekit/token` | Authorized classroom entry |
| POST | `/api/attendance/join` · `/leave` | Attendance clock |
| POST | `/api/ai/summarize` · `/quiz` · `/ask` | Lecture AI |
| POST | `/api/network/sample` | Store monitor samples |

## Deployment later

Local development is enough for a university demo. Later:

- Frontend → static web host
- Backend → cloud VM
- PostgreSQL → managed or the same VM
- LiveKit → VPS with TLS and TURN

Development can stay at $0. Public deployment may cost a VPS, domain, storage, bandwidth, TURN, and an AI API if you stop using the local engine.
