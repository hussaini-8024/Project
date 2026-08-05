#!/usr/bin/env python3
"""
UBNT Dish IP Finder — Web UI
============================
Easy browser interface to discover Ubiquiti dish IP addresses.

  python3 app.py
  → open http://127.0.0.1:5055
"""

from __future__ import annotations

import threading
from typing import Any, Dict, List

from flask import Flask, jsonify, render_template_string, request

from ubnt_discover import COMMON_DEFAULT_IPS, run_discovery

app = Flask(__name__)

# In-memory last scan (single-user local tool)
_lock = threading.Lock()
_last_result: Dict[str, Any] = {"devices": [], "error": None, "scanning": False}

HTML = r"""
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>UBNT Dish IP Finder</title>
  <style>
    :root {
      --bg: #0f1c24;
      --panel: #162a35;
      --panel-2: #1c3542;
      --text: #e8f0f4;
      --muted: #8aa3b0;
      --accent: #00a0b8;
      --accent-hover: #00c4e0;
      --ok: #2ecc71;
      --warn: #e67e22;
      --border: #2a4555;
      --mono: "IBM Plex Mono", "Consolas", "Courier New", monospace;
      --sans: "IBM Plex Sans", "Segoe UI", system-ui, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--sans);
      background:
        radial-gradient(ellipse 80% 50% at 20% -10%, #1a3a4a 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 90% 100%, #0d2a30 0%, transparent 45%),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
      line-height: 1.5;
    }
    .wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 2rem 1.25rem 3rem;
    }
    header {
      margin-bottom: 1.75rem;
    }
    header h1 {
      font-size: clamp(1.6rem, 3vw, 2.1rem);
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    header h1 span { color: var(--accent); }
    header p {
      color: var(--muted);
      margin-top: 0.4rem;
      max-width: 42rem;
      font-size: 1rem;
    }
    .controls {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      align-items: flex-end;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1.1rem 1.2rem;
      margin-bottom: 1.25rem;
    }
    .field { display: flex; flex-direction: column; gap: 0.35rem; }
    .field label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
    }
    .field input[type="number"] {
      width: 5.5rem;
      background: var(--panel-2);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: 6px;
      padding: 0.55rem 0.65rem;
      font-family: var(--mono);
      font-size: 0.95rem;
    }
    .check {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding-bottom: 0.45rem;
      color: var(--muted);
      font-size: 0.9rem;
      cursor: pointer;
      user-select: none;
    }
    .check input { accent-color: var(--accent); width: 1rem; height: 1rem; }
    button#scanBtn {
      background: var(--accent);
      color: #041218;
      border: none;
      border-radius: 6px;
      padding: 0.65rem 1.35rem;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      transition: background 0.15s ease, transform 0.1s ease;
    }
    button#scanBtn:hover:not(:disabled) { background: var(--accent-hover); }
    button#scanBtn:active:not(:disabled) { transform: scale(0.98); }
    button#scanBtn:disabled {
      opacity: 0.55;
      cursor: wait;
    }
    .status {
      min-height: 1.4rem;
      margin-bottom: 1rem;
      color: var(--muted);
      font-size: 0.92rem;
    }
    .status.scanning { color: var(--accent); }
    .status.error { color: var(--warn); }
    .status.ok { color: var(--ok); }
    .table-wrap {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: var(--panel);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.92rem;
    }
    th {
      text-align: left;
      padding: 0.85rem 1rem;
      background: var(--panel-2);
      color: var(--muted);
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    td {
      padding: 0.9rem 1rem;
      border-bottom: 1px solid var(--border);
      vertical-align: top;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(0, 160, 184, 0.06); }
    .ip {
      font-family: var(--mono);
      font-weight: 600;
      color: var(--accent-hover);
      font-size: 1.02rem;
    }
    .ip a { color: inherit; text-decoration: none; }
    .ip a:hover { text-decoration: underline; }
    .mac, .fw { font-family: var(--mono); font-size: 0.85rem; color: var(--muted); }
    .badge {
      display: inline-block;
      font-size: 0.68rem;
      padding: 0.15rem 0.45rem;
      border-radius: 4px;
      background: rgba(0, 160, 184, 0.15);
      color: var(--accent);
      margin-left: 0.35rem;
      vertical-align: middle;
    }
    .empty {
      padding: 2.5rem 1.5rem;
      text-align: center;
      color: var(--muted);
    }
    .empty strong { color: var(--text); display: block; margin-bottom: 0.5rem; }
    .tips {
      margin-top: 1.5rem;
      padding: 1.1rem 1.2rem;
      border-left: 3px solid var(--accent);
      background: rgba(0, 160, 184, 0.08);
      border-radius: 0 8px 8px 0;
      color: var(--muted);
      font-size: 0.9rem;
    }
    .tips h3 {
      color: var(--text);
      font-size: 0.85rem;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .tips ol { margin-left: 1.1rem; }
    .tips li { margin: 0.25rem 0; }
    footer {
      margin-top: 2rem;
      color: var(--muted);
      font-size: 0.8rem;
    }
    @media (max-width: 640px) {
      .controls { flex-direction: column; align-items: stretch; }
      button#scanBtn { width: 100%; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <h1><span>UBNT</span> Dish IP Finder</h1>
      <p>
        Find the IP address of any Ubiquiti dish or airMAX radio on your network —
        even when you don’t know it. Works with PowerBeam, NanoBeam, LiteBeam,
        Rocket, airFiber, and more.
      </p>
    </header>

    <div class="controls">
      <div class="field">
        <label for="timeout">Timeout (seconds)</label>
        <input id="timeout" type="number" min="2" max="30" step="1" value="5" />
      </div>
      <label class="check">
        <input id="defaults" type="checkbox" checked />
        Also check common default IPs
      </label>
      <button id="scanBtn" type="button">Find Dishes</button>
    </div>

    <div id="status" class="status">Ready — connect to the dish network, then click Find Dishes.</div>

    <div class="table-wrap">
      <div id="results">
        <div class="empty">
          <strong>No scan yet</strong>
          Results will appear here with IP, model, hostname, and MAC.
        </div>
      </div>
    </div>

    <div class="tips">
      <h3>How to connect</h3>
      <ol>
        <li>Plug your PC into the same switch / PoE LAN as the dish, or Ethernet directly to the dish.</li>
        <li>Disable VPN if discovery finds nothing.</li>
        <li>Classic airMAX default IP is <strong style="color:var(--text)">192.168.1.20</strong> (login often <code>ubnt</code> / <code>ubnt</code>).</li>
        <li>If needed, set a temporary PC IP like <code>192.168.1.100</code> / <code>255.255.255.0</code> and scan again.</li>
      </ol>
    </div>

    <footer>
      Local tool · Uses Ubiquiti Discovery Protocol (UDP 10001) · Default IPs checked:
      {{ defaults }}
    </footer>
  </div>

  <script>
    const statusEl = document.getElementById("status");
    const resultsEl = document.getElementById("results");
    const scanBtn = document.getElementById("scanBtn");

    function setStatus(text, cls) {
      statusEl.className = "status " + (cls || "");
      statusEl.textContent = text;
    }

    function esc(s) {
      return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    function renderDevices(devices) {
      if (!devices.length) {
        resultsEl.innerHTML = `
          <div class="empty">
            <strong>No Ubiquiti devices found</strong>
            Check cabling, disable VPN, try a longer timeout, or set a static IP on 192.168.1.x.
          </div>`;
        return;
      }
      const rows = devices.map(d => {
        const badge = d.source === "default-probe"
          ? '<span class="badge">default IP</span>' : "";
        const links = `
          <a href="http://${esc(d.ip)}" target="_blank" rel="noopener">http</a>
          ·
          <a href="https://${esc(d.ip)}" target="_blank" rel="noopener">https</a>`;
        return `<tr>
          <td>
            <div class="ip">${esc(d.ip)}${badge}</div>
            <div style="margin-top:0.25rem;font-size:0.8rem;color:var(--muted)">${links}</div>
          </td>
          <td>${esc(d.display_model || d.model || d.model_short || "—")}</td>
          <td>${esc(d.hostname || "—")}</td>
          <td class="mac">${esc(d.mac || "—")}</td>
          <td class="fw">${esc(d.firmware || "—")}</td>
          <td>${esc(d.essid || "—")}</td>
        </tr>`;
      }).join("");

      resultsEl.innerHTML = `
        <table>
          <thead>
            <tr>
              <th>IP Address</th>
              <th>Model</th>
              <th>Hostname</th>
              <th>MAC</th>
              <th>Firmware</th>
              <th>ESSID</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>`;
    }

    async function scan() {
      const timeout = Number(document.getElementById("timeout").value) || 5;
      const checkDefaults = document.getElementById("defaults").checked;
      scanBtn.disabled = true;
      setStatus("Scanning network for Ubiquiti dishes…", "scanning");

      try {
        const res = await fetch("/api/discover", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ timeout, check_defaults: checkDefaults }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || "Scan failed");
        renderDevices(data.devices || []);
        const n = (data.devices || []).length;
        setStatus(
          n ? `Found ${n} device${n === 1 ? "" : "s"}.` : "Scan finished — no devices found.",
          n ? "ok" : "error"
        );
      } catch (err) {
        setStatus(String(err.message || err), "error");
      } finally {
        scanBtn.disabled = false;
      }
    }

    scanBtn.addEventListener("click", scan);
  </script>
</body>
</html>
"""


@app.get("/")
def index():
    return render_template_string(
        HTML, defaults=", ".join(COMMON_DEFAULT_IPS)
    )


@app.post("/api/discover")
def api_discover():
    body = request.get_json(silent=True) or {}
    timeout = float(body.get("timeout", 5))
    timeout = max(2.0, min(timeout, 30.0))
    check_defaults = bool(body.get("check_defaults", True))

    with _lock:
        if _last_result.get("scanning"):
            return jsonify({"error": "A scan is already running"}), 409
        _last_result["scanning"] = True
        _last_result["error"] = None

    try:
        devices = run_discovery(timeout=timeout, check_defaults=check_defaults)
        payload = [d.to_dict() for d in devices]
        with _lock:
            _last_result["devices"] = payload
            _last_result["scanning"] = False
        return jsonify({"devices": payload, "count": len(payload)})
    except Exception as exc:  # noqa: BLE001 — local tool; surface errors to UI
        with _lock:
            _last_result["scanning"] = False
            _last_result["error"] = str(exc)
        return jsonify({"error": str(exc)}), 500


@app.get("/api/health")
def health():
    return jsonify({"ok": True, "app": "ubnt-dish-ip-finder"})


def main():
    print("=" * 56)
    print("  UBNT Dish IP Finder")
    print("  Open: http://127.0.0.1:5055")
    print("=" * 56)
    app.run(host="127.0.0.1", port=5055, debug=False, threaded=True)


if __name__ == "__main__":
    main()
