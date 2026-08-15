import { useQuery } from "@tanstack/react-query";
import { api } from "../api";

export function Images() {
  const images = useQuery({ queryKey: ["images"], queryFn: () => api<any[]>("/api/images") });
  const isos = useQuery({ queryKey: ["isos"], queryFn: () => api<any[]>("/api/isos") });
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Docker images & ISO repository</h1>
        <p className="text-slate-400">Shared base images use copy-on-write. Students select approved ISOs only.</p>
      </div>
      <div className="card overflow-hidden">
        <div className="border-b border-white/10 px-4 py-3 font-semibold">Shared container images</div>
        <table className="w-full text-sm">
          <thead className="text-left text-xs uppercase text-slate-400">
            <tr>
              <th className="px-4 py-2">Image</th>
              <th>Tag</th>
              <th>Size</th>
              <th>Shared</th>
            </tr>
          </thead>
          <tbody>
            {(images.data || []).map((i) => (
              <tr key={i.id} className="border-t border-white/5">
                <td className="px-4 py-2 font-mono">{i.name}</td>
                <td>{i.tag}</td>
                <td>{i.size_mb} MB</td>
                <td>{i.shared ? "CoW layers" : "private"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="card overflow-hidden">
        <div className="border-b border-white/10 px-4 py-3 font-semibold">Approved ISOs</div>
        <table className="w-full text-sm">
          <thead className="text-left text-xs uppercase text-slate-400">
            <tr>
              <th className="px-4 py-2">Name</th>
              <th>Status</th>
              <th>SHA-256</th>
              <th>Size</th>
            </tr>
          </thead>
          <tbody>
            {(isos.data || []).map((i) => (
              <tr key={i.id} className="border-t border-white/5">
                <td className="px-4 py-2">{i.name}</td>
                <td className="capitalize">{i.status}</td>
                <td className="font-mono text-xs">{i.sha256.slice(0, 16)}…</td>
                <td>{(i.size_bytes / 1e9).toFixed(1)} GB</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
