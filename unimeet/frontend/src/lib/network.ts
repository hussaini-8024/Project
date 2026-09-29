import { Room, Track } from "livekit-client";
import type { NetworkSnapshot, QualityAction } from "../types";

let lastBytes = 0;
let lastAt = 0;

function publisherPc(room: Room): RTCPeerConnection | undefined {
  const engine = (room as unknown as {
    engine?: { pcManager?: { publisher?: { pc?: RTCPeerConnection } } };
  }).engine;
  return engine?.pcManager?.publisher?.pc;
}

export async function collectRoomStats(room: Room): Promise<NetworkSnapshot> {
  const snapshot: NetworkSnapshot = {
    latencyMs: 40,
    jitterMs: 4,
    packetLoss: 0,
    bitrateKbps: 0,
    fps: 0,
    width: 0,
    height: 0,
  };

  const pub = [...room.localParticipant.videoTrackPublications.values()].find(
    (item) => item.source === Track.Source.Camera,
  );
  const settings = pub?.track?.mediaStreamTrack?.getSettings();
  if (settings) {
    snapshot.width = settings.width ?? 0;
    snapshot.height = settings.height ?? 0;
    snapshot.fps = settings.frameRate ?? 0;
  }

  const pc = publisherPc(room);
  if (!pc) return snapshot;

  const report = await pc.getStats();
  let bytes = 0;
  report.forEach((stat) => {
    if (stat.type === "remote-inbound-rtp") {
      snapshot.latencyMs = Math.round((stat.roundTripTime ?? 0.04) * 1000);
      snapshot.jitterMs = Math.round((stat.jitter ?? 0.004) * 1000);
      snapshot.packetLoss = stat.fractionLost ?? 0;
    }
    if (stat.type === "outbound-rtp" && stat.kind === "video") {
      bytes = stat.bytesSent ?? 0;
      snapshot.fps = stat.framesPerSecond ?? snapshot.fps;
      snapshot.width = stat.frameWidth ?? snapshot.width;
      snapshot.height = stat.frameHeight ?? snapshot.height;
    }
  });

  const now = Date.now();
  if (lastAt && bytes >= lastBytes) {
    snapshot.bitrateKbps = Math.round(((bytes - lastBytes) * 8) / (now - lastAt));
  }
  lastBytes = bytes;
  lastAt = now;
  return snapshot;
}

export async function applyQualityAction(room: Room, action: QualityAction, previous?: QualityAction) {
  if (
    previous &&
    previous.video === action.video &&
    previous.width === action.width &&
    previous.height === action.height &&
    previous.fps === action.fps
  ) {
    return;
  }

  if (!action.video) {
    if (room.localParticipant.isCameraEnabled) {
      await room.localParticipant.setCameraEnabled(false);
    }
    return;
  }

  await room.localParticipant.setCameraEnabled(true, {
    resolution: {
      width: action.width,
      height: action.height,
      frameRate: action.fps,
    },
  });
}
