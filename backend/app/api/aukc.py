from __future__ import annotations

import logging

import httpx
from fastapi import APIRouter, Depends
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.config import get_settings
from app.data.commands import search as command_search
from app.database import get_db
from app.deps import current_user
from app.models import User

router = APIRouter(tags=["aukc"])
logger = logging.getLogger("aukc")

SYSTEM_PROMPT = (
    "You are the AU Kamra AI Agent, a cybersecurity study assistant for a private "
    "university cyber range. Scope your answers to cybersecurity tools, commands, "
    "penetration-testing techniques, defensive security, and study help. Give concise, "
    "accurate, practical guidance with example commands where helpful. Remind students "
    "that all activity must stay inside their authorized, isolated lab. Refuse to help "
    "with attacks against systems the user is not explicitly authorized to test."
)


class AukcSearchIn(BaseModel):
    prompt: str = Field(min_length=1, max_length=4000)


def _offline_answer(prompt: str) -> str:
    """A helpful, self-contained answer when the AI backend is unavailable."""
    hits = command_search(prompt, limit=5)
    lines = [
        "AU Kamra AI Agent is running in offline mode (no live AI backend reachable), "
        "so here is guidance from the built-in cyber-range knowledge base.",
    ]
    if hits:
        lines.append("")
        lines.append("Relevant commands from the offline reference:")
        for h in hits:
            lines.append(f"• {h['tool']}: `{h['command']}` — {h['description']}")
    else:
        lines.append("")
        lines.append(
            "Try the Command Search menu for tools like nmap, hydra, sqlmap, gobuster, "
            "john, hashcat, netcat, tcpdump, and openssl. Always operate only inside your "
            "isolated lab network."
        )
    lines.append("")
    lines.append(
        "For full AI answers, configure an OpenAI API key (OPENAI_API_KEY) on the server "
        "and ensure outbound network access is permitted."
    )
    return "\n".join(lines)


@router.post("/aukc/search")
def aukc_search(
    body: AukcSearchIn,
    _: User = Depends(current_user),
    _db: Session = Depends(get_db),
) -> dict:
    """AU Kamra AI Agent. Proxies to an OpenAI-compatible chat API.

    Always returns HTTP 200. When no key is configured or the outbound request
    fails, returns ``configured: false`` with an offline fallback answer so the UI
    degrades gracefully instead of erroring.
    """
    settings = get_settings()
    key = settings.aukc_key
    if not key:
        return {
            "configured": False,
            "model": settings.aukc_ai_model,
            "answer": _offline_answer(body.prompt),
            "error": "No AI API key configured (set OPENAI_API_KEY or AUKC_AI_API_KEY).",
        }

    url = f"{settings.aukc_ai_base_url.rstrip('/')}/chat/completions"
    payload = {
        "model": settings.aukc_ai_model,
        "messages": [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": body.prompt},
        ],
        "temperature": 0.2,
    }
    headers = {"Authorization": f"Bearer {key}", "Content-Type": "application/json"}
    try:
        resp = httpx.post(
            url, json=payload, headers=headers, timeout=settings.aukc_ai_timeout_seconds
        )
        resp.raise_for_status()
        data = resp.json()
        answer = (
            data.get("choices", [{}])[0].get("message", {}).get("content", "").strip()
        )
        if not answer:
            raise ValueError("Empty response from AI backend")
        return {"configured": True, "model": settings.aukc_ai_model, "answer": answer}
    except Exception as exc:  # noqa: BLE001 - degrade gracefully on any outbound failure
        logger.warning("AUKC AI request failed: %s", exc)
        return {
            "configured": False,
            "model": settings.aukc_ai_model,
            "answer": _offline_answer(body.prompt),
            "error": f"AI request failed: {str(exc)[:200]}",
        }
