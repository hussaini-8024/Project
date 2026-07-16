"""Message types and helpers shared by server and agent."""

from __future__ import annotations

import json
from typing import Any

PROTOCOL_VERSION = 1

# Agent -> Server
MSG_REGISTER = "register"
MSG_HEARTBEAT = "heartbeat"
MSG_SHELL_RESULT = "shell_result"
MSG_INSTALL_RESULT = "install_result"
MSG_FRAME = "frame"
MSG_PONG = "pong"
MSG_STATUS = "status"

# Server -> Agent
MSG_SHELL = "shell"
MSG_INSTALL = "install"
MSG_START_LIVE = "start_live"
MSG_STOP_LIVE = "stop_live"
MSG_PING = "ping"
MSG_ACK = "ack"
MSG_ERROR = "error"


def encode(msg_type: str, payload: dict[str, Any] | None = None) -> str:
    return json.dumps({"v": PROTOCOL_VERSION, "type": msg_type, "payload": payload or {}})


def decode(raw: str) -> tuple[str, dict[str, Any]]:
    data = json.loads(raw)
    return data.get("type", ""), data.get("payload") or {}
