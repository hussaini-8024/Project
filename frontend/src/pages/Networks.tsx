import { useQuery } from "@tanstack/react-query";
import { api } from "../api";

export function Networks() {
  const { data = [] } = useQuery({ queryKey: ["networks"], queryFn: () => api<any[]>("/api/networks") });
  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-3xl font-semibold">Private networks</h1>
        <p className="text-slate-400">Each student receives a namespace, bridge, and VLAN. Lab A cannot reach Lab B.</p>
      </div>
      {data.map((n) => (
        <div key={n.id} className="card p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div className="font-semibold">{n.name}</div>
              <div className="font-mono text-sm text-slate-400">
                {n.cidr} · VLAN {n.vlan_id} · {n.namespace}
              </div>
            </div>
            <div className="text-sm">
              {n.isolated ? "Isolated" : "Peered"} · Internet {n.internet ? "on" : "off"}
            </div>
          </div>
          <table className="mt-4 w-full text-sm">
            <thead className="text-left text-xs uppercase text-slate-400">
              <tr>
                <th className="py-2">Machine</th>
                <th>IPv4</th>
                <th>MAC</th>
              </tr>
            </thead>
            <tbody>
              {n.interfaces.map((i: any) => (
                <tr key={i.mac} className="border-t border-white/5">
                  <td className="py-2">{i.machine}</td>
                  <td className="font-mono">{i.ip}</td>
                  <td className="font-mono text-xs">{i.mac}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ))}
    </div>
  );
}
