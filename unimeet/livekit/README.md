# LiveKit for UniMeet

LiveKit is the video/audio SFU. UniMeet never lets a browser invent a room token.

## Local development (recommended first)

Install the official server, then start it in development mode:

```bash
# Linux
curl -sSL https://get.livekit.io | bash

# macOS
brew install livekit
```

```bash
livekit-server --dev --bind 0.0.0.0
```

Development credentials (already in `backend/.env`):

- API key: `devkey`
- API secret: `secret`
- URL: `ws://127.0.0.1:7880`

## Docker

From `unimeet/`:

```bash
docker compose up livekit
```

## Production notes

You will need HTTPS, a public hostname, and TURN/ICE for students behind strict NATs. Keep `LIVEKIT_API_SECRET` only on the UniMeet backend.
