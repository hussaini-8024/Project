import { useEffect, useState } from "react";
import { api } from "../api/client";
import { StatusBadge } from "../components/StatusBadge";
import type { AttendanceRow } from "../types";

function formatDuration(seconds: number) {
  const minutes = Math.round(seconds / 60);
  return `${minutes} min`;
}

export function AttendancePage() {
  const [rows, setRows] = useState<AttendanceRow[]>([]);

  useEffect(() => {
    api<{ attendance: AttendanceRow[] }>("/api/attendance").then((data) => setRows(data.attendance));
  }, []);

  return (
    <>
      <div className="topbar">
        <div>
          <p className="muted">University rule</p>
          <h1>Attendance</h1>
        </div>
      </div>
      <p className="muted">
        Open-lab / long sessions: 45+ minutes present, 20–44 partial, under 20 insufficient. Scheduled
        lectures use 75% / 40% of the official class length.
      </p>
      <section className="panel" style={{ padding: 18, marginTop: 16 }}>
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Course</th>
              <th>Session</th>
              <th>Joined</th>
              <th>Left</th>
              <th>Duration</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td>
                  {row.student_name} <span className="muted">{row.university_student_id}</span>
                </td>
                <td>
                  {row.course_code} {row.course_name}
                </td>
                <td>{row.class_title}</td>
                <td>{row.join_time ? new Date(row.join_time).toLocaleString() : "—"}</td>
                <td>{row.leave_time ? new Date(row.leave_time).toLocaleString() : "in session"}</td>
                <td>{formatDuration(row.duration_seconds)}</td>
                <td>
                  <StatusBadge value={row.status} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {rows.length === 0 ? <p className="empty">No attendance yet. Join a class to start the clock.</p> : null}
      </section>
    </>
  );
}
