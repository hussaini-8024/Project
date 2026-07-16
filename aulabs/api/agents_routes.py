"""Agent registration and heartbeat API."""

from __future__ import annotations

import json
import secrets
from typing import Any

from fastapi import APIRouter, Depends, Header, HTTPException, Request
from pydantic import BaseModel, Field

from aulabs.api.deps import require_permission
from aulabs.database import get_db, utcnow

router = APIRouter()


class AgentPayload(BaseModel):
    agent_id: str = Field(min_length=8)
    agent_name: str = "agent"
    hostname: str = ""
    platform: dict[str, Any] = Field(default_factory=dict)
    cpu_percent: float = 0
    cpu_count: int = 1
    memory: dict[str, Any] = Field(default_factory=dict)
    disk: dict[str, Any] = Field(default_factory=dict)
    work_dir: str = ""
    version: str = ""
    reported_at: str = ""


def ensure_agents_table() -> None:
    db = get_db()
    with db.connect() as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS agents (
                agent_id TEXT PRIMARY KEY,
                agent_name TEXT NOT NULL,
                hostname TEXT NOT NULL DEFAULT '',
                token TEXT NOT NULL,
                platform_json TEXT NOT NULL DEFAULT '{}',
                metrics_json TEXT NOT NULL DEFAULT '{}',
                work_dir TEXT NOT NULL DEFAULT '',
                version TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'online',
                pending_commands TEXT NOT NULL DEFAULT '[]',
                created_at TEXT NOT NULL,
                last_seen TEXT NOT NULL
            );
            """
        )


def _public_agent(row: dict[str, Any]) -> dict[str, Any]:
    try:
        platform = json.loads(row.get("platform_json") or "{}")
    except json.JSONDecodeError:
        platform = {}
    try:
        metrics = json.loads(row.get("metrics_json") or "{}")
    except json.JSONDecodeError:
        metrics = {}
    return {
        "agent_id": row["agent_id"],
        "agent_name": row["agent_name"],
        "hostname": row.get("hostname") or "",
        "platform": platform,
        "metrics": metrics,
        "work_dir": row.get("work_dir") or "",
        "version": row.get("version") or "",
        "status": row.get("status") or "unknown",
        "created_at": row.get("created_at"),
        "last_seen": row.get("last_seen"),
    }


_ensure_agents_table = ensure_agents_table


@router.post("/register")
async def register_agent(body: AgentPayload):
    ensure_agents_table()
    db = get_db()
    existing = db.fetchone("SELECT * FROM agents WHERE agent_id = ?", (body.agent_id,))
    now = utcnow()
    metrics = {
        "cpu_percent": body.cpu_percent,
        "cpu_count": body.cpu_count,
        "memory": body.memory,
        "disk": body.disk,
        "reported_at": body.reported_at or now,
    }
    if existing:
        db.execute(
            """
            UPDATE agents SET agent_name=?, hostname=?, platform_json=?, metrics_json=?,
            work_dir=?, version=?, status='online', last_seen=? WHERE agent_id=?
            """,
            (
                body.agent_name,
                body.hostname,
                json.dumps(body.platform),
                json.dumps(metrics),
                body.work_dir,
                body.version,
                now,
                body.agent_id,
            ),
        )
        token = existing["token"]
    else:
        token = secrets.token_hex(24)
        db.execute(
            """
            INSERT INTO agents
            (agent_id, agent_name, hostname, token, platform_json, metrics_json,
             work_dir, version, status, pending_commands, created_at, last_seen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'online', '[]', ?, ?)
            """,
            (
                body.agent_id,
                body.agent_name,
                body.hostname,
                token,
                json.dumps(body.platform),
                json.dumps(metrics),
                body.work_dir,
                body.version,
                now,
                now,
            ),
        )
        db.audit("agent", "agent.register", body.agent_id, body.agent_name)
    return {"status": "ok", "agent_id": body.agent_id, "agent_token": token}


@router.post("/heartbeat")
async def heartbeat(
    body: AgentPayload,
    x_agent_token: str | None = Header(default=None),
):
    _ensure_agents_table()
    db = get_db()
    row = db.fetchone("SELECT * FROM agents WHERE agent_id = ?", (body.agent_id,))
    if not row:
        # Auto-register on first heartbeat
        return await register_agent(body)
    if x_agent_token and row["token"] and x_agent_token != row["token"]:
        raise HTTPException(status_code=401, detail="Invalid agent token")

    now = utcnow()
    metrics = {
        "cpu_percent": body.cpu_percent,
        "cpu_count": body.cpu_count,
        "memory": body.memory,
        "disk": body.disk,
        "reported_at": body.reported_at or now,
    }
    try:
        commands = json.loads(row.get("pending_commands") or "[]")
    except json.JSONDecodeError:
        commands = []

    db.execute(
        """
        UPDATE agents SET agent_name=?, hostname=?, platform_json=?, metrics_json=?,
        work_dir=?, version=?, status='online', pending_commands='[]', last_seen=?
        WHERE agent_id=?
        """,
        (
            body.agent_name,
            body.hostname,
            json.dumps(body.platform),
            json.dumps(metrics),
            body.work_dir,
            body.version,
            now,
            body.agent_id,
        ),
    )
    return {"status": "ok", "commands": commands}


@router.get("")
async def list_agents(user: dict = Depends(require_permission("system.manage"))):
    _ensure_agents_table()
    rows = get_db().fetchall("SELECT * FROM agents ORDER BY last_seen DESC")
    return {"agents": [_public_agent(r) for r in rows]}


@router.post("/{agent_id}/command")
async def queue_command(
    agent_id: str,
    request: Request,
    user: dict = Depends(require_permission("system.manage")),
):
    _ensure_agents_table()
    body = await request.json()
    db = get_db()
    row = db.fetchone("SELECT * FROM agents WHERE agent_id = ?", (agent_id,))
    if not row:
        raise HTTPException(status_code=404, detail="Agent not found")
    try:
        commands = json.loads(row.get("pending_commands") or "[]")
    except json.JSONDecodeError:
        commands = []
    commands.append(body)
    db.execute(
        "UPDATE agents SET pending_commands=? WHERE agent_id=?",
        (json.dumps(commands), agent_id),
    )
    db.audit(user["username"], "agent.command", agent_id, json.dumps(body)[:200])
    return {"ok": True, "queued": len(commands)}
