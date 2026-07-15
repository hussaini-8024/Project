"""Unit tests for parsers, database, generator, auth, inventory, and API."""

from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from au_kamra_loan_cards.auth import hash_password, role_allowed, verify_password
from au_kamra_loan_cards.backup import backup_zip, export_json, import_json, restore_zip
from au_kamra_loan_cards.database import Database
from au_kamra_loan_cards.generator import render_html, write_html, write_pdf
from au_kamra_loan_cards.parsers import parse_file, parse_html
from au_kamra_loan_cards.server import create_app


SAMPLES = Path(__file__).resolve().parent.parent / "au_kamra_loan_cards" / "samples"


class AuthUnitTests(unittest.TestCase):
    def test_password_hash(self):
        stored = hash_password("admin123")
        self.assertTrue(verify_password("admin123", stored))
        self.assertFalse(verify_password("wrong", stored))

    def test_roles(self):
        self.assertTrue(role_allowed("administrator", "users_manage"))
        self.assertTrue(role_allowed("allocation_officer", "inventory_allocate"))
        self.assertFalse(role_allowed("viewer", "loan_upload"))


class ParserTests(unittest.TestCase):
    def test_parse_structured_html(self):
        data = parse_file(SAMPLES / "sample_loan_card_ahmed.html")
        self.assertEqual(data["name"], "Ahmed Khan")
        self.assertEqual(data["tel_extension"], "2145")
        self.assertGreaterEqual(len(data["items"]), 2)

    def test_parse_simple_html(self):
        data = parse_file(SAMPLES / "sample_loan_card_sara.html")
        self.assertEqual(data["name"], "Sara Malik")
        self.assertEqual(data["department"], "Computer Science")
        self.assertTrue(any("LaserJet" in i["item_name"] for i in data["items"]))

    def test_roundtrip_generated_html(self):
        card = {
            "card_number": "AU-KC-TEST-1",
            "name": "Test User",
            "designation": "Engineer",
            "department": "IT",
            "tel_extension": "1001",
            "issue_date": "2026-07-01",
            "notes": "Roundtrip",
            "items": [
                {
                    "item_name": "Monitor 24inch",
                    "serial_number": "MN-001",
                    "quantity": "1",
                    "remarks": "OK",
                }
            ],
        }
        parsed = parse_html(render_html(card))
        self.assertEqual(parsed["name"], "Test User")
        self.assertEqual(parsed["items"][0]["serial_number"], "MN-001")


class DatabaseTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.db = Database(Path(self.tmp.name) / "test.db")

    def tearDown(self):
        self.tmp.cleanup()

    def test_default_admin_and_login(self):
        user = self.db.authenticate("admin", "admin123")
        self.assertIsNotNone(user)
        self.assertEqual(user["role"], "administrator")
        token = self.db.create_session(user["id"], client_type="server", client_ip="127.0.0.1")
        session_user = self.db.get_session_user(token)
        self.assertEqual(session_user["username"], "admin")
        self.db.touch_session(token, current_view="inventory", current_activity="Editing stock")
        online = self.db.online_users()
        self.assertEqual(len(online), 1)
        self.assertEqual(online[0]["current_view"], "inventory")

    def test_inventory_crud_and_search(self):
        inv_id = self.db.add_inventory(
            item_name="Dell Monitor",
            serial_number="MON-1",
            category="Display",
            status="available",
            allocation_officer="",
            added_date="2026-07-01",
        )
        self.db.update_inventory(
            inv_id,
            status="allocated",
            allocation_officer="IT Officer",
            allocated_to="Ahmed Khan",
            allocation_date="2026-07-10",
            issue_date="2026-07-11",
        )
        rows = self.db.search_inventory(allocation_officer="IT Officer", item_name="Monitor")
        self.assertEqual(len(rows), 1)
        rows = self.db.search_inventory(allocation_date_from="2026-07-01", allocation_date_to="2026-07-31")
        self.assertEqual(len(rows), 1)
        self.assertTrue(self.db.delete_inventory(inv_id))

    def test_loan_search_filters(self):
        self.db.add_loan_card(
            name="Ali Raza",
            designation="Technician",
            department="Networks",
            tel_extension="4410",
            issue_date="2026-02-10",
            items=[{"item_name": "Cisco Switch", "serial_number": "CS-9", "quantity": "1"}],
        )
        self.assertEqual(len(self.db.search(item_name="Cisco")), 1)
        self.assertEqual(len(self.db.search(tel_extension="4410")), 1)


class GeneratorTests(unittest.TestCase):
    def test_write_html_and_pdf(self):
        with tempfile.TemporaryDirectory() as tmp:
            card = {
                "card_number": "AU-KC-GEN",
                "name": "Generator Test",
                "designation": "Staff",
                "department": "IT",
                "tel_extension": "1111",
                "issue_date": "2026-07-15",
                "notes": "",
                "items": [{"item_name": "Keyboard", "serial_number": "KB-1", "quantity": "1", "remarks": ""}],
            }
            self.assertTrue(write_html(card, Path(tmp) / "card.html").exists())
            self.assertGreater(write_pdf(card, Path(tmp) / "card.pdf").stat().st_size, 500)


class ApiTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        import au_kamra_loan_cards as pkg
        import au_kamra_loan_cards.database as db_mod
        import au_kamra_loan_cards.server as server_mod

        root = Path(self.tmp.name)

        def fake_data_dir():
            (root / "uploads").mkdir(exist_ok=True)
            (root / "generated").mkdir(exist_ok=True)
            (root / "backups").mkdir(exist_ok=True)
            return root

        def fake_db_path():
            return fake_data_dir() / "loan_cards.db"

        self._orig = (pkg.data_dir, pkg.db_path, db_mod.db_path, server_mod.data_dir)
        pkg.data_dir = server_mod.data_dir = fake_data_dir
        pkg.db_path = db_mod.db_path = fake_db_path

        self.app = create_app()
        self.client = self.app.test_client()
        login = self.client.post(
            "/api/auth/login",
            data=json.dumps({"username": "admin", "password": "admin123", "client_type": "server"}),
            content_type="application/json",
        )
        self.token = login.get_json()["token"]
        self.headers = {"Authorization": f"Bearer {self.token}"}

    def tearDown(self):
        import au_kamra_loan_cards as pkg
        import au_kamra_loan_cards.database as db_mod
        import au_kamra_loan_cards.server as server_mod

        pkg.data_dir, pkg.db_path, db_mod.db_path, server_mod.data_dir = self._orig
        self.tmp.cleanup()

    def test_upload_search_inventory_users_presence(self):
        sample = SAMPLES / "sample_loan_card_ahmed.html"
        with sample.open("rb") as f:
            res = self.client.post(
                "/api/upload",
                data={"files": (f, sample.name)},
                content_type="multipart/form-data",
                headers=self.headers,
            )
        self.assertEqual(res.status_code, 200)
        self.assertEqual(res.get_json()["count"], 1)

        search = self.client.get("/api/search?name=Ahmed", headers=self.headers)
        self.assertEqual(search.get_json()["count"], 1)

        inv = self.client.post(
            "/api/inventory",
            data=json.dumps(
                {
                    "item_name": "Projector",
                    "serial_number": "PJ-1",
                    "status": "available",
                    "added_date": "2026-07-01",
                }
            ),
            content_type="application/json",
            headers=self.headers,
        )
        self.assertEqual(inv.status_code, 200)
        inv_id = inv.get_json()["item"]["id"]
        alloc = self.client.post(
            f"/api/inventory/{inv_id}/allocate",
            data=json.dumps({"allocated_to": "Sara Malik", "allocation_officer": "Admin"}),
            content_type="application/json",
            headers=self.headers,
        )
        self.assertEqual(alloc.status_code, 200)
        self.assertEqual(alloc.get_json()["item"]["status"], "allocated")

        user = self.client.post(
            "/api/users",
            data=json.dumps(
                {
                    "username": "officer1",
                    "password": "pass1234",
                    "full_name": "Alloc Officer",
                    "role": "allocation_officer",
                }
            ),
            content_type="application/json",
            headers=self.headers,
        )
        self.assertEqual(user.status_code, 200)

        # Agent login + presence
        agent = self.client.post(
            "/api/auth/login",
            data=json.dumps(
                {
                    "username": "officer1",
                    "password": "pass1234",
                    "client_type": "agent",
                    "hostname": "PC-LAB-01",
                }
            ),
            content_type="application/json",
        )
        agent_token = agent.get_json()["token"]
        self.client.post(
            "/api/auth/heartbeat",
            data=json.dumps({"view": "inventory", "activity": "Allocating projector"}),
            content_type="application/json",
            headers={"Authorization": f"Bearer {agent_token}"},
        )
        presence = self.client.get("/api/presence", headers=self.headers)
        self.assertGreaterEqual(presence.get_json()["count"], 2)

        card_id = search.get_json()["results"][0]["id"]
        file_res = self.client.get(f"/api/cards/{card_id}/file", headers=self.headers)
        self.assertEqual(file_res.status_code, 200)
        self.assertIn(b"Ahmed Khan", file_res.data)

    def test_create_card_requires_auth(self):
        denied = self.client.post(
            "/api/cards",
            data=json.dumps({"name": "X"}),
            content_type="application/json",
        )
        self.assertEqual(denied.status_code, 401)
        ok = self.client.post(
            "/api/cards",
            data=json.dumps(
                {
                    "name": "Created User",
                    "department": "Stores",
                    "tel_extension": "5555",
                    "format": "html",
                    "items": [{"item_name": "USB Hub", "serial_number": "USB-1", "quantity": "1"}],
                }
            ),
            content_type="application/json",
            headers=self.headers,
        )
        self.assertEqual(ok.status_code, 200)
        self.assertTrue(Path(ok.get_json()["generated"]["html"]).exists())


class BackupTests(unittest.TestCase):
    def test_backup_restore_export_import(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            import au_kamra_loan_cards as pkg
            import au_kamra_loan_cards.backup as backup_mod
            import au_kamra_loan_cards.database as db_mod

            def fake_data_dir():
                (root / "uploads").mkdir(exist_ok=True)
                (root / "generated").mkdir(exist_ok=True)
                (root / "backups").mkdir(exist_ok=True)
                return root

            def fake_db_path():
                return root / "loan_cards.db"

            orig = (pkg.data_dir, pkg.db_path, db_mod.db_path, backup_mod.data_dir, backup_mod.db_path)
            pkg.data_dir = backup_mod.data_dir = fake_data_dir
            pkg.db_path = db_mod.db_path = backup_mod.db_path = fake_db_path
            try:
                db = Database()
                db.add_loan_card(name="Backup Person", department="IT", tel_extension="9999")
                db.add_inventory(item_name="Cable", status="available", added_date="2026-07-01")
                zpath = backup_zip(root / "backups" / "test.zip")
                self.assertTrue(zpath.exists())
                jpath = export_json(root / "backups" / "out.json", db)
                db2 = Database(root / "other.db")
                counts = import_json(jpath, db2)
                self.assertEqual(counts["loan_cards"], 1)
                self.assertEqual(counts["inventory"], 1)
                db.clear_all()
                restore_zip(zpath)
                self.assertGreaterEqual(len(Database().search()), 1)
            finally:
                pkg.data_dir, pkg.db_path, db_mod.db_path, backup_mod.data_dir, backup_mod.db_path = orig


if __name__ == "__main__":
    unittest.main()
