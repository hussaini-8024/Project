import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { IconBook, IconClock, IconHome, IconLogout, IconSpark, IconUser } from "./Icons";

const links = [
  { to: "/dashboard", label: "Dashboard", roles: ["student", "teacher", "admin"], icon: IconHome },
  { to: "/courses", label: "Courses", roles: ["student", "teacher", "admin"], icon: IconBook },
  { to: "/attendance", label: "Attendance", roles: ["student", "teacher", "admin"], icon: IconClock },
  { to: "/ai", label: "AI Studio", roles: ["student", "teacher", "admin"], icon: IconSpark },
  { to: "/profile", label: "Profile", roles: ["student", "teacher", "admin"], icon: IconUser },
];

export function AppShell() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  if (!user) return null;
  const initial = user.name.trim().slice(0, 1).toUpperCase();

  return (
    <div className="shell">
      <aside className="sidebar">
        <div className="brand">
          <div className="crest">U</div>
          <div>
            <strong>UniMeet</strong>
            <span className="muted">Faculty of Computing</span>
          </div>
        </div>
        <nav className="nav">
          {links
            .filter((link) => link.roles.includes(user.role))
            .map((link) => (
              <NavLink key={link.to} to={link.to} className={({ isActive }) => (isActive ? "active" : "")}>
                <link.icon size={18} />
                {link.label}
              </NavLink>
            ))}
        </nav>
        <div className="who">
          <div className="row" style={{ marginBottom: 10 }}>
            <div className="avatar">{initial}</div>
            <div>
              <div>{user.name}</div>
              <div className="muted">
                {user.universityId} · {user.role}
              </div>
            </div>
          </div>
          <button
            className="btn btn-ghost"
            style={{ color: "#fff", width: "100%" }}
            onClick={async () => {
              await logout();
              navigate("/login");
            }}
          >
            <IconLogout size={16} /> Sign out
          </button>
        </div>
      </aside>
      <main className="main">
        <Outlet />
      </main>
    </div>
  );
}
