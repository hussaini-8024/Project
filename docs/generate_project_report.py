#!/usr/bin/env python3
"""Generate the University Cyber Range project report PDF."""

from __future__ import annotations

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    KeepTogether,
    ListFlowable,
    ListItem,
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

OUT = Path(__file__).resolve().parent / "University-Cyber-Range-Project-Report.pdf"
INK = colors.HexColor("#0f172a")
CYAN = colors.HexColor("#0e7490")
SLATE = colors.HexColor("#334155")
LINE = colors.HexColor("#cbd5e1")
HEAD_BG = colors.HexColor("#0f172a")
ROW_BG = colors.HexColor("#f1f5f9")


def styles():
    base = getSampleStyleSheet()
    s = {
        "cover_kicker": ParagraphStyle(
            "cover_kicker",
            parent=base["Normal"],
            fontName="Times-Bold",
            fontSize=11,
            textColor=CYAN,
            alignment=TA_CENTER,
            tracking=1.2,
            spaceAfter=8,
        ),
        "cover_title": ParagraphStyle(
            "cover_title",
            parent=base["Title"],
            fontName="Times-Bold",
            fontSize=26,
            leading=32,
            textColor=INK,
            alignment=TA_CENTER,
            spaceAfter=10,
        ),
        "cover_sub": ParagraphStyle(
            "cover_sub",
            parent=base["Normal"],
            fontName="Times-Italic",
            fontSize=13,
            leading=18,
            textColor=SLATE,
            alignment=TA_CENTER,
            spaceAfter=6,
        ),
        "cover_meta": ParagraphStyle(
            "cover_meta",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=11,
            leading=16,
            textColor=SLATE,
            alignment=TA_CENTER,
        ),
        "h1": ParagraphStyle(
            "h1",
            parent=base["Heading1"],
            fontName="Times-Bold",
            fontSize=16,
            leading=20,
            textColor=INK,
            spaceBefore=16,
            spaceAfter=8,
            borderPadding=3,
        ),
        "h2": ParagraphStyle(
            "h2",
            parent=base["Heading2"],
            fontName="Times-Bold",
            fontSize=13,
            leading=17,
            textColor=CYAN,
            spaceBefore=12,
            spaceAfter=6,
        ),
        "h3": ParagraphStyle(
            "h3",
            parent=base["Heading3"],
            fontName="Times-Bold",
            fontSize=11.5,
            leading=15,
            textColor=INK,
            spaceBefore=8,
            spaceAfter=4,
        ),
        "body": ParagraphStyle(
            "body",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=10.5,
            leading=15,
            textColor=INK,
            alignment=TA_JUSTIFY,
            spaceAfter=7,
        ),
        "bullet": ParagraphStyle(
            "bullet",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=10.5,
            leading=14.5,
            textColor=INK,
            leftIndent=12,
            spaceAfter=3,
        ),
        "q": ParagraphStyle(
            "q",
            parent=base["Normal"],
            fontName="Times-Bold",
            fontSize=11,
            leading=15,
            textColor=INK,
            spaceBefore=10,
            spaceAfter=4,
        ),
        "a": ParagraphStyle(
            "a",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=10.5,
            leading=15,
            textColor=INK,
            alignment=TA_JUSTIFY,
            leftIndent=8,
            spaceAfter=6,
        ),
        "code": ParagraphStyle(
            "code",
            parent=base["Code"],
            fontName="Courier",
            fontSize=8,
            leading=11,
            textColor=INK,
            backColor=ROW_BG,
            leftIndent=6,
            rightIndent=6,
            spaceBefore=4,
            spaceAfter=8,
        ),
        "caption": ParagraphStyle(
            "caption",
            parent=base["Normal"],
            fontName="Times-Italic",
            fontSize=9,
            textColor=SLATE,
            alignment=TA_CENTER,
            spaceBefore=2,
            spaceAfter=10,
        ),
        "toc": ParagraphStyle(
            "toc",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=11,
            leading=18,
            textColor=INK,
        ),
        "footer": ParagraphStyle(
            "footer",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=8,
            textColor=SLATE,
        ),
        "th": ParagraphStyle(
            "th",
            parent=base["Normal"],
            fontName="Times-Bold",
            fontSize=8.5,
            leading=11,
            textColor=colors.white,
        ),
        "td": ParagraphStyle(
            "td",
            parent=base["Normal"],
            fontName="Times-Roman",
            fontSize=8.5,
            leading=11,
            textColor=INK,
        ),
    }
    return s


def header_footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.4)
    canvas.line(18 * mm, A4[1] - 12 * mm, A4[0] - 18 * mm, A4[1] - 12 * mm)
    canvas.setFont("Times-Italic", 8)
    canvas.setFillColor(SLATE)
    canvas.drawString(18 * mm, A4[1] - 10 * mm, "University Cyber Range — Project Report")
    canvas.drawRightString(A4[0] - 18 * mm, A4[1] - 10 * mm, "Campus Cybersecurity Virtual Laboratory")
    canvas.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    canvas.drawString(18 * mm, 9 * mm, "Confidential training platform documentation")
    canvas.drawRightString(A4[0] - 18 * mm, 9 * mm, f"Page {doc.page}")
    canvas.restoreState()


def cover_header_footer(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(HEAD_BG)
    canvas.rect(0, A4[1] - 28 * mm, A4[0], 28 * mm, fill=1, stroke=0)
    canvas.rect(0, 0, A4[0], 22 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.white)
    canvas.setFont("Times-Bold", 9)
    canvas.drawCentredString(A4[0] / 2, A4[1] - 16 * mm, "FACULTY OF CYBERSECURITY  ·  CAMPUS CYBER RANGE")
    canvas.setFont("Times-Roman", 8)
    canvas.drawCentredString(A4[0] / 2, 10 * mm, "Project documentation  ·  Installation, architecture, and solved configuration questions")
    canvas.restoreState()


def table(headers, rows, col_widths):
    s = styles()
    data = [[Paragraph(str(h), s["th"]) for h in headers]]
    for row in rows:
        data.append([Paragraph(str(c), s["td"]) for c in row])
    t = Table(data, colWidths=col_widths, repeatRows=1)
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), HEAD_BG),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("BACKGROUND", (0, 1), (-1, -1), colors.white),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, ROW_BG]),
                ("GRID", (0, 0), (-1, -1), 0.3, LINE),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 5),
                ("RIGHTPADDING", (0, 0), (-1, -1), 5),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ]
        )
    )
    return t


def bullets(items, s):
    return ListFlowable(
        [ListItem(Paragraph(i, s["bullet"]), leftIndent=12, bulletColor=CYAN) for i in items],
        bulletType="bullet",
        start="•",
        leftIndent=18,
        bulletFontName="Times-Bold",
        bulletFontSize=10,
        spaceAfter=8,
    )


def pre(text, s):
    return Preformatted(text.strip("\n"), s["code"])


def build():
    s = styles()
    story = []

    story.append(Spacer(1, 42 * mm))
    story.append(Paragraph("PROJECT REPORT", s["cover_kicker"]))
    story.append(Paragraph("University Cybersecurity<br/>Virtual Laboratory / Cyber Range", s["cover_title"]))
    story.append(
        Paragraph(
            "Why the platform is needed, how it was designed and implemented,<br/>"
            "which software and strategies were applied, and a complete solved<br/>"
            "question-and-answer guide to installation and configuration.",
            s["cover_sub"],
        )
    )
    story.append(Spacer(1, 10 * mm))
    story.append(Paragraph("Container-first  ·  Isolated student laboratories  ·  Dynamic scheduling", s["cover_meta"]))
    story.append(Spacer(1, 16 * mm))
    story.append(
        Paragraph(
            "Repository: https://github.com/hussaini-8024/Project<br/>"
            "Management plane: FastAPI + React<br/>"
            "Workload plane: Linux network namespaces, Docker, KVM/libvirt<br/>"
            "Default private lab network: 10.0.0.0/8",
            s["cover_meta"],
        )
    )
    story.append(Spacer(1, 22 * mm))
    story.append(
        Paragraph(
            "This document describes a working campus Cyber Range as a Service. "
            "It is intended for instructors, examiners, and operators who must understand "
            "the motivation, the implementation path, and the exact install and configuration steps.",
            s["cover_sub"],
        )
    )
    story.append(PageBreak())

    story.append(Paragraph("Contents", s["h1"]))
    for line in [
        "1. Why this project is needed",
        "2. Project objectives and scope",
        "3. How the system was built",
        "4. Software stack and tools",
        "5. Strategies and design principles",
        "6. Architecture and data flows",
        "7. Isolation, networking, and the private /8 range",
        "8. Capacity, quotas, and the scheduler",
        "9. Security controls and operations",
        "10. Solved questions and answers — installation and configuration",
        "11. Verification checklist and conclusion",
        "Appendix A. Demo accounts and default ports",
        "Appendix B. Environment variables",
    ]:
        story.append(Paragraph(line, s["toc"]))
    story.append(PageBreak())

    # 1
    story.append(Paragraph("1. Why this project is needed", s["h1"]))
    story.append(
        Paragraph(
            "A university cybersecurity programme cannot be taught only from slides. Students must "
            "attack and defend real services: scan a target, exploit a deliberately vulnerable web "
            "application, inspect logs, and recover a host. Doing that on a shared classroom PC, "
            "on a student’s personal laptop, or on the campus production network is unsafe. A "
            "misconfigured tool can harm another student, leak credentials, or touch systems that "
            "were never in scope.",
            s["body"],
        )
    )
    story.append(
        Paragraph(
            "Traditional computer labs also fail at scale. If every student boots a full Windows or "
            "Kali virtual machine, a single 128 GB server is exhausted after a handful of sessions. "
            "If every student shares one “hacking VLAN”, isolation disappears. If instructors hand "
            "out cloud credits, cost and governance become the bottleneck. The campus therefore needs "
            "a <b>private Cyber Range as a Service</b>: each student receives a persistent laboratory "
            "that looks like a small private network, while the university retains control of identity, "
            "quota, audit, and capacity.",
            s["body"],
        )
    )
    story.append(Paragraph("The practical problems this project solves", s["h2"]))
    story.append(
        bullets(
            [
                "<b>Safety.</b> Vulnerable targets (DVWA, OWASP Juice Shop, WebGoat, Metasploitable) must never be reachable from another student or from the campus LAN.",
                "<b>Fairness.</b> One student must not consume the whole server. Quotas and a dynamic scheduler keep RAM, vCPU, disk, and running-container counts inside policy.",
                "<b>Pedagogy.</b> Instructors need assignments, prebuilt scenarios, snapshots, and a browser terminal so class time is spent on the exercise, not on hypervisor setup.",
                "<b>Operations.</b> Administrators need RBAC, MFA, audit logs, backups, ISO approval, and a Capacity Manager that measures safe concurrency instead of guessing “40–60 users”.",
                "<b>Efficiency.</b> Most coursework is Linux userspace. A container or a lightweight netns guest is enough. A full KVM virtual machine is used only when the kernel or a complete OS is required (Windows, kernel labs, some appliances).",
            ],
            s,
        )
    )
    story.append(
        Paragraph(
            "Without this platform, the department either under-teaches (theory only), over-provisions "
            "(one fat VM per student), or accepts unsafe sharing. The Cyber Range is the missing middle: "
            "isolated enough for offensive labs, dense enough for a campus server, and operated like a "
            "service rather than a pile of scripts.",
            s["body"],
        )
    )

    # 2
    story.append(Paragraph("2. Project objectives and scope", s["h1"]))
    story.append(
        Paragraph(
            "The objective was to deliver a <b>production-shaped, multi-user university Cyber Range</b> "
            "with a clear split between the management plane (identity, UI, API, scheduler) and the "
            "workload plane (containers, VMs, student bridges). Students never receive the host Docker "
            "socket, a host root shell, or the libvirt management socket.",
            s["body"],
        )
    )
    story.append(
        table(
            ["In scope", "Out of scope / explicit non-goal"],
            [
                [
                    "RBAC: super admin, administrator, instructor, lab manager, student",
                    "Guaranteeing a fixed 40–60 concurrent labs on every host",
                ],
                [
                    "Persistent per-student laboratory with private 10.0.0.0/8 network",
                    "Letting students attack other students or the hypervisor",
                ],
                [
                    "Container-first scheduling; KVM only when a full OS/kernel is required",
                    "Mounting docker.sock into student guests",
                ],
                [
                    "Browser terminal and console gateways, assignments, quotas, audit, backups",
                    "Replacing the unrelated Ansible Office 2016 playbooks already in the repo",
                ],
                [
                    "Staff-created extra networks and deploy-onto-network",
                    "Hard-coding a maximum user count instead of measuring capacity",
                ],
            ],
            [88 * mm, 88 * mm],
        )
    )
    story.append(Paragraph("Table 1. Scope of the Cyber Range platform.", s["caption"]))

    # 3
    story.append(Paragraph("3. How the system was built", s["h1"]))
    story.append(
        Paragraph(
            "The work was done as a monorepo. The management plane was written first so login, labs, "
            "machines, and quotas could be demonstrated without a hypervisor. Compute providers were "
            "then stacked behind one interface so the same API can run in development (mock or Linux "
            "namespaces) and in production (Docker + KVM hybrid).",
            s["body"],
        )
    )
    story.append(Paragraph("3.1 Implementation sequence", s["h2"]))
    story.append(
        bullets(
            [
                "<b>Domain model.</b> SQLAlchemy models for users, sessions, quotas, student labs, networks, machines, templates, snapshots, assignments, audit logs, backups, and compute nodes.",
                "<b>Identity.</b> JWT access tokens, refresh sessions, password hashing (passlib/bcrypt), lockout, optional TOTP MFA, role guards on every route.",
                "<b>Lab service.</b> First login creates a persistent StudentLab and a private LabNetwork (VLAN, namespace, bridge, CIDR 10.0.0.0/8).",
                "<b>Scheduler.</b> Container-first recommendation; quota checks; host thresholds (warning 85%, high/block 90%); queue when the lab RAM pool is insufficient.",
                "<b>Providers.</b> mock → namespace (Alpine rootfs + netns + veth + lab bridge) → docker → libvirt → hybrid. COMPUTE_PROVIDER=auto selects namespaces on a Linux host.",
                "<b>Guest runtime.</b> Real PTY over WebSocket: <font face='Courier'>sudo ip netns exec NS unshare --uts chroot MERGED /bin/busybox ash</font>. Same-lab ping works by IP and hostname.",
                "<b>Frontend.</b> React + TypeScript + Vite + Tailwind: dashboard, my lab, machine wizard, networks, topology, exercises, resources, admin people/infra/ops.",
                "<b>Packaging.</b> Docker Compose (Postgres, Redis, API, worker, nginx UI), Alembic, load-test CLI, deployment guide.",
                "<b>Admin networks.</b> Staff POST /api/networks and POST /api/networks/{id}/deploy so an administrator can create extra networks and place machines on them.",
            ],
            s,
        )
    )
    story.append(Paragraph("3.2 Repository layout", s["h2"]))
    story.append(
        pre(
            """
backend/          FastAPI app, providers, runtime, scheduler, Alembic
  app/api/         REST: auth, users, labs, catalog, assignments, resources
  app/providers/   mock, namespace, docker, libvirt, hybrid
  app/runtime/     Alpine guests, netns/bridge/veth, PTY
  app/services/    labs, scheduler, capacity, audit, guest, netutil
frontend/         React + TypeScript + Vite + Tailwind (port 5173)
docs/             Deployment, API, this report
loadtest/         Capacity runner (SAFE_* policy from live measurements)
docker-compose.yml  Postgres 16, Redis 7, API, worker, nginx UI
playbooks/        Pre-existing Ansible (unrelated to the range runtime)
            """,
            s,
        )
    )
    story.append(Paragraph("3.3 How a student session actually runs", s["h2"]))
    story.append(
        Paragraph(
            "Alex Chen (STU-000245) signs in. The API restores laboratory LAB-2026-000245. The lab "
            "already has a private bridge and VLAN. Starting the Web Application Security scenario "
            "creates Kali, DVWA, and Juice Shop as containers (or namespace guests in auto mode). "
            "Each guest gets the next host address on 10.0.0.0/8 (gateway 10.0.0.1, first guest "
            "10.0.0.2). The browser terminal attaches a PTY inside that guest. <font face='Courier'>ping "
            "dvwa-target</font> and <font face='Courier'>ping 10.0.0.3</font> succeed. Another student’s "
            "lab uses the same RFC1918 numbers internally but a different namespace and bridge, so "
            "the packets never meet.",
            s["body"],
        )
    )

    # 4
    story.append(Paragraph("4. Software stack and tools", s["h1"]))
    story.append(Paragraph("4.1 Management plane", s["h2"]))
    story.append(
        table(
            ["Component", "Software", "Role in this project"],
            [
                ["API", "Python 3.12, FastAPI, Uvicorn/Gunicorn", "REST + WebSocket management plane"],
                ["Validation", "Pydantic v2 / pydantic-settings", "Request bodies and .env configuration"],
                ["ORM / DB", "SQLAlchemy 2, Alembic", "Schema and migrations"],
                ["Dev database", "SQLite (backend/data/cyberrange.db)", "Zero-ops local development"],
                ["Prod database", "PostgreSQL 16", "Multi-user durable state"],
                ["Cache / queue", "Redis 7, Celery worker", "Session support and background jobs"],
                ["Auth", "python-jose (JWT HS256), passlib/bcrypt, pyotp", "Sessions, hashing, TOTP MFA"],
                ["UI", "React 18, TypeScript, Vite 6, Tailwind 3", "Operator and student console"],
                ["UI data", "TanStack Query, React Router 7", "Client cache and routes"],
                ["Terminal", "xterm.js + Fit addon", "Browser shell to the guest PTY"],
                ["Topology", "@xyflow/react", "Lab network map"],
                ["Charts", "Recharts", "Resource and capacity views"],
                ["Reverse proxy", "nginx 1.27", "Static UI, /api and /ws reverse proxy, TLS termination"],
            ],
            [32 * mm, 58 * mm, 86 * mm],
        )
    )
    story.append(Paragraph("Table 2. Management-plane software.", s["caption"]))

    story.append(Paragraph("4.2 Workload plane", s["h2"]))
    story.append(
        table(
            ["Component", "Software", "When it is used"],
            [
                ["Default guests", "Alpine minirootfs, busybox, Linux netns, veth, bridge", "COMPUTE_PROVIDER=auto/namespace — real shells and intra-lab ping without Docker"],
                ["Containers", "Docker Engine", "Production Linux services: DVWA, Juice Shop, WebGoat, Ubuntu, Wazuh"],
                ["Full VMs", "KVM, QEMU, libvirt", "Windows, Metasploitable, kernel/full-OS labs"],
                ["Images / disks", "Shared container images, qcow2 backing files, CoW volumes", "Density: many students share one golden image"],
                ["Host metrics", "psutil", "Capacity Manager samples CPU, RAM, disk, IOPS"],
                ["Packaging", "Docker Compose, Python 3.12-slim and Node 22 Alpine images", "Repeatable install"],
            ],
            [32 * mm, 62 * mm, 82 * mm],
        )
    )
    story.append(Paragraph("Table 3. Workload-plane software.", s["caption"]))

    story.append(Paragraph("4.3 Exercise catalog (authorized training only)", s["h2"]))
    story.append(
        Paragraph(
            "Vulnerable systems are labeled <b>Training Target — Authorized Laboratory Use Only</b>. "
            "They are attached only to the owning student’s isolated network.",
            s["body"],
        )
    )
    story.append(
        table(
            ["Template", "Kind", "Purpose"],
            [
                ["Ubuntu / Debian service", "Container", "Linux administration and services"],
                ["Kali Training", "Container", "Approved attacker workstation (nmap, Burp, Metasploit, …)"],
                ["DVWA, Juice Shop, WebGoat", "Container", "Authorized web-application security"],
                ["Wazuh Monitor", "Container", "Blue-team detection labs"],
                ["Metasploitable", "VM", "Classic vulnerable OS on the student VLAN"],
                ["Windows Training", "VM (KVM)", "Full OS / kernel path"],
                ["Prebuilt: web, network, Windows, Linux, defense", "Bundle", "One-click classroom scenarios"],
            ],
            [58 * mm, 38 * mm, 80 * mm],
        )
    )
    story.append(Paragraph("Table 4. Machine templates shipped with the range.", s["caption"]))

    # 5
    story.append(Paragraph("5. Strategies and design principles", s["h1"]))
    story.append(Paragraph("5.1 Container-first, VM-when-required", s["h2"]))
    story.append(
        Paragraph(
            "The scheduler never prefers a full virtual machine when a container can run the exercise. "
            "A Linux userspace service shares the host kernel and a copy-on-write image. A Windows "
            "evaluation image or a kernel lab cannot. The HybridProvider sends containers to Docker "
            "and VMs to libvirt. This is the main density strategy: tens of web labs fit in the RAM "
            "that would otherwise hold a few fat VMs.",
            s["body"],
        )
    )
    story.append(Paragraph("5.2 Plane separation", s["h2"]))
    story.append(
        Paragraph(
            "Students use HTTPS to nginx and the API. They never talk to Docker or libvirt. The "
            "workload plane holds the engines, student bridges, and disks. Operators SSH to the "
            "hypervisor; students do not. This is a classic management/control versus data/workload split.",
            s["body"],
        )
    )
    story.append(Paragraph("5.3 Isolation by construction", s["h2"]))
    story.append(
        Paragraph(
            "Each lab has its own Linux network namespace, bridge, and VLAN id. Intra-lab traffic is "
            "bridged so classmates’ machines in the same lab can ping. Inter-lab traffic is default-deny. "
            "Internet is off until staff enable SNAT. Host management addresses and Unix sockets are "
            "not in the student routing table.",
            s["body"],
        )
    )
    story.append(Paragraph("5.4 Dynamic capacity, not a magic number", s["h2"]))
    story.append(
        Paragraph(
            "A 128 GB host with a 16–24 GB reserve can be an engineering target for roughly 40–60 "
            "container-heavy labs or 8–16 concurrent full VMs, but those figures are <b>not</b> coded "
            "as a hard cap. The Capacity Manager and <font face='Courier'>POST /api/resources/loadtest</font> "
            "measure CPU, RAM, storage, IOPS, and latency and print SAFE_* fields. Logged-in users "
            "are not the same as running containers. Hundreds of accounts can exist; only running "
            "workloads consume the lab RAM pool.",
            s["body"],
        )
    )
    story.append(Paragraph("5.5 Least privilege and audit", s["h2"]))
    story.append(
        Paragraph(
            "RBAC is enforced in FastAPI dependencies (require_staff, require_admin). Students may "
            "only see their own machines and networks. Audit logs are append-only through the API. "
            "ISO uploads are checksummed and staff-approved before students can use them. Snapshots "
            "are counted against a per-student cap.",
            s["body"],
        )
    )
    story.append(Paragraph("5.6 Provider strategy (dev → prod)", s["h2"]))
    story.append(
        Paragraph(
            "Development must work on a laptop. Production must use the real engines. One API, five "
            "providers: mock (UI without privileges), namespace (real shells on Linux), docker, "
            "libvirt, hybrid. Switching is an environment variable, not a rewrite.",
            s["body"],
        )
    )

    # 6
    story.append(Paragraph("6. Architecture and data flows", s["h1"]))
    story.append(
        pre(
            """
                         INTERNET
                            |
                    FIREWALL / TLS (nginx)
                            |
              FRONTEND (React)  +  FastAPI  +  PostgreSQL / SQLite
                            |
                   LAB RESOURCE SCHEDULER
                            |
          +-----------------+-----------------+
          |                 |                 |
       Docker            KVM/QEMU        Network manager
       Engine             libvirt     (netns / bridge / VLAN)
          |                 |                 |
    Containers             VMs        Private 10.0.0.0/8 labs
            """,
            s,
        )
    )
    story.append(Paragraph("Figure 1. Management plane above, workload plane below.", s["caption"]))
    story.append(
        Paragraph(
            "Important API groups: <font face='Courier'>/api/auth</font> (login, refresh, MFA), "
            "<font face='Courier'>/api/labs</font> and <font face='Courier'>/api/machines</font> "
            "(lifecycle), <font face='Courier'>/api/networks</font> (list, staff create, staff deploy), "
            "<font face='Courier'>/api/assignments</font>, <font face='Courier'>/api/resources</font> "
            "(capacity and load test), <font face='Courier'>/api/audit</font> and "
            "<font face='Courier'>/api/backups</font>. WebSockets: monitoring, events, "
            "<font face='Courier'>/ws/terminal/{id}</font>, <font face='Courier'>/ws/console/{id}</font> "
            "with JWT in the query string and ownership re-checked.",
            s["body"],
        )
    )

    # 7
    story.append(Paragraph("7. Isolation, networking, and the private /8 range", s["h1"]))
    story.append(
        Paragraph(
            "Student laboratories use the RFC 1918 Class A network <b>10.0.0.0/8</b>, not a /24. "
            "The gateway is the first host (10.0.0.1). Guests are assigned 10.0.0.2, 10.0.0.3, and so on. "
            "Literal 0.0.0.0/8 is rejected because that block is IANA reserved. Administrators may "
            "create additional networks (prefix /8 through /30) and deploy catalog templates onto them. "
            "A student default network cannot be deleted; an empty admin-created network can.",
            s["body"],
        )
    )
    story.append(
        Paragraph(
            "Why /8 instead of /24? Classroom labs grow: Kali plus several targets plus staff-added "
            "machines must not run out of addresses, and operators think in “a private Class A lab”, "
            "not a tiny subnet. Isolation does not come from unique public CIDRs; it comes from "
            "per-lab namespaces and bridges. Two students can both use 10.0.0.2 without ever seeing "
            "each other.",
            s["body"],
        )
    )
    story.append(
        Paragraph(
            "On Linux, bridged ICMP can be stolen by iptables FORWARD unless "
            "<font face='Courier'>net.bridge.bridge-nf-call-iptables=0</font> (and the ip6/arp "
            "siblings) is set. The runtime applies that when it brings a lab bridge up. Overlayfs "
            "upperdirs are avoided on filesystems that cannot host them; each guest receives a small "
            "private copy of Alpine instead.",
            s["body"],
        )
    )

    # 8
    story.append(Paragraph("8. Capacity, quotas, and the scheduler", s["h1"]))
    story.append(
        table(
            ["Profile", "Containers", "Running", "RAM", "vCPU", "VMs", "Storage", "Snapshots"],
            [
                ["Basic", "3", "2", "2048 MB", "2", "1", "2 GB", "2"],
                ["Standard (default undergrad)", "5", "4", "4096 MB", "4", "1", "5 GB", "3"],
                ["Advanced (capstone)", "10", "8", "8192 MB", "6", "2", "20 GB", "3"],
            ],
            [40 * mm, 22 * mm, 20 * mm, 22 * mm, 18 * mm, 16 * mm, 20 * mm, 22 * mm],
        )
    )
    story.append(Paragraph("Table 5. Built-in quota profiles.", s["caption"]))
    story.append(
        Paragraph(
            "Host defaults assume 128 GB RAM (131072 MB) and 3.2 TB disk, with a 20 GB RAM reserve "
            "and 200 GB storage reserve for the OS, database, Redis, API, Docker, libvirt, and caches. "
            "The lab pool is total minus reserve. Thresholds: normal 80%, warning 85%, high/block 90%. "
            "Staff-initiated deploys onto a network are not blocked by the student’s remaining quota, "
            "so an administrator can still place a demonstration host in a full classroom lab.",
            s["body"],
        )
    )

    # 9
    story.append(Paragraph("9. Security controls and operations", s["h1"]))
    story.append(
        bullets(
            [
                "Students never receive docker.sock, libvirt-sock, or a host root shell.",
                "Login rate limit and lockout; optional administrator TOTP; HTTP-only cookies; CORS allow-list.",
                "Security headers: X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy same-origin.",
                "Internet default-deny; staff toggle per lab; SNAT only when enabled.",
                "Append-only audit of login, machine lifecycle, ISO, backup, network create/delete.",
                "Backups: database dump (14-day default retention), persistent volumes, not ephemeral exercise containers.",
                "ISO upload size cap and SHA-256; students see only approved images.",
                "Vulnerable templates carry an on-screen authorized-use warning.",
            ],
            s,
        )
    )

    # 10 Q&A — the large section the user asked for
    story.append(PageBreak())
    story.append(Paragraph("10. Solved questions and answers — installation and configuration", s["h1"]))
    story.append(
        Paragraph(
            "This section is written as examination-style questions with complete, operational answers. "
            "Commands match the repository as shipped.",
            s["body"],
        )
    )

    qa = [
        (
            "Q1. What are the two supported installation strategies, and when do you choose each one?",
            "There are two first-class strategies.<br/><br/>"
            "<b>A. Local development install</b> — Python virtualenv + Node/Vite on the operator’s machine. "
            "Use this to develop the UI/API, mark coursework, or demonstrate the range without standing up "
            "Postgres, Redis, or KVM. Database is SQLite. Compute provider is <font face='Courier'>auto</font> "
            "(Linux namespaces) or <font face='Courier'>mock</font>.<br/><br/>"
            "<b>B. Packaged / production-shaped install</b> — Docker Compose starts PostgreSQL 16, Redis 7, "
            "the FastAPI container, a Celery worker, and an nginx container that serves the built React app "
            "and proxies <font face='Courier'>/api</font> and <font face='Courier'>/ws</font>. On a real "
            "campus hypervisor you then set <font face='Courier'>COMPUTE_PROVIDER=hybrid</font> after Docker "
            "and libvirt are installed on the host. Choose A for coding; choose B for a repeatable service "
            "deploy. A third, host-native production path (section Q8) installs packages with apt and runs "
            "the same API under systemd/gunicorn behind nginx TLS.",
        ),
        (
            "Q2. What software must be present before a development install?",
            "Python 3.12 or newer, Node.js 20+ (Node 22 is used in the frontend Docker image), npm, git, "
            "and a Linux kernel with network-namespace support if you want real terminals. Optional but "
            "required for live guests: passwordless sudo for <font face='Courier'>ip netns</font>, "
            "<font face='Courier'>ip link</font>, <font face='Courier'>mount</font>, and "
            "<font face='Courier'>sysctl</font>. Windows/macOS development can still run the API/UI with "
            "<font face='Courier'>COMPUTE_PROVIDER=mock</font>, but intra-lab ping will be simulated.",
        ),
        (
            "Q3. Give the exact development installation commands.",
            "From the repository root:<br/><br/>"
            "<font face='Courier'>cd backend<br/>"
            "python3 -m venv .venv<br/>"
            "source .venv/bin/activate<br/>"
            "pip install -r requirements.txt<br/>"
            "mkdir -p data<br/>"
            "uvicorn app.main:app --reload --host 0.0.0.0 --port 8000</font><br/><br/>"
            "Second terminal:<br/><br/>"
            "<font face='Courier'>cd frontend<br/>"
            "npm install<br/>"
            "npm run dev</font><br/><br/>"
            "Open http://localhost:5173 and sign in. Vite proxies <font face='Courier'>/api</font> and "
            "<font face='Courier'>/ws</font> to 127.0.0.1:8000 (see frontend/vite.config.ts). API docs: "
            "http://localhost:8000/docs  Health: http://localhost:8000/api/health",
        ),
        (
            "Q4. What happens the first time the API starts?",
            "Lifespan in <font face='Courier'>app/main.py</font> creates the storage directory, runs "
            "<font face='Courier'>Base.metadata.create_all</font>, migrates the networks table (adds "
            "<font face='Courier'>kind</font> / <font face='Courier'>created_by</font>, rebuilds away the "
            "old UNIQUE(lab_id) so a lab may have many networks), seeds demo users/templates/quotas if "
            "the database is empty, then rewrites leftover student /24 networks onto 10.0.0.0/8 and "
            "re-provisions running guests. You do not run a separate seed command.",
        ),
        (
            "Q5. Give the Docker Compose installation strategy end-to-end.",
            "<font face='Courier'>cp .env.example .env</font> then edit secrets. "
            "<font face='Courier'>docker compose up --build</font>. Compose starts:<br/>"
            "• postgres:16-alpine — user/password/db <font face='Courier'>cyberrange</font>, volume pgdata, healthcheck pg_isready<br/>"
            "• redis:7-alpine — AOF persistence, volume redisdata<br/>"
            "• api — backend/Dockerfile (python:3.12-slim), Uvicorn :8000, DATABASE_URL points at Postgres, COOKIE_SECURE=true<br/>"
            "• worker — same image, <font face='Courier'>python -m app.worker</font><br/>"
            "• web — Node 22 build of the UI, nginx:1.27-alpine, ports 80 and 443<br/><br/>"
            "The API waits until Postgres is healthy. Persistent lab files live in volume range-storage "
            "mounted at /app/data/storage. Set COMPUTE_PROVIDER in .env; the Compose default is mock "
            "unless you override it.",
        ),
        (
            "Q6. Explain every important environment variable.",
            "APP_ENV=development|production|test. SECRET_KEY — HMAC for JWT; generate with "
            "<font face='Courier'>openssl rand -hex 32</font>. DATABASE_URL — sqlite:///./data/cyberrange.db "
            "or postgresql+psycopg2://cyberrange:PASSWORD@postgres:5432/cyberrange. REDIS_URL. CORS_ORIGINS "
            "comma list (dev: http://localhost:5173; prod: https://range.university.edu). "
            "COMPUTE_PROVIDER=auto|mock|namespace|docker|libvirt|hybrid. HOST_TOTAL_RAM_MB=131072, "
            "HOST_RESERVE_RAM_MB=20480, HOST_TOTAL_STORAGE_GB=3276. THRESHOLD_WARNING=85, "
            "THRESHOLD_HIGH=90, THRESHOLD_BLOCK=90. COOKIE_SECURE=false on http://localhost, true behind TLS. "
            "Also in Settings: access_token_minutes=30, refresh_token_days=7, session_idle_minutes=60, "
            "iso_max_gb=20, snapshot_max_per_student=3, backup_retention_days=14, ephemeral_ttl_hours=8, "
            "rate_limit_login=8, lockout_minutes=15, docker_socket=unix:///var/run/docker.sock, "
            "libvirt_uri=qemu:///system, storage_root=./data/storage.",
        ),
        (
            "Q7. How do you configure CORS, cookies, and TLS correctly?",
            "Development: CORS_ORIGINS includes the Vite origin; COOKIE_SECURE=false; SameSite=lax. "
            "Production: CORS_ORIGINS is only the public HTTPS origin; COOKIE_SECURE=true; terminate TLS "
            "on nginx or Traefik; enable HSTS; do not expose Uvicorn on the public NIC. nginx must "
            "forward <font face='Courier'>/api/</font> and upgrade WebSockets on <font face='Courier'>/ws/</font> "
            "(already in frontend/nginx.conf: proxy_http_version 1.1, Upgrade and Connection headers). "
            "Set X-Forwarded-Proto so the API knows it is behind HTTPS.",
        ),
        (
            "Q8. Describe the native production host installation (Ubuntu 22.04/24.04 or Debian 12).",
            "Target: 128 GB RAM, 3.2 TB disk, /dev/kvm present.<br/><br/>"
            "<font face='Courier'>sudo apt update<br/>"
            "sudo apt install -y qemu-kvm libvirt-daemon-system libvirt-clients bridge-utils \\<br/>"
            "  docker.io docker-compose-plugin nginx postgresql redis-server certbot python3-certbot-nginx<br/>"
            "sudo usermod -aG kvm,libvirt,docker $USER</font><br/><br/>"
            "Enable forwarding: <font face='Courier'>echo 'net.ipv4.ip_forward=1' | sudo tee "
            "/etc/sysctl.d/99-cyberrange.conf &amp;&amp; sudo sysctl --system</font>. "
            "Obtain a certificate with certbot. Point nginx at the UI build and proxy API/WS as in "
            "frontend/nginx.conf. Run the API with gunicorn/uvicorn under systemd. Set "
            "COMPUTE_PROVIDER=hybrid, DATABASE_URL to PostgreSQL, COOKIE_SECURE=true. "
            "Never mount /var/run/docker.sock or /var/run/libvirt/libvirt-sock into student containers.",
        ),
        (
            "Q9. What storage layout should operators create on a 3.2 TB server?",
            "<font face='Courier'>/var/lib/cyberrange/images</font> — shared container and VM templates (CoW backing files).<br/>"
            "<font face='Courier'>/var/lib/cyberrange/volumes</font> — persistent student data.<br/>"
            "<font face='Courier'>/var/lib/cyberrange/vms</font> — thin-provisioned qcow2.<br/>"
            "<font face='Courier'>/var/lib/cyberrange/isos</font> — administrator ISO repository.<br/>"
            "<font face='Courier'>/var/lib/cyberrange/backups</font> — database + metadata + selected disks.<br/>"
            "<font face='Courier'>/var/log/cyberrange</font> — rotated logs.<br/><br/>"
            "Apply filesystem quotas per category. Snapshot count is also enforced in software "
            "(snapshot_max_per_student). Point storage_root at this tree in production.",
        ),
        (
            "Q10. How is the private lab network configured, and how do you verify it?",
            "Default CIDR is 10.0.0.0/8 (app/services/netutil.py DEFAULT_LAB_CIDR). Gateway 10.0.0.1, "
            "guests from 10.0.0.2. Each LabNetwork stores vlan_id, namespace, bridge, isolated, internet, "
            "kind (student|admin). Runtime: create bridge, disable bridge-nf iptables, add veth into the "
            "guest netns, assign <font face='Courier'>ip addr add 10.0.0.2/8 dev eth0</font>. "
            "Verify: student Lab page shows 10.0.0.0/8; terminal <font face='Courier'>ip -4 addr</font> "
            "shows /8; <font face='Courier'>ping 10.0.0.3</font> and <font face='Courier'>ping dvwa-target</font> "
            "succeed; a second student’s namespace is a different <font face='Courier'>ip netns</font> "
            "and cannot reach the first. Staff create extra nets with POST /api/networks (body: name, "
            "cidr, optional lab_id, isolated, internet) and deploy with POST /api/networks/{id}/deploy "
            "(template_slug, name, optional owner_id).",
        ),
        (
            "Q11. A student can ping by IP but not by hostname. What is the configuration explanation?",
            "Hostnames are written into the guest’s <font face='Courier'>/etc/hosts</font> inside the "
            "chroot (merged Alpine rootfs), not into the hypervisor’s /etc/hosts. The browser terminal "
            "runs <font face='Courier'>ip netns exec … chroot MERGED busybox ash</font>, so names "
            "resolve. A raw <font face='Courier'>ip netns exec NS ping dvwa-target</font> without chroot "
            "uses the host resolver and fails. This is expected, not a routing bug.",
        ),
        (
            "Q12. Intra-lab ping fails with 100% loss even though addresses look correct. What do you check?",
            "1) Both guests are RUNNING and share the same network_id. 2) <font face='Courier'>ip -4 addr</font> "
            "inside each guest shows the /8 prefix, not a leftover /24. 3) "
            "<font face='Courier'>sysctl net.bridge.bridge-nf-call-iptables</font> is 0 (the runtime sets "
            "this; if a host policy resets it, bridged ICMP is filtered). 4) nftables/iptables must not "
            "FORWARD-drop the lab bridge. 5) You are not trying to ping another student’s lab — that is "
            "denied by design.",
        ),
        (
            "Q13. How do compute providers get selected, and how do you switch from lab demo to production?",
            "get_provider() reads COMPUTE_PROVIDER. auto and namespace → NamespaceProvider (Alpine + netns). "
            "docker → DockerProvider. libvirt → LibvirtProvider. hybrid → HybridProvider (Docker for "
            "containers, libvirt for VMs). anything else → MockProvider. Development: auto. Compose "
            "demo: mock until the host is a hypervisor. Campus server: hybrid after KVM and Docker are "
            "healthy. Restart the API after changing the variable (Settings is lru_cached).",
        ),
        (
            "Q14. How is nginx configured in the Compose UI image?",
            "frontend/nginx.conf listens on 80, serves /usr/share/nginx/html, SPA fallback try_files "
            "to index.html. location /api/ proxies to http://api:8000/api/. /docs and /openapi.json "
            "proxy to the API. /ws/ upgrades the connection. That is the entire public configuration "
            "strategy for the containerized UI: one origin, no CORS needed between UI and API inside "
            "the same host header.",
        ),
        (
            "Q15. How do you install and run the load-test / capacity strategy?",
            "As an administrator: POST /api/resources/loadtest with optional {\"steps\":[10,20,…,100]}. "
            "Or from the repo: <font face='Courier'>python loadtest/run.py --api http://127.0.0.1:8000 "
            "--user admin --password 'CyberRange!Admin2026'</font>. Use the printed SAFE_* fields as "
            "the production concurrency policy. Re-run after hardware or golden-image changes. Do not "
            "hard-code 40–60 students in application code.",
        ),
        (
            "Q16. How are backups configured, and what should not be backed up?",
            "POST /api/backups (admin) records a backup object; retention is backup_retention_days (14). "
            "Production playbook: pg_dump on a cron, rsync or zfs send of /var/lib/cyberrange/volumes, "
            "copy-on-write VM snapshots, replicate the approved ISO/template catalog. Test restore "
            "quarterly (database, lab metadata, one VM disk). Do not back up ephemeral exercise "
            "containers — they are rebuilt from templates.",
        ),
        (
            "Q17. How do you configure RBAC and the demo users after install?",
            "Roles: super_admin, administrator, instructor, lab_manager, student. Seeded accounts "
            "(change immediately on a production host): admin / CyberRange!Admin2026, instructor / "
            "CyberRange!Teach2026, labmanager / CyberRange!Lab2026, student / CyberRange!Stud2026 "
            "(STU-000245, lab LAB-2026-000245). Administrators create users via POST /api/users. "
            "Enable TOTP MFA for operators. Students cannot POST /api/networks (403 Staff access required).",
        ),
        (
            "Q18. A student hits “vCPU quota exceeded” or “Storage quota exceeded”. What is the configuration lever?",
            "Quota profiles Basic/Standard/Advanced are rows in resource_quotas. PATCH /api/quotas/{id} "
            "or assign a higher profile on the user (quota_name). Running-container, RAM, vCPU, VM, "
            "storage, and snapshot caps are independent. Starting an existing machine must not double-count "
            "its disk (the scheduler passes existing=machine). Staff deploy onto a network uses "
            "ignore_quota so an instructor can still place a target in a full student lab.",
        ),
        (
            "Q19. How do you configure isolation firewall rules beyond what the app creates?",
            "The app creates per-lab bridges and namespaces. Operators should still add nftables/iptables "
            "that drop: lab → other lab bridges, lab → host management addresses, lab → Docker/libvirt "
            "sockets. Internet, when staff-enabled, is SNAT only. Do not enable unrestricted forwarding "
            "on the hypervisor. SSH to the hypervisor is limited to operators; noVNC/SPICE if used is "
            "bound to localhost behind nginx.",
        ),
        (
            "Q20. How would you add a second compute node (multi-node strategy)?",
            "The scheduler already picks a healthy ComputeNode by RAM, CPU, KVM/Docker flags, and load. "
            "The first generation seeds node-01 as controller+worker. To scale: insert another "
            "compute_nodes row, run a worker on that host with the same DATABASE_URL/REDIS_URL, and "
            "ensure the provider on that worker can reach local Docker/libvirt. No application rewrite "
            "is required for the first extra node. Keep one management plane; do not give students a "
            "path to the new node’s sockets.",
        ),
        (
            "Q21. List the production hardening checklist after installation.",
            "MFA on for all administrators; demo passwords rotated; SECRET_KEY replaced; ISO uploads "
            "limited and checksummed; student outbound internet default-deny; audit log shipped to a "
            "write-once store; host reserve and 90% emergency threshold confirmed; console gateway bound "
            "to localhost; SSH to the hypervisor limited to operators; CORS and cookie flags match HTTPS; "
            "Postgres not published to the internet; Redis bound to the compose network only.",
        ),
        (
            "Q22. How do you verify a complete configuration with a classroom scenario?",
            "1) Login as student. 2) Open My Lab — CIDR 10.0.0.0/8. 3) Create Machine → prebuilt Web "
            "Application Security (or start existing Kali/DVWA/Juice Shop). 4) Open Kali terminal; "
            "ping 10.0.0.3 and ping dvwa-target. 5) Logout; login as admin; Networks → Create network "
            "(10.0.0.0/8) attached to LAB-2026-000245 → Deploy debian/ubuntu. 6) Confirm the new host "
            "appears on that network. 7) GET /api/health, GET /api/resources, POST /api/resources/loadtest. "
            "8) Confirm a second student cannot see the first student’s machines.",
        ),
        (
            "Q23. Which Python and Node packages are load-bearing, and why were they chosen?",
            "FastAPI — async API and OpenAPI for free. Uvicorn — ASGI. SQLAlchemy 2 — typed mappings. "
            "Alembic — SQL migrations when SQLite create_all is not enough. python-jose + passlib — "
            "standard JWT/password stack. pyotp — MFA without an external IdP. psutil — host samples "
            "for the Capacity Manager. docker SDK — optional live containers. Redis/Celery — worker "
            "and future job fan-out. React/Vite/Tailwind — fast campus UI. xterm.js — real terminal. "
            "pytest — scheduler, netutil, schema rebuild, runtime spec tests. These are mainstream "
            "tools a university operator can hire for, not a custom framework.",
        ),
        (
            "Q24. What must never be configured, even if it would “make labs easier”?",
            "Do not put students in group docker or libvirt. Do not share one bridge for the whole class. "
            "Do not allocate 100% of RAM to labs (keep HOST_RESERVE_RAM_MB). Do not publish Postgres "
            "or Redis. Do not disable isolation to “fix ping”. Do not leave demo passwords on a "
            "networked host. Do not back up ephemeral containers as if they were unique data. Do not "
            "treat logged-in count as running-lab count when sizing the server.",
        ),
    ]

    for q, a in qa:
        story.append(KeepTogether([Paragraph(q, s["q"]), Paragraph(a, s["a"])]))

    # 11
    story.append(Paragraph("11. Verification checklist and conclusion", s["h1"]))
    story.append(
        Paragraph(
            "The platform exists because a university cannot teach offensive and defensive security "
            "on shared desktops or on the production network. It was built as a management plane "
            "(FastAPI + React) over a workload plane (namespaces, Docker, KVM), with container-first "
            "scheduling, per-student 10.0.0.0/8 laboratories, staff-created extra networks, quotas, "
            "audit, and a Capacity Manager that measures safe concurrency instead of inventing it.",
            s["body"],
        )
    )
    story.append(
        Paragraph(
            "After installation, success looks like this: students sign in, receive a private lab, "
            "run authorized targets, ping peers inside that lab only, and never touch the hypervisor. "
            "Administrators create networks, deploy machines, read audit logs, and re-run load tests "
            "when hardware changes. That is the whole point of the Cyber Range — a controlled place "
            "to practise, at campus scale.",
            s["body"],
        )
    )

    story.append(Paragraph("Appendix A. Demo accounts and default ports", s["h1"]))
    story.append(
        table(
            ["Role", "Username", "Password", "Notes"],
            [
                ["Super administrator", "admin", "CyberRange!Admin2026", "Rotate on any shared host"],
                ["Instructor", "instructor", "CyberRange!Teach2026", "Assignments and class labs"],
                ["Lab manager", "labmanager", "CyberRange!Lab2026", "Images, ISOs, capacity"],
                ["Student", "student", "CyberRange!Stud2026", "STU-000245 / LAB-2026-000245"],
            ],
            [40 * mm, 32 * mm, 52 * mm, 52 * mm],
        )
    )
    story.append(Paragraph("Table 6. Seeded demo accounts.", s["caption"]))
    story.append(
        table(
            ["Service", "Port", "Bind"],
            [
                ["Vite UI (development)", "5173", "0.0.0.0, proxies /api and /ws to 8000"],
                ["FastAPI / Uvicorn", "8000", "0.0.0.0 in dev; localhost behind nginx in prod"],
                ["nginx UI (Compose)", "80 / 443", "public HTTP(S)"],
                ["PostgreSQL (Compose)", "5432", "internal compose network only"],
                ["Redis (Compose)", "6379", "internal compose network only"],
            ],
            [55 * mm, 35 * mm, 86 * mm],
        )
    )
    story.append(Paragraph("Table 7. Default ports.", s["caption"]))

    story.append(Paragraph("Appendix B. Environment variables (quick copy)", s["h1"]))
    story.append(
        pre(
            """
APP_ENV=development
SECRET_KEY=change-me-in-production-use-openssl-rand-hex-32
DATABASE_URL=sqlite:///./data/cyberrange.db
# DATABASE_URL=postgresql+psycopg2://cyberrange:change-me@postgres:5432/cyberrange
REDIS_URL=redis://localhost:6379/0
CORS_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
COMPUTE_PROVIDER=auto
HOST_TOTAL_RAM_MB=131072
HOST_RESERVE_RAM_MB=20480
HOST_TOTAL_STORAGE_GB=3276
THRESHOLD_WARNING=85
THRESHOLD_HIGH=90
THRESHOLD_BLOCK=90
COOKIE_SECURE=false
            """,
            s,
        )
    )
    story.append(
        Paragraph(
            "End of report. Source repository: https://github.com/hussaini-8024/Project — "
            "see also docs/DEPLOYMENT.md and docs/API.md.",
            s["body"],
        )
    )

    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=18 * mm,
        bottomMargin=18 * mm,
        title="University Cyber Range — Project Report",
        author="University Cyber Range",
        subject="Motivation, implementation, software, strategies, installation and configuration Q&A",
    )
    doc.build(story, onFirstPage=cover_header_footer, onLaterPages=header_footer)
    return OUT


if __name__ == "__main__":
    path = build()
    print(path)
    print("bytes", path.stat().st_size)
