import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { StatusBadge } from "../components/StatusBadge";
import { useAuth } from "../auth/AuthContext";
import type { AttendanceRow, ClassSession, Course } from "../types";

interface DashboardData {
  courses: Course[];
  classes: ClassSession[];
  attendance: AttendanceRow[];
  stats: { courses: number; liveClasses: number; attendanceRecords: number; users: number };
}

export function DashboardPage() {
  const { user } = useAuth();
  const [data, setData] = useState<DashboardData | null>(null);

  useEffect(() => {
    api<DashboardData>("/api/dashboard").then(setData);
  }, []);

  if (!user || !data) return <p className="muted">Loading your university workspace…</p>;

  const live = data.classes.filter((item) => item.status === "live" || item.is_open_lab);
  const title =
    user.role === "admin" ? "Registrar dashboard" : user.role === "teacher" ? "Teaching dashboard" : "Student dashboard";

  return (
    <>
      <div className="topbar">
        <div>
          <p className="muted">Faculty of Computing</p>
          <h1>{title}</h1>
        </div>
        <span className={`badge ${user.role}`}>{user.role}</span>
      </div>
      <div className="grid stats">
        <article className="panel stat">
          <span>Courses</span>
          <strong>{data.stats.courses}</strong>
        </article>
        <article className="panel stat">
          <span>Live classrooms</span>
          <strong>{data.stats.liveClasses}</strong>
        </article>
        <article className="panel stat">
          <span>Attendance records</span>
          <strong>{data.stats.attendanceRecords}</strong>
        </article>
        <article className="panel stat">
          <span>{user.role === "admin" ? "People" : "Your ID"}</span>
          <strong>{user.role === "admin" ? data.stats.users : user.universityId}</strong>
        </article>
      </div>
      <div className="grid two" style={{ marginTop: 18 }}>
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Join a class</h2>
          <p className="muted">Campus live rooms are listed here. Enrollment is checked on the server when you join.</p>
          {live.length === 0 ? (
            <p className="empty">No live class right now.</p>
          ) : (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Course</th>
                    <th>Session</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {live.map((item) => (
                    <tr key={item.id}>
                      <td>
                        {item.course_code} · {item.course_name}
                      </td>
                      <td>{item.title}</td>
                      <td>
                        <StatusBadge value={item.status} />
                      </td>
                      <td>
                        <Link className="btn btn-gold" to={`/classroom/${item.id}`}>
                          Join class
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Your courses</h2>
          {data.courses.map((course) => (
            <p key={course.id}>
              <Link to={`/courses/${course.id}`}>
                <strong>{course.course_code}</strong> {course.course_name}
              </Link>
              <br />
              <span className="muted">
                {course.teacher_name ?? "Unassigned"} · {course.enrolled_count ?? 0} students
              </span>
            </p>
          ))}
        </section>
      </div>
    </>
  );
}
