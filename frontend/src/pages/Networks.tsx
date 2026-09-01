import { FormEvent, useState } from "react";
import { Link } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type Template } from "../api";
import { useAuth } from "../auth";

type NetRow = {
  id: string;
  name: string;
  cidr: string;
  vlan_id: number;
  namespace: string;
  isolated: boolean;
  internet: boolean;
  bridge: string;
  kind: string;
  lab_id: string | null;
  lab_name: string | null;
  interfaces: { ip: string; mac: string; machine: string | null; machine_id: string | null }[];
};

export function Networks() {
  const { user } = useAuth();
  const staff = Boolean(user && user.role !== "student");
  const qc = useQueryClient();
  const { data = [] } = useQuery({ queryKey: ["networks"], queryFn: () => api<NetRow[]>("/api/networks") });
  const templates = useQuery({
    queryKey: ["templates"],
    queryFn: () => api<Template[]>("/api/templates"),
    enabled: staff,
  });
  const labs = useQuery({
    queryKey: ["labs"],
    queryFn: () => api<any[]>("/api/labs"),
    enabled: staff,
  });
  const students = useQuery({
    queryKey: ["students"],
    queryFn: () => api<any[]>("/api/students"),
    enabled: staff,
  });

  const [createForm, setCreateForm] = useState({
    name: "",
    cidr: "10.0.0.0/8",
    lab_id: "",
    isolated: true,
    internet: false,
  });
  const [deploy, setDeploy] = useState<Record<string, { template_slug: string; name: string; owner_id: string }>>({});

  const createNet = useMutation({
    mutationFn: () =>
      api("/api/networks", {
        method: "POST",
        body: JSON.stringify({
          name: createForm.name,
          cidr: createForm.cidr,
          lab_id: createForm.lab_id || null,
          isolated: createForm.isolated,
          internet: createForm.internet,
        }),
      }),
    onSuccess: () => {
      setCreateForm({ name: "", cidr: "10.0.0.0/8", lab_id: "", isolated: true, internet: false });
      void qc.invalidateQueries({ queryKey: ["networks"] });
      void qc.invalidateQueries({ queryKey: ["lab"] });
    },
  });
  const deployNet = useMutation({
    mutationFn: ({ id, body }: { id: string; body: { template_slug: string; name: string; owner_id: string } }) =>
      api(`/api/networks/${id}/deploy`, {
        method: "POST",
        body: JSON.stringify({
          template_slug: body.template_slug,
          name: body.name || undefined,
          owner_id: body.owner_id || undefined,
        }),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["networks"] });
      void qc.invalidateQueries({ queryKey: ["machines"] });
      void qc.invalidateQueries({ queryKey: ["lab"] });
    },
  });
  const removeNet = useMutation({
    mutationFn: (id: string) => api(`/api/networks/${id}`, { method: "DELETE" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["networks"] }),
  });

  function onCreate(e: FormEvent) {
    e.preventDefault();
    createNet.mutate();
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-3xl font-semibold">Private networks</h1>
        <p className="text-slate-400">
          Student labs use a private <span className="font-mono text-slate-200">10.0.0.0/8</span> range, not a /24.
          Each lab still has its own namespace, bridge, and VLAN, so Lab A cannot reach Lab B.
        </p>
      </div>

      {staff && (
        <form className="card grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-6" onSubmit={onCreate}>
          <div className="xl:col-span-6 text-sm font-medium text-cyan-glow">Create and deploy a network</div>
          <input
            className="input"
            required
            placeholder="Network name"
            value={createForm.name}
            onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })}
          />
          <input
            className="input font-mono"
            required
            placeholder="10.0.0.0/8"
            value={createForm.cidr}
            onChange={(e) => setCreateForm({ ...createForm, cidr: e.target.value })}
          />
          <select
            className="input"
            value={createForm.lab_id}
            onChange={(e) => setCreateForm({ ...createForm, lab_id: e.target.value })}
          >
            <option value="">Attach to my lab</option>
            {(labs.data ?? []).map((lab) => (
              <option key={lab.id} value={lab.public_id}>
                {lab.public_id} · {lab.student}
              </option>
            ))}
          </select>
          <label className="flex items-center gap-2 text-sm text-slate-300">
            <input
              type="checkbox"
              checked={createForm.isolated}
              onChange={(e) => setCreateForm({ ...createForm, isolated: e.target.checked })}
            />
            Isolated
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-300">
            <input
              type="checkbox"
              checked={createForm.internet}
              onChange={(e) => setCreateForm({ ...createForm, internet: e.target.checked })}
            />
            Internet
          </label>
          <button className="btn-primary" type="submit" disabled={createNet.isPending}>
            {createNet.isPending ? "Creating…" : "Create network"}
          </button>
          {createNet.isError && (
            <div className="xl:col-span-6 text-sm text-rose-300">{(createNet.error as Error).message}</div>
          )}
        </form>
      )}

      {data.map((n) => {
        const form = deploy[n.id] || { template_slug: "ubuntu", name: "", owner_id: "" };
        return (
          <div key={n.id} className="card p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <div className="font-semibold">{n.name}</div>
                <div className="font-mono text-sm text-slate-400">
                  {n.cidr} · VLAN {n.vlan_id} · {n.namespace}
                </div>
                <div className="mt-1 text-xs text-slate-500">
                  {n.kind === "admin" ? "Administrator network" : "Student lab network"}
                  {n.lab_id ? ` · ${n.lab_id}` : ""}
                  {n.lab_name ? ` · ${n.lab_name}` : ""}
                </div>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <span>
                  {n.isolated ? "Isolated" : "Peered"} · Internet {n.internet ? "on" : "off"}
                </span>
                {staff && n.kind === "admin" && n.interfaces.length === 0 && (
                  <button className="btn-ghost" type="button" onClick={() => removeNet.mutate(n.id)}>
                    Delete
                  </button>
                )}
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
                {n.interfaces.map((i) => (
                  <tr key={i.mac} className="border-t border-white/5">
                    <td className="py-2">
                      {i.machine_id ? <Link to={`/terminal/${i.machine_id}`}>{i.machine}</Link> : i.machine}
                    </td>
                    <td className="font-mono">{i.ip}</td>
                    <td className="font-mono text-xs">{i.mac}</td>
                  </tr>
                ))}
                {n.interfaces.length === 0 && (
                  <tr>
                    <td className="py-3 text-slate-500" colSpan={3}>
                      No machines on this network yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
            {staff && (
              <form
                className="mt-4 grid gap-3 border-t border-white/5 pt-4 md:grid-cols-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  deployNet.mutate({ id: n.id, body: form });
                }}
              >
                <select
                  className="input"
                  value={form.template_slug}
                  onChange={(e) => setDeploy({ ...deploy, [n.id]: { ...form, template_slug: e.target.value } })}
                >
                  {(templates.data ?? []).map((t) => (
                    <option key={t.slug} value={t.slug}>
                      {t.name}
                    </option>
                  ))}
                </select>
                <input
                  className="input"
                  placeholder="Machine name"
                  value={form.name}
                  onChange={(e) => setDeploy({ ...deploy, [n.id]: { ...form, name: e.target.value } })}
                />
                <select
                  className="input"
                  value={form.owner_id}
                  onChange={(e) => setDeploy({ ...deploy, [n.id]: { ...form, owner_id: e.target.value } })}
                >
                  <option value="">Owner: lab student / me</option>
                  {(students.data ?? []).map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.username} · {s.public_id}
                    </option>
                  ))}
                </select>
                <button className="btn-primary" type="submit" disabled={deployNet.isPending}>
                  {deployNet.isPending ? "Deploying…" : "Deploy machine"}
                </button>
                {deployNet.isError && (
                  <div className="md:col-span-4 text-sm text-rose-300">{(deployNet.error as Error).message}</div>
                )}
              </form>
            )}
          </div>
        );
      })}
    </div>
  );
}
