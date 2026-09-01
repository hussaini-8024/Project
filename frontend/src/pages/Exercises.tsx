import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "../api";
import { useAuth } from "../auth";

export function Exercises() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const { data = [] } = useQuery({ queryKey: ["assignments"], queryFn: () => api<any[]>("/api/assignments") });
  const start = useMutation({
    mutationFn: (id: string) => api(`/api/assignments/${id}/start`, { method: "POST" }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["assignments"] });
      void qc.invalidateQueries({ queryKey: ["machines"] });
    },
  });
  const complete = useMutation({
    mutationFn: (id: string) => api(`/api/assignments/${id}/complete`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["assignments"] }),
  });

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-3xl font-semibold">Exercises & assignments</h1>
        <p className="text-slate-400">Starting an assignment provisions the required isolated environment.</p>
      </div>
      {data.map((a) => (
        <div key={a.id} className="card p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 className="text-lg font-semibold">{a.title}</h3>
              <p className="text-sm text-slate-400">{a.objective || a.description}</p>
              <div className="mt-2 text-xs text-slate-500">
                Required: {a.required_templates.join(", ")} · {a.duration_minutes} minutes · {a.course}
              </div>
            </div>
            <div className="text-right">
              <div className="text-xs uppercase text-slate-400">Status</div>
              <div className="capitalize">{a.status || "visible"}</div>
              {a.grade && <div className="text-cyan-glow">Grade {a.grade}</div>}
            </div>
          </div>
          {user?.role === "student" && (
            <div className="mt-4 flex gap-2">
              <button className="btn-primary" onClick={() => start.mutate(a.id)}>
                Start lab
              </button>
              <button className="btn-ghost" onClick={() => complete.mutate(a.id)}>
                Mark completed
              </button>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
