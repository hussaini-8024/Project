import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { IconBook, IconClock, IconPulse, IconVideo } from "../components/Icons";
import { useAuth } from "../auth/AuthContext";
import { formatMinutes, formatPercent } from "../lib/format";
import type { AttendanceRow, ClassSession, Course } from "../types";

interface SubjectRow {
  courseId: number;
  courseCode: string;
  courseName: string;
  teacherName?: string;
  sessions: number;
  attended: number;
  percent: number;
  grade: string;
}

interface StudentReport {
  overall: {
    percent: number;
    grade: string;
    subjects: number;
    sessions: number;
    attended: number;
    absences: number;
    expectedSeconds: number;
    actualSeconds: number;
    low: boolean;
  };
  subjects: SubjectRow[];
}

interface Institution {
  classes: { id: number; course_code: string; course_name: string; students: number; sessions: number; average: number }[];
  students: { studentId: number; name: string; universityId: string; percent: number; grade: string; sessions: number; attended: number }[];
  lowAttendance: { studentId: number; name: string; percent: number; grade: string }[];
  settings: { low_threshold: number };
}

interface DashboardData {
  courses: Course[];
  classes: ClassSession[];
  attendance: AttendanceRow[];
  report: StudentReport | null;
  institution: Institution | null;
  notifications: { id: number; title: string; body: string; created_at: string }[];
  unread: number;
  stats: { courses: number; liveClasses: number; attendanceRecords: number; users: number };
}

function greeting(name: string) {
  const hour = new Date().getHours();
  const when = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";
  return `${when}, ${name.split(" ")[0]}`;
}

export function DashboardPage() {
  const { user } = useAuth();
  const [data, setData] = useState<DashboardData | null>(null);

  useEffect(() => {
    api<DashboardData>("/api/dashboard").then(setData);
  }, []);

  if (!user || !data) return <p className="muted">Opening your university workspace…</p>;

  const live = data.classes.filter((item) => item.status === "live" || item.is_open_lab);
  const upcoming = data.classes.filter((item) => item.status === "scheduled").slice(0, 5);
  const previous = data.classes.filter((item) => item.status === "ended").slice(0, 5);
  const teachers = [...new Set(data.courses.map((course) => course.teacher_name).filter(Boolean))];
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
          <span><IconBook size={16} /> {user.role === "student" ? "My classes" : "Courses"}</span>
          <strong>{data.stats.courses}</strong>
        </article>
        <article className="panel stat">
          <span><IconVideo size={16} /> Active meetings</span>
          <strong>{data.stats.liveClasses}</strong>
        </article>
        <article className="panel stat">
          <span><IconClock size={16} /> {data.report ? "Overall attendance" : "Attendance rows"}</span>
          <strong>{data.report ? formatPercent(data.report.overall.percent) : data.stats.attendanceRecords}</strong>
        </article>
        <article className="panel stat">
          <span><IconPulse size={16} /> {data.report ? "Attendance grade" : user.role === "admin" ? "People" : "Your ID"}</span>
          <strong>{data.report ? data.report.overall.grade : user.role === "admin" ? data.stats.users : user.universityId}</strong>
        </article>
      </div>

      {data.report ? (
        <section className="panel" style={{ marginTop: 18 }}>
          <div className="row" style={{ justifyContent: "space-between" }}>
            <h2>My attendance</h2>
            <Link className="btn" to="/reports">Open full report</Link>
          </div>
          <p className="muted">
            {formatMinutes(data.report.overall.actualSeconds)} connected of {formatMinutes(data.report.overall.expectedSeconds)} expected
            · {data.report.overall.attended}/{data.report.overall.sessions} sessions · {data.report.overall.absences} absences
          </p>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Subject</th>
                  <th>Teacher</th>
                  <th>Sessions</th>
                  <th>Attended</th>
                  <th>Attendance</th>
                  <th>Grade</th>
                </tr>
              </thead>
              <tbody>
                {data.report.subjects.map((row) => (
                  <tr key={row.courseId}>
                    <td><Link to={`/courses/${row.courseId}`}>{row.courseCode} {row.courseName}</Link></td>
                    <td>{row.teacherName ?? "—"}</td>
                    <td>{row.sessions}</td>
                    <td>{row.attended}</td>
                    <td>{formatPercent(row.percent)}</td>
                    <td><span className={`badge ${row.grade === "A" || row.grade === "B" ? "present" : "partial"}`}>{row.grade}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}

      <div className="grid two" style={{ marginTop: 18 }}>
        <section className="panel">
          <h2>Active meetings</h2>
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
          <h2>{user.role === "student" ? "My subjects" : "Your courses"}</h2>
          {data.courses.length === 0 ? <p className="empty">No courses assigned yet.</p> : null}
          {data.courses.map((course) => (
            <Link className="course-tile" key={course.id} to={`/courses/${course.id}`}>
              <strong>{course.course_code}</strong> {course.course_name}
              <div className="muted">
                {course.teacher_name ?? "Unassigned"} · {course.semester_name ?? `Sem ${course.semester}`} · {course.enrolled_count ?? 0} students
              </div>
            </Link>
          ))}
          {user.role === "student" && teachers.length ? (
            <p className="muted" style={{ marginTop: 16 }}>My teachers: {teachers.join(", ")}</p>
          ) : null}
        </section>
      </div>

      <div className="grid two" style={{ marginTop: 18 }}>
        <section className="panel">
          <h2>Upcoming classes</h2>
          {upcoming.length === 0 ? <p className="empty">Nothing scheduled.</p> : null}
          {upcoming.map((item) => (
            <div className="course-tile" key={item.id}>
              <strong>{item.course_code}</strong> {item.title}
              <div className="muted">{new Date(item.start_time).toLocaleString()}</div>
            </div>
          ))}
        </section>
        <section className="panel">
          <h2>Previous sessions</h2>
          {previous.length === 0 ? <p className="empty">No ended sessions yet.</p> : null}
          {previous.map((item) => (
            <div className="course-tile" key={item.id}>
              <strong>{item.session_code || item.course_code}</strong> {item.title}
              <div className="muted">{new Date(item.start_time).toLocaleString()}</div>
            </div>
          ))}
        </section>
      </div>

      {data.institution ? (
        <section className="panel" style={{ marginTop: 18 }}>
          <div className="row" style={{ justifyContent: "space-between" }}>
            <h2>{user.role === "admin" ? "Institution reports" : "Class reports"}</h2>
            <div className="row">
              <Link className="btn" to="/reports">All reports</Link>
              <Link className="btn" to="/analytics">Analytics</Link>
            </div>
          </div>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Class</th>
                  <th>Students</th>
                  <th>Sessions</th>
                  <th>Average attendance</th>
                </tr>
              </thead>
              <tbody>
                {data.institution.classes.map((row) => (
                  <tr key={row.id}>
                    <td><Link to={`/reports/courses/${row.id}`}>{row.course_code} {row.course_name}</Link></td>
                    <td>{row.students}</td>
                    <td>{row.sessions}</td>
                    <td>{formatPercent(row.average)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data.institution.lowAttendance.length ? (
            <>
              <h3 className="serif" style={{ marginTop: 18 }}>Below {data.institution.settings.low_threshold}% attendance</h3>
              {data.institution.lowAttendance.map((row) => (
                <p key={row.studentId}>
                  <Link to={`/reports/students/${row.studentId}`}>{row.name}</Link> · {formatPercent(row.percent)} · {row.grade}
                </p>
              ))}
            </>
          ) : (
            <p className="muted">No students are currently below the attendance threshold.</p>
          )}
        </section>
      ) : null}

      <section className="panel" style={{ marginTop: 18 }}>
        <div className="row" style={{ justifyContent: "space-between" }}>
          <h2>Notifications</h2>
          <Link className="btn" to="/notifications">{data.unread} unread</Link>
        </div>
        {data.notifications.length === 0 ? <p className="empty">No notifications yet.</p> : null}
        {data.notifications.map((item) => (
          <div className="course-tile" key={item.id}>
            <strong>{item.title}</strong>
            <div className="muted">{item.body}</div>
          </div>
        ))}
      </section>
    </>
  );
}
