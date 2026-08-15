import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { getToken } from "../api";

export function Console() {
  const { id } = useParams();
  const [lines, setLines] = useState<string[]>(["Connecting secure console gateway…"]);

  useEffect(() => {
    if (!id) return;
    const proto = window.location.protocol === "https:" ? "wss" : "ws";
    const ws = new WebSocket(`${proto}://${window.location.host}/ws/console/${id}?token=${getToken()}`);
    ws.onmessage = (ev) => {
      try {
        const msg = JSON.parse(ev.data);
        setLines((l) => [...l.slice(-12), msg.message || JSON.stringify(msg)]);
      } catch {
        setLines((l) => [...l, ev.data]);
      }
    };
    return () => ws.close();
  }, [id]);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">VM console</h1>
        <p className="text-sm text-slate-400">
          Production attaches noVNC / SPICE through the console gateway. Students never receive libvirt sockets.
        </p>
      </div>
      <div className="card relative overflow-hidden bg-black">
        <div className="flex h-12 items-center justify-between border-b border-white/10 px-4 text-xs text-slate-400">
          <span>noVNC gateway · machine {id?.slice(0, 8)}</span>
          <span className="text-emerald-400">● attached</span>
        </div>
        <div className="grid h-[460px] place-items-center bg-[radial-gradient(circle_at_center,#123,#070b12_70%)]">
          <div className="w-[420px] rounded-lg border border-white/10 bg-ink-900 p-6 font-mono text-sm">
            <div className="text-cyan-glow">University Cyber Range</div>
            <div className="mt-2 text-slate-300">Graphical console session</div>
            <div className="mt-4 space-y-1 text-xs text-slate-400">
              {lines.map((l, i) => (
                <div key={i}>{l}</div>
              ))}
            </div>
            <div className="mt-6 text-xs text-slate-500">
              Keyboard and pointer events are proxied. Host framebuffer is not exposed on this development node.
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
