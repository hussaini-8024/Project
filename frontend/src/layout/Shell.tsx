import { NavLink, Outlet, useNavigate } from "react-router-dom";
import {
  Activity,
  Box,
  Cpu,
  Fingerprint,
  Globe,
  HardDrive,
  LayoutDashboard,
  LogOut,
  Moon,
  Network,
  Plus,
  Server,
  Settings,
  Shield,
  Sun,
  TerminalSquare,
  Users,
  UsersRound,
} from "lucide-react";
import { useEffect, useState } from "react";
import { useAuth } from "../auth";

const studentNav = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard },
  { to: "/lab", label: "My Lab", icon: Shield },
  { to: "/machines", label: "Machines", icon: Server },
  { to: "/machines/create", label: "Create Machine", icon: Plus },
  { to: "/templates", label: "Templates", icon: Box },
  { to: "/images", label: "Images & ISOs", icon: HardDrive },
  { to: "/networks", label: "Networks", icon: Globe },
  { to: "/topology", label: "Topology", icon: Network },
  { to: "/exercises", label: "Exercises", icon: Fingerprint },
  { to: "/resources", label: "Resource Usage", icon: Cpu },
  { to: "/activity", label: "Activity", icon: Activity },
  { to: "/settings", label: "Settings", icon: Settings },
];

const staffExtra = [
  { to: "/admin", label: "Range Overview", icon: LayoutDashboard },
  { to: "/admin/people", label: "People & Sessions", icon: Users },
  { to: "/admin/groups", label: "Groups & Policies", icon: UsersRound },
  { to: "/admin/infra", label: "Scheduler & Storage", icon: Server },
  { to: "/admin/ops", label: "Audit & Backups", icon: Shield },
];

export function Shell() {
  const { user, logout } = useAuth();
  const nav = useNavigate();
  const [light, setLight] = useState(false);

  useEffect(() => {
    document.documentElement.classList.toggle("dark", !light);
    document.documentElement.classList.toggle("light", light);
    document.body.classList.toggle("bg-slate-100", light);
    document.body.classList.toggle("text-slate-900", light);
  }, [light]);

  const staff = user && !["student"].includes(user.role);
  const items = staff ? [...staffExtra, ...studentNav.filter((i) => i.to !== "/")] : studentNav;

  return (
    <div className={`min-h-screen grid-overlay ${light ? "bg-slate-100 text-slate-900" : ""}`}>
      <aside className="fixed inset-y-0 left-0 z-20 flex w-64 flex-col border-r border-white/10 bg-ink-900/95">
        <div className="flex items-center gap-3 px-5 py-5">
          <div className="grid h-10 w-10 place-items-center rounded-lg bg-cyan-glow/15 text-cyan-glow">
            <TerminalSquare size={20} />
          </div>
          <div>
            <div className="text-xs uppercase tracking-[0.2em] text-cyan-glow/80">Campus</div>
            <div className="font-semibold leading-tight">Cyber Range</div>
          </div>
        </div>
        <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === "/"}
              className={({ isActive }) =>
                `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm ${
                  isActive ? "bg-cyan-glow/15 text-cyan-glow" : "text-slate-300 hover:bg-white/5"
                }`
              }
            >
              <item.icon size={16} />
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="border-t border-white/10 p-4 text-xs text-slate-400">
          <div className="font-mono text-slate-200">{user?.public_id}</div>
          <div>
            {user?.full_name} · {user?.role.replace("_", " ")}
          </div>
          {user?.lab_id && <div className="font-mono">{user.lab_id}</div>}
        </div>
      </aside>
      <div className="ml-64">
        <header className="sticky top-0 z-10 flex items-center justify-between border-b border-white/10 bg-ink-950/70 px-6 py-3 backdrop-blur">
          <div className="text-sm text-slate-400">
            Container-first · Isolated student labs · Authorized training only
          </div>
          <div className="flex items-center gap-2">
            <button className="btn-ghost" onClick={() => setLight((v) => !v)} type="button">
              {light ? <Moon size={16} /> : <Sun size={16} />}
              {light ? "Dark" : "Light"}
            </button>
            <button
              className="btn-ghost"
              type="button"
              onClick={async () => {
                await logout();
                nav("/login");
              }}
            >
              <LogOut size={16} />
              Logout
            </button>
          </div>
        </header>
        <main className="p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
