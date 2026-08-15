from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass


@dataclass
class ProviderResult:
    ref: str
    ok: bool
    message: str = ""


class ComputeProvider(ABC):
    """Workload-plane adapter. Students never talk to this directly."""

    @abstractmethod
    def create_container(self, name: str, image: str, vcpu: int, ram_mb: int, network: str) -> ProviderResult: ...

    @abstractmethod
    def create_vm(self, name: str, image: str, vcpu: int, ram_mb: int, disk_gb: int, network: str) -> ProviderResult: ...

    @abstractmethod
    def start(self, ref: str, kind: str) -> ProviderResult: ...

    @abstractmethod
    def stop(self, ref: str, kind: str) -> ProviderResult: ...

    @abstractmethod
    def pause(self, ref: str, kind: str) -> ProviderResult: ...

    @abstractmethod
    def delete(self, ref: str, kind: str) -> ProviderResult: ...

    @abstractmethod
    def snapshot(self, ref: str, name: str) -> ProviderResult: ...

    @abstractmethod
    def restore(self, ref: str, snapshot: str) -> ProviderResult: ...
