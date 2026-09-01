from __future__ import annotations

import secrets

from app.providers.base import ComputeProvider, ProviderResult


class MockProvider(ComputeProvider):
    """In-process provider for development and UI preview without KVM/Docker."""

    def _ref(self, prefix: str) -> str:
        return f"{prefix}-{secrets.token_hex(6)}"

    def create_container(self, name: str, image: str, vcpu: int, ram_mb: int, network: str) -> ProviderResult:
        return ProviderResult(self._ref("ctr"), True, f"mock container {name} from shared image {image}")

    def create_vm(self, name: str, image: str, vcpu: int, ram_mb: int, disk_gb: int, network: str) -> ProviderResult:
        return ProviderResult(self._ref("vm"), True, f"mock thin-provisioned VM {name}")

    def start(self, ref: str, kind: str) -> ProviderResult:
        return ProviderResult(ref, True, f"started {kind}")

    def stop(self, ref: str, kind: str) -> ProviderResult:
        return ProviderResult(ref, True, f"stopped {kind}")

    def pause(self, ref: str, kind: str) -> ProviderResult:
        return ProviderResult(ref, True, f"paused {kind}")

    def delete(self, ref: str, kind: str) -> ProviderResult:
        return ProviderResult(ref, True, f"deleted {kind}")

    def snapshot(self, ref: str, name: str) -> ProviderResult:
        return ProviderResult(f"snap-{secrets.token_hex(4)}", True, name)

    def restore(self, ref: str, snapshot: str) -> ProviderResult:
        return ProviderResult(ref, True, f"restored {snapshot}")
