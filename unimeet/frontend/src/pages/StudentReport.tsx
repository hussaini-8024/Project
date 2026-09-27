import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { api, downloadCsv } from "../api/client";
import { formatMinutes, formatPercent, formatWhen } from "../lib/format";

interface Segment {
  joined_at: string;
  left_at: string | null;
  duration_seconds: number;
}

interface HistoryRow {
  classId: number;
  sessionCode: string;
  title: string;
  date: string;
  subject: string;
  courseCode: string;
  start: string;
  end: string;
  firstJoin: string | null;
  lastLeave: string | null;
  joins: number;
  leaves: number;
  durationSeconds: number;
  expectedSeconds: number;
  percent: number;
  status: string;
  segments: Segment[];
}

interface Report {
  student: {
    name: string;
    student_id: string;
    semester: number;
    section: string;
    department: string;
    program: string;
    email: string;
  };
  overall: {
    percent: number;
    grade: string;
    subjects: number;
    sessions: number;
    attended: number;
    absences: number;
    lateJoins: number;
    earlyLeaves: number;
    rejoins: number;
    expectedSeconds: number;
    actualSeconds: number;
    low: boolean;
  };
  subjects: {
    courseId: number;
    courseCode: string;
    courseName: string;
    teacherName: string;
    sessions: number;
    attended: number;
    expectedSeconds: number;
    actualSeconds: number;
    percent: number;
    grade: string;
  }[];
  history: HistoryRow[];
}

export function StudentReportPage() {
  const { studentId } = useParams();
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!studentId) return;
    api<{ report: Report }>(`/api/reports/students/${studentId}`)
      .then((data) => setReport(data.report))
      .catch((err) => setError(err.message ?? "Unable to load report"));
  }, [studentId]);

  if (error) return <p className="error">{error}</p>;
  if (!report) return <p className="muted">Loading student report…</p>;

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">{report.student.program} · {report.student.department} · Section {report.student.section}</p>
          <h1>{report.student.name}</h1>
          <p className="muted">{report.student.student_id} · {report.student.email}</p>
        </div>
        <button
          className="btn btn-gold"
          type="button"
          onClick={() => downloadCsv(`/api/reports/export?kind=student&studentId=${studentId}`, `student-${report.student.student_id}.csv`)}
        >
          Export CSV
        </button>
      </div>
      <div className="grid stats">
        <article className="panel stat"><span>Overall</span><strong>{formatPercent(report.overall.percent)}</strong></article>
        <article className="panel stat"><span>Grade</span><strong>{report.overall.grade}</strong></article>
        <article className="panel stat"><span>Sessions</span><strong>{report.overall.attended}/{report.overall.sessions}</strong></article>
        <article className="panel stat"><span>Connected</span><strong>{formatMinutes(report.overall.actualSeconds)}</strong></article>
      </div>
      <p className="page-lead muted">
        Expected {formatMinutes(report.overall.expectedSeconds)} · Absences {report.overall.absences} · Late joins {report.overall.lateJoins} · Early leaves {report.overall.earlyLeaves} · Rejoins {report.overall.rejoins}
        {report.overall.low ? " · Below required attendance" : ""}
      </p>
      <section className="panel" style={{ marginBottom: 18 }}>
        <h2>Subject attendance</h2>
        <p className="muted">Overall percentage uses total actual minutes / total expected minutes, not an average of subject percentages.</p>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Sessions</th>
                <th>Attended</th>
                <th>Expected</th>
                <th>Actual</th>
                <th>%</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              {report.subjects.map((row) => (
                <tr key={row.courseId}>
                  <td><Link to={`/reports/courses/${row.courseId}`}>{row.courseCode} {row.courseName}</Link></td>
                  <td>{row.teacherName}</td>
                  <td>{row.sessions}</td>
                  <td>{row.attended}</td>
                  <td>{formatMinutes(row.expectedSeconds)}</td>
                  <td>{formatMinutes(row.actualSeconds)}</td>
                  <td>{formatPercent(row.percent)}</td>
                  <td>{row.grade}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      <section className="panel">
        <h2>Session history</h2>
        {report.history.map((row) => (
          <article className="history-card" key={row.classId}>
            <div className="row" style={{ justifyContent: "space-between" }}>
              <strong>{row.courseCode} · {row.subject} · {row.sessionCode || row.title}</strong>
              <span className={`badge ${row.status}`}>{formatPercent(row.percent)} · {row.status}</span>
            </div>
            <p className="muted">
              {formatWhen(row.start)} — {formatWhen(row.end)} · first join {formatWhen(row.firstJoin)} · last leave {formatWhen(row.lastLeave)} · {row.joins} joins / {row.leaves} leaves · {formatMinutes(row.durationSeconds)} of {formatMinutes(row.expectedSeconds)}
            </p>
            {row.segments.map((segment, index) => (
              <div className="metric" key={`${row.classId}-${index}`}>
                <span>{formatWhen(segment.joined_at)} — {segment.left_at ? formatWhen(segment.left_at) : "still connected"}</span>
                <span>{formatMinutes(segment.duration_seconds || 0)}</span>
              </div>
            ))}
          </article>
        ))}
        {report.history.length === 0 ? <p className="empty">No ended or live sessions yet.</p> : null}
      </section>
    </>
  );
}
