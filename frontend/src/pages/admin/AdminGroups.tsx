import { FormEvent, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type Group, type InactivityAlert, type Template } from "../../api";

export function AdminGroups() {
  const qc = useQueryClient();
  const groups = useQuery({ queryKey: ["groups"], queryFn: () => api<Group[]>("/api/groups") });
  const templates = useQuery({ queryKey: ["templates"], queryFn: () => api<Template[]>("/api/templates") });
  const alerts = useQuery({
    queryKey: ["group-alerts"],
    queryFn: () => api<InactivityAlert[]>("/api/groups/alerts"),
    refetchInterval: 15000,
  });

  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [newGroup, setNewGroup] = useState({ name: "", kind: "student", description: "" });
  const [addMember, setAddMember] = useState("");
  const [deploy, setDeploy] = useState({ name: "Group Kali", template_slug: "kali", environment: "container", internet: false });
  const [result, setResult] = useState<string>("");

  const selected = (groups.data || []).find((g) => g.id === selectedId) || null;

  function refetchAll() {
    qc.invalidateQueries({ queryKey: ["groups"] });
    qc.invalidateQueries({ queryKey: ["group-alerts"] });
  }

  const createGroup = useMutation({
    mutationFn: () => api<Group>("/api/groups", { method: "POST", body: JSON.stringify(newGroup) }),
    onSuccess: (g) => {
      setNewGroup({ name: "", kind: "student", description: "" });
      setSelectedId(g.id);
      refetchAll();
    },
  });
  const deleteGroup = useMutation({
    mutationFn: (id: string) => api(`/api/groups/${id}?detach=true`, { method: "DELETE" }),
    onSuccess: () => {
      setSelectedId(null);
      refetchAll();
    },
  });
  const addMembers = useMutation({
    mutationFn: (id: string) =>
      api(`/api/groups/${id}/members`, { method: "POST", body: JSON.stringify({ usernames: [addMember.trim()] }) }),
    onSuccess: (res: any) => {
      setAddMember("");
      if (res?.errors?.length) setResult(res.errors.map((e: any) => e.error).join("; "));
      refetchAll();
    },
  });
  const removeMember = useMutation({
    mutationFn: (payload: { id: string; userId: string }) =>
      api(`/api/groups/${payload.id}/members/${payload.userId}`, { method: "DELETE" }),
    onSuccess: () => refetchAll(),
  });
  const setPolicies = useMutation({
    mutationFn: (payload: { id: string; body: any }) =>
      api(`/api/groups/${payload.id}/policies`, { method: "PATCH", body: JSON.stringify(payload.body) }),
    onSuccess: (res: any) => {
      setResult(`Policies applied. Internet updated on ${res.internet_labs_applied} member lab(s).`);
      refetchAll();
    },
  });
  const shutdown = useMutation({
    mutationFn: (id: string) => api<any>(`/api/groups/${id}/shutdown`, { method: "POST" }),
    onSuccess: (res) => {
      setResult(`Group shutdown complete: stopped ${res.stopped} running machine(s).`);
      refetchAll();
    },
  });
  const startGroup = useMutation({
    mutationFn: (id: string) => api<any>(`/api/groups/${id}/start`, { method: "POST" }),
    onSuccess: (res) => {
      setResult(`Group start: ${res.started} machine(s) started.`);
      refetchAll();
    },
  });
  const deployGroup = useMutation({
    mutationFn: (id: string) => api<any>(`/api/groups/${id}/deploy`, { method: "POST", body: JSON.stringify(deploy) }),
    onSuccess: (res) => {
      setResult(`Deployed to ${res.created}/${res.total} student(s) in ${res.group}.`);
      refetchAll();
    },
  });

  function onCreate(e: FormEvent) {
    e.preventDefault();
    createGroup.mutate();
  }

  return (
    <div className="space-y-6">
      <div>
        <div className="text-xs uppercase tracking-[0.2em] text-cyan-glow">Administrator</div>
        <h1 className="text-3xl font-semibold">Groups &amp; policies</h1>
        <p className="mt-1 text-sm text-slate-400">
          Organize students and instructors into groups, attach policies, and control machines group-wide.
        </p>
      </div>

      {result && (
        <div className="card flex items-center justify-between border border-cyan-glow/30 p-3 text-sm">
          <span className="text-cyan-glow">{result}</span>
          <button className="btn-ghost" onClick={() => setResult("")}>
            Dismiss
          </button>
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-[340px,1fr]">
        <div className="space-y-4">
          <form className="card space-y-3 p-5" onSubmit={onCreate}>
            <h2 className="font-semibold">New group</h2>
            <input className="input" placeholder="Group name" value={newGroup.name} onChange={(e) => setNewGroup({ ...newGroup, name: e.target.value })} />
            <select className="input" value={newGroup.kind} onChange={(e) => setNewGroup({ ...newGroup, kind: e.target.value })}>
              <option value="student">Student group</option>
              <option value="instructor">Instructor group</option>
            </select>
            <input className="input" placeholder="Description" value={newGroup.description} onChange={(e) => setNewGroup({ ...newGroup, description: e.target.value })} />
            <button className="btn-primary w-full" type="submit">
              Create group
            </button>
            {createGroup.error && <div className="text-sm text-rose-300">{(createGroup.error as Error).message}</div>}
          </form>

          <div className="card p-3">
            <h2 className="px-2 py-1 font-semibold">Groups</h2>
            <div className="space-y-1">
              {(groups.data || []).map((g) => (
                <button
                  key={g.id}
                  onClick={() => setSelectedId(g.id)}
                  className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm ${
                    selectedId === g.id ? "bg-cyan-glow/15 text-cyan-glow" : "hover:bg-white/5"
                  }`}
                >
                  <span>
                    <span className="font-medium">{g.name}</span>
                    <span className="ml-2 rounded bg-white/10 px-1.5 py-0.5 text-[10px] uppercase">{g.kind}</span>
                  </span>
                  <span className="text-xs text-slate-400">{g.member_count}</span>
                </button>
              ))}
              {!groups.data?.length && <div className="px-3 py-2 text-sm text-slate-500">No groups yet.</div>}
            </div>
          </div>

          <div className="card p-5">
            <h2 className="font-semibold text-amber-300">Inactivity alerts</h2>
            <p className="text-xs text-slate-500">Students idle past their group threshold.</p>
            <div className="mt-3 space-y-2 text-sm">
              {(alerts.data || []).map((a) => (
                <div key={a.user_id} className="rounded-lg border border-amber-400/20 bg-amber-400/5 px-3 py-2">
                  <div className="font-medium">{a.full_name || a.username}</div>
                  <div className="text-xs text-slate-400">
                    idle {a.idle_days}d (threshold {a.threshold_days}d){a.group ? ` · ${a.group}` : ""}
                  </div>
                </div>
              ))}
              {!alerts.data?.length && <div className="text-sm text-slate-500">No inactive students.</div>}
            </div>
          </div>
        </div>

        <div className="space-y-4">
          {!selected && <div className="card p-8 text-center text-slate-500">Select a group to manage members and policies.</div>}
          {selected && (
            <>
              <div className="card space-y-1 p-5">
                <div className="flex items-center justify-between">
                  <h2 className="text-xl font-semibold">{selected.name}</h2>
                  <button className="btn-danger" onClick={() => deleteGroup.mutate(selected.id)}>
                    Delete group
                  </button>
                </div>
                <div className="font-mono text-xs text-slate-500">{selected.public_id}</div>
                <div className="text-sm text-slate-400">
                  {selected.kind} group · {selected.member_count} member(s){selected.description ? ` · ${selected.description}` : ""}
                </div>
              </div>

              <div className="card space-y-4 p-5">
                <h3 className="font-semibold">Policies</h3>
                <PolicyEditor
                  key={selected.id}
                  group={selected}
                  onSave={(body) => setPolicies.mutate({ id: selected.id, body })}
                  saving={setPolicies.isPending}
                />
                {setPolicies.error && <div className="text-sm text-rose-300">{(setPolicies.error as Error).message}</div>}
              </div>

              {selected.kind === "student" && (
                <div className="card space-y-3 p-5">
                  <h3 className="font-semibold">Group-wide machine control</h3>
                  <div className="grid gap-2 md:grid-cols-4">
                    <input className="input" placeholder="Machine name" value={deploy.name} onChange={(e) => setDeploy({ ...deploy, name: e.target.value })} />
                    <select className="input" value={deploy.template_slug} onChange={(e) => setDeploy({ ...deploy, template_slug: e.target.value })}>
                      {(templates.data || []).map((t) => (
                        <option key={t.slug} value={t.slug}>
                          {t.name}
                        </option>
                      ))}
                    </select>
                    <select className="input" value={deploy.environment} onChange={(e) => setDeploy({ ...deploy, environment: e.target.value })}>
                      <option value="container">Container</option>
                      <option value="vm">VM (if required)</option>
                    </select>
                    <label className="flex items-center gap-2 text-sm">
                      <input type="checkbox" checked={deploy.internet} onChange={(e) => setDeploy({ ...deploy, internet: e.target.checked })} />
                      Internet
                    </label>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button className="btn-primary" disabled={deployGroup.isPending} onClick={() => deployGroup.mutate(selected.id)}>
                      Deploy &amp; run for whole group
                    </button>
                    <button className="btn-ghost" disabled={startGroup.isPending} onClick={() => startGroup.mutate(selected.id)}>
                      Start all machines
                    </button>
                    <button className="btn-danger" disabled={shutdown.isPending} onClick={() => shutdown.mutate(selected.id)}>
                      Shut down all VMs
                    </button>
                  </div>
                  <p className="text-xs text-slate-500">
                    Deploy provisions one isolated machine per student. Shutdown stops only this group's running machines; instructor
                    machines are never affected.
                  </p>
                </div>
              )}

              <div className="card p-5">
                <h3 className="font-semibold">Members</h3>
                <div className="mt-3 flex gap-2">
                  <input
                    className="input flex-1"
                    placeholder={selected.kind === "student" ? "Add student by username / ID" : "Add instructor by username / ID"}
                    value={addMember}
                    onChange={(e) => setAddMember(e.target.value)}
                  />
                  <button className="btn-primary" disabled={!addMember.trim()} onClick={() => addMembers.mutate(selected.id)}>
                    Add
                  </button>
                </div>
                {addMembers.error && <div className="mt-2 text-sm text-rose-300">{(addMembers.error as Error).message}</div>}
                <div className="mt-3 space-y-2 text-sm">
                  {selected.members.map((m) => (
                    <div key={m.id} className="flex items-center justify-between rounded-lg border border-white/5 px-3 py-2">
                      <div>
                        <div className="font-medium">{m.full_name || m.username}</div>
                        <div className="font-mono text-xs text-slate-500">
                          {m.public_id} · {m.role}
                        </div>
                      </div>
                      <button className="btn-ghost" onClick={() => removeMember.mutate({ id: selected.id, userId: m.id })}>
                        Remove
                      </button>
                    </div>
                  ))}
                  {!selected.members.length && <div className="text-slate-500">No members yet.</div>}
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function PolicyEditor({
  group,
  onSave,
  saving,
}: {
  group: Group;
  onSave: (body: any) => void;
  saving: boolean;
}) {
  const [internet, setInternet] = useState(group.internet_policy);
  const [cap, setCap] = useState<string>(group.max_machines == null ? "" : String(group.max_machines));
  const [days, setDays] = useState<number>(group.inactivity_alert_days);

  return (
    <div className="space-y-3">
      <div className="grid gap-3 md:grid-cols-3">
        <label className="text-sm">
          <div className="mb-1 text-slate-400">Internet policy</div>
          <select className="input w-full" value={internet} onChange={(e) => setInternet(e.target.value as Group["internet_policy"])}>
            <option value="disabled">Disabled (internet OFF for all)</option>
            <option value="enabled">Enabled (internet ON for all)</option>
            <option value="unset">Unset (leave per-student)</option>
          </select>
        </label>
        <label className="text-sm">
          <div className="mb-1 text-slate-400">Machine cap (blank = no cap)</div>
          <input className="input w-full" type="number" min={1} placeholder="no cap" value={cap} onChange={(e) => setCap(e.target.value)} />
        </label>
        <label className="text-sm">
          <div className="mb-1 text-slate-400">Inactivity alert (days)</div>
          <input className="input w-full" type="number" min={1} value={days} onChange={(e) => setDays(Number(e.target.value))} />
        </label>
      </div>
      <button
        className="btn-primary"
        disabled={saving}
        onClick={() =>
          onSave({
            internet_policy: internet,
            inactivity_alert_days: days,
            ...(cap.trim() === "" ? { clear_max_machines: true } : { max_machines: Number(cap) }),
          })
        }
      >
        Save &amp; apply policies
      </button>
    </div>
  );
}
