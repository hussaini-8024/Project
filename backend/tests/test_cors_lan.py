import re

from app.config import Settings


def test_lan_origin_regex_allows_private_ips() -> None:
    pattern = Settings(cors_allow_lan=True).cors_origin_regex
    assert pattern is not None
    rx = re.compile(pattern)
    assert rx.fullmatch("http://192.168.1.20:5173")
    assert rx.fullmatch("http://10.0.0.5")
    assert rx.fullmatch("http://172.30.0.2:5173")
    assert rx.fullmatch("http://range.local")
    assert rx.fullmatch("http://127.0.0.1:5173")
    assert not rx.fullmatch("https://evil.example")


def test_lan_origin_regex_can_be_disabled() -> None:
    assert Settings(cors_allow_lan=False).cors_origin_regex is None
