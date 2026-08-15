from __future__ import annotations

import asyncio
import json
from datetime import datetime

from fastapi import APIRouter, WebSocket, WebSocketDisconnect
from jose import JWTError

from sqlalchemy.orm import joinedload

from app.database import SessionLocal
from app.models import ConsoleSession, LabNetwork, Machine, NetworkInterface, Role, StudentLab, TerminalSession, User
from app.runtime.linux import get_runtime
from app.runtime.pty import PtySession
from app.security import decode_token
from app.services import audit
from app.services.capacity import occupancy, sample_host
from app.services.guest import provision_guest

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
        return db.query(User).options(joinedload(User.lab)).filter(User.id == payload.get("sub")).first()
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
    machine = (
        db.query(Machine)
        .options(
            joinedload(Machine.lab).joinedload(StudentLab.network).joinedload(LabNetwork.interfaces).joinedload(NetworkInterface.machine),
            joinedload(Machine.interfaces),
        )
        .filter((Machine.id == machine_id) | (Machine.public_id == machine_id))
        .first()
    )
    if not machine or (user.role == Role.STUDENT and machine.owner_id != user.id):
        db.close()
        await ws.close(code=4403)
        return
    name = machine.name
    public_id = machine.public_id
    ref = machine.provider_ref or machine.public_id
    lab_public = machine.lab.public_id if machine.lab else ""
    try:
        provision_guest(machine)
        argv = get_runtime().shell_command(ref)
    except Exception as exc:
        db.close()
        await ws.accept()
        await ws.send_text(f"\r\nUnable to attach Linux guest: {exc}\r\n")
        await ws.close()
        return
    sess = TerminalSession(user_id=user.id, machine_id=machine.id)
    db.add(sess)
    audit.record(
        db,
        user=user,
        action="terminal.open",
        resource=public_id,
        machine=name,
        lab_id=lab_public,
    )
    db.commit()
    db.close()
    await ws.accept()
    pty = PtySession(argv)
    pty.resize(120, 32)
    stop = asyncio.Event()

    async def on_output(text: str) -> None:
        try:
            await ws.send_text(text)
        except Exception:
            stop.set()

    loop = asyncio.get_running_loop()

    def _ready() -> None:
        data = pty.read()
        if data:
            loop.create_task(on_output(data.decode("utf-8", errors="replace")))

    loop.add_reader(pty.master_fd, _ready)
    try:
        while True:
            message = await ws.receive()
            if message.get("type") == "websocket.disconnect":
                break
            text = message.get("text")
            if text is None:
                continue
            if text.startswith("{") and '"type":"resize"' in text:
                try:
                    payload = json.loads(text)
                    pty.resize(int(payload.get("cols", 120)), int(payload.get("rows", 32)))
                except Exception:
                    pass
                continue
            pty.write(text.encode("utf-8", errors="replace"))
    except WebSocketDisconnect:
        pass
    finally:
        loop.remove_reader(pty.master_fd)
        pty.close()


@router.websocket("/ws/console/{machine_id}")
async def console(ws: WebSocket, machine_id: str) -> None:
    user = _user_from_ws(ws)
    if not user:
        await ws.close(code=4401)
        return
    db = SessionLocal()
    machine = (
        db.query(Machine)
        .options(joinedload(Machine.lab))
        .filter((Machine.id == machine_id) | (Machine.public_id == machine_id))
        .first()
    )
    if not machine or (user.role == Role.STUDENT and machine.owner_id != user.id):
        db.close()
        await ws.close(code=4403)
        return
    name = machine.name
    kind = machine.kind.value
    public_id = machine.public_id
    lab_public = machine.lab.public_id if machine.lab else ""
    db.add(ConsoleSession(user_id=user.id, machine_id=machine.id))
    audit.record(
        db,
        user=user,
        action="console.open",
        resource=public_id,
        machine=name,
        lab_id=lab_public,
    )
    db.commit()
    db.close()
    await ws.accept()
    await ws.send_text(
        json.dumps(
            {
                "type": "console",
                "protocol": "novnc-gateway",
                "machine": name,
                "kind": kind,
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
