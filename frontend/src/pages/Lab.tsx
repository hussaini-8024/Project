import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { api, type Machine } from "../api";
import { StatusBadge } from "../components/StatusBadge";

export function Lab() {
  const { data } = useQuery({ queryKey: ["lab"], queryFn: () => api<any>("/api/labs/me") });
  const machines: Machine[] = data?.machines ?? [];
  return (
    <div className="space-y-6">
      <div>
        <div className="text-xs uppercase tracking-[0.2em] text-cyan-glow">Persistent laboratory</div>
        <h1 className="text-3xl font-semibold">{data?.name || "My Lab"}</h1>
        <p className="font-mono text-slate-400">{data?.public_id}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="card p-4">
          <div className="text-xs text-slate-400">Private network</div>
          <div className="mt-1 font-mono">{data?.network?.cidr}</div>
          <div className="text-xs text-slate-500">
            VLAN {data?.network?.vlan_id} · {data?.network?.namespace}
          </div>
        </div>
        <div className="card p-4">
          <div className="text-xs text-slate-400">Isolation</div>
          <div className="mt-1">{data?.network?.isolated ? "Strict — no lab-to-lab traffic" : "Peered"}</div>
          <div className="text-xs text-slate-500">
            Other student labs are unreachable. Ping lab peers by IP or hostname (for example{" "}
            <span className="font-mono">ping dvwa-target</span>).
          </div>
        </div>
        <div className="card p-4">
          <div className="text-xs text-slate-400">Internet</div>
          <div className="mt-1">{data?.internet_enabled ? "Controlled NAT" : "Disabled (default)"}</div>
          <div className="text-xs text-slate-500">Staff can enable outbound NAT</div>
        </div>
      </div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {machines.map((m) => (
          <div key={m.id} className="card p-5">
            <div className="flex items-start justify-between">
              <div>
                <div className="text-xs uppercase text-slate-400">{m.kind}</div>
                <h3 className="text-lg font-semibold">{m.name}</h3>
              </div>
              <StatusBadge status={m.status} />
            </div>
            {m.vulnerable && (
              <div className="mt-3 rounded-lg border border-amber-400/30 bg-amber-400/10 px-3 py-2 text-xs text-amber-200">
                {m.warning_label || "Training Target — Authorized Laboratory Use Only"}
              </div>
            )}
            <div className="mt-3 font-mono text-xs text-slate-400">
              {m.ip} · {m.vcpu} vCPU · {m.ram_mb} MB
            </div>
            <div className="mt-4 flex gap-2">
              <Link className="btn-ghost" to={`/terminal/${m.id}`}>
                Terminal
              </Link>
              <Link className="btn-ghost" to={`/console/${m.id}`}>
                Console
              </Link>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
