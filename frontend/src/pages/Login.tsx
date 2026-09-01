import { FormEvent, useEffect, useState } from "react";
import { Navigate } from "react-router-dom";
import { Shield, TerminalSquare } from "lucide-react";
import { useAuth } from "../auth";
import { api } from "../api";

const demos = [
  { role: "Administrator", user: "admin", pass: "CyberRange!Admin2026" },
  { role: "Instructor", user: "instructor", pass: "CyberRange!Teach2026" },
  { role: "Student", user: "student", pass: "CyberRange!Stud2026" },
];

export function Login() {
  const { user, login } = useAuth();
  const [username, setUsername] = useState("student");
  const [password, setPassword] = useState("CyberRange!Stud2026");
  const [totp, setTotp] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  if (user) return <Navigate to="/" replace />;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      await login(username, password, totp || undefined);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Login failed");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid min-h-screen grid-cols-1 lg:grid-cols-2">
      <div className="relative hidden overflow-hidden bg-ink-900 lg:block">
        <div className="absolute inset-0 grid-overlay" />
        <div className="relative flex h-full flex-col justify-between p-12">
          <div className="flex items-center gap-3 text-cyan-glow">
            <TerminalSquare />
            <span className="text-sm uppercase tracking-[0.25em]">University Cyber Range</span>
          </div>
          <div>
            <h1 className="max-w-xl text-4xl font-semibold leading-tight">
              Isolated cybersecurity laboratories for every student.
            </h1>
            <p className="mt-4 max-w-lg text-slate-400">
              Container-first density. Full VMs only when a kernel is required. Private networks,
              browser terminals, and a live resource scheduler.
            </p>
            <div className="mt-8 grid max-w-md grid-cols-2 gap-3 text-sm">
              {[
                ["Logged-in", "≠ compute"],
                ["Active lab", "uses RAM"],
                ["Container", "preferred"],
                ["Full VM", "when required"],
              ].map(([k, v]) => (
                <div key={k} className="card px-4 py-3">
                  <div className="text-slate-400">{k}</div>
                  <div className="font-medium">{v}</div>
                </div>
              ))}
            </div>
          </div>
          <div className="flex items-center gap-2 text-xs text-slate-500">
            <Shield size={14} /> Authorized training environments only. No host access.
          </div>
        </div>
      </div>
      <div className="flex items-center justify-center p-8">
        <form onSubmit={onSubmit} className="card w-full max-w-md p-8 shadow-glow">
          <h2 className="text-2xl font-semibold">Sign in</h2>
          <p className="mt-1 text-sm text-slate-400">Students are provisioned by administrators.</p>
          <label className="mt-6 block text-xs uppercase tracking-wide text-slate-400">Username</label>
          <input className="input mt-1" value={username} onChange={(e) => setUsername(e.target.value)} />
          <label className="mt-4 block text-xs uppercase tracking-wide text-slate-400">Password</label>
          <input
            className="input mt-1"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          <label className="mt-4 block text-xs uppercase tracking-wide text-slate-400">
            MFA code (administrators)
          </label>
          <input className="input mt-1" value={totp} onChange={(e) => setTotp(e.target.value)} placeholder="Optional" />
          {error && <div className="mt-4 rounded-lg bg-rose-500/15 px-3 py-2 text-sm text-rose-300">{error}</div>}
          <LanHint />
          <button className="btn-primary mt-6 w-full" disabled={busy} type="submit">
            {busy ? "Authenticating…" : "Enter laboratory"}
          </button>
          <div className="mt-6 space-y-2 text-xs text-slate-400">
            <div className="uppercase tracking-wide">Demo accounts</div>
            {demos.map((d) => (
              <button
                key={d.user}
                type="button"
                className="flex w-full items-center justify-between rounded-lg border border-white/5 px-3 py-2 text-left hover:bg-white/5"
                onClick={() => {
                  setUsername(d.user);
                  setPassword(d.pass);
                }}
              >
                <span>{d.role}</span>
                <span className="font-mono text-slate-300">{d.user}</span>
              </button>
            ))}
          </div>
        </form>
      </div>
    </div>
  );
}

function LanHint() {
  const [urls, setUrls] = useState<string[]>([]);
  useEffect(() => {
    api<{ login_urls?: string[] }>("/api/health")
      .then((h) => setUrls(h.login_urls ?? []))
      .catch(() => setUrls([]));
  }, []);
  const here = typeof window !== "undefined" ? window.location.origin : "";
  return (
    <div className="mt-4 rounded-lg border border-white/10 bg-white/5 px-3 py-2 font-mono text-xs text-slate-300">
      <div className="text-[10px] uppercase tracking-wide text-slate-500">Open from this PC or another on the LAN</div>
      <div className="mt-1 break-all">{here}/login</div>
      {urls
        .filter((u) => !here || !u.startsWith(here))
        .map((u) => (
          <div key={u} className="mt-1 break-all text-cyan-glow">
            {u}
          </div>
        ))}
    </div>
  );
}
