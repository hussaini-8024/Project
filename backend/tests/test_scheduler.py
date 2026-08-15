from app.models import MachineKind, MachineTemplate, EnvironmentKind
from app.services.scheduler import recommend_kind


def test_container_first_for_linux_userspace() -> None:
    tmpl = MachineTemplate(
        name="Ubuntu",
        slug="ubuntu",
        environment=EnvironmentKind.CONTAINER,
        recommended_kind=MachineKind.CONTAINER,
        os_family="linux",
        image_ref="ubuntu",
        requires_kernel=False,
        requires_full_os=False,
    )
    kind, alts = recommend_kind(tmpl, MachineKind.VM)
    assert kind == MachineKind.CONTAINER
    assert alts


def test_full_vm_when_kernel_required() -> None:
    tmpl = MachineTemplate(
        name="Windows",
        slug="windows",
        environment=EnvironmentKind.VM,
        recommended_kind=MachineKind.VM,
        os_family="windows",
        image_ref="iso",
        requires_kernel=True,
        requires_full_os=True,
    )
    kind, _ = recommend_kind(tmpl, MachineKind.VM)
    assert kind == MachineKind.VM
