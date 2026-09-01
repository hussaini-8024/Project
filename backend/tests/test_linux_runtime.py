from app.runtime.linux import GuestSpec, LinuxRuntime, _hostify


def test_hostify() -> None:
    assert _hostify("Kali Training") == "kali-training"
    assert _hostify("DVWA Target") == "dvwa-target"


def test_spec_roundtrip(tmp_path, monkeypatch) -> None:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path / "storage"))
    from app.config import get_settings

    get_settings.cache_clear()
    runtime = LinuxRuntime()
    spec = GuestSpec(
        ref="MCH-TEST1",
        hostname="kali-training",
        ipv4="10.0.0.2",
        cidr="10.0.0.0/8",
        lab_key="ns-test",
        bridge="brtest1",
        peers=[("10.0.0.3", "dvwa-target")],
    )
    runtime.save_spec(spec)
    loaded = runtime.load_spec("MCH-TEST1")
    assert loaded is not None
    assert loaded.ipv4 == "10.0.0.2"
    assert loaded.cidr == "10.0.0.0/8"
    get_settings.cache_clear()
