import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "../../api";
import { ResourceBar } from "../../components/ResourceBar";

export function AdminInfra() {
  const qc = useQueryClient();
  const sched = useQuery({ queryKey: ["scheduler"], queryFn: () => api<any>("/api/resources/scheduler") });
  const storage = useQuery({ queryKey: ["storage"], queryFn: () => api<any>("/api/storage") });
  const quotas = useQuery({ queryKey: ["quotas"], queryFn: () => api<any[]>("/api/quotas") });
  const load = useMutation({
    mutationFn: () => api<any>("/api/resources/loadtest", { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["load"] }),
  });
  const loadSummary = useQuery({ queryKey: ["load"], queryFn: () => api<any>("/api/resources/loadtest") });

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-semibold">Scheduler, storage & capacity</h1>
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="card p-5">
          <h2 className="font-semibold">Compute nodes</h2>
          {(sched.data?.nodes || []).map((n: any) => (
            <div key={n.id} className="mt-3 rounded-lg border border-white/5 p-3 text-sm">
              <div className="font-medium">
                {n.name} · {n.status}
              </div>
              <div className="font-mono text-xs text-slate-400">
                {n.ram_mb} MB · {n.cpu_cores} cores · {n.storage_gb} GB · Docker {n.docker ? "yes" : "no"} · KVM{" "}
                {n.kvm ? "yes" : "no"}
              </div>
            </div>
          ))}
          <h3 className="mt-5 font-semibold">Queue</h3>
          {(sched.data?.queued || []).length === 0 && <p className="mt-2 text-sm text-slate-400">No queued labs.</p>}
          {(sched.data?.queued || []).map((q: any) => (
            <div key={q.id} className="mt-2 text-sm">
              #{q.position} {q.name} ({q.kind}) — {q.reason}
            </div>
          ))}
        </div>
        <div className="card space-y-3 p-5">
          <h2 className="font-semibold">Storage {storage.data?.total_gb} GB</h2>
          {storage.data && (
            <>
              <ResourceBar label="Used" value={storage.data.used_percent} max={100} suffix="%" />
              {Object.entries(storage.data.categories).map(([k, v]) => (
                <div key={k} className="flex justify-between text-sm">
                  <span className="capitalize text-slate-400">{k.replace("_", " ")}</span>
                  <span className="font-mono">{v as number} GB</span>
                </div>
              ))}
            </>
          )}
        </div>
      </div>
      <div className="card p-5">
        <h2 className="font-semibold">Quota profiles</h2>
        <div className="mt-3 grid gap-3 md:grid-cols-3">
          {(quotas.data || []).map((q) => (
            <div key={q.id} className="rounded-lg border border-white/10 p-3 text-sm">
              <div className="font-semibold">{q.name}</div>
              <div className="mt-1 text-slate-400">{q.description}</div>
              <div className="mt-2 font-mono text-xs">
                {q.max_containers} ctr · {q.max_vms} VM · {q.max_ram_mb} MB · {q.max_storage_gb} GB
              </div>
            </div>
          ))}
        </div>
      </div>
      <div className="card p-5">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold">Load testing</h2>
          <button className="btn-primary" onClick={() => load.mutate()} disabled={load.isPending}>
            {load.isPending ? "Running 10–100 students…" : "Run capacity suite"}
          </button>
        </div>
        <p className="mt-2 text-sm text-slate-400">
          Measures CPU, RAM, IOPS, boot time, API/DB/terminal/console/scheduler latency. Results recommend production
          concurrency — they do not guarantee it.
        </p>
        {load.data && (
          <pre className="mt-4 overflow-auto rounded-lg bg-black/40 p-4 text-xs">
            {JSON.stringify(load.data.summary, null, 2)}
          </pre>
        )}
        {loadSummary.data && !load.data && (
          <pre className="mt-4 overflow-auto rounded-lg bg-black/40 p-4 text-xs">
            {JSON.stringify(loadSummary.data, null, 2)}
          </pre>
        )}
      </div>
    </div>
  );
}
