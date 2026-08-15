import { FormEvent, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "../../api";

export function AdminPeople() {
  const qc = useQueryClient();
  const users = useQuery({ queryKey: ["users"], queryFn: () => api<any[]>("/api/users") });
  const sessions = useQuery({ queryKey: ["sessions"], queryFn: () => api<any[]>("/api/sessions") });
  const [form, setForm] = useState({
    username: "",
    email: "",
    full_name: "",
    password: "ChangeMe!2026",
    role: "student",
    quota_name: "Standard",
    course: "",
  });

  const create = useMutation({
    mutationFn: () => api("/api/users", { method: "POST", body: JSON.stringify(form) }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["users"] }),
  });
  const disable = useMutation({
    mutationFn: (id: string) => api(`/api/users/${id}/disable`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["users"] }),
  });
  const kill = useMutation({
    mutationFn: (id: string) => api(`/api/sessions/${id}/terminate`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["sessions"] }),
  });

  function onCreate(e: FormEvent) {
    e.preventDefault();
    create.mutate();
  }

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-semibold">People & sessions</h1>
      <form className="card grid gap-3 p-5 md:grid-cols-3" onSubmit={onCreate}>
        <input className="input" placeholder="Username" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} />
        <input className="input" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        <input className="input" placeholder="Full name" value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} />
        <select className="input" value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
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
        <button className="btn-primary md:col-span-3" type="submit">
          Create account
        </button>
        {create.error && <div className="text-sm text-rose-300">{(create.error as Error).message}</div>}
      </form>
      <div className="card overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-left text-xs uppercase text-slate-400">
            <tr>
              <th className="px-4 py-3">Public ID</th>
              <th>User</th>
              <th>Role</th>
              <th>Quota</th>
              <th>Lab</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {(users.data || []).map((u) => (
              <tr key={u.id} className="border-t border-white/5">
                <td className="px-4 py-2 font-mono text-xs">{u.public_id}</td>
                <td>
                  {u.full_name}
                  <div className="text-xs text-slate-500">{u.username}</div>
                </td>
                <td>{u.role}</td>
                <td>{u.quota}</td>
                <td className="font-mono text-xs">{u.lab_id || "—"}</td>
                <td>{u.status}</td>
                <td>
                  <button className="btn-ghost" onClick={() => disable.mutate(u.id)}>
                    Disable
                  </button>
                </td>
              </tr>
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
        </div>
      </div>
    </div>
  );
}
