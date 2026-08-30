import type { NetworkSnapshot, QualityAction, QualityTier } from "../types";

export function classifyNetwork(snapshot: NetworkSnapshot, simulated?: QualityTier | null): QualityTier {
  if (simulated) return simulated;
  const loss = snapshot.packetLoss * 100;
  const rtt = snapshot.latencyMs;
  if (rtt > 800 || loss > 15) return "critical";
  if (rtt > 400 || loss > 8) return "very_poor";
  if (rtt > 220 || loss > 4) return "poor";
  if (rtt > 120 || loss > 1.5) return "good";
  return "excellent";
}

export function decideAction(
  tier: QualityTier,
  smart720: boolean,
  audioPriority: boolean,
): QualityAction {
  if (audioPriority || tier === "critical") {
    return { video: false, width: 0, height: 0, fps: 0, bitrate: 0, label: "Audio priority — video paused" };
  }
  if (tier === "excellent") {
    return { video: true, width: 1920, height: 1080, fps: 30, bitrate: 2500, label: "1080p / 30 FPS" };
  }
  if (tier === "good") {
    return { video: true, width: 1280, height: 720, fps: 30, bitrate: 1500, label: "720p / 30 FPS" };
  }
  if (tier === "poor") {
    if (smart720) {
      return {
        video: true,
        width: 1280,
        height: 720,
        fps: 18,
        bitrate: 700,
        label: "Smart 720p — hold resolution, reduce FPS/bitrate",
      };
    }
    return { video: true, width: 854, height: 480, fps: 20, bitrate: 600, label: "480p / reduced bitrate" };
  }
  return { video: true, width: 854, height: 480, fps: 15, bitrate: 400, label: "480p — last resort" };
}

export const TIER_COPY: Record<QualityTier, string> = {
  excellent: "Excellent",
  good: "Good",
  poor: "Poor",
  very_poor: "Very poor",
  critical: "Critical",
};
