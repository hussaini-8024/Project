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
from server.remote_push import push_agent_windows
from server.agent_packages import (
    find_agent_binary,
    generate_all_packages,
    list_platform_status,
    resolve_download,
    save_uploaded_binary,
)
from agent.discovery import listen_udp_beacons, probe_host
from agent.install_service import hash_uninstall_password

db = Database()
app = FastAPI(title="AU-Kamra IT Experts Remote Manager", version="1.0.0")

STATIC_DIR = Path(__file__).resolve().parent / "static"
PACKAGES_DIR = ROOT / "data" / "packages"
BIN_DIR = ROOT / "bin"
PACKAGES_DIR.mkdir(parents=True, exist_ok=True)
BIN_DIR.mkdir(parents=True, exist_ok=True)

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
    if "RMM_ENROLLMENT_TOKEN" in os.environ or not db.get_setting("enrollment_token"):
        db.set_setting("enrollment_token", enroll)
    if not db.get_setting("uninstall_password_hash"):
        plain = os.environ.get("RMM_UNINSTALL_PASSWORD", config.DEFAULT_UNINSTALL_PASSWORD)
        db.set_setting("uninstall_password_hash", hash_uninstall_password(plain))
        db.set_setting("uninstall_password_hint", "Set in Admin → Uninstall password")


bootstrap()


def uninstall_hash() -> str:
    return db.get_setting("uninstall_password_hash", hash_uninstall_password(config.DEFAULT_UNINSTALL_PASSWORD))


async def push_uninstall_config_to_agents() -> None:
    msg = proto.encode(proto.MSG_CONFIG, {"uninstall_password_hash": uninstall_hash()})
    for ws in list(agent_sockets.values()):
        try:
            await ws.send_text(msg)
        except Exception:
            pass


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


class UninstallPasswordBody(BaseModel):
    password: str


class ManualPcBody(BaseModel):
    ip_address: str
    username: str
    password: str


class DiscoverBody(BaseModel):
    subnet_cidr: str = ""  # e.g. 192.168.1.0/24 — optional active scan


class RemoteUninstallBody(BaseModel):
    password: str


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


@app.get("/api/public/agent-bootstrap")
async def agent_bootstrap() -> dict[str, str]:
    """Public bootstrap for agent installers (hash only — not the password)."""
    return {
        "uninstall_password_hash": uninstall_hash(),
        "enrollment_required": "true",
    }


@app.get("/api/uninstall-password")
async def get_uninstall_password_meta(admin: dict = Depends(require_admin)) -> dict[str, str]:
    return {
        "configured": "true" if db.get_setting("uninstall_password_hash") else "false",
        "hint": db.get_setting("uninstall_password_hint", "Managed by administrator"),
        "default_note": "Agents cannot be uninstalled without this password.",
    }


@app.post("/api/uninstall-password")
async def set_uninstall_password(
    body: UninstallPasswordBody, admin: dict = Depends(require_admin)
) -> dict[str, str]:
    if len(body.password) < 6:
        raise HTTPException(status_code=400, detail="Password must be at least 6 characters")
    db.set_setting("uninstall_password_hash", hash_uninstall_password(body.password))
    db.set_setting("uninstall_password_hint", "Updated by administrator")
    await push_uninstall_config_to_agents()
    db.audit(admin["username"], "set_uninstall_password", detail="Pushed hash to connected agents")
    return {"ok": "true"}


def _allow_agent_download(token: str | None, authorization: str | None) -> bool:
    expected = db.get_setting("enrollment_token", config.DEFAULT_ENROLLMENT_TOKEN)
    if token and secrets.compare_digest(str(token), str(expected)):
        return True
    if authorization and authorization.startswith("Bearer "):
        if db.get_admin_by_session(authorization.removeprefix("Bearer ").strip()):
            return True
    return False


@app.get("/api/agent-binary")
async def agent_binary_status(admin: dict = Depends(require_admin)) -> dict[str, Any]:
    """Multi-platform agent availability for the admin panel."""
    platforms = list_platform_status()
    any_native = any(p["native_available"] for p in platforms)
    return {
        "available": any_native or any(p["kit_available"] for p in platforms),
        "platforms": platforms,
        "hint": "Click Generate to build packages, or Upload a native agent per OS. Then download Windows / macOS / Linux.",
        "download_windows": "/api/agent-binary/download/windows",
        "download_macos": "/api/agent-binary/download/macos",
        "download_linux": "/api/agent-binary/download/linux",
    }


@app.get("/api/agent-binary/download")
async def download_agent_binary_legacy(
    token: str | None = Query(default=None),
    authorization: str | None = Header(default=None),
    platform: str = Query(default="windows"),
) -> FileResponse:
    """Legacy download URL — defaults to Windows, accepts ?platform=."""
    return await download_agent_for_platform(platform, "auto", token, authorization)


@app.get("/api/agent-binary/download/{platform}")
async def download_agent_for_platform(
    platform: str,
    prefer: str = Query(default="auto"),
    token: str | None = Query(default=None),
    authorization: str | None = Header(default=None),
) -> FileResponse:
    if not _allow_agent_download(token, authorization):
        raise HTTPException(status_code=401, detail="Unauthorized")
    plat = platform.lower().strip()
    if plat in ("mac", "osx", "darwin"):
        plat = "macos"
    if plat in ("win", "win32", "win64"):
        plat = "windows"
    try:
        path, filename = resolve_download(plat, prefer=prefer)
    except ValueError:
        raise HTTPException(status_code=400, detail="platform must be windows, macos, or linux")
    except FileNotFoundError as exc:
        raise HTTPException(status_code=404, detail=str(exc))
    if not path.is_file():
        raise HTTPException(
            status_code=404,
            detail=f"No agent package for {plat}. Use Generate or Upload in the admin panel.",
        )
    return FileResponse(path, filename=filename, media_type="application/octet-stream")


@app.post("/api/agent-binary/generate")
async def generate_agent_packages(admin: dict = Depends(require_admin)) -> dict[str, Any]:
    """Generate Windows/macOS/Linux download kits; build native agent for this host OS if possible."""
    result = await asyncio.to_thread(generate_all_packages, True)
    db.audit(admin["username"], "generate_agent_packages", detail=str(result.get("built_native")))
    return {"ok": True, **result, "platforms": list_platform_status()}


@app.post("/api/agent-binary/upload/{platform}")
async def upload_agent_binary(
    platform: str,
    file: UploadFile = File(...),
    admin: dict = Depends(require_admin),
) -> dict[str, Any]:
    """Admin uploads a pre-built native agent for windows / macos / linux."""
    plat = platform.lower().strip()
    if plat in ("mac", "osx", "darwin"):
        plat = "macos"
    if plat not in ("windows", "macos", "linux"):
        raise HTTPException(status_code=400, detail="platform must be windows, macos, or linux")
    data = await file.read()
    if len(data) < 64:
        raise HTTPException(status_code=400, detail="File too small")
    path = save_uploaded_binary(plat, file.filename or "agent", data)
    # Refresh kit so zip includes the native binary
    from server.agent_packages import build_kit_zip

    kit = build_kit_zip(plat)
    db.audit(admin["username"], "upload_agent_binary", target=plat, detail=str(path))
    return {"ok": True, "platform": plat, "path": str(path), "kit": str(kit), "size": len(data)}


@app.get("/api/discovered")
async def list_discovered(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_discovered()


@app.post("/api/discover")
async def discover_network(body: DiscoverBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    """Listen for agent UDP beacons and optionally probe a /24 subnet."""
    beacons = await asyncio.to_thread(listen_udp_beacons, 4.0)
    for b in beacons:
        db.upsert_discovered(b)

    probed: list[dict[str, Any]] = []
    cidr = (body.subnet_cidr or "").strip()
    if cidr.endswith("/24"):
        base = cidr.split("/")[0].rsplit(".", 1)[0]

        async def probe_one(i: int) -> None:
            ip = f"{base}.{i}"
            data = await asyncio.to_thread(probe_host, ip)
            if data:
                db.upsert_discovered(data)
                probed.append(data)

        await asyncio.gather(*[probe_one(i) for i in range(1, 255)])

    # Also refresh from currently connected agents
    for aid, _ws in agent_sockets.items():
        agent = db.get_agent(aid)
        if agent:
            db.upsert_discovered(
                {
                    "agent_id": agent["id"],
                    "hostname": agent["hostname"],
                    "username": agent.get("username"),
                    "os_info": agent.get("os_info"),
                    "ip": agent.get("ip_address"),
                    "installed": True,
                }
            )

    db.audit(admin["username"], "discover_network", detail=cidr or "udp-beacons")
    return {"discovered": db.list_discovered(), "beacon_count": len(beacons), "probed_count": len(probed)}


@app.get("/api/pending-pcs")
async def list_pending(admin: dict = Depends(require_admin)) -> list[dict[str, Any]]:
    return db.list_pending_pcs()


@app.post("/api/pending-pcs")
async def add_manual_pc(body: ManualPcBody, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    """Manually add a remote PC by IP + Windows admin credentials and push-install the agent."""
    ip = body.ip_address.strip()
    if not ip:
        raise HTTPException(status_code=400, detail="IP address required")
    pending_id = db.add_pending_pc(ip, body.username.strip(), body.password)
    db.audit(admin["username"], "add_manual_pc", target=ip, detail=body.username)

    agent_exe = find_agent_binary()
    enroll = db.get_setting("enrollment_token", config.DEFAULT_ENROLLMENT_TOKEN)
    # Prefer a reachable public URL for agents
    public = os.environ.get("RMM_PUBLIC_URL", "").rstrip("/")
    server_url = public or f"http://{ _guess_server_ip()}:{os.environ.get('RMM_PORT', config.DEFAULT_SERVER_PORT)}"

    if not agent_exe:
        db.update_pending_pc(
            pending_id,
            "needs_binary",
            "Agent .exe missing on server. Build DiscloseRMM-Agent.exe and place in bin/ or dist/.",
        )
        return {
            "id": pending_id,
            "status": "needs_binary",
            "detail": "Place DiscloseRMM-Agent.exe on the server, then retry push.",
        }

    ok, detail = await asyncio.to_thread(
        push_agent_windows,
        ip,
        body.username.strip(),
        body.password,
        server_url,
        enroll,
        agent_exe,
    )
    db.update_pending_pc(pending_id, "installed" if ok else "failed", detail)
    db.audit(admin["username"], "push_install", target=ip, detail=detail)
    return {"id": pending_id, "status": "installed" if ok else "failed", "detail": detail}


@app.post("/api/pending-pcs/{pending_id}/retry")
async def retry_pending(pending_id: int, admin: dict = Depends(require_admin)) -> dict[str, Any]:
    row = db.get_pending_pc(pending_id)
    if not row:
        raise HTTPException(status_code=404, detail="Not found")
    body = ManualPcBody(ip_address=row["ip_address"], username=row["username"], password=row["password"])
    # Reuse add logic by calling push directly
    agent_exe = find_agent_binary()
    enroll = db.get_setting("enrollment_token", config.DEFAULT_ENROLLMENT_TOKEN)
    public = os.environ.get("RMM_PUBLIC_URL", "").rstrip("/")
    server_url = public or f"http://{_guess_server_ip()}:{os.environ.get('RMM_PORT', config.DEFAULT_SERVER_PORT)}"
    if not agent_exe:
        db.update_pending_pc(pending_id, "needs_binary", "Agent .exe missing on server")
        raise HTTPException(status_code=400, detail="Agent .exe missing on server")
    ok, detail = await asyncio.to_thread(
        push_agent_windows,
        body.ip_address,
        body.username,
        body.password,
        server_url,
        enroll,
        agent_exe,
    )
    db.update_pending_pc(pending_id, "installed" if ok else "failed", detail)
    db.audit(admin["username"], "retry_push_install", target=body.ip_address, detail=detail)
    return {"status": "installed" if ok else "failed", "detail": detail}


@app.delete("/api/pending-pcs/{pending_id}")
async def delete_pending(pending_id: int, admin: dict = Depends(require_admin)) -> dict[str, str]:
    db.delete_pending_pc(pending_id)
    return {"ok": "true"}


@app.post("/api/agents/{agent_id}/remote-uninstall")
async def remote_uninstall(
    agent_id: str, body: RemoteUninstallBody, admin: dict = Depends(require_admin)
) -> dict[str, Any]:
    expected = uninstall_hash()
    if hash_uninstall_password(body.password) != expected:
        raise HTTPException(status_code=403, detail="Incorrect uninstall password")
    ws = agent_sockets.get(agent_id)
    if not ws:
        raise HTTPException(status_code=400, detail="Agent not connected")
    await ws.send_text(proto.encode(proto.MSG_REMOTE_UNINSTALL, {"password": body.password}))
    db.audit(admin["username"], "remote_uninstall", target=agent_id)
    return {"ok": "true", "detail": "Uninstall command sent to agent"}


def _guess_server_ip() -> str:
    try:
        s = __import__("socket").socket(__import__("socket").AF_INET, __import__("socket").SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"


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
        db.upsert_discovered(
            {
                "agent_id": agent_id,
                "hostname": payload.get("hostname"),
                "username": payload.get("username"),
                "os_info": payload.get("os_info"),
                "ip": payload.get("ip_address"),
                "installed": payload.get("installed", True),
            }
        )
        await websocket.send_text(
            proto.encode(
                proto.MSG_ACK,
                {"agent_id": agent_id, "uninstall_password_hash": uninstall_hash()},
            )
        )
        db.audit("system", "agent_connected", target=agent_id)

        while True:
            raw = await websocket.receive_text()
            msg_type, payload = proto.decode(raw)
            if msg_type == proto.MSG_HEARTBEAT:
                db.touch_agent(agent_id, online=True)
                if payload.get("ip") or payload.get("hostname"):
                    db.upsert_discovered(
                        {
                            "agent_id": agent_id,
                            "hostname": payload.get("hostname"),
                            "username": payload.get("username"),
                            "os_info": payload.get("os_info"),
                            "ip": payload.get("ip") or payload.get("ip_address"),
                            "installed": payload.get("installed", True),
                        }
                    )
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
    print("AU-Kamra IT Experts Remote Manager")
    print(f"Server listening on http://{host}:{port}")
    print("Live sessions ALWAYS display a visible banner on remote PCs.")
    print(f"Default admin: {os.environ.get('RMM_ADMIN_USER', config.DEFAULT_ADMIN_USER)}")
    uvicorn.run(app, host=host, port=port, log_level="info")


if __name__ == "__main__":
    main()
