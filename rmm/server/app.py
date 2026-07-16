"""
Legitimate RMM Server — disclosed remote management for company-owned PCs.

Live viewing ALWAYS shows a visible banner on the remote PC.
All admin actions are written to an audit log.
"""

from __future__ import annotations

import asyncio
import os
import platform
import secrets
import sys
import time
from pathlib import Path
from typing import Any

# Allow running from repo root or packaged exe
ROOT = Path(__file__).resolve().parent.parent
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from fastapi import (
    Depends,
    FastAPI,
    File,
    Header,
    HTTPException,
    Query,
    UploadFile,
    WebSocket,
    WebSocketDisconnect,
)
from fastapi.responses import FileResponse, HTMLResponse
from pydantic import BaseModel, Field
import uvicorn

from shared import config
from shared import protocol as proto
from server.database import Database

db = Database()
app = FastAPI(title="DiscloseRMM Server", version="1.0.0")

STATIC_DIR = Path(__file__).resolve().parent / "static"
PACKAGES_DIR = ROOT / "data" / "packages"
PACKAGES_DIR.mkdir(parents=True, exist_ok=True)

# agent_id -> WebSocket
agent_sockets: dict[str, WebSocket] = {}
# agent_id -> set of admin viewer websockets
live_viewers: dict[str, set[WebSocket]] = {}
# pending shell/install futures: job_id -> Future
pending_results: dict[str, asyncio.Future] = {}


def bootstrap() -> None:
    admin_user = os.environ.get("RMM_ADMIN_USER", config.DEFAULT_ADMIN_USER)
    admin_pass = os.environ.get("RMM_ADMIN_PASSWORD", config.DEFAULT_ADMIN_PASSWORD)
    enroll = os.environ.get("RMM_ENROLLMENT_TOKEN", config.DEFAULT_ENROLLMENT_TOKEN)
    db.ensure_admin(admin_user, admin_pass)
    # Always apply enrollment token from env when explicitly set; otherwise keep/create default
    if "RMM_ENROLLMENT_TOKEN" in os.environ or not db.get_setting("enrollment_token"):
        db.set_setting("enrollment_token", enroll)


bootstrap()


# ---------- Auth helpers ----------


def require_admin(authorization: str | None = Header(default=None)) -> dict[str, Any]:
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing session token")
    token = authorization.removeprefix("Bearer ").strip()
    admin = db.get_admin_by_session(token)
    if not admin:
        raise HTTPException(status_code=401, detail="Invalid or expired session")
    return admin


class LoginBody(BaseModel):
    username: str
    password: str


class GroupBody(BaseModel):
    name: str
    description: str = ""
    member_ids: list[str] = Field(default_factory=list)


class MembersBody(BaseModel):
    member_ids: list[str]


class PackageBody(BaseModel):
    name: str
    local_path: str
    args: str = ""


class DeployBody(BaseModel):
    package_id: int
    group_id: int | None = None
    agent_id: str | None = None


class ShellBody(BaseModel):
    agent_id: str
    command: str
    shell: str = "cmd"  # cmd | powershell | bash


class NetworkHostBody(BaseModel):
    label: str
    host: str


class EnrollTokenBody(BaseModel):
    token: str


# ---------- HTTP: auth & dashboard ----------


@app.get("/", response_class=HTMLResponse)
async def index() -> HTMLResponse:
    html_path = STATIC_DIR / "index.html"
    return HTMLResponse(html_path.read_text(encoding="utf-8"))


@app.post("/api/login")
async def login(body: LoginBody) -> dict[str, Any]:
    admin_id = db.verify_admin(body.username, body.password)
    if admin_id is None:
        raise HTTPException(status_code=401, detail="Invalid credentials")
    token = db.create_session(admin_id)
    db.audit(body.username, "login", detail="Administrator signed in")
    return {"token": token, "username": body.username}


@app.post("/api/logout")
async def logout(admin: dict = Depends(require_admin), authorization: str = Header()) -> dict[str, str]:
    token = authorization.removeprefix("Bearer ").strip()
    db.delete_session(token)
    db.audit(admin["username"], "logout")
    return {"ok": "true"}


@app.get("/api/me")
async def me(admin: dict = Depends(require_admin)) -> dict[str, Any]:
    return {"username": admin["username"]}


# ---------- Agents ----------


@app.get("/api/agents")
async def list_agents(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    db.mark_stale_agents_offline(config.AGENT_OFFLINE_AFTER_SECONDS)
    agents = db.list_agents()
    for a in agents:
        a["connected"] = a["id"] in agent_sockets
        a["live_viewers"] = len(live_viewers.get(a["id"], set()))
    return agents


@app.delete("/api/agents/{agent_id}")
async def remove_agent(agent_id: str, admin: dict = Depends(require_admin)) -> dict[str, str]:
    db.delete_agent(agent_id)
    db.audit(admin["username"], "remove_agent", target=agent_id)
    ws = agent_sockets.pop(agent_id, None)
    if ws:
        try:
            await ws.close()
        except Exception:
            pass
    return {"ok": "true"}


@app.get("/api/enrollment")
async def get_enrollment(admin: dict = Depends(require_admin)) -> dict[str, str]:
    return {
        "token": db.get_setting("enrollment_token", config.DEFAULT_ENROLLMENT_TOKEN),
        "hint": "Install the agent on a remote PC and start it with --server and --token.",
    }


@app.post("/api/enrollment")
async def set_enrollment(body: EnrollTokenBody, admin: dict = Depends(require_admin)) -> dict[str, str]:
    db.set_setting("enrollment_token", body.token)
    db.audit(admin["username"], "rotate_enrollment_token")
    return {"ok": "true"}


# ---------- Groups ----------


@app.get("/api/groups")
async def list_groups(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_groups()


@app.post("/api/groups")
async def create_group(body: GroupBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    gid = db.create_group(body.name, body.description)
    if body.member_ids:
        db.set_group_members(gid, body.member_ids)
    db.audit(admin["username"], "create_group", target=str(gid), detail=body.name)
    return {"id": gid}


@app.put("/api/groups/{group_id}/members")
async def update_members(
    group_id: int, body: MembersBody, admin: dict = Depends(require_admin)
) -> dict[str, str]:
    db.set_group_members(group_id, body.member_ids)
    db.audit(admin["username"], "update_group_members", target=str(group_id), detail=str(body.member_ids))
    return {"ok": "true"}


@app.delete("/api/groups/{group_id}")
async def delete_group(group_id: int, admin: dict = Depends(require_admin)) -> dict[str, str]:
    db.delete_group(group_id)
    db.audit(admin["username"], "delete_group", target=str(group_id))
    return {"ok": "true"}


# ---------- Software packages & deploy ----------


@app.get("/api/packages")
async def list_packages(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_packages()


@app.post("/api/packages")
async def register_package(body: PackageBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    path = Path(body.local_path)
    if not path.is_file():
        raise HTTPException(status_code=400, detail=f"File not found on server: {body.local_path}")
    # Copy into managed packages dir for controlled distribution
    dest = PACKAGES_DIR / f"{int(time.time())}_{path.name}"
    dest.write_bytes(path.read_bytes())
    pid = db.add_package(body.name, str(dest), body.args)
    db.audit(admin["username"], "register_package", target=str(pid), detail=body.name)
    return {"id": pid, "stored_path": str(dest)}


@app.post("/api/packages/upload")
async def upload_package(
    name: str,
    args: str = "",
    file: UploadFile = File(...),
    admin: dict = Depends(require_admin),
) -> dict[str, Any]:
    filename = Path(file.filename or "setup.exe").name
    dest = PACKAGES_DIR / f"{int(time.time())}_{filename}"
    dest.write_bytes(await file.read())
    pid = db.add_package(name or filename, str(dest), args)
    db.audit(admin["username"], "upload_package", target=str(pid), detail=filename)
    return {"id": pid, "stored_path": str(dest)}


@app.delete("/api/packages/{package_id}")
async def delete_package(package_id: int, admin: dict = Depends(require_admin)) -> dict[str, str]:
    pkg = db.get_package(package_id)
    if pkg:
        try:
            Path(pkg["local_path"]).unlink(missing_ok=True)
        except Exception:
            pass
    db.delete_package(package_id)
    db.audit(admin["username"], "delete_package", target=str(package_id))
    return {"ok": "true"}


@app.get("/api/packages/{package_id}/download")
async def download_package(
    package_id: int,
    token: str | None = Query(default=None),
    authorization: str | None = Header(default=None),
) -> FileResponse:
    """Agents download with ?token=<enrollment>; admins may use Bearer session."""
    allowed = False
    expected = db.get_setting("enrollment_token", config.DEFAULT_ENROLLMENT_TOKEN)
    if token and secrets.compare_digest(str(token), str(expected)):
        allowed = True
    elif authorization and authorization.startswith("Bearer "):
        if db.get_admin_by_session(authorization.removeprefix("Bearer ").strip()):
            allowed = True
    if not allowed:
        raise HTTPException(status_code=401, detail="Unauthorized package download")
    pkg = db.get_package(package_id)
    if not pkg:
        raise HTTPException(status_code=404, detail="Package not found")
    path = Path(pkg["local_path"])
    if not path.is_file():
        raise HTTPException(status_code=404, detail="Package file missing")
    return FileResponse(path, filename=path.name)


@app.post("/api/deploy")
async def deploy(body: DeployBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    package = db.get_package(body.package_id)
    if not package:
        raise HTTPException(status_code=404, detail="Package not found")
    targets: list[str] = []
    if body.agent_id:
        targets = [body.agent_id]
    elif body.group_id is not None:
        targets = db.get_group_member_ids(body.group_id)
    else:
        raise HTTPException(status_code=400, detail="Provide agent_id or group_id")
    if not targets:
        raise HTTPException(status_code=400, detail="No target agents")

    job_ids = []
    for aid in targets:
        job_id = secrets.token_hex(8)
        db.create_job(
            job_id,
            "install",
            target_agent_id=aid,
            target_group_id=body.group_id,
            package_id=body.package_id,
            created_by=admin["username"],
        )
        job_ids.append(job_id)
        ws = agent_sockets.get(aid)
        if ws:
            await ws.send_text(
                proto.encode(
                    proto.MSG_INSTALL,
                    {
                        "job_id": job_id,
                        "package_id": package["id"],
                        "name": package["name"],
                        "download_path": f"/api/packages/{package['id']}/download",
                        "args": package.get("args") or "",
                        "filename": Path(package["local_path"]).name,
                    },
                )
            )
            db.update_job(job_id, "sent")
        else:
            db.update_job(job_id, "failed", "Agent not connected")

    db.audit(
        admin["username"],
        "deploy_package",
        target=str(body.group_id or body.agent_id),
        detail=f"package={body.package_id} targets={targets}",
    )
    return {"job_ids": job_ids, "targets": targets}


# ---------- Remote shell ----------


@app.post("/api/shell")
async def remote_shell(body: ShellBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    if body.shell not in ("cmd", "powershell", "bash"):
        raise HTTPException(status_code=400, detail="shell must be cmd, powershell, or bash")
    ws = agent_sockets.get(body.agent_id)
    if not ws:
        raise HTTPException(status_code=400, detail="Agent not connected")
    job_id = secrets.token_hex(8)
    db.create_job(
        job_id,
        "shell",
        target_agent_id=body.agent_id,
        command=body.command,
        created_by=admin["username"],
    )
    loop = asyncio.get_running_loop()
    fut: asyncio.Future = loop.create_future()
    pending_results[job_id] = fut
    await ws.send_text(
        proto.encode(
            proto.MSG_SHELL,
            {"job_id": job_id, "command": body.command, "shell": body.shell},
        )
    )
    db.audit(
        admin["username"],
        "remote_shell",
        target=body.agent_id,
        detail=f"{body.shell}: {body.command}",
    )
    try:
        result = await asyncio.wait_for(fut, timeout=120)
    except asyncio.TimeoutError:
        db.update_job(job_id, "timeout")
        pending_results.pop(job_id, None)
        raise HTTPException(status_code=504, detail="Shell command timed out")
    db.update_job(job_id, "completed", result.get("output", "")[:8000])
    return {"job_id": job_id, **result}


# ---------- Jobs & audit ----------


@app.get("/api/jobs")
async def list_jobs(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_jobs()


@app.get("/api/audit")
async def list_audit(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_audit()


# ---------- Network monitoring ----------


@app.get("/api/network")
async def list_network(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_network_hosts()


@app.post("/api/network")
async def add_network(body: NetworkHostBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    hid = db.add_network_host(body.label, body.host)
    db.audit(admin["username"], "add_network_host", target=body.host, detail=body.label)
    return {"id": hid}


@app.delete("/api/network/{host_id}")
async def delete_network(host_id: int, admin: dict = Depends(require_admin)) -> dict[str, str]:
    db.delete_network_host(host_id)
    db.audit(admin["username"], "delete_network_host", target=str(host_id))
    return {"ok": "true"}


async def _ping_host(host: str) -> tuple[str, float | None]:
    """ICMP-ish reachability via system ping. Returns (live|dead, latency_ms)."""
    system = platform.system().lower()
    if system == "windows":
        cmd = ["ping", "-n", "1", "-w", "1000", host]
    else:
        cmd = ["ping", "-c", "1", "-W", "1", host]
    start = time.perf_counter()
    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL,
        )
        code = await asyncio.wait_for(proc.wait(), timeout=5)
        latency = (time.perf_counter() - start) * 1000
        if code == 0:
            return "live", round(latency, 1)
        return "dead", None
    except Exception:
        return "dead", None


@app.post("/api/network/scan")
async def scan_network(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    hosts = db.list_network_hosts()
    results = []
    for h in hosts:
        status, latency = await _ping_host(h["host"])
        db.update_network_host_status(h["id"], status, latency)
        results.append({**h, "last_status": status, "last_latency_ms": latency, "last_checked": time.time()})
    db.audit(admin["username"], "network_scan", detail=f"{len(hosts)} hosts")
    return results


# ---------- Agent WebSocket ----------


@app.websocket("/ws/agent")
async def agent_ws(websocket: WebSocket) -> None:
    await websocket.accept()
    agent_id: str | None = None
    try:
        raw = await websocket.receive_text()
        msg_type, payload = proto.decode(raw)
        if msg_type != proto.MSG_REGISTER:
            await websocket.send_text(proto.encode(proto.MSG_ERROR, {"error": "Expected register"}))
            await websocket.close()
            return
        token = payload.get("enrollment_token", "")
        expected = db.get_setting("enrollment_token", config.DEFAULT_ENROLLMENT_TOKEN)
        if not secrets.compare_digest(str(token), str(expected)):
            await websocket.send_text(proto.encode(proto.MSG_ERROR, {"error": "Invalid enrollment token"}))
            await websocket.close()
            return
        agent_id = str(payload.get("agent_id") or secrets.token_hex(8))
        db.upsert_agent(
            agent_id,
            hostname=str(payload.get("hostname", "unknown")),
            username=str(payload.get("username", "")),
            os_info=str(payload.get("os_info", "")),
            ip_address=str(payload.get("ip_address", websocket.client.host if websocket.client else "")),
        )
        agent_sockets[agent_id] = websocket
        await websocket.send_text(proto.encode(proto.MSG_ACK, {"agent_id": agent_id}))
        db.audit("system", "agent_connected", target=agent_id)

        while True:
            raw = await websocket.receive_text()
            msg_type, payload = proto.decode(raw)
            if msg_type == proto.MSG_HEARTBEAT:
                db.touch_agent(agent_id, online=True)
            elif msg_type == proto.MSG_SHELL_RESULT:
                job_id = payload.get("job_id")
                fut = pending_results.pop(job_id, None) if job_id else None
                if fut and not fut.done():
                    fut.set_result(payload)
            elif msg_type == proto.MSG_INSTALL_RESULT:
                job_id = payload.get("job_id")
                if job_id:
                    status = "completed" if payload.get("ok") else "failed"
                    db.update_job(job_id, status, str(payload.get("output", ""))[:8000])
            elif msg_type == proto.MSG_FRAME:
                # Relay JPEG frame to admin viewers; agent already shows visible banner
                viewers = live_viewers.get(agent_id, set())
                dead = []
                frame_msg = proto.encode(proto.MSG_FRAME, payload)
                for viewer in list(viewers):
                    try:
                        await viewer.send_text(frame_msg)
                    except Exception:
                        dead.append(viewer)
                for d in dead:
                    viewers.discard(d)
            elif msg_type == proto.MSG_STATUS:
                db.touch_agent(agent_id, online=True)
    except WebSocketDisconnect:
        pass
    except Exception:
        pass
    finally:
        if agent_id:
            if agent_sockets.get(agent_id) is websocket:
                agent_sockets.pop(agent_id, None)
            db.touch_agent(agent_id, online=False)
            db.audit("system", "agent_disconnected", target=agent_id)
            # Stop live for remaining viewers
            for viewer in list(live_viewers.get(agent_id, set())):
                try:
                    await viewer.send_text(proto.encode(proto.MSG_STOP_LIVE, {"reason": "agent_disconnected"}))
                except Exception:
                    pass
            live_viewers.pop(agent_id, None)


# ---------- Admin live view WebSocket ----------


@app.websocket("/ws/live/{agent_id}")
async def live_ws(websocket: WebSocket, agent_id: str) -> None:
    # Auth via query token
    token = websocket.query_params.get("token", "")
    admin = db.get_admin_by_session(token)
    if not admin:
        await websocket.close(code=4401)
        return
    await websocket.accept()
    agent_ws_conn = agent_sockets.get(agent_id)
    if not agent_ws_conn:
        await websocket.send_text(proto.encode(proto.MSG_ERROR, {"error": "Agent offline"}))
        await websocket.close()
        return

    live_viewers.setdefault(agent_id, set()).add(websocket)
    db.audit(admin["username"], "start_live_view", target=agent_id, detail="Visible banner required on agent")
    try:
        await agent_ws_conn.send_text(
            proto.encode(
                proto.MSG_START_LIVE,
                {
                    "fps": config.LIVE_FPS,
                    "quality": config.LIVE_JPEG_QUALITY,
                    "max_width": config.LIVE_MAX_WIDTH,
                    "banner_text": config.MONITOR_BANNER_TEXT,
                    "banner_subtext": config.MONITOR_BANNER_SUBTEXT,
                },
            )
        )
        while True:
            # Keep connection open; admin may send stop
            raw = await websocket.receive_text()
            msg_type, _ = proto.decode(raw)
            if msg_type == proto.MSG_STOP_LIVE:
                break
    except WebSocketDisconnect:
        pass
    finally:
        viewers = live_viewers.get(agent_id, set())
        viewers.discard(websocket)
        db.audit(admin["username"], "stop_live_view", target=agent_id)
        if not viewers:
            live_viewers.pop(agent_id, None)
            agent_ws_conn = agent_sockets.get(agent_id)
            if agent_ws_conn:
                try:
                    await agent_ws_conn.send_text(proto.encode(proto.MSG_STOP_LIVE, {}))
                except Exception:
                    pass


def main() -> None:
    host = os.environ.get("RMM_HOST", config.DEFAULT_SERVER_HOST)
    port = int(os.environ.get("RMM_PORT", config.DEFAULT_SERVER_PORT))
    print(f"DiscloseRMM server listening on http://{host}:{port}")
    print("Live sessions ALWAYS display a visible banner on remote PCs.")
    print(f"Default admin: {os.environ.get('RMM_ADMIN_USER', config.DEFAULT_ADMIN_USER)}")
    uvicorn.run(app, host=host, port=port, log_level="info")


if __name__ == "__main__":
    main()
