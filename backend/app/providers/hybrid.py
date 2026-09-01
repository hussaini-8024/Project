from __future__ import annotations

from app.providers.base import ComputeProvider, ProviderResult
from app.providers.docker_provider import DockerProvider
from app.providers.libvirt_provider import LibvirtProvider


class HybridProvider(ComputeProvider):
    """Container-first: Docker for containers, libvirt for full VMs."""

    def __init__(self) -> None:
        self.docker = DockerProvider()
        self.kvm = LibvirtProvider()

    def create_container(self, name: str, image: str, vcpu: int, ram_mb: int, network: str) -> ProviderResult:
        return self.docker.create_container(name, image, vcpu, ram_mb, network)

    def create_vm(self, name: str, image: str, vcpu: int, ram_mb: int, disk_gb: int, network: str) -> ProviderResult:
        return self.kvm.create_vm(name, image, vcpu, ram_mb, disk_gb, network)

    def start(self, ref: str, kind: str) -> ProviderResult:
        return self.docker.start(ref, kind) if kind == "container" else self.kvm.start(ref, kind)

    def stop(self, ref: str, kind: str) -> ProviderResult:
        return self.docker.stop(ref, kind) if kind == "container" else self.kvm.stop(ref, kind)

    def pause(self, ref: str, kind: str) -> ProviderResult:
        return self.docker.pause(ref, kind) if kind == "container" else self.kvm.pause(ref, kind)

    def delete(self, ref: str, kind: str) -> ProviderResult:
        return self.docker.delete(ref, kind) if kind == "container" else self.kvm.delete(ref, kind)

    def snapshot(self, ref: str, name: str) -> ProviderResult:
        return self.docker.snapshot(ref, name)

    def restore(self, ref: str, snapshot: str) -> ProviderResult:
        return self.kvm.restore(ref, snapshot)
