from __future__ import annotations

from app.providers.base import ComputeProvider, ProviderResult
from app.providers.mock import MockProvider


class DockerProvider(ComputeProvider):
    """Production Docker adapter. Falls back to mock if the socket is unavailable."""

    def __init__(self) -> None:
        self._fallback = MockProvider()
        self._client = None
        try:
            import docker

            self._client = docker.from_env()
            self._client.ping()
        except Exception:
            self._client = None

    def create_container(self, name: str, image: str, vcpu: int, ram_mb: int, network: str) -> ProviderResult:
        if not self._client:
            return self._fallback.create_container(name, image, vcpu, ram_mb, network)
        nano = max(1, vcpu) * 1_000_000_000
        mem = max(64, ram_mb) * 1024 * 1024
        container = self._client.containers.create(
            image=image,
            name=name,
            nano_cpus=nano,
            mem_limit=mem,
            network=network or None,
            labels={"cyberrange": "student", "name": name},
            detach=True,
        )
        return ProviderResult(container.id[:12], True, "created")

    def create_vm(self, name: str, image: str, vcpu: int, ram_mb: int, disk_gb: int, network: str) -> ProviderResult:
        return ProviderResult("", False, "Docker provider cannot create full VMs")

    def start(self, ref: str, kind: str) -> ProviderResult:
        if not self._client:
            return self._fallback.start(ref, kind)
        self._client.containers.get(ref).start()
        return ProviderResult(ref, True, "started")

    def stop(self, ref: str, kind: str) -> ProviderResult:
        if not self._client:
            return self._fallback.stop(ref, kind)
        self._client.containers.get(ref).stop(timeout=10)
        return ProviderResult(ref, True, "stopped")

    def pause(self, ref: str, kind: str) -> ProviderResult:
        if not self._client:
            return self._fallback.pause(ref, kind)
        self._client.containers.get(ref).pause()
        return ProviderResult(ref, True, "paused")

    def delete(self, ref: str, kind: str) -> ProviderResult:
        if not self._client:
            return self._fallback.delete(ref, kind)
        c = self._client.containers.get(ref)
        try:
            c.stop(timeout=5)
        except Exception:
            pass
        c.remove(v=False)
        return ProviderResult(ref, True, "deleted")

    def snapshot(self, ref: str, name: str) -> ProviderResult:
        if not self._client:
            return self._fallback.snapshot(ref, name)
        img = self._client.containers.get(ref).commit(repository=f"cyberrange/{name}")
        return ProviderResult(img.id[:12], True, name)

    def restore(self, ref: str, snapshot: str) -> ProviderResult:
        return self._fallback.restore(ref, snapshot)
