#!/usr/bin/env python3
"""Drive the capacity suite and print the production recommendation."""

from __future__ import annotations

import argparse
import json
import sys
import urllib.error
import urllib.request


def req(url: str, method: str = "GET", token: str | None = None, body: dict | None = None) -> dict:
    data = None if body is None else json.dumps(body).encode()
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    request = urllib.request.Request(url, data=data, headers=headers, method=method)
    with urllib.request.urlopen(request, timeout=120) as resp:
        return json.loads(resp.read().decode())


def main() -> int:
    p = argparse.ArgumentParser(description="Cyber Range load test")
    p.add_argument("--api", default="http://127.0.0.1:8000")
    p.add_argument("--user", default="admin")
    p.add_argument("--password", default="CyberRange!Admin2026")
    args = p.parse_args()
    try:
        login = req(
            f"{args.api}/api/auth/login",
            "POST",
            body={"username": args.user, "password": args.password},
        )
    except urllib.error.HTTPError as exc:
        print(exc.read().decode(), file=sys.stderr)
        return 1
    token = login["access_token"]
    report = req(f"{args.api}/api/resources/loadtest", "POST", token, {})
    print(json.dumps(report["summary"], indent=2))
    print("\nPer-step results:")
    for row in report["reports"]:
        print(
            f"  {row['students']:>3} students  cpu={row['cpu_utilization']}%  "
            f"ram={row['ram_utilization']}%  result={row['result']}"
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
