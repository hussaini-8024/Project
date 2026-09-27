import { useEffect, useState } from "react";
import { api } from "../api/client";

interface Semester {
  id: number;
  name: string;
  academic_year: string;
  start_date: string;
  end_date: string;
  status: string;
}

export function SemestersPage() {
  const [rows, setRows] = useState<Semester[]>([]);
  const [form, setForm] = useState({
    name: "Fall 2027",
    academicYear: "2027-2028",
    startDate: "2027-08-15",
    endDate: "2027-12-20",
    status: "planned",
  });

  const load = () => api<{ semesters: Semester[] }>("/api/semesters").then((data) => setRows(data.semesters));

  useEffect(() => {
    load();
  }, []);

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">Academic calendar</p>
          <h1>Semesters</h1>
        </div>
      </div>
      <section className="panel" style={{ marginBottom: 18 }}>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Year</th>
                <th>Dates</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.name}</td>
                  <td>{row.academic_year}</td>
                  <td>{String(row.start_date).slice(0, 10)} — {String(row.end_date).slice(0, 10)}</td>
                  <td><span className={`badge ${row.status === "active" ? "present" : "scheduled"}`}>{row.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      <section className="panel">
        <h2>Create semester</h2>
        <label>Name<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
        <label>Academic year<input value={form.academicYear} onChange={(e) => setForm({ ...form, academicYear: e.target.value })} /></label>
        <label>Start<input type="date" value={form.startDate} onChange={(e) => setForm({ ...form, startDate: e.target.value })} /></label>
        <label>End<input type="date" value={form.endDate} onChange={(e) => setForm({ ...form, endDate: e.target.value })} /></label>
        <label>Status
          <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
            <option value="planned">planned</option>
            <option value="active">active</option>
            <option value="closed">closed</option>
          </select>
        </label>
        <button
          className="btn btn-gold"
          type="button"
          onClick={async () => {
            await api("/api/semesters", { method: "POST", body: JSON.stringify(form) });
            load();
          }}
        >
          Save semester
        </button>
      </section>
    </>
  );
}
