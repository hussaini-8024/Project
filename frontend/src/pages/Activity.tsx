import { useQuery } from "@tanstack/react-query";
import { api } from "../api";

export function Activity() {
  const { data = [] } = useQuery({ queryKey: ["activity"], queryFn: () => api<any[]>("/api/activity") });
  return (
    <div className="space-y-5">
      <h1 className="text-3xl font-semibold">Activity</h1>
      <div className="card divide-y divide-white/5">
        {data.map((a, i) => (
          <div key={i} className="flex items-center justify-between px-4 py-3 text-sm">
            <div>
              <div className="font-medium">{a.action}</div>
              <div className="font-mono text-xs text-slate-500">{a.resource}</div>
            </div>
            <div className="text-right text-xs text-slate-400">
              <div>{a.result}</div>
              <div>{new Date(a.timestamp).toLocaleString()}</div>
            </div>
          </div>
        ))}
        {!data.length && <div className="p-6 text-slate-400">No activity yet.</div>}
      </div>
    </div>
  );
}
