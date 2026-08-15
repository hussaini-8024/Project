import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, getToken } from "../../api";
import { ResourceBar } from "../../components/ResourceBar";

export function AdminHome() {
  const { data } = useQuery({
    queryKey: ["resources"],
    queryFn: () => api<any>("/api/resources"),
    refetchInterval: 5000,
  });
  const labs = useQuery({ queryKey: ["labs"], queryFn: () => api<any[]>("/api/labs") });
  const [live, setLive] = useState<any>(null);

  useEffect(() => {
    const proto = window.location.protocol === "https:" ? "wss" : "ws";
    const ws = new WebSocket(`${proto}://${window.location.host}/ws/monitoring?token=${getToken()}`);
    ws.onmessage = (ev) => setLive(JSON.parse(ev.data));
    return () => ws.close();
  }, []);

  const host = live || data?.host;
  const occ = live || data?.occupancy;
  if (!data) return <div>Loading range telemetry…</div>;

  return (
    <div className="space-y-6">
      <div>
        <div className="text-xs uppercase tracking-[0.2em] text-cyan-glow">Administrator</div>
        <h1 className="text-3xl font-semibold">Cyber Range overview</h1>
      </div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Tile label="CPU" value={`${host?.cpu_percent ?? data.host.cpu_percent}%`} />
        <Tile label="RAM" value={`${host?.ram_percent ?? data.host.ram_percent}%`} />
        <Tile label="Storage" value={`${host?.storage_percent ?? data.host.storage_percent}%`} />
        <Tile label="Level" value={(host?.level || data.host.level).toUpperCase()} />
      </div>
      <div className="card space-y-4 p-5">
        <ResourceBar label="CPU" value={host?.cpu_percent ?? data.host.cpu_percent} max={100} suffix="%" />
        <ResourceBar label="RAM" value={host?.ram_percent ?? data.host.ram_percent} max={100} suffix="%" />
        <ResourceBar label="Storage" value={host?.storage_percent ?? data.host.storage_percent} max={100} suffix="%" />
      </div>
      <div className="grid gap-3 md:grid-cols-5">
        <Tile label="Registered students" value={occ?.registered_students ?? data.occupancy.registered_students} />
        <Tile label="Logged-in" value={occ?.logged_in ?? data.occupancy.logged_in} />
        <Tile label="Active labs" value={occ?.active_labs ?? data.occupancy.active_labs} />
        <Tile label="Containers" value={occ?.running_containers ?? data.occupancy.running_containers} />
        <Tile label="Full VMs" value={occ?.running_vms ?? data.occupancy.running_vms} />
      </div>
      <div className="card p-5 text-sm text-slate-400">
        <div className="font-semibold text-slate-200">Capacity policy (engineering targets, not guarantees)</div>
        <p className="mt-2">
          Safe container labs: {data.capacity.safe_container_labs} · Safe VM users: {data.capacity.safe_full_vm_users}.
          Host reserve {data.occupancy.host_reserve_ram_mb} MB is never given to student workloads.
        </p>
        <p className="mt-2">{data.occupancy.note}</p>
      </div>
      <div className="card p-5">
        <h2 className="font-semibold">Student laboratories</h2>
        <div className="mt-3 space-y-2 text-sm">
          {(labs.data || []).map((l) => (
            <div key={l.id} className="flex justify-between rounded-lg border border-white/5 px-3 py-2">
              <div>
                <div className="font-medium">{l.student}</div>
                <div className="font-mono text-xs text-slate-500">{l.public_id}</div>
              </div>
              <div className="text-slate-400">{l.machines.length} machines</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function Tile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="card p-4">
      <div className="text-xs uppercase text-slate-400">{label}</div>
      <div className="mt-1 font-mono text-2xl">{value}</div>
    </div>
  );
}
