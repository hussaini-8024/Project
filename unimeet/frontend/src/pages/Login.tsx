import { useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { ApiError } from "../api/client";
import { useAuth } from "../auth/AuthContext";
import type { Role } from "../types";

const demos: { role: Role; id: string; name: string; note: string }[] = [
  { role: "student", id: "STU-1001", name: "Ali Khan", note: "Enrolled in CS-501" },
  { role: "student", id: "STU-1004", name: "John Smith", note: "Not enrolled — access denied" },
  { role: "teacher", id: "TCH-2001", name: "Dr. Ahmed", note: "Database Systems" },
  { role: "admin", id: "ADM-3001", name: "Registrar", note: "Create courses & enroll" },
];

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [role, setRole] = useState<Role>("student");
  const [universityId, setUniversityId] = useState("STU-1001");
  const [password, setPassword] = useState("UniMeet@2026");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const user = await login(universityId, password, role);
      navigate(`/${user.role}-dashboard`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to sign in.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login-page">
      <section className="login-hero">
        <div>
          <div className="crest">U</div>
          <h1>UniMeet</h1>
          <p>
            The official virtual classroom of the Faculty of Computing. Enrollment-aware access,
            attendance, and network-adaptive lectures for daily university use.
          </p>
        </div>
        <div className="login-meta">
          <span>BSCS · Semester system</span>
          <span>LiveKit SFU</span>
          <span>Smart 720p</span>
        </div>
      </section>
      <div className="login-card-wrap">
        <form className="card login-card" onSubmit={onSubmit}>
          <h2>University sign-in</h2>
          <p className="muted">Use your student, teacher, or registrar ID.</p>
          <div className="role-tabs">
            {(["student", "teacher", "admin"] as Role[]).map((item) => (
              <button
                key={item}
                type="button"
                className={role === item ? "active" : ""}
                onClick={() => setRole(item)}
              >
                {item}
              </button>
            ))}
          </div>
          <label htmlFor="uid">{role === "admin" ? "Admin ID" : role === "teacher" ? "Teacher ID" : "Student ID"}</label>
          <input id="uid" value={universityId} onChange={(e) => setUniversityId(e.target.value)} autoComplete="username" />
          <label htmlFor="pw">Password</label>
          <input id="pw" type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" />
          {error ? <div className="error">{error}</div> : null}
          <button className="btn btn-primary" disabled={busy}>
            {busy ? "Signing in…" : "Enter UniMeet"}
          </button>
          <div className="demo-grid">
            {demos.map((demo) => (
              <button
                key={demo.id}
                type="button"
                className="demo-row"
                onClick={() => {
                  setRole(demo.role);
                  setUniversityId(demo.id);
                  setPassword("UniMeet@2026");
                }}
              >
                <span>
                  <strong>{demo.name}</strong> · {demo.id}
                </span>
                <span className="muted">{demo.note}</span>
              </button>
            ))}
          </div>
        </form>
      </div>
    </div>
  );
}
