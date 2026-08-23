from __future__ import annotations


def test_instructor_announcement_fans_out_to_group_students(api):
    headers = api.auth_headers("instructor")
    res = api.post(
        "/api/announcements",
        headers=headers,
        json={
            "title": "Midterm lab this week",
            "body": "Complete the web security lab.",
            "scope": "group",
            "group_id": api.ids["group"],
        },
    )
    assert res.status_code == 201, res.text
    data = res.json()
    # Only one student is a member of the group -> exactly one delivery.
    assert data["delivered"] == 1

    # The group student sees an unread notification.
    student = api.auth_headers("student")
    notif = api.get("/api/notifications", headers=student).json()
    assert notif["unread"] >= 1
    assert any(n["title"] == "Midterm lab this week" for n in notif["items"])

    # A student outside the group receives nothing from a group-scoped announcement.
    outsider = api.auth_headers("jordan")
    notif2 = api.get("/api/notifications", headers=outsider).json()
    assert notif2["unread"] == 0


def test_students_cannot_post_announcements(api):
    student = api.auth_headers("student")
    res = api.post(
        "/api/announcements",
        headers=student,
        json={"title": "hi", "body": "x", "scope": "group", "group_id": api.ids["group"]},
    )
    assert res.status_code == 403


def test_instructor_cannot_announce_to_all(api):
    headers = api.auth_headers("instructor")
    res = api.post(
        "/api/announcements",
        headers=headers,
        json={"title": "everyone", "body": "x", "scope": "all"},
    )
    assert res.status_code == 403


def test_admin_announce_to_all_reaches_every_student(api):
    headers = api.auth_headers("admin")
    res = api.post(
        "/api/announcements",
        headers=headers,
        json={"title": "Maintenance window", "body": "Range reboot tonight.", "scope": "all"},
    )
    assert res.status_code == 201, res.text
    # Two students exist in the seed.
    assert res.json()["delivered"] == 2
    for who in ("student", "jordan"):
        notif = api.get("/api/notifications", headers=api.auth_headers(who)).json()
        assert any(n["title"] == "Maintenance window" for n in notif["items"])


def test_mark_read_and_read_all(api):
    admin = api.auth_headers("admin")
    api.post(
        "/api/announcements",
        headers=admin,
        json={"title": "Notice one", "body": "b", "scope": "all"},
    )
    student = api.auth_headers("student")
    notif = api.get("/api/notifications", headers=student).json()
    assert notif["unread"] >= 1
    first_id = notif["items"][0]["id"]

    r = api.post(f"/api/notifications/{first_id}/read", headers=student)
    assert r.status_code == 200
    after = api.get("/api/notifications", headers=student).json()
    assert after["unread"] == notif["unread"] - 1

    api.post("/api/notifications/read-all", headers=student)
    final = api.get("/api/notifications", headers=student).json()
    assert final["unread"] == 0


def test_cannot_mark_other_users_notification(api):
    admin = api.auth_headers("admin")
    api.post("/api/announcements", headers=admin, json={"title": "Notice", "body": "b", "scope": "all"})
    student = api.auth_headers("student")
    nid = api.get("/api/notifications", headers=student).json()["items"][0]["id"]
    # jordan cannot mark student's notification.
    res = api.post(f"/api/notifications/{nid}/read", headers=api.auth_headers("jordan"))
    assert res.status_code == 404


def test_new_assignment_notifies_students(api):
    headers = api.auth_headers("instructor")
    res = api.post(
        "/api/assignments",
        headers=headers,
        json={"title": "SQLi Lab", "objective": "Find the injection.", "required_templates": []},
    )
    assert res.status_code == 200, res.text
    student = api.auth_headers("student")
    notif = api.get("/api/notifications", headers=student).json()
    assert any(n["kind"] == "assignment" and "SQLi Lab" in n["title"] for n in notif["items"])
