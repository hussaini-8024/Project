import { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { api, downloadCsv } from "../api/client";
import { formatMinutes, formatPercent } from "../lib/format";

interface StudentRow {
  studentId: number;
  name: string;
  universityId: string;
  sessions: number;
  attended: number;
  actualSeconds: number;
  expectedSeconds: number;
  percent: number;
  grade: string;
  status: string;
}

interface CourseReport {
  courseId: number;
  students: StudentRow[];
  sessions: number;
  enrolled: number;
  average: number;
  highest: number;
  lowest: number;
  belowThreshold: StudentRow[];
  gradeDistribution: { grade: string; count: number }[];
  settings: { low_threshold: number };
}

export function CourseReportPage() {
  const { courseId } = useParams();
  const [report, setReport] = useState<CourseReport | null>(null);
  const [q, setQ] = useState("");
  const [grade, setGrade] = useState("");

  useEffect(() => {
    if (!courseId) return;
    api<{ report: CourseReport }>(`/api/reports/courses/${courseId}`).then((data) => setReport(data.report));
  }, [courseId]);

  const rows = useMemo(() => {
    if (!report) return [];
    return report.students.filter((row) => {
      if (q && !`${row.name} ${row.universityId}`.toLowerCase().includes(q.toLowerCase())) return false;
      if (grade && row.grade !== grade) return false;
      return true;
    });
  }, [report, q, grade]);

  if (!report) return <p className="muted">Loading class report…</p>;

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">Class attendance</p>
          <h1>Course report</h1>
        </div>
        <button className="btn btn-gold" type="button" onClick={() => downloadCsv(`/api/reports/export?kind=course&courseId=${courseId}`, `course-${courseId}.csv`)}>
          Export CSV
        </button>
      </div>
      <div className="grid stats">
        <article className="panel stat"><span>Students</span><strong>{report.enrolled}</strong></article>
        <article className="panel stat"><span>Sessions</span><strong>{report.sessions}</strong></article>
        <article className="panel stat"><span>Average</span><strong>{formatPercent(report.average)}</strong></article>
        <article className="panel stat"><span>Range</span><strong>{formatPercent(report.lowest)}–{formatPercent(report.highest)}</strong></article>
      </div>
      <section className="panel" style={{ marginTop: 18 }}>
        <div className="filters">
          <input placeholder="Search student" value={q} onChange={(e) => setQ(e.target.value)} />
          <select value={grade} onChange={(e) => setGrade(e.target.value)}>
            <option value="">All grades</option>
            {report.gradeDistribution.map((item) => (
              <option key={item.grade} value={item.grade}>{item.grade} ({item.count})</option>
            ))}
          </select>
        </div>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Sessions</th>
                <th>Attended</th>
                <th>Actual</th>
                <th>Attendance</th>
                <th>Grade</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.studentId}>
                  <td>
                    <Link to={`/reports/students/${row.studentId}`}>{row.name}</Link>
                    <span className="muted"> · {row.universityId}</span>
                  </td>
                  <td>{row.sessions}</td>
                  <td>{row.attended}</td>
                  <td>{formatMinutes(row.actualSeconds)}</td>
                  <td>{formatPercent(row.percent)}</td>
                  <td>{row.grade}</td>
                  <td><span className={`badge ${row.status === "Low" ? "insufficient" : "present"}`}>{row.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="muted" style={{ marginTop: 12 }}>
          {report.belowThreshold.length} students below the {report.settings.low_threshold}% threshold.
        </p>
      </section>
    </>
  );
}
