"""Parse HTML and PDF loan card files into structured records."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any, Optional

from bs4 import BeautifulSoup
from pypdf import PdfReader


FIELD_ALIASES = {
    "name": [
        "name",
        "employee name",
        "officer name",
        "staff name",
        "full name",
        "borrower",
        "card holder",
        "holder name",
    ],
    "designation": [
        "designation",
        "rank",
        "title",
        "post",
        "appointment",
        "job title",
    ],
    "department": [
        "department",
        "dept",
        "section",
        "wing",
        "branch",
        "unit",
        "directorate",
    ],
    "tel_extension": [
        "tel.extension",
        "tel extension",
        "telephone extension",
        "phone extension",
        "extension",
        "ext",
        "ext.",
        "tel ext",
        "contact extension",
        "telephone",
        "phone",
    ],
    "issue_date": [
        "issue date",
        "issued date",
        "date of issue",
        "date issued",
        "issued on",
        "loan date",
        "date",
    ],
    "card_number": [
        "card number",
        "loan card no",
        "loan card number",
        "card no",
        "card #",
        "lc no",
        "reference",
        "ref no",
    ],
}


def _norm(text: str) -> str:
    return re.sub(r"\s+", " ", (text or "").strip().lower())


def _match_field(label: str) -> Optional[str]:
    n = _norm(label).rstrip(":")
    if not n:
        return None
    # Exact alias match first
    for field, aliases in FIELD_ALIASES.items():
        for alias in aliases:
            if n == alias:
                return field
    # Prefix / "label starts with alias" (avoid short aliases matching inside other words)
    for field, aliases in FIELD_ALIASES.items():
        for alias in aliases:
            if len(alias) < 4:
                continue
            if n.startswith(alias + " ") or n.startswith(alias + "/") or n.startswith(alias + "("):
                return field
    # Conservative contains match for multi-word aliases only
    for field, aliases in FIELD_ALIASES.items():
        for alias in aliases:
            if " " in alias and alias in n:
                return field
    return None


def _clean_value(value: str) -> str:
    value = re.sub(r"\s+", " ", (value or "").strip())
    value = value.strip(":-–—|")
    return value.strip()


def empty_record() -> dict[str, Any]:
    return {
        "card_number": "",
        "name": "",
        "designation": "",
        "department": "",
        "tel_extension": "",
        "issue_date": "",
        "notes": "",
        "items": [],
    }


def parse_html(content: str | bytes) -> dict[str, Any]:
    if isinstance(content, bytes):
        content = content.decode("utf-8", errors="replace")
    soup = BeautifulSoup(content, "lxml")
    record = empty_record()

    # Preferred: data-field attributes from our generator
    for field in (
        "card_number",
        "name",
        "designation",
        "department",
        "tel_extension",
        "issue_date",
        "notes",
    ):
        el = soup.select_one(f'[data-field="{field}"]')
        if el:
            record[field] = _clean_value(el.get_text(" ", strip=True))

    # Table label/value pairs (2–3 column meta rows only)
    for row in soup.find_all("tr"):
        cells = row.find_all(["th", "td"])
        if len(cells) < 2 or len(cells) > 3:
            continue
        label = cells[0].get_text(" ", strip=True)
        if _norm(label) in {
            "item name",
            "item",
            "equipment",
            "serial number",
            "qty",
            "quantity",
            "remarks",
            "#",
        }:
            continue
        field = _match_field(label)
        if field and not record.get(field):
            record[field] = _clean_value(cells[1].get_text(" ", strip=True))

    # Definition lists
    for dt in soup.find_all("dt"):
        field = _match_field(dt.get_text(" ", strip=True))
        dd = dt.find_next_sibling("dd")
        if field and dd and not record.get(field):
            record[field] = _clean_value(dd.get_text(" ", strip=True))

    # Labeled spans / paragraphs: "Name: John"
    text = soup.get_text("\n", strip=True)
    record = _fill_from_text(record, text)

    # Equipment table
    items = _parse_equipment_from_html(soup)
    if not items:
        items = _parse_equipment_from_text(text)
    record["items"] = items

    if not record["name"]:
        title = soup.find("title")
        h1 = soup.find(["h1", "h2"])
        guess = (h1 or title)
        if guess:
            record["name"] = _clean_value(guess.get_text(" ", strip=True))
    if not record["name"]:
        record["name"] = "Unknown"

    return record


def _parse_equipment_from_html(soup: BeautifulSoup) -> list[dict[str, str]]:
    items: list[dict[str, str]] = []
    for table in soup.find_all("table"):
        headers = [
            _norm(th.get_text(" ", strip=True))
            for th in table.find_all("th")
        ]
        looks_like_equipment = any(
            any(k in h for k in ("item", "equipment", "description", "serial", "qty", "quantity"))
            for h in headers
        )
        if not looks_like_equipment and "equipment" not in _norm(table.get_text(" ", strip=True)[:80]):
            # still try data-section
            if table.get("data-section") != "equipment":
                continue

        name_idx = serial_idx = qty_idx = remarks_idx = None
        for i, h in enumerate(headers):
            if any(k in h for k in ("item", "equipment", "description", "particular")):
                name_idx = i
            elif "serial" in h or h in ("s/n", "sn"):
                serial_idx = i
            elif "qty" in h or "quantity" in h:
                qty_idx = i
            elif any(k in h for k in ("remark", "note", "condition")):
                remarks_idx = i

        body_rows = table.find_all("tr")
        start = 1 if headers else 0
        for row in body_rows[start:]:
            cells = [c.get_text(" ", strip=True) for c in row.find_all(["td", "th"])]
            if not cells or all(not c for c in cells):
                continue
            joined = _norm(" ".join(cells))
            if any(
                k in joined
                for k in (
                    "item name",
                    "serial number",
                    "item / equipment",
                    "equipment list",
                )
            ) and not any(c.isdigit() for c in cells[:1]):
                # header row
                if all(
                    _norm(c) in {
                        "item name",
                        "item",
                        "equipment",
                        "serial number",
                        "serial",
                        "qty",
                        "quantity",
                        "remarks",
                        "#",
                        "s.no",
                        "sr",
                        "description",
                    }
                    or any(
                        x in _norm(c)
                        for x in ("item", "serial", "qty", "remark", "equipment")
                    )
                    for c in cells
                ):
                    continue
            item_name = (
                cells[name_idx]
                if name_idx is not None and name_idx < len(cells)
                else cells[0]
            )
            if _norm(item_name) in {
                "item",
                "item name",
                "equipment",
                "s.no",
                "sr",
                "description",
                "#",
            }:
                continue
            if _match_field(item_name) and len(cells) <= 2:
                continue
            items.append(
                {
                    "item_name": _clean_value(item_name),
                    "serial_number": _clean_value(
                        cells[serial_idx]
                        if serial_idx is not None and serial_idx < len(cells)
                        else (cells[1] if len(cells) > 1 else "")
                    ),
                    "quantity": _clean_value(
                        cells[qty_idx]
                        if qty_idx is not None and qty_idx < len(cells)
                        else (cells[2] if len(cells) > 2 else "1")
                    ),
                    "remarks": _clean_value(
                        cells[remarks_idx]
                        if remarks_idx is not None and remarks_idx < len(cells)
                        else (cells[3] if len(cells) > 3 else "")
                    ),
                }
            )
    # data-item nodes
    for el in soup.select("[data-item-name]"):
        items.append(
            {
                "item_name": _clean_value(el.get("data-item-name", "")),
                "serial_number": _clean_value(el.get("data-serial", "")),
                "quantity": _clean_value(el.get("data-qty", "1")),
                "remarks": _clean_value(el.get("data-remarks", "")),
            }
        )
    # de-dup
    seen = set()
    unique = []
    for it in items:
        key = (it["item_name"], it["serial_number"])
        if key in seen or not it["item_name"]:
            continue
        seen.add(key)
        unique.append(it)
    return unique


def _fill_from_text(record: dict[str, Any], text: str) -> dict[str, Any]:
    patterns = [
        (r"(?im)^(?:employee\s+)?name\s*[:\-]\s*(.+)$", "name"),
        (r"(?im)^designation\s*[:\-]\s*(.+)$", "designation"),
        (r"(?im)^(?:department|dept\.?|section)\s*[:\-]\s*(.+)$", "department"),
        (
            r"(?im)^(?:tel\.?\s*extension|telephone\s*extension|extension|ext\.?)\s*[:\-]\s*(.+)$",
            "tel_extension",
        ),
        (
            r"(?im)^(?:issue\s*date|date\s*of\s*issue|issued\s*on|loan\s*date)\s*[:\-]\s*(.+)$",
            "issue_date",
        ),
        (
            r"(?im)^(?:card\s*(?:number|no\.?#?)|loan\s*card\s*no\.?|ref(?:erence)?\s*no\.?)\s*[:\-]\s*(.+)$",
            "card_number",
        ),
    ]
    for pattern, field in patterns:
        if record.get(field):
            continue
        m = re.search(pattern, text)
        if m:
            record[field] = _clean_value(m.group(1))
    return record


def _parse_equipment_from_text(text: str) -> list[dict[str, str]]:
    items: list[dict[str, str]] = []
    # Lines like: Laptop | SN-123 | Qty: 1
    for line in text.splitlines():
        line = line.strip()
        if not line or len(line) < 3:
            continue
        if re.search(r"(?i)item\s*name|equipment\s*list|serial\s*number", line):
            continue
        # bullet / numbered equipment lines
        m = re.match(
            r"(?i)^(?:[\-\*\u2022]|\d+[.)])\s*(.+?)(?:\s*[|\t]\s*|\s{2,})(SN[:\s-]?\S+)?(?:\s*[|\t]\s*|\s{2,})?(?:qty[:\s]*)?(\d+)?$",
            line,
        )
        if m:
            items.append(
                {
                    "item_name": _clean_value(m.group(1)),
                    "serial_number": _clean_value(m.group(2) or ""),
                    "quantity": _clean_value(m.group(3) or "1"),
                    "remarks": "",
                }
            )
    return items


def parse_pdf(path: Path) -> dict[str, Any]:
    reader = PdfReader(str(path))
    parts: list[str] = []
    for page in reader.pages:
        try:
            parts.append(page.extract_text() or "")
        except Exception:
            continue
    text = "\n".join(parts)
    record = empty_record()
    record = _fill_from_text(record, text)
    record["items"] = _parse_equipment_from_text(text)

    # Fallback: key: value on same line for remaining fields
    for line in text.splitlines():
        if ":" not in line:
            continue
        label, _, value = line.partition(":")
        field = _match_field(label)
        if field and not record.get(field):
            record[field] = _clean_value(value)

    if not record["name"]:
        # first non-empty non-header line
        for line in text.splitlines():
            cleaned = _clean_value(line)
            if cleaned and not re.search(r"(?i)loan\s*card|air\s*university|kamra|it\s*dept", cleaned):
                record["name"] = cleaned[:120]
                break
    if not record["name"]:
        record["name"] = path.stem.replace("_", " ").replace("-", " ")
    return record


def parse_file(path: Path) -> dict[str, Any]:
    path = Path(path)
    suffix = path.suffix.lower()
    if suffix in {".html", ".htm"}:
        return parse_html(path.read_bytes())
    if suffix == ".pdf":
        return parse_pdf(path)
    raise ValueError(f"Unsupported file type: {suffix}. Use PDF or HTML.")
