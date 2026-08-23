from __future__ import annotations


def test_aukc_requires_auth(api):
    res = api.post("/api/aukc/search", json={"prompt": "how do I scan ports?"})
    assert res.status_code == 401


def test_aukc_returns_configured_false_without_key(api, monkeypatch):
    from app.config import get_settings

    settings = get_settings()
    monkeypatch.setattr(settings, "openai_api_key", "", raising=False)
    monkeypatch.setattr(settings, "aukc_ai_api_key", "", raising=False)

    student = api.auth_headers("student")
    res = api.post(
        "/api/aukc/search", headers=student, json={"prompt": "how do I scan ports with nmap?"}
    )
    # Always 200 for graceful UI degradation.
    assert res.status_code == 200
    body = res.json()
    assert body["configured"] is False
    assert body["answer"]
    assert "error" in body
    # Offline answer should surface relevant reference material.
    assert "nmap" in body["answer"].lower()


def test_aukc_configured_true_with_mocked_backend(api, monkeypatch):
    from app.config import get_settings

    settings = get_settings()
    monkeypatch.setattr(settings, "openai_api_key", "sk-test-key", raising=False)

    class _Resp:
        def raise_for_status(self):
            return None

        def json(self):
            return {"choices": [{"message": {"content": "Use nmap -sV to fingerprint services."}}]}

    def _fake_post(url, **kwargs):
        assert "chat/completions" in url
        return _Resp()

    monkeypatch.setattr("app.api.aukc.httpx.post", _fake_post)

    student = api.auth_headers("student")
    res = api.post("/api/aukc/search", headers=student, json={"prompt": "fingerprint services"})
    assert res.status_code == 200
    body = res.json()
    assert body["configured"] is True
    assert "nmap" in body["answer"].lower()


def test_aukc_degrades_when_backend_errors(api, monkeypatch):
    from app.config import get_settings

    settings = get_settings()
    monkeypatch.setattr(settings, "openai_api_key", "sk-test-key", raising=False)

    def _boom(url, **kwargs):
        raise RuntimeError("egress blocked")

    monkeypatch.setattr("app.api.aukc.httpx.post", _boom)

    student = api.auth_headers("student")
    res = api.post("/api/aukc/search", headers=student, json={"prompt": "hello"})
    assert res.status_code == 200
    body = res.json()
    assert body["configured"] is False
    assert "egress blocked" in body["error"]
