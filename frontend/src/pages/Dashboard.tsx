import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { api, type Machine } from "../api";
import { useAuth } from "../auth";
import { StatusBadge } from "../components/StatusBadge";
import { ResourceBar } from "../components/ResourceBar";

export function Dashboard() {
  const { user } = useAuth();
  const lab = useQuery({ queryKey: ["lab"], queryFn: () => api<any>("/api/labs/me") });
  const usage = useQuery({ queryKey: ["usage"], queryFn: () => api<any>("/api/users/me/usage") });
  const res = useQuery({ queryKey: ["resources"], queryFn: () => api<any>("/api/resources") });
  const machines: Machine[] = lab.data?.machines ?? [];
  const running = machines.filter((m) => m.status === "running");
  const stopped = machines.filter((m) => m.status === "stopped");

  return (
    <div className="space-y-6">
      <div>
        <div className="text-xs uppercase tracking-[0.2em] text-cyan-glow">Session</div>
        <h1 className="text-3xl font-semibold">Welcome back, {user?.full_name || user?.username}</h1>
        <p className="mt-1 text-slate-400">
          {user?.public_id} · Lab {user?.lab_id || lab.data?.public_id} · {user?.course || "Unassigned course"}
        </p>
      </div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Stat label="Running machines" value={running.length} />
        <Stat label="Stopped machines" value={stopped.length} />
        <Stat label="Queued" value={machines.filter((m) => m.status === "queued").length} />
        <Stat label="Quota" value={usage.data?.quota?.name || "—"} />
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="card p-5 lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="font-semibold">Laboratory machines</h2>
            <Link className="btn-primary" to="/machines/create">
              Create machine
            </Link>
          </div>
          <div className="space-y-2">
            {machines.map((m) => (
              <div key={m.id} className="flex items-center justify-between rounded-lg border border-white/5 px-3 py-2">
                <div>
                  <div className="font-medium">{m.name}</div>
                  <div className="font-mono text-xs text-slate-400">
                    {m.kind} · {m.ram_mb} MB · {m.ip || "no address"}
                  </div>
                </div>
                <StatusBadge status={m.status} />
              </div>
            ))}
            {!machines.length && <div className="text-sm text-slate-400">No machines yet. Start from a template.</div>}
          </div>
        </div>
        <div className="card space-y-4 p-5">
          <h2 className="font-semibold">Your quota</h2>
          <ResourceBar label="RAM" value={usage.data?.ram_mb || 0} max={usage.data?.quota?.max_ram_mb || 1} suffix="MB" />
          <ResourceBar label="vCPU" value={usage.data?.vcpu || 0} max={usage.data?.quota?.max_vcpu || 1} />
          <ResourceBar label="Storage" value={usage.data?.disk_gb || 0} max={usage.data?.quota?.max_storage_gb || 1} suffix="GB" />
          <div className="text-xs text-slate-500">
            Host pool is shared. The scheduler may queue heavy VMs while still allowing lightweight containers.
          </div>
        </div>
      </div>
      {res.data && (
        <div className="card p-5">
          <h2 className="mb-3 font-semibold">Range telemetry</h2>
          <div className="grid gap-4 md:grid-cols-3">
            <ResourceBar label="CPU" value={res.data.host.cpu_percent} max={100} suffix="%" />
            <ResourceBar label="RAM" value={res.data.host.ram_percent} max={100} suffix="%" />
            <ResourceBar label="Storage" value={res.data.host.storage_percent} max={100} suffix="%" />
          </div>
          <div className="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <Mini k="Logged-in" v={res.data.distinction.logged_in} />
            <Mini k="Active labs" v={res.data.distinction.active_labs} />
            <Mini k="Containers" v={res.data.distinction.running_containers} />
            <Mini k="Full VMs" v={res.data.distinction.running_vms} />
          </div>
        </div>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="card p-4">
      <div className="text-xs uppercase tracking-wide text-slate-400">{label}</div>
      <div className="mt-1 text-2xl font-semibold">{value}</div>
    </div>
  );
}

function Mini({ k, v }: { k: string; v: number }) {
  return (
    <div className="rounded-lg bg-white/5 px-3 py-2">
      <div className="text-xs text-slate-400">{k}</div>
      <div className="font-mono text-lg">{v}</div>
    </div>
  );
}
