import { useQuery } from "@tanstack/react-query";
import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { api } from "../api";
import { ResourceBar } from "../components/ResourceBar";

export function Resources() {
  const { data } = useQuery({
    queryKey: ["resources"],
    queryFn: () => api<any>("/api/resources"),
    refetchInterval: 4000,
  });
  const usage = useQuery({ queryKey: ["usage"], queryFn: () => api<any>("/api/users/me/usage") });
  if (!data) return null;
  const chart = [
    { n: "CPU", v: data.host.cpu_percent },
    { n: "RAM", v: data.host.ram_percent },
    { n: "Disk", v: data.host.storage_percent },
  ];
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Resource usage</h1>
        <p className="text-slate-400">
          Logged-in users are not the same as active labs. Capacity is measured, not hard-coded.
        </p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <ResourceBar label="Host CPU" value={data.host.cpu_percent} max={100} suffix="%" />
        <ResourceBar label="Host RAM" value={data.host.ram_percent} max={100} suffix="%" />
        <ResourceBar label="Storage" value={data.host.storage_percent} max={100} suffix="%" />
      </div>
      <div className="card h-64 p-4">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={chart}>
            <XAxis dataKey="n" stroke="#94a3b8" />
            <YAxis stroke="#94a3b8" />
            <Tooltip />
            <Area dataKey="v" stroke="#3ee0c8" fill="#3ee0c833" />
          </AreaChart>
        </ResponsiveContainer>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <div className="card p-5">
          <h2 className="font-semibold">Your consumption</h2>
          <div className="mt-3 space-y-3">
            <ResourceBar label="RAM" value={usage.data?.ram_mb || 0} max={usage.data?.quota?.max_ram_mb || 1} suffix="MB" />
            <ResourceBar label="vCPU" value={usage.data?.vcpu || 0} max={usage.data?.quota?.max_vcpu || 1} />
          </div>
        </div>
        <div className="card p-5 text-sm">
          <h2 className="font-semibold">Capacity manager</h2>
          <p className="mt-2 text-slate-400">{data.capacity.disclaimer}</p>
          <ul className="mt-3 space-y-1 font-mono text-xs">
            <li>safe active students: {data.capacity.safe_concurrent_active_students}</li>
            <li>safe container labs: {data.capacity.safe_container_labs}</li>
            <li>safe full VM users: {data.capacity.safe_full_vm_users}</li>
          </ul>
        </div>
      </div>
    </div>
  );
}
