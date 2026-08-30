import { useState, type FormEvent } from "react";
import { api } from "../api/client";
import { useAuth } from "../auth/AuthContext";

export function ProfilePage() {
  const { user, refresh } = useAuth();
  const [name, setName] = useState(user?.name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [message, setMessage] = useState("");

  if (!user) return null;

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    await api("/api/profile", {
      method: "PUT",
      body: JSON.stringify({
        name,
        email,
        currentPassword: currentPassword || undefined,
        newPassword: newPassword || undefined,
      }),
    });
    await refresh();
    setCurrentPassword("");
    setNewPassword("");
    setMessage("Profile saved.");
  };

  return (
    <>
      <div className="topbar">
        <div>
          <p className="muted">{user.universityId}</p>
          <h1>Profile</h1>
        </div>
      </div>
      <form className="panel" style={{ padding: 18, maxWidth: 560 }} onSubmit={onSubmit}>
        <label>
          Full name
          <input value={name} onChange={(e) => setName(e.target.value)} />
        </label>
        <label>
          University email
          <input value={email} onChange={(e) => setEmail(e.target.value)} />
        </label>
        {user.student ? (
          <p className="muted">
            BSCS · Semester {user.student.semester} · Section {user.student.section}
          </p>
        ) : null}
        {user.teacher ? <p className="muted">{user.teacher.title}</p> : null}
        <label>
          Current password
          <input type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} />
        </label>
        <label>
          New password
          <input type="password" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} />
        </label>
        {message ? <p>{message}</p> : null}
        <button className="btn btn-primary" style={{ width: "auto" }}>
          Save profile
        </button>
      </form>
    </>
  );
}
