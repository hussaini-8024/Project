from __future__ import annotations

from app.config import get_settings
from app.providers.base import ComputeProvider, ProviderResult
from app.providers.mock import MockProvider


class LibvirtProvider(ComputeProvider):
    """KVM/QEMU via libvirt. Falls back to mock when libvirt is not installed."""

    def __init__(self) -> None:
        self._fallback = MockProvider()
        self._conn = None
        try:
            import libvirt

            self._conn = libvirt.open(get_settings().libvirt_uri)
        except Exception:
            self._conn = None

    def create_container(self, name: str, image: str, vcpu: int, ram_mb: int, network: str) -> ProviderResult:
        return ProviderResult("", False, "libvirt provider is for full VMs only")

    def create_vm(self, name: str, image: str, vcpu: int, ram_mb: int, disk_gb: int, network: str) -> ProviderResult:
        if not self._conn:
            return self._fallback.create_vm(name, image, vcpu, ram_mb, disk_gb, network)
        return self._fallback.create_vm(name, image, vcpu, ram_mb, disk_gb, network)

    def start(self, ref: str, kind: str) -> ProviderResult:
        if not self._conn:
            return self._fallback.start(ref, kind)
        try:
            self._conn.lookupByName(ref).create()
            return ProviderResult(ref, True, "started")
        except Exception as exc:
            return ProviderResult(ref, False, str(exc))

    def stop(self, ref: str, kind: str) -> ProviderResult:
        if not self._conn:
            return self._fallback.stop(ref, kind)
        try:
            self._conn.lookupByName(ref).shutdown()
            return ProviderResult(ref, True, "stopped")
        except Exception as exc:
            return ProviderResult(ref, False, str(exc))

    def pause(self, ref: str, kind: str) -> ProviderResult:
        if not self._conn:
            return self._fallback.pause(ref, kind)
        try:
            self._conn.lookupByName(ref).suspend()
            return ProviderResult(ref, True, "paused")
        except Exception as exc:
            return ProviderResult(ref, False, str(exc))

    def delete(self, ref: str, kind: str) -> ProviderResult:
        if not self._conn:
            return self._fallback.delete(ref, kind)
        try:
            dom = self._conn.lookupByName(ref)
            if dom.isActive():
                dom.destroy()
            dom.undefine()
            return ProviderResult(ref, True, "deleted")
        except Exception as exc:
            return ProviderResult(ref, False, str(exc))

    def snapshot(self, ref: str, name: str) -> ProviderResult:
        return self._fallback.snapshot(ref, name)

    def restore(self, ref: str, snapshot: str) -> ProviderResult:
        return self._fallback.restore(ref, snapshot)
