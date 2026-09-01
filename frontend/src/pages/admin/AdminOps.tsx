import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "../../api";

export function AdminOps() {
  const qc = useQueryClient();
  const audit = useQuery({ queryKey: ["audit"], queryFn: () => api<any[]>("/api/audit") });
  const backups = useQuery({ queryKey: ["backups"], queryFn: () => api<any[]>("/api/backups") });
  const settings = useQuery({ queryKey: ["settings"], queryFn: () => api<any>("/api/settings") });
  const create = useMutation({
    mutationFn: () => api("/api/backups", { method: "POST", body: JSON.stringify({ kind: "database" }) }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["backups"] }),
  });
  const restore = useMutation({
    mutationFn: (id: string) => api(`/api/backups/${id}/restore`, { method: "POST" }),
  });

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-semibold">Audit, backups & system settings</h1>
      <div className="card p-5 text-sm">
        <h2 className="font-semibold">System</h2>
        <pre className="mt-3 overflow-auto text-xs text-slate-400">{JSON.stringify(settings.data, null, 2)}</pre>
      </div>
      <div className="card p-5">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold">Backups</h2>
          <button className="btn-primary" onClick={() => create.mutate()}>
            Create backup
          </button>
        </div>
        <div className="mt-3 space-y-2 text-sm">
          {(backups.data || []).map((b) => (
            <div key={b.id} className="flex items-center justify-between rounded-lg border border-white/5 px-3 py-2">
              <div>
                <div className="font-medium">
                  {b.name} · {b.kind}
                </div>
                <div className="text-xs text-slate-500">
                  {b.size_mb} MB · {b.status} · {b.created_at}
                </div>
              </div>
              <button className="btn-ghost" onClick={() => restore.mutate(b.id)}>
                Restore
              </button>
            </div>
          ))}
        </div>
      </div>
      <div className="card overflow-hidden">
        <div className="border-b border-white/10 px-4 py-3 font-semibold">Immutable audit log</div>
        <div className="max-h-[480px] overflow-auto">
          <table className="w-full text-left text-xs">
            <thead className="sticky top-0 bg-ink-900 text-slate-400">
              <tr>
                <th className="px-3 py-2">Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Resource</th>
                <th>Result</th>
              </tr>
            </thead>
            <tbody>
              {(audit.data || []).map((a) => (
                <tr key={a.id} className="border-t border-white/5">
                  <td className="px-3 py-2 font-mono">{a.timestamp}</td>
                  <td>
                    {a.user}
                    <div className="text-slate-500">{a.role}</div>
                  </td>
                  <td>{a.action}</td>
                  <td className="font-mono">{a.resource}</td>
                  <td>{a.result}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
