# Testing

## Automated (JVM)

```bash
cd LocalMeshChat
./gradlew testDebugUnitTest
```

Coverage:

| Test | Expectation |
| --- | --- |
| Packet codec round-trip | Signed packet encodes/decodes; signature verifies |
| Bad magic / oversized | Codec rejects without crashing |
| AES-GCM + ECDH | Round-trip; ECDH is symmetric |
| Group key wrap | Recipient unwraps; outsider cannot |
| Duplicate id | Second ingest is not delivered |
| TTL=1 unicast | Not forwarded forever |
| Group flood | Delivered locally and flooded with TTL-1 |
| Expired / bad version | Validator reject |
| Chat request reject | Private chat stays closed |
| Chat request accept | Private chat opens |

## Device tests

Use physical phones. Emulators usually lack Bluetooth and often have isolated networking.

### TEST 1 — Two phones, same Wi-Fi (no internet required)

Both discover each other. Both show Local Network Chat. Group messages appear once.

### TEST 2 — Two phones, Bluetooth

Enable Bluetooth, grant nearby permissions. BLE find → RFCOMM. Messages work.

### TEST 3 — Bridge

Phone A Wi-Fi only, Phone B Wi-Fi+BT (bridge), Phone C Bluetooth only.  
A group message from A should reach C and the reverse.

### TEST 4 — Several devices

Group messages must not render twice (duplicate cache).

### TEST 5 — Process death

Force-stop, reopen. Identity remains. Discovery resumes. In-memory history is gone (by design).

### TEST 6 / 7 — Disable one radio

Wi-Fi off: Bluetooth path remains. Bluetooth off: Wi-Fi path remains.

### TEST 8 — Airplane mode with Wi-Fi or BT still allowed

If the OS still permits LAN or Bluetooth, LocalMesh still works. No cloud call is made.

### TEST 9 / 10 — Private request

Reject → Open Chat stays unavailable. Accept → encrypted private thread.

### TEST 11 / 12 — Abuse

Malformed frames increment dropped counters. Duplicate message IDs display once.

## Building the APK

```bash
./gradlew assembleDebug
```

Install `app/build/outputs/apk/debug/app-debug.apk` with `adb install -r`.
