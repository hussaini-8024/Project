# AU-Kamra-IT Loan Cards Management

Desktop software for managing IT equipment loan cards at **Air University — Kamra Campus**.

Upload PDF or HTML loan cards, search with filters, open the original file, generate new cards in the same format, and keep secure backups — all from one Windows application.

## Features

- **Upload** PDF / HTML loan card files (multiple at once)
- **Search & filter** by:
  - Name
  - Designation
  - Department
  - Issue date (from / to)
  - Item / Equipment name
  - Tel. extension number
- **Open original file** when you click a result (same format you uploaded)
- **Generate** new loan cards as HTML and/or PDF in the AU-Kamra format
- **Activity log** of uploads, edits, backups, and imports
- **Backup / Restore** (full ZIP of database + files)
- **Import / Export** (JSON and CSV)

## Software name

**AU-Kamra-IT Loan Cards Management**

## Windows support

Runs on **Windows 7 / 8 / 10 / 11** (64-bit recommended).

Build a **single `.exe`** with the included script (see below). No separate installer required — copy the EXE and run it.

## Quick start (from source)

```bat
python -m pip install -r requirements.txt
python run.py
```

The app opens in your browser (or a desktop window on Windows when `pywebview` is installed) at `http://127.0.0.1:8765/`.

## Build single EXE (Windows)

1. Install [Python 3.8+](https://www.python.org/downloads/) and check **Add Python to PATH**.
2. Double-click:

```text
scripts\build_windows.bat
```

3. Find the EXE at:

```text
dist\AU-Kamra-IT-Loan-Cards.exe
```

Data is stored next to the EXE in a folder named `AU_Kamra_Data` (database, uploads, generated cards, backups).

## Sample files

Try the sample HTML loan cards in:

```text
au_kamra_loan_cards\samples\
```

## Tests

```bat
python -m unittest discover -s tests -v
```

## Project layout

```text
run.py                          Entry point
au_kamra_loan_cards/
  main.py                       Desktop launcher
  server.py                     Local web API + UI
  database.py                   SQLite storage
  parsers.py                    PDF / HTML parsers
  generator.py                  HTML / PDF loan card generator
  backup.py                     Backup, restore, import, export
  templates/                    UI
  static/                       CSS / JS
  samples/                      Example loan cards
scripts/build_windows.bat       One-file EXE builder
```

## Notes

- Only **PDF** and **HTML/HTM** uploads are accepted.
- Generated cards use the same structured HTML format the importer understands, so re-import works cleanly.
- Keep periodic ZIP backups from the **Backup / Import** screen.
