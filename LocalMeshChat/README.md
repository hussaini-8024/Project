# LocalMesh Chat

Serverless, local-first Android chat for nearby devices. No internet, no cloud, no Firebase, no central server.

Open the app → create a display name once → automatically join **Local Network Chat**. Nearby phones discover each other over Wi-Fi and Bluetooth. A phone that has both can **forward encrypted packets** between the two (application-level bridge, not a kernel/IP bridge).

## What this is

* One cryptographic identity per physical installation
* Default group chat for the discovered local mesh
* Private chats only after an accept/reject request
* AES-256-GCM payloads, ECDSA P-256 identities, Android Keystore
* Messages kept in memory only (not a chat history database)

## Open in Android Studio

1. Open the `LocalMeshChat/` folder as a Gradle project.
2. Install SDK 35 and Build-Tools 35.
3. Sync, run on a physical device (Wi-Fi + Bluetooth work best on hardware).

## Command-line debug APK

```bash
cd LocalMeshChat
export ANDROID_HOME=/path/to/android-sdk
./gradlew assembleDebug
```

APK:

`app/build/outputs/apk/debug/app-debug.apk`

Install on two or more phones, create names, grant nearby/Bluetooth permissions, and send a group message.

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/CRYPTO.md](docs/CRYPTO.md), and [docs/TESTING.md](docs/TESTING.md).

## Android limitations (not faked)

* This is **not** a system VPN or IP router. Device B forwards **LocalMesh packets**, not arbitrary IP traffic.
* Some Wi-Fi APs isolate clients (mDNS/UDP multicast fail). Bluetooth still works; try a non-isolated LAN.
* Bluetooth RFCOMM connection count is limited by the OS/stack.
* Keystore ECDH is only on API 31+. This app stores a wrapping AES key in Keystore and a static P-256 key-agreement key wrapped on disk, with the **signing** key in Keystore on API 26+.
* Insecure RFCOMM is used so users are not forced through OS pairing. Authentication is application cryptography, not Bluetooth pairing.

## Security goal for history

The app **does not intentionally persist chat messages**. Android screenshots, notifications, RAM, backups, and OEM logging are outside complete app control.
