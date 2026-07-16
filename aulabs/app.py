"""FastAPI application — AU Labs IT Management panel."""

from __future__ import annotations

from pathlib import Path

from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from starlette.middleware.sessions import SessionMiddleware

from aulabs import __app_name__, __version__
from aulabs.api import api_router
from aulabs.api.agents_routes import ensure_agents_table
from aulabs.config import get_settings
from aulabs.services.users import UserService

WEB_DIR = Path(__file__).parent / "web"
templates = Jinja2Templates(directory=str(WEB_DIR / "templates"))


def create_app() -> FastAPI:
    settings = get_settings()
    app = FastAPI(
        title=__app_name__,
        version=__version__,
        docs_url="/api/docs",
        redoc_url=None,
    )
    app.add_middleware(
        SessionMiddleware,
        secret_key=settings.secret_key,
        session_cookie=settings.session_cookie,
        max_age=settings.session_ttl_hours * 3600,
        same_site="lax",
        https_only=False,
    )

    static_dir = WEB_DIR / "static"
    static_dir.mkdir(parents=True, exist_ok=True)
    app.mount("/static", StaticFiles(directory=str(static_dir)), name="static")
    app.include_router(api_router, prefix="/api")

    @app.on_event("startup")
    def _startup() -> None:
        settings.ensure_paths()
        UserService().ensure_admin()
        ensure_agents_table()

    @app.get("/", response_class=HTMLResponse)
    async def index(request: Request):
        user = request.session.get("user")
        if not user:
            return RedirectResponse("/login", status_code=302)
        return templates.TemplateResponse(
            "dashboard.html",
            {
                "request": request,
                "app_name": settings.app_name,
                "version": __version__,
                "user": user,
            },
        )

    @app.get("/login", response_class=HTMLResponse)
    async def login_page(request: Request):
        if request.session.get("user"):
            return RedirectResponse("/", status_code=302)
        return templates.TemplateResponse(
            "login.html",
            {
                "request": request,
                "app_name": settings.app_name,
                "version": __version__,
            },
        )

    @app.get("/health")
    async def health():
        return {"status": "ok", "app": __app_name__, "version": __version__}

    return app


app = create_app()
