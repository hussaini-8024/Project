from __future__ import annotations

from ipaddress import IPv4Network

from sqlalchemy import text
from sqlalchemy.engine import Engine
from sqlalchemy.orm import Session

from app.models import LabNetwork, Machine, MachineStatus
from app.services.netutil import DEFAULT_LAB_CIDR, readdress_slash8


def migrate_network_schema(engine: Engine) -> None:
    with engine.begin() as conn:
        cols = [row[1] for row in conn.execute(text("PRAGMA table_info(networks)"))]
        if not cols:
            return
        if "kind" not in cols:
            conn.execute(text("ALTER TABLE networks ADD COLUMN kind VARCHAR(32) DEFAULT 'student'"))
        if "created_by" not in cols:
            conn.execute(text("ALTER TABLE networks ADD COLUMN created_by VARCHAR(36)"))
        for row in conn.execute(text("PRAGMA index_list('networks')")):
            unique = bool(row[2])
            name = row[1]
            if not unique:
                continue
            info = list(conn.execute(text(f"PRAGMA index_info('{name}')")))
            if any(col[2] == "lab_id" for col in info):
                conn.execute(text(f'DROP INDEX IF EXISTS "{name}"'))


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
