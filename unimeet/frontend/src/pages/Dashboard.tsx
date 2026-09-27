import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { IconBook, IconClock, IconPulse, IconVideo } from "../components/Icons";
import { useAuth } from "../auth/AuthContext";
import type { AttendanceRow, ClassSession, Course } from "../types";

interface DashboardData {
  courses: Course[];
  classes: ClassSession[];
  attendance: AttendanceRow[];
  stats: { courses: number; liveClasses: number; attendanceRecords: number; users: number };
}

function greeting(name: string) {
  const hour = new Date().getHours();
  const when = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";
  const first = name.split(" ")[0];
  return `${when}, ${first}`;
}

export function DashboardPage() {
  const { user } = useAuth();
  const [data, setData] = useState<DashboardData | null>(null);

  useEffect(() => {
    api<DashboardData>("/api/dashboard").then(setData);
  }, []);

  if (!user || !data) return <p className="muted">Opening your university workspace…</p>;

  const live = data.classes.filter((item) => item.status === "live" || item.is_open_lab);
  const title =
    user.role === "admin" ? "Registrar desk" : user.role === "teacher" ? "Teaching studio" : "Student hall";

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">Faculty of Computing</p>
          <h1>{greeting(user.name)}</h1>
          <p className="muted">{title} · Horizon Hall is open for today’s lectures.</p>
        </div>
        <span className={`badge ${user.role}`}>{user.role}</span>
      </div>
      <div className="grid stats">
        <article className="panel stat">
          <span><IconBook size={16} /> Courses</span>
          <strong>{data.stats.courses}</strong>
        </article>
        <article className="panel stat">
          <span><IconVideo size={16} /> Live rooms</span>
          <strong>{data.stats.liveClasses}</strong>
        </article>
        <article className="panel stat">
          <span><IconClock size={16} /> Attendance</span>
          <strong>{data.stats.attendanceRecords}</strong>
        </article>
        <article className="panel stat">
          <span><IconPulse size={16} /> {user.role === "admin" ? "People" : "Your ID"}</span>
          <strong>{user.role === "admin" ? data.stats.users : user.universityId}</strong>
        </article>
      </div>
      <div className="grid two" style={{ marginTop: 8 }}>
        <section className="panel">
          <h2>Join a class</h2>
          <p className="muted">Campus live rooms appear here. Enrollment is checked on the server when you join.</p>
          {live.length === 0 ? (
            <p className="empty">No live class right now.</p>
          ) : (
            live.map((item) => (
              <div className="live-card" key={item.id}>
                <div>
                  <div className="pulse">Live now</div>
                  <h3 className="serif" style={{ margin: "8px 0 4px", fontSize: 26 }}>
                    {item.course_code} · {item.course_name}
                  </h3>
                  <div className="muted" style={{ color: "#d9d1c0" }}>{item.title}</div>
                </div>
                <Link className="btn btn-gold" to={`/classroom/${item.id}`}>
                  Join class
                </Link>
              </div>
            ))
          )}
        </section>
        <section className="panel">
          <h2>Your courses</h2>
          {data.courses.length === 0 ? <p className="empty">No courses assigned yet.</p> : null}
          {data.courses.map((course) => (
            <Link className="course-tile" key={course.id} to={`/courses/${course.id}`}>
              <strong>{course.course_code}</strong> {course.course_name}
              <div className="muted">
                {course.teacher_name ?? "Unassigned"} · {course.enrolled_count ?? 0} students
              </div>
            </Link>
          ))}
        </section>
      </div>
    </>
  );
}
