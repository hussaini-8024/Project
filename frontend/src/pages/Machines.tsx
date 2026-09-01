import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { api, type Machine } from "../api";
import { StatusBadge } from "../components/StatusBadge";

export function Machines() {
  const qc = useQueryClient();
  const { data = [] } = useQuery({ queryKey: ["machines"], queryFn: () => api<Machine[]>("/api/machines") });
  const act = useMutation({
    mutationFn: ({ id, op }: { id: string; op: string }) =>
      op === "delete"
        ? api(`/api/machines/${id}`, { method: "DELETE" })
        : api(`/api/machines/${id}/${op}`, { method: "POST" }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["machines"] });
      void qc.invalidateQueries({ queryKey: ["lab"] });
    },
  });

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-semibold">Machines</h1>
          <p className="text-slate-400">Lifecycle controls stay inside your isolated lab.</p>
        </div>
        <Link className="btn-primary" to="/machines/create">
          Create machine
        </Link>
      </div>
      <div className="card overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="bg-white/5 text-xs uppercase text-slate-400">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th>Kind</th>
              <th>Status</th>
              <th>Resources</th>
              <th>Address</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {data.map((m) => (
              <tr key={m.id} className="border-t border-white/5">
                <td className="px-4 py-3">
                  <div className="font-medium">{m.name}</div>
                  <div className="font-mono text-xs text-slate-500">{m.public_id}</div>
                </td>
                <td className="capitalize">{m.kind}</td>
                <td>
                  <StatusBadge status={m.status} />
                  {m.queue_position && (
                    <div className="mt-1 text-xs text-violet-300">
                      Queue #{m.queue_position} · ~wait · {m.queue_reason}
                    </div>
                  )}
                </td>
                <td className="font-mono text-xs">
                  {m.vcpu} vCPU / {m.ram_mb} MB / {m.disk_gb} GB
                </td>
                <td className="font-mono text-xs">{m.ip || "—"}</td>
                <td className="space-x-1 pr-3 text-right">
                  <button className="btn-ghost" onClick={() => act.mutate({ id: m.id, op: "start" })}>
                    Start
                  </button>
                  <button className="btn-ghost" onClick={() => act.mutate({ id: m.id, op: "stop" })}>
                    Stop
                  </button>
                  <button className="btn-ghost" onClick={() => act.mutate({ id: m.id, op: "restart" })}>
                    Restart
                  </button>
                  <Link className="btn-ghost" to={`/terminal/${m.id}`}>
                    Term
                  </Link>
                  <Link className="btn-ghost" to={`/console/${m.id}`}>
                    TTY
                  </Link>
                  <button className="btn-danger" onClick={() => act.mutate({ id: m.id, op: "delete" })}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
