"""API route package."""

from fastapi import APIRouter

from aulabs.api import auth_routes, users_routes, storage_routes, sessions_routes, system_routes

api_router = APIRouter()
api_router.include_router(auth_routes.router, tags=["auth"])
api_router.include_router(users_routes.router, prefix="/users", tags=["users"])
api_router.include_router(storage_routes.router, prefix="/storage", tags=["storage"])
api_router.include_router(sessions_routes.router, prefix="/sessions", tags=["sessions"])
api_router.include_router(system_routes.router, prefix="/system", tags=["system"])
