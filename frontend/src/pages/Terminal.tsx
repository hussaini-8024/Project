import { useEffect, useRef } from "react";
import { useParams } from "react-router-dom";
import { Terminal as XTerm } from "@xterm/xterm";
import { FitAddon } from "@xterm/addon-fit";
import "@xterm/xterm/css/xterm.css";
import { getToken } from "../api";

export function Terminal() {
  const { id } = useParams();
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!ref.current || !id) return;
    const term = new XTerm({
      cursorBlink: true,
      fontFamily: "IBM Plex Mono, monospace",
      fontSize: 14,
      theme: { background: "#070b12", foreground: "#d7e2f0", cursor: "#3ee0c8" },
    });
    const fit = new FitAddon();
    term.loadAddon(fit);
    term.open(ref.current);
    const tryFit = () => {
      try {
        if (ref.current && ref.current.clientHeight > 0) fit.fit();
      } catch {
        /* terminal not measured yet */
      }
    };
    requestAnimationFrame(tryFit);
    const proto = window.location.protocol === "https:" ? "wss" : "ws";
    const ws = new WebSocket(`${proto}://${window.location.host}/ws/terminal/${id}?token=${getToken()}`);
    ws.onmessage = (ev) => term.write(ev.data);
    ws.onerror = () => term.writeln("\r\n\x1b[31mGateway connection failed.\x1b[0m");
    term.onData((data) => {
      if (ws.readyState === WebSocket.OPEN) ws.send(data);
    });
    const onResize = () => tryFit();
    window.addEventListener("resize", onResize);
    return () => {
      window.removeEventListener("resize", onResize);
      ws.close();
      term.dispose();
    };
  }, [id]);

  return (
    <div className="space-y-3">
      <div>
        <h1 className="text-2xl font-semibold">Browser terminal</h1>
        <p className="text-sm text-slate-400">
          HTTPS/WSS → terminal gateway → student container/VM. The virtualization host is not reachable.
        </p>
      </div>
      <div className="card overflow-hidden p-2">
        <div ref={ref} className="h-[520px] w-full" />
      </div>
    </div>
  );
}
