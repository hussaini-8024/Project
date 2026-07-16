"""Default configuration for legitimate RMM (disclosed sessions)."""

from __future__ import annotations

DEFAULT_SERVER_HOST = "0.0.0.0"
DEFAULT_SERVER_PORT = 8443
DEFAULT_ADMIN_USER = "admin"
DEFAULT_ADMIN_PASSWORD = "ChangeMeNow!"
DEFAULT_ENROLLMENT_TOKEN = "enroll-change-me"

# Heartbeat / presence
AGENT_HEARTBEAT_SECONDS = 10
AGENT_OFFLINE_AFTER_SECONDS = 30

# Live view: frames per second (keep modest for LAN)
LIVE_FPS = 2
LIVE_JPEG_QUALITY = 50
LIVE_MAX_WIDTH = 1280

# Visible on-screen indicator text (must remain shown during live sessions)
MONITOR_BANNER_TEXT = "REMOTE SESSION ACTIVE — IT is viewing this screen"
MONITOR_BANNER_SUBTEXT = "This session is audited. Contact your administrator if unexpected."

# Permanent install / uninstall protection
DEFAULT_UNINSTALL_PASSWORD = "UninstallMe!"

# LAN discovery (agent beacon + server scan)
DISCOVERY_MAGIC = "DiscloseRMM-v1"
DISCOVERY_UDP_PORT = 38443
DISCOVERY_TCP_PORT = 38444
DISCOVERY_INTERVAL_SECONDS = 8
