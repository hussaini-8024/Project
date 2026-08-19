# Cryptographic design

LocalMesh uses existing JCA algorithms. It does not invent primitives.

## Identity

* **Signing key:** ECDSA P-256 in Android Keystore (`SHA256withECDSA`). Never exported, never sent.
* **User ID:** SHA-256 of the uncompressed identity public key (65-byte `0x04||X||Y`).
* **Static key-agreement key:** P-256 ECDH key generated in software. The private key is wrapped with an AES-256-GCM key that *does* live in Android Keystore. This is required because Keystore `KeyAgreement` is not available before API 31.

## Link handshake

Each TCP/RFCOMM session:

1. Ephemeral P-256 key pair
2. Signed HELLO containing identity pub, static KA pub, ephemeral pub
3. ECDH(local ephemeral, peer ephemeral)
4. HKDF-SHA256 with a canonical transcript of both identity pubs and both ephemeral pubs
5. Two 256-bit keys (send/recv ordered by User ID)

Further frames on that socket are AES-256-GCM. A bridge therefore cannot read payloads that are also encrypted with group or pairwise keys (and cannot read link-encrypted frames of *other* hops).

## Group messages (LNGK)

Local Network Chat uses a **Local Network Group Key**:

* Random 256-bit AES key + epoch
* Payload: AES-256-GCM(LNGK, UTF-8 body, AAD = sender User ID)
* Outer packet still ECDSA-signed by the sender
* New members receive the key via `GROUP_KEY_WRAP`: ECDH(ephemeral, recipient static KA) → HKDF → AES-GCM wrap of (key||epoch||origin)

Convergence: higher epoch wins; equal epoch prefers the smaller key id (SHA-256 of the key).

This is **not MLS**. It is a practical ad-hoc design for nearby devices. A newly joined member can decrypt subsequent (and wrapped historical key) group traffic for the current epoch. Epoch rotation happens when a node generates a newer key and peers adopt it.

## Private messages

After `CHAT_ACCEPT`:

* Session key = HKDF(ECDH(our static KA, peer static KA), sorted user IDs)
* Body encrypted with AES-256-GCM
* AAD binds both user IDs
* Bridges only forward the signed outer packet; they do not have the pairwise key

## Replay and abuse

* Unique 128-bit message IDs
* Timestamp window ±10 minutes
* Duplicate cache (~2 minutes)
* TTL 1..16
* 32 KiB packet cap / 12 KiB payload cap
* Per-neighbor token bucket
* Safe binary decoder (no Java deserialization)

## What is logged

`SecureLog` never takes message bodies or key material. Release builds omit debug handshake traces.
