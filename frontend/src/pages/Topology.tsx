import { Background, Controls, ReactFlow } from "@xyflow/react";
import "@xyflow/react/dist/style.css";
import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";
import { api, type Machine } from "../api";

export function Topology() {
  const lab = useQuery({ queryKey: ["lab"], queryFn: () => api<any>("/api/labs/me") });
  const machines: Machine[] = lab.data?.machines ?? [];
  const { nodes, edges } = useMemo(() => {
    const ns = [
      {
        id: "net",
        position: { x: 280, y: 20 },
        data: { label: lab.data?.network?.cidr || "Private lab net" },
        style: nodeStyle("#3ee0c8"),
      },
    ];
    const es = [];
    machines.forEach((m, i) => {
      ns.push({
        id: m.id,
        position: { x: 40 + (i % 3) * 240, y: 160 + Math.floor(i / 3) * 120 },
        data: { label: `${m.name}\n${m.ip || m.kind}` },
        style: nodeStyle(m.kind === "vm" ? "#f59e0b" : "#38bdf8"),
      });
      es.push({ id: `e-${m.id}`, source: "net", target: m.id });
    });
    return { nodes: ns, edges: es };
  }, [lab.data, machines]);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-3xl font-semibold">Network topology</h1>
        <p className="text-slate-400">Interactive map of your isolated laboratory. No uplink to other students.</p>
      </div>
      <div className="card h-[560px] overflow-hidden">
        <ReactFlow nodes={nodes} edges={edges} fitView>
          <Background color="#1f2a3d" />
          <Controls />
        </ReactFlow>
      </div>
    </div>
  );
}

function nodeStyle(color: string) {
  return {
    background: "#111b2e",
    color: "#e2e8f0",
    border: `1px solid ${color}`,
    borderRadius: 12,
    padding: 10,
    whiteSpace: "pre-line" as const,
    fontSize: 12,
  };
}
