import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

const links = [
  { to: "/dashboard", label: "Dashboard", roles: ["student", "teacher", "admin"] },
  { to: "/courses", label: "Courses", roles: ["student", "teacher", "admin"] },
  { to: "/attendance", label: "Attendance", roles: ["student", "teacher", "admin"] },
  { to: "/ai", label: "AI Studio", roles: ["student", "teacher", "admin"] },
  { to: "/profile", label: "Profile", roles: ["student", "teacher", "admin"] },
];

export function AppShell() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  if (!user) return null;

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
                {link.label}
              </NavLink>
            ))}
        </nav>
        <div className="who">
          <div>{user.name}</div>
          <div className="muted">
            {user.universityId} · {user.role}
          </div>
          <button
            className="btn btn-ghost"
            style={{ color: "#fff", marginTop: 10 }}
            onClick={async () => {
              await logout();
              navigate("/login");
            }}
          >
            Sign out
          </button>
        </div>
      </aside>
      <main className="main">
        <Outlet />
      </main>
    </div>
  );
}
