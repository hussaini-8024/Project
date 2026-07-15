"""Unit tests for parsers, database, generator, and API."""

from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from au_kamra_loan_cards.backup import backup_zip, export_json, import_json, restore_zip
from au_kamra_loan_cards.database import Database
from au_kamra_loan_cards.generator import render_html, write_html, write_pdf
from au_kamra_loan_cards.parsers import parse_file, parse_html
from au_kamra_loan_cards.server import create_app


SAMPLES = Path(__file__).resolve().parent.parent / "au_kamra_loan_cards" / "samples"


class ParserTests(unittest.TestCase):
    def test_parse_structured_html(self):
        path = SAMPLES / "sample_loan_card_ahmed.html"
        data = parse_file(path)
        self.assertEqual(data["name"], "Ahmed Khan")
        self.assertEqual(data["designation"], "Assistant Manager IT")
        self.assertEqual(data["department"], "Information Technology")
        self.assertEqual(data["tel_extension"], "2145")
        self.assertEqual(data["issue_date"], "2026-03-15")
        self.assertGreaterEqual(len(data["items"]), 2)
        self.assertIn("Laptop", data["items"][0]["item_name"])

    def test_parse_simple_html(self):
        path = SAMPLES / "sample_loan_card_sara.html"
        data = parse_file(path)
        self.assertEqual(data["name"], "Sara Malik")
        self.assertEqual(data["department"], "Computer Science")
        self.assertEqual(data["tel_extension"], "3301")
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
        html = render_html(card)
        parsed = parse_html(html)
        self.assertEqual(parsed["name"], "Test User")
        self.assertEqual(parsed["tel_extension"], "1001")
        self.assertEqual(parsed["items"][0]["item_name"], "Monitor 24inch")
        self.assertEqual(parsed["items"][0]["serial_number"], "MN-001")


class DatabaseTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.db = Database(Path(self.tmp.name) / "test.db")

    def tearDown(self):
        self.tmp.cleanup()

    def test_add_search_filters(self):
        self.db.add_loan_card(
            name="Ali Raza",
            designation="Technician",
            department="Networks",
            tel_extension="4410",
            issue_date="2026-02-10",
            items=[{"item_name": "Cisco Switch", "serial_number": "CS-9", "quantity": "1"}],
        )
        self.db.add_loan_card(
            name="Nadia Hussain",
            designation="Officer",
            department="Admin",
            tel_extension="2200",
            issue_date="2026-05-01",
            items=[{"item_name": "Desktop PC", "serial_number": "PC-1", "quantity": "1"}],
        )
        by_name = self.db.search(name="Ali")
        self.assertEqual(len(by_name), 1)
        by_dept = self.db.search(department="Admin")
        self.assertEqual(len(by_dept), 1)
        by_item = self.db.search(item_name="Cisco")
        self.assertEqual(len(by_item), 1)
        by_tel = self.db.search(tel_extension="4410")
        self.assertEqual(len(by_tel), 1)
        by_date = self.db.search(issue_date_from="2026-04-01", issue_date_to="2026-12-31")
        self.assertEqual(len(by_date), 1)
        by_desig = self.db.search(designation="Technician")
        self.assertEqual(len(by_desig), 1)


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
            html_path = write_html(card, Path(tmp) / "card.html")
            pdf_path = write_pdf(card, Path(tmp) / "card.pdf")
            self.assertTrue(html_path.exists())
            self.assertTrue(pdf_path.exists())
            self.assertGreater(pdf_path.stat().st_size, 500)


class ApiTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        # Point data dir via monkeypatch of module functions used by create_app routes
        import au_kamra_loan_cards as pkg
        import au_kamra_loan_cards.server as server_mod

        self._orig_data_dir = pkg.data_dir
        self._orig_db_path = pkg.db_path
        root = Path(self.tmp.name)

        def fake_data_dir():
            root.mkdir(parents=True, exist_ok=True)
            (root / "uploads").mkdir(exist_ok=True)
            (root / "generated").mkdir(exist_ok=True)
            (root / "backups").mkdir(exist_ok=True)
            return root

        def fake_db_path():
            return fake_data_dir() / "loan_cards.db"

        pkg.data_dir = fake_data_dir
        pkg.db_path = fake_db_path
        server_mod.data_dir = fake_data_dir
        server_mod.db_path = getattr(server_mod, "db_path", fake_db_path)
        # Database() inside create_app uses db_path from package
        from au_kamra_loan_cards import database as db_mod

        self._orig_db_mod_path = db_mod.db_path
        db_mod.db_path = fake_db_path

        self.app = create_app()
        self.client = self.app.test_client()

    def tearDown(self):
        import au_kamra_loan_cards as pkg
        import au_kamra_loan_cards.database as db_mod
        import au_kamra_loan_cards.server as server_mod

        pkg.data_dir = self._orig_data_dir
        pkg.db_path = self._orig_db_path
        server_mod.data_dir = self._orig_data_dir
        db_mod.db_path = self._orig_db_mod_path
        self.tmp.cleanup()

    def test_upload_and_search(self):
        sample = SAMPLES / "sample_loan_card_ahmed.html"
        with sample.open("rb") as f:
            res = self.client.post(
                "/api/upload",
                data={"files": (f, sample.name)},
                content_type="multipart/form-data",
            )
        self.assertEqual(res.status_code, 200)
        payload = res.get_json()
        self.assertEqual(payload["count"], 1)

        search = self.client.get("/api/search?name=Ahmed")
        self.assertEqual(search.status_code, 200)
        results = search.get_json()["results"]
        self.assertEqual(len(results), 1)
        self.assertEqual(results[0]["tel_extension"], "2145")

        card_id = results[0]["id"]
        detail = self.client.get(f"/api/cards/{card_id}")
        self.assertEqual(detail.status_code, 200)
        self.assertGreaterEqual(len(detail.get_json()["items"]), 2)

        file_res = self.client.get(f"/api/cards/{card_id}/file")
        self.assertEqual(file_res.status_code, 200)
        self.assertIn(b"Ahmed Khan", file_res.data)

    def test_create_card(self):
        res = self.client.post(
            "/api/cards",
            data=json.dumps(
                {
                    "name": "Created User",
                    "designation": "Clerk",
                    "department": "Stores",
                    "tel_extension": "5555",
                    "issue_date": "2026-07-15",
                    "format": "html",
                    "items": [
                        {
                            "item_name": "USB Hub",
                            "serial_number": "USB-1",
                            "quantity": "1",
                            "remarks": "",
                        }
                    ],
                }
            ),
            content_type="application/json",
        )
        self.assertEqual(res.status_code, 200)
        body = res.get_json()
        self.assertTrue(body["ok"])
        self.assertTrue(Path(body["generated"]["html"]).exists())


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
                zpath = backup_zip(root / "backups" / "test.zip")
                self.assertTrue(zpath.exists())
                jpath = export_json(root / "backups" / "out.json", db)
                self.assertTrue(jpath.exists())

                db2 = Database(root / "other.db")
                count = import_json(jpath, db2)
                self.assertEqual(count, 1)
                self.assertEqual(db2.search(name="Backup")[0]["name"], "Backup Person")

                # wipe and restore
                db.clear_all()
                self.assertEqual(len(db.search()), 0)
                restore_zip(zpath)
                db3 = Database()
                self.assertGreaterEqual(len(db3.search()), 1)
            finally:
                pkg.data_dir, pkg.db_path, db_mod.db_path, backup_mod.data_dir, backup_mod.db_path = orig


if __name__ == "__main__":
    unittest.main()
