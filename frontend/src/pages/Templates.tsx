import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { api, type Template } from "../api";

export function Templates() {
  const { data = [] } = useQuery({ queryKey: ["templates"], queryFn: () => api<Template[]>("/api/templates") });
  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-3xl font-semibold">Machine templates</h1>
        <p className="text-slate-400">Approved catalog. Vulnerable targets are labeled and isolated.</p>
      </div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {data.map((t) => (
          <div key={t.slug} className="card flex flex-col p-5">
            <div className="text-xs uppercase text-slate-400">
              {t.category} · {t.recommended_kind}
            </div>
            <h3 className="mt-1 text-lg font-semibold">{t.name}</h3>
            <p className="mt-2 flex-1 text-sm text-slate-400">{t.description}</p>
            {t.is_vulnerable_target && (
              <div className="mt-3 text-xs text-amber-300">Training Target — Authorized Laboratory Use Only</div>
            )}
            <div className="mt-3 font-mono text-xs text-slate-500">
              {t.default_vcpu} vCPU · {t.default_ram_mb} MB · {t.default_disk_gb} GB
            </div>
            {t.tools?.length > 0 && (
              <div className="mt-2 flex flex-wrap gap-1">
                {t.tools.map((tool) => (
                  <span key={tool} className="rounded bg-white/5 px-2 py-0.5 text-xs">
                    {tool}
                  </span>
                ))}
              </div>
            )}
            <Link className="btn-primary mt-4" to="/machines/create">
              Use template
            </Link>
          </div>
        ))}
      </div>
    </div>
  );
}
