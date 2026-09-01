from pathlib import Path

from app.config import Settings, get_settings
from app.main import lan_ipv4s


ROOT = Path(__file__).resolve().parents[2]


def test_nginx_listens_on_port_80_and_fallbacks() -> None:
    conf = (ROOT / "scripts" / "nginx-lan.conf").read_text()
    assert "listen 80" in conf
    assert "listen 8080" in conf
    assert "listen 18080" in conf
    assert "root /var/www/cyberrange" in conf
    assert "try_files $uri $uri/ /index.html" in conf
    assert "proxy_pass http://127.0.0.1:18000/api/" in conf
    assert "127.0.0.1:5173" not in conf


def test_ports_env_pins_campus_server() -> None:
    text = (ROOT / "scripts" / "ports.env").read_text()
    assert "172.26.1.3" in text
    assert "CYBERRANGE_UI_PORT=80" in text
    assert "18000" in text


def test_install_scripts_exist() -> None:
    assert (ROOT / "scripts" / "install-ubuntu-deps.sh").is_file()
    assert (ROOT / "scripts" / "run-ubuntu-lan.sh").is_file()
    assert (ROOT / "scripts" / "deploy-ubuntu-server.sh").is_file()
    assert (ROOT / "scripts" / "cyberrange-boot.sh").is_file()
    text = (ROOT / "scripts" / "run-ubuntu-lan.sh").read_text()
    assert "172.26.1.3" in text
    assert "127.0.0.1/login" in text


def test_systemd_unit_restarts_api() -> None:
    unit = (ROOT / "scripts" / "systemd" / "cyberrange-api.service").read_text()
    assert "Restart=always" in unit
    assert "--port 18000" in unit
    assert "PUBLIC_HOST=172.26.1.3" in unit
    assert "PUBLIC_UI_PORT=80" in unit


def test_health_advertises_only_campus_ip(api, monkeypatch) -> None:
    monkeypatch.setenv("PUBLIC_HOST", "172.26.1.3")
    monkeypatch.setenv("PUBLIC_UI_PORT", "80")
    monkeypatch.setenv("PUBLIC_HOST_ONLY", "true")
    get_settings.cache_clear()
    res = api.get("/api/health")
    assert res.status_code == 200
    body = res.json()
    assert body["server_ip"] == "172.26.1.3"
    assert body["lan_ips"] == ["172.26.1.3"]
    assert body["login_urls"] == ["http://172.26.1.3/login"]
    assert body["local_login_url"] == "http://127.0.0.1/login"
    assert not any("172.30." in u for u in body["login_urls"])


def test_default_public_host_is_campus_server() -> None:
    s = Settings(_env_file=None)
    assert s.public_host == "172.26.1.3"
    assert s.public_ui_port == 80
    assert s.public_host_only is True


def test_lan_ipv4s_exclusive_campus_ip(monkeypatch) -> None:
    monkeypatch.setenv("PUBLIC_HOST", "172.26.1.3")
    monkeypatch.setenv("PUBLIC_HOST_ONLY", "true")
    get_settings.cache_clear()
    assert lan_ipv4s() == ["172.26.1.3"]


def test_environment_exposes_port_80() -> None:
    env = (ROOT / ".cursor" / "environment.json").read_text()
    assert "cyberrange-boot.sh" in env
    assert '"port": 80' in env
