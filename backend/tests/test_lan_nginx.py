from pathlib import Path

from app.config import Settings
from app.main import lan_ipv4s


ROOT = Path(__file__).resolve().parents[2]


def test_nginx_lan_serves_built_ui_not_vite() -> None:
    conf = (ROOT / "scripts" / "nginx-lan.conf").read_text()
    assert "listen 80" in conf
    assert "listen 8080" in conf
    assert "root /var/www/cyberrange" in conf
    assert "try_files $uri $uri/ /index.html" in conf
    assert "proxy_pass http://127.0.0.1:8000/api/" in conf
    assert "proxy_pass http://127.0.0.1:8000/ws/" in conf
    assert "127.0.0.1:5173" not in conf


def test_install_scripts_exist() -> None:
    assert (ROOT / "scripts" / "install-ubuntu-deps.sh").is_file()
    assert (ROOT / "scripts" / "run-ubuntu-lan.sh").is_file()
    text = (ROOT / "scripts" / "run-ubuntu-lan.sh").read_text()
    assert "install-ubuntu-deps.sh" in text
    assert "Unhandled" in text or "5173" in text


def test_health_login_urls_use_port_80(api) -> None:
    res = api.get("/api/health")
    assert res.status_code == 200
    body = res.json()
    assert body["status"] == "ok"
    assert body["login_urls"]
    assert any(url.endswith("/login") for url in body["login_urls"])
    assert any(":5173" not in url for url in body["login_urls"])


def test_default_public_ui_is_port_80() -> None:
    s = Settings()
    assert s.public_ui_port == 80
    assert s.public_host == "172.26.1.3"


def test_lan_ipv4s_includes_configured_host() -> None:
    ips = lan_ipv4s()
    assert "172.26.1.3" in ips
