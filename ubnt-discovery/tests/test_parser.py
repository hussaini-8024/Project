#!/usr/bin/env python3
"""Unit tests for Ubiquiti discovery payload parsing (no live network needed)."""

import struct
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from ubnt_discover import (  # noqa: E402
    FIELD_ESSID,
    FIELD_FIRMWARE,
    FIELD_HOSTNAME,
    FIELD_MAC,
    FIELD_MAC_AND_IP,
    FIELD_MODEL_FULL,
    FIELD_MODEL_SHORT,
    parse_discovery_payload,
)


def tlv(ftype: int, data: bytes) -> bytes:
    return bytes([ftype]) + struct.pack("!H", len(data)) + data


def build_reply(*fields: bytes) -> bytes:
    body = b"".join(fields)
    # signature 01 00 00 + length byte (approximate)
    return bytes([0x01, 0x00, 0x00, len(body)]) + body


class TestParseDiscovery(unittest.TestCase):
    def test_parse_full_device(self):
        mac = bytes.fromhex("AABBCCDDEEFF")
        ip = bytes([192, 168, 1, 20])
        payload = build_reply(
            tlv(FIELD_MAC, mac),
            tlv(FIELD_MAC_AND_IP, mac + ip),
            tlv(FIELD_HOSTNAME, b"AP-Tower-1"),
            tlv(FIELD_MODEL_SHORT, b"PBE-5AC-Gen2"),
            tlv(FIELD_MODEL_FULL, b"PowerBeam 5AC Gen2"),
            tlv(FIELD_FIRMWARE, b"WA.v8.7.11"),
            tlv(FIELD_ESSID, b"Backhaul-Link"),
        )
        device = parse_discovery_payload(payload, "192.168.1.20")
        self.assertIsNotNone(device)
        assert device is not None
        self.assertEqual(device.ip, "192.168.1.20")
        self.assertEqual(device.mac, "AA:BB:CC:DD:EE:FF")
        self.assertEqual(device.hostname, "AP-Tower-1")
        self.assertEqual(device.model, "PowerBeam 5AC Gen2")
        self.assertEqual(device.model_short, "PBE-5AC-Gen2")
        self.assertEqual(device.firmware, "WA.v8.7.11")
        self.assertEqual(device.essid, "Backhaul-Link")
        self.assertIn("PowerBeam", device.display_model)

    def test_reject_invalid_signature(self):
        self.assertIsNone(parse_discovery_payload(b"\x99\x00\x00\x00", "1.2.3.4"))

    def test_reject_short_payload(self):
        self.assertIsNone(parse_discovery_payload(b"\x01\x00", "1.2.3.4"))

    def test_tlv_ip_when_reply_from_broadcastish(self):
        mac = bytes.fromhex("112233445566")
        ip = bytes([10, 0, 0, 50])
        payload = build_reply(tlv(FIELD_MAC_AND_IP, mac + ip))
        device = parse_discovery_payload(payload, "255.255.255.255")
        self.assertIsNotNone(device)
        assert device is not None
        self.assertEqual(device.ip, "10.0.0.50")
        self.assertEqual(device.mac, "11:22:33:44:55:66")


if __name__ == "__main__":
    unittest.main()
