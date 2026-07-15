"""Generate loan card HTML and PDF files in the AU-Kamra format."""

from __future__ import annotations

import html
from datetime import datetime
from pathlib import Path
from typing import Any, Optional

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

from au_kamra_loan_cards import data_dir


def _esc(value: Any) -> str:
    return html.escape("" if value is None else str(value))


def next_card_number() -> str:
    stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    return f"AU-KC-{stamp}"


def render_html(card: dict[str, Any]) -> str:
    items = card.get("items") or []
    rows = []
    for i, item in enumerate(items, start=1):
        rows.append(
            f"""
            <tr data-item-name="{_esc(item.get('item_name', ''))}"
                data-serial="{_esc(item.get('serial_number', ''))}"
                data-qty="{_esc(item.get('quantity', '1'))}"
                data-remarks="{_esc(item.get('remarks', ''))}">
              <td>{i}</td>
              <td>{_esc(item.get('item_name', ''))}</td>
              <td>{_esc(item.get('serial_number', ''))}</td>
              <td>{_esc(item.get('quantity', '1'))}</td>
              <td>{_esc(item.get('remarks', ''))}</td>
            </tr>
            """
        )
    if not rows:
        rows.append(
            "<tr><td colspan='5' style='text-align:center;color:#667'>No equipment listed</td></tr>"
        )

    return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Loan Card — {_esc(card.get('name', ''))}</title>
  <style>
    :root {{
      --navy: #0a1628;
      --steel: #1e3a5f;
      --gold: #c4a35a;
      --line: #d5dde8;
      --muted: #5b6b7c;
    }}
    * {{ box-sizing: border-box; }}
    body {{
      margin: 0;
      background: #eef2f7;
      color: var(--navy);
      font-family: "Segoe UI", "Candara", "Trebuchet MS", sans-serif;
      padding: 24px;
    }}
    .sheet {{
      max-width: 900px;
      margin: 0 auto;
      background: #fff;
      border: 1px solid var(--line);
      box-shadow: 0 10px 30px rgba(10, 22, 40, 0.08);
    }}
    .banner {{
      background: linear-gradient(120deg, var(--navy), var(--steel));
      color: #fff;
      padding: 22px 28px 18px;
      border-bottom: 4px solid var(--gold);
    }}
    .banner .org {{
      font-size: 12px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #c9d6e8;
      margin-bottom: 6px;
    }}
    .banner h1 {{
      margin: 0;
      font-family: Georgia, "Times New Roman", serif;
      font-weight: 600;
      font-size: 26px;
    }}
    .banner .sub {{
      margin-top: 6px;
      color: #d7e2f0;
      font-size: 14px;
    }}
    .meta {{
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      padding: 14px 28px;
      background: #f7f9fc;
      border-bottom: 1px solid var(--line);
      font-size: 13px;
      color: var(--muted);
    }}
    .content {{ padding: 24px 28px 8px; }}
    .grid {{
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px 28px;
      margin-bottom: 22px;
    }}
    .field label {{
      display: block;
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 4px;
    }}
    .field .value {{
      font-size: 16px;
      font-weight: 600;
      padding-bottom: 6px;
      border-bottom: 1px solid var(--line);
      min-height: 28px;
    }}
    h2 {{
      margin: 8px 0 12px;
      font-size: 15px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--steel);
      border-left: 3px solid var(--gold);
      padding-left: 10px;
    }}
    table {{
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
      font-size: 14px;
    }}
    th, td {{
      border: 1px solid var(--line);
      padding: 10px 12px;
      text-align: left;
    }}
    th {{
      background: #f0f4f9;
      color: var(--steel);
      font-size: 12px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }}
    .notes {{
      margin-bottom: 28px;
      padding: 12px 14px;
      background: #fafbfd;
      border: 1px dashed var(--line);
      min-height: 48px;
      white-space: pre-wrap;
    }}
    .signs {{
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 20px;
      padding: 8px 28px 28px;
    }}
    .sign {{
      text-align: center;
      padding-top: 48px;
      border-top: 1px solid var(--navy);
      font-size: 12px;
      color: var(--muted);
    }}
    @media print {{
      body {{ background: #fff; padding: 0; }}
      .sheet {{ box-shadow: none; border: none; }}
    }}
    @media (max-width: 700px) {{
      .grid, .signs {{ grid-template-columns: 1fr; }}
    }}
  </style>
</head>
<body>
  <article class="sheet" data-loancard="au-kamra">
    <header class="banner">
      <div class="org">Air University — Kamra Campus</div>
      <h1>IT Loan Card</h1>
      <div class="sub">AU-Kamra-IT Loan Cards Management</div>
    </header>
    <div class="meta">
      <div>Card No: <strong data-field="card_number">{_esc(card.get('card_number', ''))}</strong></div>
      <div>Issue Date: <strong data-field="issue_date">{_esc(card.get('issue_date', ''))}</strong></div>
    </div>
    <div class="content">
      <div class="grid">
        <div class="field">
          <label>Name</label>
          <div class="value" data-field="name">{_esc(card.get('name', ''))}</div>
        </div>
        <div class="field">
          <label>Designation</label>
          <div class="value" data-field="designation">{_esc(card.get('designation', ''))}</div>
        </div>
        <div class="field">
          <label>Department</label>
          <div class="value" data-field="department">{_esc(card.get('department', ''))}</div>
        </div>
        <div class="field">
          <label>Tel. Extension</label>
          <div class="value" data-field="tel_extension">{_esc(card.get('tel_extension', ''))}</div>
        </div>
      </div>

      <h2>Equipment / Items</h2>
      <table data-section="equipment">
        <thead>
          <tr>
            <th style="width:48px">#</th>
            <th>Item / Equipment</th>
            <th>Serial Number</th>
            <th style="width:70px">Qty</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          {''.join(rows)}
        </tbody>
      </table>

      <h2>Notes</h2>
      <div class="notes" data-field="notes">{_esc(card.get('notes', ''))}</div>
    </div>
    <div class="signs">
      <div class="sign">Issued By (IT)</div>
      <div class="sign">Received By</div>
      <div class="sign">Approved By</div>
    </div>
  </article>
</body>
</html>
"""


def write_html(card: dict[str, Any], path: Optional[Path] = None) -> Path:
    if path is None:
        safe = "".join(
            ch if ch.isalnum() or ch in "-_ " else "_"
            for ch in (card.get("name") or "loan_card")
        ).strip().replace(" ", "_")
        stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        path = data_dir() / "generated" / f"LoanCard_{safe}_{stamp}.html"
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(render_html(card), encoding="utf-8")
    return path


def write_pdf(card: dict[str, Any], path: Optional[Path] = None) -> Path:
    if path is None:
        safe = "".join(
            ch if ch.isalnum() or ch in "-_ " else "_"
            for ch in (card.get("name") or "loan_card")
        ).strip().replace(" ", "_")
        stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        path = data_dir() / "generated" / f"LoanCard_{safe}_{stamp}.pdf"
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)

    doc = SimpleDocTemplate(
        str(path),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=16 * mm,
        bottomMargin=16 * mm,
        title=f"Loan Card — {card.get('name', '')}",
    )
    styles = getSampleStyleSheet()
    title = ParagraphStyle(
        "TitleAU",
        parent=styles["Title"],
        fontName="Times-Bold",
        fontSize=20,
        textColor=colors.HexColor("#0a1628"),
        alignment=TA_CENTER,
        spaceAfter=4,
    )
    org = ParagraphStyle(
        "OrgAU",
        parent=styles["Normal"],
        fontSize=10,
        textColor=colors.HexColor("#1e3a5f"),
        alignment=TA_CENTER,
        spaceAfter=12,
    )
    label = ParagraphStyle(
        "LabelAU",
        parent=styles["Normal"],
        fontSize=9,
        textColor=colors.HexColor("#5b6b7c"),
        spaceAfter=2,
    )
    value = ParagraphStyle(
        "ValueAU",
        parent=styles["Normal"],
        fontSize=12,
        textColor=colors.HexColor("#0a1628"),
        spaceAfter=10,
    )
    body = ParagraphStyle(
        "BodyAU",
        parent=styles["Normal"],
        fontSize=10,
        alignment=TA_LEFT,
    )

    story = [
        Paragraph("AIR UNIVERSITY — KAMRA CAMPUS", org),
        Paragraph("IT Loan Card", title),
        Paragraph("AU-Kamra-IT Loan Cards Management", org),
        Spacer(1, 6),
    ]

    meta = [
        [
            Paragraph(f"<b>Card No:</b> {_esc(card.get('card_number', ''))}", body),
            Paragraph(f"<b>Issue Date:</b> {_esc(card.get('issue_date', ''))}", body),
        ]
    ]
    story.append(
        Table(
            meta,
            colWidths=[90 * mm, 70 * mm],
            style=TableStyle(
                [
                    ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#f0f4f9")),
                    ("BOX", (0, 0), (-1, -1), 0.5, colors.HexColor("#d5dde8")),
                    ("LEFTPADDING", (0, 0), (-1, -1), 8),
                    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                    ("TOPPADDING", (0, 0), (-1, -1), 8),
                    ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
                ]
            ),
        )
    )
    story.append(Spacer(1, 12))

    fields = [
        ("Name", card.get("name", "")),
        ("Designation", card.get("designation", "")),
        ("Department", card.get("department", "")),
        ("Tel. Extension", card.get("tel_extension", "")),
    ]
    for lbl, val in fields:
        story.append(Paragraph(lbl.upper(), label))
        story.append(Paragraph(_esc(val) or "&nbsp;", value))

    story.append(Paragraph("<b>EQUIPMENT / ITEMS</b>", value))
    data = [["#", "Item / Equipment", "Serial Number", "Qty", "Remarks"]]
    items = card.get("items") or []
    if not items:
        data.append(["—", "No equipment listed", "", "", ""])
    else:
        for i, item in enumerate(items, start=1):
            data.append(
                [
                    str(i),
                    item.get("item_name", ""),
                    item.get("serial_number", ""),
                    item.get("quantity", "1"),
                    item.get("remarks", ""),
                ]
            )
    table = Table(data, colWidths=[12 * mm, 58 * mm, 42 * mm, 16 * mm, 42 * mm])
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#1e3a5f")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
                ("FONTSIZE", (0, 0), (-1, -1), 9),
                ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#d5dde8")),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 6),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
                (
                    "ROWBACKGROUNDS",
                    (0, 1),
                    (-1, -1),
                    [colors.white, colors.HexColor("#f7f9fc")],
                ),
            ]
        )
    )
    story.append(table)
    story.append(Spacer(1, 14))
    story.append(Paragraph("<b>NOTES</b>", value))
    story.append(Paragraph(_esc(card.get("notes", "")) or "—", body))
    story.append(Spacer(1, 28))
    signs = Table(
        [["Issued By (IT)", "Received By", "Approved By"]],
        colWidths=[55 * mm, 55 * mm, 55 * mm],
        style=TableStyle(
            [
                ("ALIGN", (0, 0), (-1, -1), "CENTER"),
                ("FONTSIZE", (0, 0), (-1, -1), 9),
                ("TEXTCOLOR", (0, 0), (-1, -1), colors.HexColor("#5b6b7c")),
                ("LINEABOVE", (0, 0), (-1, -1), 0.6, colors.HexColor("#0a1628")),
                ("TOPPADDING", (0, 0), (-1, -1), 10),
            ]
        ),
    )
    story.append(signs)
    doc.build(story)
    return path
