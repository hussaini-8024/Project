# UBNT Dish IP Finder

Find the **IP address of any Ubiquiti (UBNT) dish / airMAX radio** on your network — even when you don’t know it.

Works with common Ubiquiti outdoor radios such as:

- PowerBeam / PowerBeam AC  
- NanoBeam / NanoStation  
- LiteBeam / LiteBeam AC  
- Rocket / Rocket Prism  
- airFiber / airMAX M / AC models  
- Other devices that answer the Ubiquiti Discovery Protocol  

---

## Why this tool?

Ubiquiti dishes often keep a fixed IP (or a forgotten custom IP). If you don’t know it, you can’t open the web UI. This app **broadcasts the Ubiquiti discovery protocol** (UDP port **10001**) so radios announce themselves with:

| Info | Description |
|------|-------------|
| **IP address** | Management address to open in a browser |
| **Model** | e.g. PowerBeam 5AC |
| **Hostname** | Device name |
| **MAC** | Hardware address |
| **Firmware** | airOS version |
| **ESSID** | Wireless SSID (when present) |

It can also **probe common factory-default IPs** (especially `192.168.1.20`).

---

## Quick start (easiest)

### Option A — Web app (recommended)

```bash
cd ubnt-discovery
pip install -r requirements.txt
python3 app.py
```

Then open: **http://127.0.0.1:5055**

Click **Find Dishes**.

**Windows:** double-click `launch.bat`  
**Linux / macOS:**

```bash
chmod +x launch.sh
./launch.sh
```

### Option B — Command line (no web UI)

```bash
cd ubnt-discovery
python3 ubnt_discover.py
```

Useful flags:

```bash
# Wait longer for slow links
python3 ubnt_discover.py --timeout 10

# Also check 192.168.1.20 and other defaults
python3 ubnt_discover.py --check-defaults

# JSON output (for scripts)
python3 ubnt_discover.py --json --check-defaults
```

---

## How to connect your PC

1. Connect Ethernet from your PC to the **same switch / PoE injector LAN** as the dish  
   (or cable **directly** to the dish’s LAN port for a one-to-one link).
2. Turn **VPN off** while scanning.
3. Click **Find Dishes** (or run the CLI).
4. Open the shown IP in a browser: `http://<IP>` or `https://<IP>`  
   Default login on many airOS devices: **`ubnt` / `ubnt`**.

### If nothing is found

| Step | Action |
|------|--------|
| 1 | Set a temporary static IP on your PC: `192.168.1.100` / mask `255.255.255.0` |
| 2 | Run again with `--check-defaults` (probes `192.168.1.20`, etc.) |
| 3 | Increase timeout: `--timeout 10` |
| 4 | Confirm the dish has power (PoE) and the LAN link light is on |
| 5 | Try a different Ethernet cable / PoE injector |

Classic **airMAX factory default IP:** `192.168.1.20`

---

## How it works

```
Your PC                          Ubiquiti Dish
   |                                    |
   |  UDP broadcast → 255.255.255.255:10001
   |  payload: 01 00 00 00 / 02 08 00 00
   |----------------------------------->|
   |                                    |
   |  Discovery reply (TLV: IP, MAC,    |
   |  model, hostname, firmware…)       |
   |<-----------------------------------|
   |                                    |
   |  (optional) TCP probe default IPs  |
```

No agent is installed on the dish. Discovery is the same family of protocol used by Ubiquiti’s own discovery / WiFiman-style tools.

---

## Files

| File | Purpose |
|------|---------|
| `ubnt_discover.py` | Core discovery engine + CLI |
| `app.py` | Easy web UI (Flask) |
| `requirements.txt` | Python dependencies |
| `launch.sh` / `launch.bat` | One-click start |
| `tests/test_parser.py` | Unit tests for reply parsing |

---

## Official Ubiquiti alternatives

If you prefer vendor tools:

- **[UISP](https://uisp.ui.com/)** — network management / discovery  
- **WiFiman** (mobile) — can discover nearby UniFi / some UBNT devices  
- Legacy **Ubiquiti Discovery Tool** (older airOS installs)

This project is a lightweight, local alternative focused specifically on **finding dish IPs quickly**.

---

## Security note

Discovery replies can include device identity information on the local network. Use this tool only on networks you own or are authorized to manage. Do not expose UDP port 10001 to the public Internet.
