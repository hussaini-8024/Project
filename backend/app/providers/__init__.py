from app.config import get_settings
from app.providers.base import ComputeProvider
from app.providers.mock import MockProvider


def get_provider() -> ComputeProvider:
    settings = get_settings()
    if settings.compute_provider == "docker":
        from app.providers.docker_provider import DockerProvider

        return DockerProvider()
    if settings.compute_provider == "libvirt":
        from app.providers.libvirt_provider import LibvirtProvider

        return LibvirtProvider()
    if settings.compute_provider == "hybrid":
        from app.providers.hybrid import HybridProvider

        return HybridProvider()
    return MockProvider()
