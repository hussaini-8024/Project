import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { api, downloadCsv } from "../api/client";
import { useAuth } from "../auth/AuthContext";
import { formatPercent } from "../lib/format";

interface StudentRow {
  studentId: number;
  name: string;
  universityId: string;
  percent: number;
  grade: string;
  sessions: number;
  attended: number;
}

interface ClassRow {
  id: number;
  course_code: string;
  course_name: string;
  students: number;
  sessions: number;
  average: number;
}

interface Institution {
  classes: ClassRow[];
  students: StudentRow[];
  lowAttendance: StudentRow[];
  settings: { low_threshold: number };
}

interface MyReport {
  overall: { percent: number; grade: string; sessions: number; attended: number; expectedSeconds: number; actualSeconds: number };
  subjects: { courseId: number; courseCode: string; courseName: string; sessions: number; attended: number; percent: number; grade: string }[];
  student: { id: number; name: string; student_id: string };
}

export function ReportsPage() {
  const { user } = useAuth();
  const [mine, setMine] = useState<MyReport | null>(null);
  const [institution, setInstitution] = useState<Institution | null>(null);
  const [q, setQ] = useState("");
  const [grade, setGrade] = useState("");
  const [min, setMin] = useState("");
  const [max, setMax] = useState("");

  useEffect(() => {
    if (user?.role === "student") {
      api<{ report: MyReport }>("/api/reports/me").then((data) => setMine(data.report));
    } else {
      api<{ report: Institution }>("/api/reports/institution").then((data) => setInstitution(data.report));
    }
  }, [user?.role]);

  const students = useMemo(() => {
    if (!institution) return [];
    return institution.students.filter((row) => {
      const hay = `${row.name} ${row.universityId}`.toLowerCase();
      if (q && !hay.includes(q.toLowerCase())) return false;
      if (grade && row.grade !== grade) return false;
      if (min && row.percent < Number(min)) return false;
      if (max && row.percent > Number(max)) return false;
      return true;
    });
  }, [institution, q, grade, min, max]);

  if (user?.role === "student") {
    if (!mine) return <p className="muted">Loading your attendance report…</p>;
    return (
      <>
        <div className="topbar">
          <div>
            <p className="eyebrow">Student report</p>
            <h1>{mine.student.name}</h1>
          </div>
          <div className="row">
            <Link className="btn" to={`/reports/students/${mine.student.id}`}>Session history</Link>
            <button className="btn btn-gold" type="button" onClick={() => downloadCsv(`/api/reports/export?kind=student&studentId=${mine.student.id}`, "my-attendance.csv")}>
              Export CSV
            </button>
          </div>
        </div>
        <section className="panel">
          <p className="muted">
            Overall {formatPercent(mine.overall.percent)} · Grade {mine.overall.grade} · {mine.overall.attended}/{mine.overall.sessions} sessions
          </p>
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>Sessions</th>
                <th>Attended</th>
                <th>Attendance</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              {mine.subjects.map((row) => (
                <tr key={row.courseId}>
                  <td><Link to={`/courses/${row.courseId}`}>{row.courseCode} {row.courseName}</Link></td>
                  <td>{row.sessions}</td>
                  <td>{row.attended}</td>
                  <td>{formatPercent(row.percent)}</td>
                  <td>{row.grade}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </>
    );
  }

  if (!institution) return <p className="muted">Loading reports…</p>;

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">{user?.role === "admin" ? "Institution" : "Assigned classes"}</p>
          <h1>Attendance reports</h1>
        </div>
        <button className="btn btn-gold" type="button" onClick={() => downloadCsv("/api/reports/export?kind=institution", "attendance-institution.csv")}>
          Export CSV
        </button>
      </div>
      <section className="panel" style={{ marginBottom: 18 }}>
        <h2>Classes</h2>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Class</th>
                <th>Students</th>
                <th>Sessions</th>
                <th>Average</th>
              </tr>
            </thead>
            <tbody>
              {institution.classes.map((row) => (
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
      </section>
      <section className="panel">
        <h2>Students</h2>
        <div className="filters">
          <input placeholder="Search student" value={q} onChange={(e) => setQ(e.target.value)} />
          <input placeholder="Min %" value={min} onChange={(e) => setMin(e.target.value)} />
          <input placeholder="Max %" value={max} onChange={(e) => setMax(e.target.value)} />
          <select value={grade} onChange={(e) => setGrade(e.target.value)}>
            <option value="">All grades</option>
            {["A", "B", "C", "D", "F"].map((item) => (
              <option key={item} value={item}>{item}</option>
            ))}
          </select>
        </div>
        <p className="muted">Low-attendance threshold: {institution.settings.low_threshold}%</p>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Sessions</th>
                <th>Attended</th>
                <th>Attendance</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              {students.map((row) => (
                <tr key={row.studentId}>
                  <td>
                    <Link to={`/reports/students/${row.studentId}`}>{row.name}</Link>
                    <span className="muted"> · {row.universityId}</span>
                  </td>
                  <td>{row.sessions}</td>
                  <td>{row.attended}</td>
                  <td>{formatPercent(row.percent)}</td>
                  <td>{row.grade}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </>
  );
}
