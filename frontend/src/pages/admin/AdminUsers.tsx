import { Fragment, FormEvent, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type Group } from "../../api";

function randomPassword() {
  const chars = "ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789";
  let out = "";
  for (let i = 0; i < 14; i++) out += chars[Math.floor(Math.random() * chars.length)];
  return `${out}!9`;
}

export function AdminUsers() {
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const users = useQuery({
    queryKey: ["users", search],
    queryFn: () => api<any[]>(`/api/users${search ? `?q=${encodeURIComponent(search)}` : ""}`),
  });
  const groups = useQuery({ queryKey: ["groups"], queryFn: () => api<Group[]>("/api/groups") });
  const sessions = useQuery({ queryKey: ["sessions"], queryFn: () => api<any[]>("/api/sessions") });

  const studentGroups = (groups.data || []).filter((g) => g.kind === "student");
  const instructorGroups = (groups.data || []).filter((g) => g.kind === "instructor");

  const [form, setForm] = useState({
    username: "",
    email: "",
    full_name: "",
    password: "ChangeMe!2026",
    role: "student",
    quota_name: "Standard",
    course: "",
  });
  const [formGroups, setFormGroups] = useState<string[]>([]);
  const [editId, setEditId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<any>({});
  const [pwdId, setPwdId] = useState<string | null>(null);
  const [pwd, setPwd] = useState("");
  const [groupsId, setGroupsId] = useState<string | null>(null);
  const [notice, setNotice] = useState("");

  const create = useMutation({
    mutationFn: () => api("/api/users", { method: "POST", body: JSON.stringify({ ...form, group_ids: formGroups }) }),
    onSuccess: () => {
      setFormGroups([]);
      qc.invalidateQueries({ queryKey: ["users"] });
      qc.invalidateQueries({ queryKey: ["groups"] });
    },
  });
  const disable = useMutation({
    mutationFn: (id: string) => api(`/api/users/${id}/disable`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["users"] }),
  });
  const kill = useMutation({
    mutationFn: (id: string) => api(`/api/sessions/${id}/terminate`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["sessions"] }),
  });
  const update = useMutation({
    mutationFn: (payload: { id: string; body: any }) =>
      api(`/api/users/${payload.id}`, { method: "PATCH", body: JSON.stringify(payload.body) }),
    onSuccess: () => {
      setEditId(null);
      qc.invalidateQueries({ queryKey: ["users"] });
    },
  });
  const setPassword = useMutation({
    mutationFn: (payload: { id: string; password: string }) =>
      api<{ temporary_password: string }>(`/api/users/${payload.id}/reset-password`, {
        method: "POST",
        body: JSON.stringify({ password: payload.password }),
      }),
    onSuccess: (res, vars) => {
      const u = (users.data || []).find((x) => x.id === vars.id);
      setNotice(`Password for ${u?.username ?? "user"} set to: ${res.temporary_password}`);
      setPwdId(null);
      setPwd("");
    },
  });
  const setGroups = useMutation({
    mutationFn: (payload: { id: string; group_ids: string[] }) =>
      api(`/api/users/${payload.id}/groups`, { method: "PUT", body: JSON.stringify({ group_ids: payload.group_ids }) }),
    onSuccess: () => {
      setGroupsId(null);
      qc.invalidateQueries({ queryKey: ["users"] });
      qc.invalidateQueries({ queryKey: ["groups"] });
    },
  });

  function onCreate(e: FormEvent) {
    e.preventDefault();
    create.mutate();
  }

  function startEdit(u: any) {
    setPwdId(null);
    setGroupsId(null);
    setEditId(u.id);
    setEditForm({
      username: u.username,
      full_name: u.full_name,
      email: u.email,
      role: u.role,
      quota_name: u.quota || "Standard",
      course: u.course,
    });
  }

  return (
    <div className="space-y-6">
      <div>
        <div className="text-xs uppercase tracking-[0.2em] text-cyan-glow">Administrator</div>
        <h1 className="text-3xl font-semibold">Users</h1>
        <p className="mt-1 text-sm text-slate-400">Create accounts, manage credentials, and assign groups.</p>
      </div>

      {notice && (
        <div className="card flex items-center justify-between border border-cyan-glow/30 p-3 text-sm">
          <span className="font-mono text-cyan-glow">{notice}</span>
          <button className="btn-ghost" onClick={() => setNotice("")}>
            Dismiss
          </button>
        </div>
      )}

      <form className="card grid gap-3 p-5 md:grid-cols-3" onSubmit={onCreate}>
        <input className="input" placeholder="Username" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} />
        <input className="input" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        <input className="input" placeholder="Full name" value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} />
        <div className="flex gap-2 md:col-span-2">
          <input className="input flex-1" placeholder="Initial password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          <button type="button" className="btn-ghost" onClick={() => setForm({ ...form, password: randomPassword() })}>
            Generate
          </button>
        </div>
        <select className="input" value={form.role} onChange={(e) => { setForm({ ...form, role: e.target.value }); setFormGroups([]); }}>
          <option value="student">Student</option>
          <option value="instructor">Instructor</option>
          <option value="lab_manager">Lab manager</option>
          <option value="administrator">Administrator</option>
        </select>
        <select className="input" value={form.quota_name} onChange={(e) => setForm({ ...form, quota_name: e.target.value })}>
          <option>Basic</option>
          <option>Standard</option>
          <option>Advanced</option>
        </select>
        <input className="input" placeholder="Course" value={form.course} onChange={(e) => setForm({ ...form, course: e.target.value })} />
        {form.role === "student" && (
          <select
            className="input md:col-span-3"
            value={formGroups[0] || ""}
            onChange={(e) => setFormGroups(e.target.value ? [e.target.value] : [])}
          >
            <option value="">No group</option>
            {studentGroups.map((g) => (
              <option key={g.id} value={g.id}>
                {g.name}
              </option>
            ))}
          </select>
        )}
        {form.role === "instructor" && (
          <div className="md:col-span-3">
            <div className="mb-1 text-xs text-slate-400">Assign to instructor groups (multiple allowed)</div>
            <div className="flex flex-wrap gap-2">
              {instructorGroups.map((g) => (
                <label key={g.id} className="flex items-center gap-1.5 rounded border border-white/10 px-2 py-1 text-sm">
                  <input
                    type="checkbox"
                    checked={formGroups.includes(g.id)}
                    onChange={(e) =>
                      setFormGroups((prev) => (e.target.checked ? [...prev, g.id] : prev.filter((x) => x !== g.id)))
                    }
                  />
                  {g.name}
                </label>
              ))}
              {!instructorGroups.length && <span className="text-xs text-slate-500">No instructor groups yet.</span>}
            </div>
          </div>
        )}
        <button className="btn-primary md:col-span-3" type="submit">
          Create account
        </button>
        {create.error && <div className="text-sm text-rose-300">{(create.error as Error).message}</div>}
      </form>

      <div className="flex items-center gap-3">
        <input
          className="input max-w-md"
          placeholder="Search by username, name, or email…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        {search && (
          <button className="btn-ghost" onClick={() => setSearch("")}>
            Clear
          </button>
        )}
        <span className="text-xs text-slate-500">{(users.data || []).length} account(s)</span>
      </div>

      <div className="card overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-left text-xs uppercase text-slate-400">
            <tr>
              <th className="px-4 py-3">Public ID</th>
              <th>User</th>
              <th>Role</th>
              <th>Groups</th>
              <th>Quota</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {(users.data || []).map((u) => (
              <Fragment key={u.id}>
                <tr className="border-t border-white/5">
                  <td className="px-4 py-2 font-mono text-xs">{u.public_id}</td>
                  <td>
                    {u.full_name}
                    <div className="text-xs text-slate-500">
                      {u.username} · {u.email}
                    </div>
                  </td>
                  <td>{u.role}</td>
                  <td className="text-xs">
                    {(u.groups || []).length ? u.groups.map((g: any) => g.name).join(", ") : "—"}
                  </td>
                  <td>{u.quota}</td>
                  <td>{u.status}</td>
                  <td className="whitespace-nowrap">
                    <button className="btn-ghost" onClick={() => startEdit(u)}>
                      Edit
                    </button>
                    {(u.role === "student" || u.role === "instructor") && (
                      <button
                        className="btn-ghost"
                        onClick={() => {
                          setEditId(null);
                          setPwdId(null);
                          setGroupsId(groupsId === u.id ? null : u.id);
                        }}
                      >
                        Groups
                      </button>
                    )}
                    <button
                      className="btn-ghost"
                      onClick={() => {
                        setEditId(null);
                        setGroupsId(null);
                        setPwdId(pwdId === u.id ? null : u.id);
                        setPwd("");
                      }}
                    >
                      Password
                    </button>
                    <button className="btn-ghost" onClick={() => disable.mutate(u.id)}>
                      Disable
                    </button>
                  </td>
                </tr>
                {editId === u.id && (
                  <tr className="border-t border-white/5 bg-white/5">
                    <td colSpan={7} className="px-4 py-3">
                      <div className="grid gap-2 md:grid-cols-3">
                        <input className="input" placeholder="Username" value={editForm.username} onChange={(e) => setEditForm({ ...editForm, username: e.target.value })} />
                        <input className="input" placeholder="Full name" value={editForm.full_name} onChange={(e) => setEditForm({ ...editForm, full_name: e.target.value })} />
                        <input className="input" placeholder="Email" value={editForm.email} onChange={(e) => setEditForm({ ...editForm, email: e.target.value })} />
                        <select className="input" value={editForm.role} onChange={(e) => setEditForm({ ...editForm, role: e.target.value })}>
                          <option value="student">Student</option>
                          <option value="instructor">Instructor</option>
                          <option value="lab_manager">Lab manager</option>
                          <option value="administrator">Administrator</option>
                        </select>
                        <select className="input" value={editForm.quota_name} onChange={(e) => setEditForm({ ...editForm, quota_name: e.target.value })}>
                          <option>Basic</option>
                          <option>Standard</option>
                          <option>Advanced</option>
                        </select>
                        <input className="input" placeholder="Course" value={editForm.course} onChange={(e) => setEditForm({ ...editForm, course: e.target.value })} />
                      </div>
                      <div className="mt-2 flex items-center gap-2">
                        <button className="btn-primary" onClick={() => update.mutate({ id: u.id, body: editForm })}>
                          Save changes
                        </button>
                        <button className="btn-ghost" onClick={() => setEditId(null)}>
                          Cancel
                        </button>
                        {update.error && <span className="text-sm text-rose-300">{(update.error as Error).message}</span>}
                      </div>
                    </td>
                  </tr>
                )}
                {groupsId === u.id && (
                  <tr className="border-t border-white/5 bg-white/5">
                    <td colSpan={7} className="px-4 py-3">
                      <GroupAssign
                        user={u}
                        studentGroups={studentGroups}
                        instructorGroups={instructorGroups}
                        saving={setGroups.isPending}
                        error={(setGroups.error as Error)?.message}
                        onSave={(ids) => setGroups.mutate({ id: u.id, group_ids: ids })}
                        onCancel={() => setGroupsId(null)}
                      />
                    </td>
                  </tr>
                )}
                {pwdId === u.id && (
                  <tr className="border-t border-white/5 bg-white/5">
                    <td colSpan={7} className="px-4 py-3">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm text-slate-400">Set a new password for {u.username}:</span>
                        <input className="input w-64" type="text" placeholder="New password" value={pwd} onChange={(e) => setPwd(e.target.value)} />
                        <button type="button" className="btn-ghost" onClick={() => setPwd(randomPassword())}>
                          Generate
                        </button>
                        <button className="btn-primary" disabled={pwd.length < 8} onClick={() => setPassword.mutate({ id: u.id, password: pwd })}>
                          Set password
                        </button>
                        <button className="btn-ghost" onClick={() => setPwdId(null)}>
                          Cancel
                        </button>
                      </div>
                      <div className="mt-1 text-xs text-slate-500">
                        The password is shown here so you can share it with the {u.role}. Existing hashes are never displayed.
                      </div>
                      {setPassword.error && <div className="text-sm text-rose-300">{(setPassword.error as Error).message}</div>}
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card p-5">
        <h2 className="font-semibold">Active sessions</h2>
        <div className="mt-3 space-y-2 text-sm">
          {(sessions.data || []).map((s) => (
            <div key={s.id} className="flex items-center justify-between rounded-lg border border-white/5 px-3 py-2">
              <div>
                <div className="font-mono text-xs">{s.public_id}</div>
                {s.user} · {s.ip}
              </div>
              <button className="btn-danger" onClick={() => kill.mutate(s.id)}>
                Terminate
              </button>
            </div>
          ))}
          {!sessions.data?.length && <div className="text-slate-500">No active sessions.</div>}
        </div>
      </div>
    </div>
  );
}

function GroupAssign({
  user,
  studentGroups,
  instructorGroups,
  saving,
  error,
  onSave,
  onCancel,
}: {
  user: any;
  studentGroups: Group[];
  instructorGroups: Group[];
  saving: boolean;
  error?: string;
  onSave: (ids: string[]) => void;
  onCancel: () => void;
}) {
  const current: string[] = user.group_ids || [];
  const [selected, setSelected] = useState<string[]>(current);
  const isStudent = user.role === "student";
  const options = isStudent ? studentGroups : instructorGroups;

  return (
    <div className="space-y-2">
      <div className="text-sm text-slate-400">
        {isStudent
          ? "Students may belong to at most one group — pick one."
          : "Instructors may belong to multiple groups — select any."}
      </div>
      {isStudent ? (
        <select
          className="input max-w-sm"
          value={selected[0] || ""}
          onChange={(e) => setSelected(e.target.value ? [e.target.value] : [])}
        >
          <option value="">No group</option>
          {options.map((g) => (
            <option key={g.id} value={g.id}>
              {g.name}
            </option>
          ))}
        </select>
      ) : (
        <div className="flex flex-wrap gap-2">
          {options.map((g) => (
            <label key={g.id} className="flex items-center gap-1.5 rounded border border-white/10 px-2 py-1 text-sm">
              <input
                type="checkbox"
                checked={selected.includes(g.id)}
                onChange={(e) =>
                  setSelected((prev) => (e.target.checked ? [...prev, g.id] : prev.filter((x) => x !== g.id)))
                }
              />
              {g.name}
            </label>
          ))}
          {!options.length && <span className="text-xs text-slate-500">No matching groups.</span>}
        </div>
      )}
      <div className="flex items-center gap-2">
        <button className="btn-primary" disabled={saving} onClick={() => onSave(selected)}>
          Save groups
        </button>
        <button className="btn-ghost" onClick={onCancel}>
          Cancel
        </button>
        {error && <span className="text-sm text-rose-300">{error}</span>}
      </div>
    </div>
  );
}
