#!/usr/bin/env bash
set -euo pipefail
API="${API_URL:-http://127.0.0.1:4000/api}"

login() {
  local id="$1" role="$2"
  curl -sS -X POST "$API/auth/login" \
    -H 'Content-Type: application/json' \
    -d "{\"universityId\":\"$id\",\"password\":\"UniMeet@2026\",\"role\":\"$role\"}"
}

token() {
  python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])'
}

ALI=$(login STU-1001 student | token)
JOHN=$(login STU-1004 student | token)
TEACHER=$(login TCH-2001 teacher | token)

echo "Ali token request (should succeed):"
ALI_JOIN=$(curl -sS -w '\n%{http_code}' -X POST "$API/livekit/token" \
  -H "Authorization: Bearer $ALI" -H 'Content-Type: application/json' \
  -d '{"classId":1}')
echo "$ALI_JOIN"

echo "John token request (should be 403 not_enrolled):"
JOHN_JOIN=$(curl -sS -w '\n%{http_code}' -X POST "$API/livekit/token" \
  -H "Authorization: Bearer $JOHN" -H 'Content-Type: application/json' \
  -d '{"classId":1}')
echo "$JOHN_JOIN"

echo "Teacher token request (should succeed):"
curl -sS -X POST "$API/livekit/token" \
  -H "Authorization: Bearer $TEACHER" -H 'Content-Type: application/json' \
  -d '{"classId":1}' | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("roomName"), d.get("url"))'

echo "AI summary:"
curl -sS -X POST "$API/ai/summarize" \
  -H "Authorization: Bearer $ALI" -H 'Content-Type: application/json' \
  -d '{"classId":1}' | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("engine"), d.get("summary","")[:180])'
