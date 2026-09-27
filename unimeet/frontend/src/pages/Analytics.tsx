import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { formatPercent } from "../lib/format";

interface Analytics {
  classes: { id: number; course_code: string; course_name: string; average: number; students: number; sessions: number }[];
  students: { studentId: number; name: string; percent: number; grade: string }[];
  gradeDistribution: { grade: string; count: number }[];
  bySubject: { label: string; value: number }[];
  trend: { day: string; percent: number }[];
  highAttendance: { studentId: number; name: string; percent: number }[];
  lowAttendance: { studentId: number; name: string; percent: number }[];
  settings: { low_threshold: number };
}

export function AnalyticsPage() {
  const [data, setData] = useState<Analytics | null>(null);

  useEffect(() => {
    api<{ analytics: Analytics }>("/api/reports/analytics").then((row) => setData(row.analytics));
  }, []);

  if (!data) return <p className="muted">Building attendance analytics…</p>;
  const maxSubject = Math.max(1, ...data.bySubject.map((row) => row.value));
  const maxTrend = Math.max(1, ...data.trend.map((row) => row.percent));
  const maxGrade = Math.max(1, ...data.gradeDistribution.map((row) => row.count));

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">Duration-weighted</p>
          <h1>Attendance analytics</h1>
        </div>
      </div>
      <div className="grid two">
        <section className="panel">
          <h2>Attendance by subject</h2>
          {data.bySubject.map((row) => (
            <div className="bar-row" key={row.label}>
              <span>{row.label}</span>
              <div className="bar"><i style={{ width: `${(row.value / maxSubject) * 100}%` }} /></div>
              <strong>{formatPercent(row.value)}</strong>
            </div>
          ))}
        </section>
        <section className="panel">
          <h2>Grade distribution</h2>
          {data.gradeDistribution.map((row) => (
            <div className="bar-row" key={row.grade}>
              <span>{row.grade}</span>
              <div className="bar"><i style={{ width: `${(row.count / maxGrade) * 100}%` }} /></div>
              <strong>{row.count}</strong>
            </div>
          ))}
        </section>
      </div>
      <section className="panel" style={{ marginTop: 18 }}>
        <h2>Attendance trend</h2>
        {data.trend.length === 0 ? <p className="empty">No ended sessions yet.</p> : null}
        {data.trend.map((row) => (
          <div className="bar-row" key={row.day}>
            <span>{row.day}</span>
            <div className="bar"><i style={{ width: `${(row.percent / maxTrend) * 100}%` }} /></div>
            <strong>{formatPercent(row.percent)}</strong>
          </div>
        ))}
      </section>
      <div className="grid two" style={{ marginTop: 18 }}>
        <section className="panel">
          <h2>High attendance</h2>
          {data.highAttendance.map((row) => (
            <p key={row.studentId}><Link to={`/reports/students/${row.studentId}`}>{row.name}</Link> · {formatPercent(row.percent)}</p>
          ))}
        </section>
        <section className="panel">
          <h2>Below {data.settings.low_threshold}%</h2>
          {data.lowAttendance.length === 0 ? <p className="empty">No students below the threshold.</p> : null}
          {data.lowAttendance.map((row) => (
            <p key={row.studentId}><Link to={`/reports/students/${row.studentId}`}>{row.name}</Link> · {formatPercent(row.percent)}</p>
          ))}
        </section>
      </div>
    </>
  );
}
