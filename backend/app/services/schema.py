from __future__ import annotations

from ipaddress import IPv4Network

from sqlalchemy import text
from sqlalchemy.engine import Engine
from sqlalchemy.orm import Session

from app.models import LabNetwork, Machine, MachineStatus
from app.services.netutil import DEFAULT_LAB_CIDR, readdress_slash8


def migrate_network_schema(engine: Engine) -> None:
    with engine.begin() as conn:
        row = conn.execute(text("SELECT sql FROM sqlite_master WHERE type='table' AND name='networks'")).fetchone()
        if not row:
            return
        sql = " ".join((row[0] or "").split())
        cols = [r[1] for r in conn.execute(text("PRAGMA table_info(networks)"))]
        if "kind" not in cols:
            conn.execute(text("ALTER TABLE networks ADD COLUMN kind VARCHAR(32) DEFAULT 'student'"))
            cols.append("kind")
        if "created_by" not in cols:
            conn.execute(text("ALTER TABLE networks ADD COLUMN created_by VARCHAR(36)"))
            cols.append("created_by")
        unique_lab = "UNIQUE (lab_id)" in sql or "UNIQUE(lab_id)" in sql.replace(" ", "")
        if not unique_lab:
            return
        conn.execute(text("PRAGMA foreign_keys=OFF"))
        conn.execute(
            text(
                """
                CREATE TABLE networks_new (
                    id VARCHAR(36) NOT NULL PRIMARY KEY,
                    lab_id VARCHAR(36),
                    name VARCHAR(64) NOT NULL,
                    cidr VARCHAR(32) NOT NULL DEFAULT '10.0.0.0/8',
                    vlan_id INTEGER NOT NULL,
                    namespace VARCHAR(64) NOT NULL,
                    isolated BOOLEAN NOT NULL DEFAULT 1,
                    internet BOOLEAN NOT NULL DEFAULT 0,
                    bridge VARCHAR(64) NOT NULL DEFAULT '',
                    kind VARCHAR(32) DEFAULT 'student',
                    created_by VARCHAR(36),
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY(lab_id) REFERENCES student_labs (id) ON DELETE SET NULL,
                    FOREIGN KEY(created_by) REFERENCES users (id)
                )
                """
            )
        )
        conn.execute(
            text(
                """
                INSERT INTO networks_new (
                    id, lab_id, name, cidr, vlan_id, namespace, isolated, internet,
                    bridge, kind, created_by, created_at
                )
                SELECT
                    id, lab_id, name, cidr, vlan_id, namespace, isolated, internet,
                    bridge, COALESCE(kind, 'student'), created_by, created_at
                FROM networks
                """
            )
        )
        conn.execute(text("DROP TABLE networks"))
        conn.execute(text("ALTER TABLE networks_new RENAME TO networks"))
        conn.execute(text("PRAGMA foreign_keys=ON"))


def migrate_slash8_networks(db: Session) -> None:
    """Rewrite leftover student /24 labs onto 10.0.0.0/8. Admin-created CIDRs are left alone."""
    restart_ids: list[str] = []
    changed = False
    for net in db.query(LabNetwork).all():
        if (net.kind or "student") != "student":
            continue
        try:
            parsed = IPv4Network(net.cidr, strict=False)
        except ValueError:
            parsed = None
        if parsed and parsed.prefixlen == 8:
            continue
        readdress_slash8(net, DEFAULT_LAB_CIDR)
        changed = True
        for iface in net.interfaces:
            machine = iface.machine
            if machine and machine.status == MachineStatus.RUNNING:
                machine.status = MachineStatus.STOPPED
                restart_ids.append(machine.id)
    if not changed:
        return
    db.commit()
    from app.services.guest import provision_guest

    for machine_id in restart_ids:
        machine = db.get(Machine, machine_id)
        if not machine:
            continue
        try:
            provision_guest(machine)
            machine.status = MachineStatus.RUNNING
            machine.error_message = ""
        except Exception as exc:
            machine.status = MachineStatus.ERROR
            machine.error_message = str(exc)[:400]
    db.commit()
