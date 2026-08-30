import {
  ControlBar,
  GridLayout,
  LiveKitRoom,
  ParticipantTile,
  RoomAudioRenderer,
  useParticipants,
  useRoomContext,
  useTracks,
  Chat,
} from "@livekit/components-react";
import "@livekit/components-styles";
import { Track } from "livekit-client";
import { useEffect, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ApiError, api } from "../api/client";
import { applyQualityAction, collectRoomStats } from "../lib/network";
import { classifyNetwork, decideAction, TIER_COPY } from "../lib/quality";
import type { ClassSession, NetworkSnapshot, QualityAction, QualityTier } from "../types";
import { useAuth } from "../auth/AuthContext";

interface TokenResponse {
  token: string;
  url: string;
  roomName: string;
  class: ClassSession;
}

export function ClassroomPage() {
  const { classId } = useParams();
  const { user } = useAuth();
  const [session, setSession] = useState<TokenResponse | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const [smart720, setSmart720] = useState(true);
  const [audioPriority, setAudioPriority] = useState(false);
  const [simulated, setSimulated] = useState<QualityTier | "">("");
  const left = useRef(false);

  useEffect(() => {
    if (!classId) return;
    api<TokenResponse>("/api/livekit/token", {
      method: "POST",
      body: JSON.stringify({ classId: Number(classId) }),
    })
      .then(setSession)
      .catch((err) => setError(err instanceof ApiError ? err : new ApiError(500, "Join failed", "error", {})));

    return () => {
      if (left.current || user?.role !== "student") return;
      left.current = true;
      void api("/api/attendance/leave", {
        method: "POST",
        body: JSON.stringify({ classId: Number(classId) }),
      }).catch(() => undefined);
    };
  }, [classId, user?.role]);

  if (error) {
    return (
      <div className="card denied">
        <p className="badge insufficient">Access denied</p>
        <h1>{error.message}</h1>
        <p className="muted">
          UniMeet checked login, role, enrollment, and whether the class is active on the server before
          issuing a LiveKit token. React never decides this by itself.
        </p>
        <Link className="btn btn-primary" to="/dashboard">
          Return to dashboard
        </Link>
      </div>
    );
  }

  if (!session) return <p className="muted" style={{ padding: 32 }}>Authorizing classroom entry…</p>;

  return (
    <div className="classroom" data-lk-theme="default">
      <LiveKitRoom
        token={session.token}
        serverUrl={session.url}
        connect
        audio
        video={{ resolution: { width: 1280, height: 720, frameRate: 30 } }}
        options={{ adaptiveStream: true, dynacast: true }}
        onDisconnected={() => {
          if (user?.role === "student" && !left.current) {
            left.current = true;
            void api("/api/attendance/leave", {
              method: "POST",
              body: JSON.stringify({ classId: Number(classId) }),
            }).catch(() => undefined);
          }
        }}
        style={{ height: "100%" }}
      >
        <ClassroomChrome
          session={session}
          smart720={smart720}
          setSmart720={setSmart720}
          audioPriority={audioPriority}
          setAudioPriority={setAudioPriority}
          simulated={simulated}
          setSimulated={setSimulated}
        />
      </LiveKitRoom>
    </div>
  );
}

function ClassroomChrome({
  session,
  smart720,
  setSmart720,
  audioPriority,
  setAudioPriority,
  simulated,
  setSimulated,
}: {
  session: TokenResponse;
  smart720: boolean;
  setSmart720: (value: boolean) => void;
  audioPriority: boolean;
  setAudioPriority: (value: boolean) => void;
  simulated: QualityTier | "";
  setSimulated: (value: QualityTier | "") => void;
}) {
  const tracks = useTracks(
    [
      { source: Track.Source.Camera, withPlaceholder: true },
      { source: Track.Source.ScreenShare, withPlaceholder: false },
    ],
    { onlySubscribed: false },
  );

  return (
    <>
      <header className="classroom-top">
        <div>
          <strong>
            {session.class.course_code} · {session.class.course_name}
          </strong>
          <div className="muted">{session.class.title}</div>
        </div>
        <div className="row">
          <label className="toggle">
            <input type="checkbox" checked={smart720} onChange={(e) => setSmart720(e.target.checked)} />
            Smart Quality: {smart720 ? "ON" : "OFF"}
          </label>
          <label className="toggle">
            <input type="checkbox" checked={audioPriority} onChange={(e) => setAudioPriority(e.target.checked)} />
            Audio priority
          </label>
          <select value={simulated} onChange={(e) => setSimulated(e.target.value as QualityTier | "")}>
            <option value="">Live network</option>
            <option value="excellent">Simulate excellent</option>
            <option value="good">Simulate good</option>
            <option value="poor">Simulate poor</option>
            <option value="very_poor">Simulate very poor</option>
            <option value="critical">Simulate critical</option>
          </select>
          <Link className="btn btn-danger" to="/dashboard">
            Leave
          </Link>
        </div>
      </header>
      <div className="classroom-body">
        <div className="stage">
          <GridLayout tracks={tracks} style={{ height: "100%" }}>
            <ParticipantTile />
          </GridLayout>
        </div>
        <aside className="side-dock">
          <QualityHud
            classId={session.class.id}
            smart720={smart720}
            audioPriority={audioPriority}
            simulated={simulated || null}
          />
          <ParticipantsList />
          <div style={{ minHeight: 220 }}>
            <Chat />
          </div>
        </aside>
      </div>
      <footer className="classroom-bottom">
        <ControlBar variation="minimal" />
        <RoomAudioRenderer />
      </footer>
    </>
  );
}

function ParticipantsList() {
  const participants = useParticipants();
  return (
    <div className="dock-card">
      <strong>Participants · {participants.length}</strong>
      {participants.map((p) => (
        <div key={p.identity} className="metric">
          <span>{p.name || p.identity}</span>
          <span>{p.isCameraEnabled ? "Cam" : "Cam off"} · {p.isMicrophoneEnabled ? "Mic" : "Muted"}</span>
        </div>
      ))}
    </div>
  );
}

function QualityHud({
  classId,
  smart720,
  audioPriority,
  simulated,
}: {
  classId: number;
  smart720: boolean;
  audioPriority: boolean;
  simulated: QualityTier | null;
}) {
  const room = useRoomContext();
  const [snapshot, setSnapshot] = useState<NetworkSnapshot | null>(null);
  const [tier, setTier] = useState<QualityTier>("good");
  const [action, setAction] = useState<QualityAction | null>(null);
  const previous = useRef<QualityAction | undefined>(undefined);
  const reportAt = useRef(0);

  useEffect(() => {
    const timer = window.setInterval(async () => {
      const stats = await collectRoomStats(room);
      const nextTier = classifyNetwork(stats, simulated);
      const nextAction = decideAction(nextTier, smart720, audioPriority);
      await applyQualityAction(room, nextAction, previous.current);
      previous.current = nextAction;
      setSnapshot(stats);
      setTier(nextTier);
      setAction(nextAction);
      if (Date.now() - reportAt.current > 8000) {
        reportAt.current = Date.now();
        void api("/api/network/sample", {
          method: "POST",
          body: JSON.stringify({
            classId,
            packetLoss: stats.packetLoss,
            latencyMs: stats.latencyMs,
            jitterMs: stats.jitterMs,
            fps: stats.fps,
            bitrateKbps: stats.bitrateKbps,
            resolution: stats.width ? `${stats.width}x${stats.height}` : "",
            qualityTier: nextTier,
          }),
        }).catch(() => undefined);
      }
    }, 2500);
    return () => window.clearInterval(timer);
  }, [room, smart720, audioPriority, simulated, classId]);

  return (
    <div className="hud">
      <div className="row">
        <strong>Network monitor</strong>
        <span className={`badge ${tier}`}>{TIER_COPY[tier]}</span>
      </div>
      <div className="metric">
        <span>Decision</span>
        <span>{action?.label ?? "Measuring…"}</span>
      </div>
      <div className="metric">
        <span>Latency</span>
        <span>{snapshot ? `${snapshot.latencyMs} ms` : "—"}</span>
      </div>
      <div className="metric">
        <span>Packet loss</span>
        <span>{snapshot ? `${(snapshot.packetLoss * 100).toFixed(1)}%` : "—"}</span>
      </div>
      <div className="metric">
        <span>Jitter</span>
        <span>{snapshot ? `${snapshot.jitterMs} ms` : "—"}</span>
      </div>
      <div className="metric">
        <span>Bitrate / FPS</span>
        <span>
          {snapshot ? `${snapshot.bitrateKbps} kbps` : "—"} · {snapshot?.fps ?? 0} fps
        </span>
      </div>
      <div className="metric">
        <span>Resolution</span>
        <span>{snapshot?.width ? `${snapshot.width}×${snapshot.height}` : "—"}</span>
      </div>
    </div>
  );
}
