from __future__ import annotations

from datetime import datetime, timedelta

from sqlalchemy.orm import Session

from app.models import (
    Assignment,
    AssignmentStatus,
    AssignmentSubmission,
    AuditLog,
    Backup,
    ComputeNode,
    ContainerImage,
    EnvironmentKind,
    IsoImage,
    IsoStatus,
    Machine,
    MachineKind,
    MachineStatus,
    MachineTemplate,
    QuotaProfile,
    Role,
    StorageVolume,
    SystemSetting,
    User,
    UserStatus,
)
from app.security import hash_password, public_id
from app.services.labs import create_machine, ensure_lab


TEMPLATES = [
    {
        "name": "Ubuntu Service",
        "slug": "ubuntu",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "cyberrange/ubuntu-lab:22.04",
        "default_vcpu": 1,
        "default_ram_mb": 512,
        "default_disk_gb": 2,
        "requires_kernel": False,
        "requires_full_os": False,
        "tools": ["openssh", "nmap"],
        "description": "Lightweight Ubuntu userspace for services and Linux security labs.",
        "category": "linux",
    },
    {
        "name": "Debian Service",
        "slug": "debian",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "cyberrange/debian-lab:12",
        "default_vcpu": 1,
        "default_ram_mb": 512,
        "default_disk_gb": 2,
        "tools": ["openssh"],
        "description": "Debian userspace container for network services.",
        "category": "linux",
    },
    {
        "name": "Kali Training",
        "slug": "kali",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "cyberrange/kali-training:latest",
        "default_vcpu": 2,
        "default_ram_mb": 1024,
        "default_disk_gb": 2,
        "tools": ["nmap", "wireshark", "burpsuite", "owasp-zap", "metasploit", "openssh"],
        "description": "Approved security-training workstation. Tools operate only inside the student lab.",
        "category": "attacker",
    },
    {
        "name": "OWASP Juice Shop",
        "slug": "juice-shop",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "bkimminich/juice-shop:latest",
        "default_vcpu": 1,
        "default_ram_mb": 512,
        "default_disk_gb": 1,
        "is_vulnerable_target": True,
        "warning_label": "Training Target — Authorized Laboratory Use Only",
        "description": "Intentionally vulnerable web application for authorized coursework.",
        "category": "web",
    },
    {
        "name": "DVWA",
        "slug": "dvwa",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "cyberrange/dvwa:latest",
        "default_vcpu": 1,
        "default_ram_mb": 384,
        "default_disk_gb": 1,
        "is_vulnerable_target": True,
        "warning_label": "Training Target — Authorized Laboratory Use Only",
        "description": "Damn Vulnerable Web Application for authorized web-security labs.",
        "category": "web",
    },
    {
        "name": "WebGoat",
        "slug": "webgoat",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "webgoat/goatandwolf:latest",
        "default_vcpu": 1,
        "default_ram_mb": 768,
        "default_disk_gb": 1,
        "is_vulnerable_target": True,
        "warning_label": "Training Target — Authorized Laboratory Use Only",
        "description": "OWASP WebGoat lessons for authorized classroom use.",
        "category": "web",
    },
    {
        "name": "Metasploitable",
        "slug": "metasploitable",
        "environment": EnvironmentKind.VM,
        "recommended_kind": MachineKind.VM,
        "os_family": "linux",
        "image_ref": "cyberrange/metasploitable:2",
        "default_vcpu": 1,
        "default_ram_mb": 1024,
        "default_disk_gb": 8,
        "requires_full_os": True,
        "is_vulnerable_target": True,
        "warning_label": "Training Target — Authorized Laboratory Use Only",
        "description": "Classic vulnerable OS image. Restricted to the student's isolated network.",
        "category": "network",
    },
    {
        "name": "Windows Training",
        "slug": "windows",
        "environment": EnvironmentKind.VM,
        "recommended_kind": MachineKind.VM,
        "os_family": "windows",
        "image_ref": "iso:windows-eval",
        "default_vcpu": 2,
        "default_ram_mb": 4096,
        "default_disk_gb": 40,
        "requires_kernel": True,
        "requires_full_os": True,
        "description": "Full Windows evaluation/training VM. Uses KVM and thin-provisioned disks.",
        "category": "windows",
    },
    {
        "name": "Wazuh Monitor",
        "slug": "wazuh",
        "environment": EnvironmentKind.CONTAINER,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "cyberrange/wazuh-lab:latest",
        "default_vcpu": 2,
        "default_ram_mb": 1536,
        "default_disk_gb": 8,
        "tools": ["wazuh"],
        "description": "Defensive monitoring stack for authorized blue-team labs.",
        "category": "defense",
    },
]


PREBUILT = [
    {
        "name": "Web Application Security",
        "slug": "lab-webapp",
        "environment": EnvironmentKind.PREBUILT,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "bundle:kali+dvwa+juice+webgoat",
        "default_vcpu": 4,
        "default_ram_mb": 2560,
        "default_disk_gb": 8,
        "description": "Kali + DVWA + Juice Shop + WebGoat on an isolated student network.",
        "category": "scenario",
        "is_vulnerable_target": True,
        "warning_label": "Training Target — Authorized Laboratory Use Only",
    },
    {
        "name": "Network Security",
        "slug": "lab-network",
        "environment": EnvironmentKind.PREBUILT,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "bundle:kali+ubuntu+vuln",
        "default_vcpu": 3,
        "default_ram_mb": 2048,
        "default_disk_gb": 8,
        "description": "Kali + Ubuntu server + vulnerable service target.",
        "category": "scenario",
    },
    {
        "name": "Windows Security",
        "slug": "lab-windows",
        "environment": EnvironmentKind.PREBUILT,
        "recommended_kind": MachineKind.VM,
        "os_family": "mixed",
        "image_ref": "bundle:kali+windows",
        "default_vcpu": 4,
        "default_ram_mb": 6144,
        "default_disk_gb": 50,
        "requires_full_os": True,
        "description": "Kali container + Windows training VM. Scheduler may queue under high load.",
        "category": "scenario",
    },
    {
        "name": "Linux Security",
        "slug": "lab-linux",
        "environment": EnvironmentKind.PREBUILT,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "bundle:kali+ubuntu+debian",
        "default_vcpu": 3,
        "default_ram_mb": 2048,
        "default_disk_gb": 6,
        "description": "Kali + Ubuntu + Debian containers.",
        "category": "scenario",
    },
    {
        "name": "Defensive Security",
        "slug": "lab-defense",
        "environment": EnvironmentKind.PREBUILT,
        "recommended_kind": MachineKind.CONTAINER,
        "os_family": "linux",
        "image_ref": "bundle:linux+wazuh",
        "default_vcpu": 3,
        "default_ram_mb": 2560,
        "default_disk_gb": 12,
        "description": "Linux server + Wazuh + log pipeline for blue-team exercises.",
        "category": "scenario",
    },
]


def seed(db: Session) -> None:
    if db.query(User).first():
        return

    basic = QuotaProfile(
        name="Basic",
        max_containers=3,
        max_running_containers=2,
        max_ram_mb=2048,
        max_vcpu=2,
        max_vms=1,
        max_storage_gb=2,
        max_snapshots=2,
        description="Introductory coursework",
    )
    standard = QuotaProfile(
        name="Standard",
        max_containers=5,
        max_running_containers=4,
        max_ram_mb=4096,
        max_vcpu=4,
        max_vms=1,
        max_storage_gb=5,
        max_snapshots=3,
        description="Default undergraduate profile",
    )
    advanced = QuotaProfile(
        name="Advanced",
        max_containers=10,
        max_running_containers=8,
        max_ram_mb=8192,
        max_vcpu=6,
        max_vms=2,
        max_storage_gb=20,
        max_snapshots=3,
        description="Capstone / graduate labs",
    )
    db.add_all([basic, standard, advanced])
    db.flush()

    admin = User(
        public_id=public_id("ADM"),
        username="admin",
        email="admin@cyberrange.university",
        full_name="Range Administrator",
        hashed_password=hash_password("CyberRange!Admin2026"),
        role=Role.SUPER_ADMIN,
        status=UserStatus.ACTIVE,
        course="Operations",
        mfa_enabled=False,
    )
    instructor = User(
        public_id=public_id("INS"),
        username="instructor",
        email="instructor@cyberrange.university",
        full_name="Dr. Elena Voss",
        hashed_password=hash_password("CyberRange!Teach2026"),
        role=Role.INSTRUCTOR,
        status=UserStatus.ACTIVE,
        course="CYB-301 Web Security",
    )
    labman = User(
        public_id=public_id("LABM"),
        username="labmanager",
        email="labs@cyberrange.university",
        full_name="Lab Manager",
        hashed_password=hash_password("CyberRange!Lab2026"),
        role=Role.LAB_MANAGER,
        status=UserStatus.ACTIVE,
    )
    student = User(
        public_id="STU-000245",
        username="student",
        email="alex.chen@students.university",
        full_name="Alex Chen",
        hashed_password=hash_password("CyberRange!Stud2026"),
        role=Role.STUDENT,
        status=UserStatus.ACTIVE,
        quota_id=standard.id,
        course="CYB-301 Web Security",
        expires_at=datetime.utcnow() + timedelta(days=180),
    )
    student2 = User(
        public_id=public_id("STU"),
        username="jordan",
        email="jordan.okoye@students.university",
        full_name="Jordan Okoye",
        hashed_password=hash_password("CyberRange!Stud2026"),
        role=Role.STUDENT,
        status=UserStatus.ACTIVE,
        quota_id=basic.id,
        course="CYB-210 Intro",
    )
    db.add_all([admin, instructor, labman, student, student2])
    db.flush()

    db.add(
        ComputeNode(
            name="node-01",
            hostname="range-01.campus.local",
            role="controller+worker",
            status="healthy",
            ram_mb=131072,
            cpu_cores=32,
            storage_gb=3276,
            kvm_available=True,
            docker_available=True,
            labels={"site": "main-campus", "generation": "1"},
        )
    )

    for spec in TEMPLATES + PREBUILT:
        db.add(MachineTemplate(**spec))
    db.flush()

    images = [
        ("cyberrange/ubuntu-lab", "22.04", 78, "Shared Ubuntu base — copy-on-write layers"),
        ("cyberrange/debian-lab", "12", 72, "Shared Debian base"),
        ("cyberrange/kali-training", "latest", 890, "Approved training tools image"),
        ("bkimminich/juice-shop", "latest", 210, "OWASP Juice Shop"),
        ("cyberrange/dvwa", "latest", 160, "DVWA training target"),
        ("webgoat/goatandwolf", "latest", 340, "WebGoat + WebWolf"),
        ("cyberrange/wazuh-lab", "latest", 620, "Wazuh lab stack"),
    ]
    for name, tag, size, desc in images:
        db.add(ContainerImage(name=name, tag=tag, size_mb=size, description=desc, shared=True, approved=True))

    db.add_all(
        [
            IsoImage(
                name="Ubuntu 22.04 Server",
                filename="ubuntu-22.04.4-live-server-amd64.iso",
                size_bytes=2_100_000_000,
                sha256="a" * 64,
                status=IsoStatus.APPROVED,
                uploaded_by=admin.id,
                os_family="linux",
                approved_for_students=True,
            ),
            IsoImage(
                name="Windows 11 Evaluation",
                filename="Win11_Eval_x64.iso",
                size_bytes=5_400_000_000,
                sha256="b" * 64,
                status=IsoStatus.APPROVED,
                uploaded_by=admin.id,
                os_family="windows",
                approved_for_students=True,
            ),
            IsoImage(
                name="Kali Linux",
                filename="kali-linux-2024.3-installer-amd64.iso",
                size_bytes=3_800_000_000,
                sha256="c" * 64,
                status=IsoStatus.PENDING,
                uploaded_by=labman.id,
                os_family="linux",
                approved_for_students=False,
            ),
        ]
    )

    assignment = Assignment(
        title="Web Application Security Lab 01",
        description="Identify vulnerabilities in the authorized training applications.",
        objective="Use Kali training tools against DVWA and Juice Shop in your isolated lab only.",
        required_templates=["kali", "dvwa"],
        duration_minutes=120,
        created_by=instructor.id,
        course="CYB-301 Web Security",
    )
    db.add(assignment)
    db.flush()
    db.add(
        AssignmentSubmission(
            assignment_id=assignment.id,
            student_id=student.id,
            status=AssignmentStatus.NOT_STARTED,
        )
    )

    db.add(
        Backup(
            kind="database",
            name="nightly-postgres",
            path="/var/backups/cyberrange/pg.dump",
            size_mb=48,
            status="completed",
            created_by=admin.id,
            expires_at=datetime.utcnow() + timedelta(days=14),
        )
    )
    db.add(
        SystemSetting(
            key="capacity",
            value={
                "host_total_ram_mb": 131072,
                "host_reserve_ram_mb": 20480,
                "threshold_warning": 85,
                "threshold_block": 90,
            },
        )
    )
    db.add(
        AuditLog(
            username="system",
            role="system",
            action="platform.seed",
            resource="database",
            result="success",
            detail="Initial catalog, quotas, and demo accounts created",
        )
    )
    db.commit()

    # Provision the demo student lab so the dashboard is immediately useful
    kali = db.query(MachineTemplate).filter_by(slug="kali").one()
    dvwa = db.query(MachineTemplate).filter_by(slug="dvwa").one()
    juice = db.query(MachineTemplate).filter_by(slug="juice-shop").one()
    ensure_lab(db, student)
    student.lab.public_id = "LAB-2026-000245"
    db.commit()
    for tmpl, name, ram in ((kali, "Kali Training", 1024), (dvwa, "DVWA Target", 384), (juice, "Juice Shop", 512)):
        create_machine(
            db,
            student,
            name=name,
            template=tmpl,
            kind=MachineKind.CONTAINER,
            vcpu=tmpl.default_vcpu,
            ram_mb=ram,
            disk_gb=tmpl.default_disk_gb,
            internet=False,
            isolated=True,
            ephemeral=False,
            ip="127.0.0.1",
        )

    db.add(
        StorageVolume(
            owner_id=student.id,
            lab_id=student.lab.id,
            name="evidence-locker",
            category="student",
            size_gb=1.0,
            persistent=True,
            path=f"/var/lib/cyberrange/students/{student.public_id}/evidence",
        )
    )
    db.commit()
