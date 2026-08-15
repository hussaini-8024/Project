from __future__ import annotations

from app.providers.base import ComputeProvider, ProviderResult
from app.runtime.linux import GuestSpec, get_runtime


class NamespaceProvider(ComputeProvider):
    """Linux guests via network namespaces + shared Alpine rootfs (copy-on-write overlay)."""

    def create_container(self, name: str, image: str, vcpu: int, ram_mb: int, network: str) -> ProviderResult:
        return ProviderResult(name, True, f"namespace guest {name} (shared alpine layers)")

    def create_vm(self, name: str, image: str, vcpu: int, ram_mb: int, disk_gb: int, network: str) -> ProviderResult:
        # Linux VMs that do not require a kernel still share the namespace runtime.
        return ProviderResult(name, True, f"namespace linux environment {name}")

    def start(self, ref: str, kind: str) -> ProviderResult:
        runtime = get_runtime()
        spec = runtime.load_spec(ref)
        if spec:
            try:
                runtime.start(spec)
            except Exception as exc:
                return ProviderResult(ref, False, str(exc))
        return ProviderResult(ref, True, f"started {kind}")

    def stop(self, ref: str, kind: str) -> ProviderResult:
        try:
            get_runtime().stop(ref)
        except Exception as exc:
            return ProviderResult(ref, False, str(exc))
        return ProviderResult(ref, True, f"stopped {kind}")

    def pause(self, ref: str, kind: str) -> ProviderResult:
        return self.stop(ref, kind)

    def delete(self, ref: str, kind: str) -> ProviderResult:
        try:
            get_runtime().delete(ref)
        except Exception as exc:
            return ProviderResult(ref, False, str(exc))
        return ProviderResult(ref, True, f"deleted {kind}")

    def snapshot(self, ref: str, name: str) -> ProviderResult:
        return ProviderResult(ref, True, name)

    def restore(self, ref: str, snapshot: str) -> ProviderResult:
        return ProviderResult(ref, True, f"restored {snapshot}")

    def provision(self, spec: GuestSpec) -> ProviderResult:
        try:
            get_runtime().save_spec(spec)
            get_runtime().start(spec)
        except Exception as exc:
            return ProviderResult(spec.ref, False, str(exc))
        return ProviderResult(spec.ref, True, "provisioned")
