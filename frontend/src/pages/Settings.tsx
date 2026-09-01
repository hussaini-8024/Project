import { FormEvent, useState } from "react";
import { api } from "../api";
import { useAuth } from "../auth";

export function Settings() {
  const { user, refresh } = useAuth();
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [msg, setMsg] = useState("");

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    try {
      await api("/api/auth/password", {
        method: "POST",
        body: JSON.stringify({ current_password: current, new_password: next }),
      });
      setMsg("Password updated");
      await refresh();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : "Failed");
    }
  }

  return (
    <div className="mx-auto max-w-xl space-y-6">
      <h1 className="text-3xl font-semibold">Settings</h1>
      <div className="card p-5 text-sm">
        <div>Account {user?.public_id}</div>
        <div className="text-slate-400">{user?.email}</div>
        <div className="mt-2">Role {user?.role} · Quota {user?.quota}</div>
      </div>
      <form className="card space-y-3 p-5" onSubmit={onSubmit}>
        <h2 className="font-semibold">Change password</h2>
        <input className="input" type="password" placeholder="Current" value={current} onChange={(e) => setCurrent(e.target.value)} />
        <input className="input" type="password" placeholder="New (min 10)" value={next} onChange={(e) => setNext(e.target.value)} />
        <button className="btn-primary" type="submit">
          Update
        </button>
        {msg && <div className="text-sm text-cyan-glow">{msg}</div>}
      </form>
    </div>
  );
}
