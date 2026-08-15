from __future__ import annotations

import asyncio
import json
from datetime import datetime

from fastapi import APIRouter, WebSocket, WebSocketDisconnect
from jose import JWTError

from app.database import SessionLocal
from app.models import ConsoleSession, Machine, Role, TerminalSession, User
from app.security import decode_token
from app.services import audit
from app.services.capacity import occupancy, sample_host

router = APIRouter(tags=["websockets"])


def _user_from_ws(ws: WebSocket) -> User | None:
    token = ws.query_params.get("token") or ws.headers.get("authorization", "").replace("Bearer ", "")
    if not token:
        return None
    try:
        payload = decode_token(token)
    except (ValueError, JWTError):
        return None
    db = SessionLocal()
    try:
        return db.get(User, payload.get("sub"))
    finally:
        db.close()


@router.websocket("/ws/monitoring")
async def monitoring(ws: WebSocket) -> None:
    user = _user_from_ws(ws)
    if not user:
        await ws.close(code=4401)
        return
    await ws.accept()
    try:
        while True:
            db = SessionLocal()
            try:
                host = sample_host()
                occ = occupancy(db)
                payload = {
                    "type": "telemetry",
                    "ts": datetime.utcnow().isoformat(),
                    "cpu": host.cpu_percent,
                    "ram": host.ram_percent,
                    "storage": host.storage_percent,
                    "disk_iops": host.disk_iops,
                    "net_mbps": host.net_mbps,
                    "level": host.level,
                    **occ,
                }
            finally:
                db.close()
            await ws.send_text(json.dumps(payload))
            await asyncio.sleep(2)
    except WebSocketDisconnect:
        return


@router.websocket("/ws/events")
async def events(ws: WebSocket) -> None:
    user = _user_from_ws(ws)
    if not user:
        await ws.close(code=4401)
        return
    await ws.accept()
    try:
        while True:
            db = SessionLocal()
            try:
                from app.models import Machine as M

                q = db.query(M)
                if user.role == Role.STUDENT:
                    q = q.filter(M.owner_id == user.id)
                machines = [
                    {"id": m.public_id, "name": m.name, "status": m.status.value, "kind": m.kind.value}
                    for m in q.all()
                ]
            finally:
                db.close()
            await ws.send_text(json.dumps({"type": "machines", "machines": machines}))
            await asyncio.sleep(3)
    except WebSocketDisconnect:
        return


@router.websocket("/ws/terminal/{machine_id}")
async def terminal(ws: WebSocket, machine_id: str) -> None:
    user = _user_from_ws(ws)
    if not user:
        await ws.close(code=4401)
        return
    db = SessionLocal()
    machine = db.query(Machine).filter((Machine.id == machine_id) | (Machine.public_id == machine_id)).first()
    if not machine or (user.role == Role.STUDENT and machine.owner_id != user.id):
        db.close()
        await ws.close(code=4403)
        return
    sess = TerminalSession(user_id=user.id, machine_id=machine.id)
    db.add(sess)
    audit.record(db, user=user, action="terminal.open", resource=machine.public_id, machine=machine.name)
    db.commit()
    db.close()
    await ws.accept()
    banner = (
        f"\r\n\x1b[36mUniversity Cyber Range\x1b[0m — isolated lab terminal\r\n"
        f"Machine: {machine.name} ({machine.public_id})  Kind: {machine.kind.value}\r\n"
        f"This session is proxied through the terminal gateway. "
        f"The virtualization host is not reachable.\r\n"
        f"Security testing is limited to your own laboratory.\r\n\r\n"
        f"{machine.name.lower().replace(' ', '-')}$ "
    )
    await ws.send_text(banner)
    buffer = ""
    try:
        while True:
            data = await ws.receive_text()
            if data in ("\r", "\n"):
                cmd = buffer.strip()
                buffer = ""
                reply = _simulate_shell(cmd, machine.name)
                await ws.send_text(f"\r\n{reply}\r\n{machine.name.lower().replace(' ', '-')}$ ")
            elif data in ("\x7f", "\b"):
                buffer = buffer[:-1]
                await ws.send_text("\b \b")
            else:
                buffer += data
                await ws.send_text(data)
    except WebSocketDisconnect:
        return


def _simulate_shell(cmd: str, machine: str) -> str:
    if not cmd:
        return ""
    if cmd in ("help", "?"):
        return "Authorized lab shell. Commands: help, id, hostname, ip a, nmap --help, exit"
    if cmd == "id":
        return "uid=1000(student) gid=1000(student) groups=1000(student)"
    if cmd == "hostname":
        return machine.lower().replace(" ", "-")
    if cmd.startswith("ip"):
        return "eth0  10.lab.0.12/24  UP  isolated-namespace"
    if cmd.startswith("nmap"):
        return "Nmap 7.94 ( https://nmap.org )\nUsage restricted to this student lab network."
    if cmd == "exit":
        return "logout"
    return f"{cmd}: executed in isolated lab namespace (training gateway)"


@router.websocket("/ws/console/{machine_id}")
async def console(ws: WebSocket, machine_id: str) -> None:
    user = _user_from_ws(ws)
    if not user:
        await ws.close(code=4401)
        return
    db = SessionLocal()
    machine = db.query(Machine).filter((Machine.id == machine_id) | (Machine.public_id == machine_id)).first()
    if not machine or (user.role == Role.STUDENT and machine.owner_id != user.id):
        db.close()
        await ws.close(code=4403)
        return
    db.add(ConsoleSession(user_id=user.id, machine_id=machine.id))
    audit.record(db, user=user, action="console.open", resource=machine.public_id, machine=machine.name)
    db.commit()
    db.close()
    await ws.accept()
    await ws.send_text(
        json.dumps(
            {
                "type": "console",
                "protocol": "novnc-gateway",
                "machine": machine.name,
                "kind": machine.kind.value,
                "message": "Secure console gateway attached. Production uses noVNC/SPICE to KVM.",
            }
        )
    )
    try:
        while True:
            await ws.receive_text()
            await ws.send_text(json.dumps({"type": "ack", "ts": datetime.utcnow().isoformat()}))
    except WebSocketDisconnect:
        return
