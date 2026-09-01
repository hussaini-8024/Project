from __future__ import annotations

from app.data.commands import COMMANDS, search


def test_dataset_is_substantial():
    assert len(COMMANDS) >= 40
    for entry in COMMANDS:
        assert entry["tool"] and entry["command"] and entry["description"]
        assert isinstance(entry.get("tags", []), list)


def test_search_filters_by_tool():
    results = search("nmap")
    assert results
    assert all("nmap" in " ".join([r["tool"], r["command"], r["description"], " ".join(r["tags"])]).lower() for r in results)


def test_search_is_case_insensitive_and_multiterm():
    assert search("NMAP") == search("nmap")
    # multi-term AND search
    results = search("sql injection")
    assert results
    assert any(r["tool"] == "sqlmap" for r in results)


def test_search_empty_returns_all():
    assert len(search("")) == len(COMMANDS)


def test_commands_endpoint_requires_auth_and_filters(api):
    # Unauthenticated -> 401
    assert api.get("/api/commands?q=nmap").status_code == 401

    student = api.auth_headers("student")
    res = api.get("/api/commands", headers=student, params={"q": "nmap"})
    assert res.status_code == 200
    body = res.json()
    assert body["count"] >= 1
    assert body["total"] == len(COMMANDS)
    assert all("nmap" in r["command"].lower() or r["tool"] == "nmap" for r in body["results"])
    assert "recon" in body["categories"]
