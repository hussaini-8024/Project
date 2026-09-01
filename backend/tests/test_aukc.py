from __future__ import annotations

from io import BytesIO


def _make_pdf(pages: list[str]) -> bytes:
    """Render a tiny multi-page PDF with the given page texts using reportlab."""
    from reportlab.lib.pagesizes import letter
    from reportlab.pdfgen import canvas

    buf = BytesIO()
    c = canvas.Canvas(buf, pagesize=letter)
    for text in pages:
        y = 750
        for line in text.split("\n"):
            c.drawString(72, y, line)
            y -= 18
        c.showPage()
    c.save()
    return buf.getvalue()


def _upload(api, headers, pages, title="Test Book", filename="book.pdf"):
    pdf = _make_pdf(pages)
    return api.post(
        "/api/aukc/books",
        headers=headers,
        files={"file": (filename, pdf, "application/pdf")},
        data={"title": title},
    )


def test_aukc_search_requires_auth(api):
    res = api.post("/api/aukc/search", json={"query": "nmap"})
    assert res.status_code == 401


def test_empty_library_returns_graceful_message(api):
    student = api.auth_headers("student")
    res = api.post("/api/aukc/search", headers=student, json={"query": "nmap"})
    assert res.status_code == 200
    body = res.json()
    assert body["results"] == []
    assert "No books in the library" in body["message"]


def test_admin_can_upload_student_cannot(api):
    admin = api.auth_headers("admin")
    res = _upload(
        api,
        admin,
        ["Nmap is a network scanner used for port scanning and host discovery."],
        title="Network Security",
    )
    assert res.status_code == 200, res.text
    body = res.json()
    assert body["title"] == "Network Security"
    assert body["page_count"] >= 1
    assert body["size_bytes"] > 0

    student = api.auth_headers("student")
    res = _upload(api, student, ["should not be allowed"])
    assert res.status_code == 403


def test_non_pdf_upload_rejected(api):
    admin = api.auth_headers("admin")
    res = api.post(
        "/api/aukc/books",
        headers=admin,
        files={"file": ("notes.txt", b"just plain text", "text/plain")},
        data={"title": "Bad"},
    )
    assert res.status_code == 400


def test_search_returns_uploaded_book_page(api):
    admin = api.auth_headers("admin")
    _upload(
        api,
        admin,
        [
            "Introduction chapter with unrelated filler content.",
            "Port scanning with nmap lets you enumerate open services quickly.",
        ],
        title="Recon Handbook",
    )

    student = api.auth_headers("student")
    res = api.post("/api/aukc/search", headers=student, json={"query": "nmap port scanning"})
    assert res.status_code == 200
    body = res.json()
    assert body["results"], body
    top = body["results"][0]
    assert top["book_title"] == "Recon Handbook"
    assert top["page_number"] == 2
    assert "<mark>" in top["snippet"]
    assert top["score"] > 0


def test_no_match_returns_graceful_message(api):
    admin = api.auth_headers("admin")
    _upload(api, admin, ["Content about firewalls and defense in depth."])
    student = api.auth_headers("student")
    res = api.post(
        "/api/aukc/search", headers=student, json={"query": "quantumcryptographyxyz"}
    )
    assert res.status_code == 200
    body = res.json()
    assert body["results"] == []
    assert "No relevant passages" in body["message"]


def test_multi_book_search_returns_different_books(api):
    admin = api.auth_headers("admin")
    _upload(api, admin, ["Hydra performs brute force attacks against ssh logins."], title="Book A")
    _upload(api, admin, ["An ssh server should enforce key based authentication."], title="Book B")

    student = api.auth_headers("student")
    res = api.post("/api/aukc/search", headers=student, json={"query": "ssh", "limit": 10})
    assert res.status_code == 200
    titles = {r["book_title"] for r in res.json()["results"]}
    assert {"Book A", "Book B"} <= titles


def test_list_and_delete_book(api):
    admin = api.auth_headers("admin")
    up = _upload(api, admin, ["Deletable content about tcpdump packet capture."], title="Temp")
    book_id = up.json()["id"]

    student = api.auth_headers("student")
    listed = api.get("/api/aukc/books", headers=student)
    assert listed.status_code == 200
    assert any(b["id"] == book_id for b in listed.json())

    # Students cannot delete.
    res = api.delete(f"/api/aukc/books/{book_id}", headers=student)
    assert res.status_code == 403

    # Admin can delete.
    res = api.delete(f"/api/aukc/books/{book_id}", headers=admin)
    assert res.status_code == 200

    after = api.get("/api/aukc/books", headers=admin)
    assert all(b["id"] != book_id for b in after.json())
