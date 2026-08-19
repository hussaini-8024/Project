# LocalMesh architecture

## Package map

```
com.localmesh.chat
  core/crypto        Identity, AES-GCM, ECDH, HKDF, group/private sessions
  core/protocol      Packet types, binary codec, factory
  core/networking    Framed TCP/RFCOMM IO, handshake, transport interface
  core/discovery     Endpoint records
  core/routing       Duplicate cache, routing table, mesh router
  core/security      Validation, rate limiting
  core/storage       Settings DataStore (not chat history)
  data/repositories  In-memory peers, messages, chat requests
  domain/*           Strongly typed models
  services/wifi      NSD + UDP multicast + TCP
  services/bluetooth BLE advertise/scan + RFCOMM
  services/routing   MeshEngine (bridge + dispatch)
  ui/*               Compose screens + ViewModels
```

UI never opens sockets. `MeshEngine` owns transports.

## Peer discovery

1. **Wi-Fi:** NSD `_localmesh._tcp` plus UDP multicast beacons on `239.55.55.55:47822`.
2. **Bluetooth:** BLE advertisements with an 8-byte user-id prefix, then RFCOMM (`createInsecureRfcommSocketToServiceRecord`).
3. After a TCP/RFCOMM connect, both sides exchange signed `HELLO` / `HELLO_ACK` containing display name, identity public key, key-agreement public key, ephemeral ECDH public key, and capability bits.
4. User IDs are `SHA-256(identity public key)`. Display names are never authentication.

## Wi-Fi transport

* TCP listen on port **47821**.
* Length-prefixed frames, then AES-256-GCM after handshake.
* Recovers from socket errors by dropping the session and waiting for the next beacon/NSD resolve.
* Internet connectivity is not used. `INTERNET` permission is required by Android for local sockets.

## Bluetooth transport

* RFCOMM server + client, shared UUID.
* BLE used only for discovery.
* If the adapter is missing or off, the transport reports unavailable and the rest of the app continues.

## Bridge / routing

A device with Wi-Fi and Bluetooth up, plus at least one peer on each medium, sets **BRIDGE ACTIVE**.

Incoming packets:

1. Size/version/TTL/timestamp checks
2. Rate limit per neighbor
3. ECDSA verify
4. Duplicate-id cache
5. Local delivery if group or destined to us
6. Forward/flood with TTL-1, never back to the ingress neighbor

Identities are User IDs, not IP addresses. Routes expire.

## Default local group

There is no join/create UI. Group id is the constant `localmesh.local-network`. Every running LocalMesh node participates. Bluetooth-only nodes receive group frames via a dual-homed forwarder.

## Private chat requests

`CHAT_REQUEST` → peer UI Accept/Reject → `CHAT_ACCEPT` / `CHAT_REJECT`. Only `ACCEPTED` opens a pairwise AES-GCM session. Rejected requests never create a thread.

## One account per device

`AndroidIdentityStore` refuses `create()` if an identity already exists. Settings → Account → Delete Account wipes Keystore aliases, wrapped KA key, DataStore, and in-memory state.
