from __future__ import annotations

import socket
from contextlib import asynccontextmanager
from pathlib import Path

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app import __version__
from app.api import (
    announcements,
    assignments,
    aukc,
    auth,
    catalog,
    commands,
    groups,
    labs,
    resources,
    users,
    ws,
)
from app.config import get_settings
from app.database import Base, SessionLocal, engine
from app.seed import seed
from app.services.schema import (
    migrate_group_memberships,
    migrate_network_schema,
    migrate_notifications,
    migrate_slash8_networks,
    migrate_user_groups,
)


@asynccontextmanager
async def lifespan(_app: FastAPI):
    settings = get_settings()
    Path(settings.storage_root).mkdir(parents=True, exist_ok=True)
    Base.metadata.create_all(bind=engine)
    migrate_network_schema(engine)
    migrate_user_groups(engine)
    migrate_group_memberships(engine)
    migrate_notifications(engine)
    db = SessionLocal()
    try:
        seed(db)
        migrate_slash8_networks(db)
    finally:
        db.close()
    yield


settings = get_settings()
app = FastAPI(
    title="University Cyber Range API",
    description=(
        "Private university cybersecurity virtual lab / cyber range. "
        "Container-first, VM-when-required. Students never receive host Docker or libvirt sockets."
    ),
    version=__version__,
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url="/redoc",
    openapi_url="/openapi.json",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origin_list,
    allow_origin_regex=settings.cors_origin_regex,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.middleware("http")
async def security_headers(request: Request, call_next):
    response = await call_next(request)
    response.headers["X-Content-Type-Options"] = "nosniff"
    response.headers["X-Frame-Options"] = "DENY"
    response.headers["Referrer-Policy"] = "same-origin"
    response.headers["X-Range-Plane"] = "management"
    return response


@app.exception_handler(ValueError)
async def value_error_handler(_request: Request, exc: ValueError):
    return JSONResponse({"detail": str(exc)}, status_code=400)


app.include_router(auth.router, prefix="/api")
app.include_router(users.router, prefix="/api")
app.include_router(groups.router, prefix="/api")
app.include_router(labs.router, prefix="/api")
app.include_router(catalog.router, prefix="/api")
app.include_router(assignments.router, prefix="/api")
app.include_router(resources.router, prefix="/api")
app.include_router(announcements.router, prefix="/api")
app.include_router(commands.router, prefix="/api")
app.include_router(aukc.router, prefix="/api")
app.include_router(ws.router)


def lan_ipv4s() -> list[str]:
    found: set[str] = set()
    public = (settings.public_host or "").strip()
    if public:
        found.add(public)
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.connect(("8.8.8.8", 80))
            found.add(sock.getsockname()[0])
    except OSError:
        pass
    try:
        for info in socket.getaddrinfo(socket.gethostname(), None, socket.AF_INET):
            found.add(info[4][0])
    except OSError:
        pass
    return sorted(ip for ip in found if not ip.startswith("127."))


@app.get("/api/health")
def health() -> dict:
    ips = lan_ipv4s()
    port = settings.public_ui_port
    return {
        "status": "ok",
        "version": __version__,
        "provider": settings.compute_provider,
        "lan_ips": ips,
        "login_urls": [f"http://{ip}:{port}/login" for ip in ips],
    }


@app.get("/api")
def root() -> dict:
    return {
        "name": settings.app_name,
        "version": __version__,
        "docs": "/docs",
        "principle": "container-first, VM-when-required",
    }
