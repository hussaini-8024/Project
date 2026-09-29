import { useEffect, useState } from "react";
import { api } from "../api/client";

interface Rule {
  minPercent: number;
  maxPercent: number;
  grade: string;
  label: string;
}

export function GradesAdminPage() {
  const [rules, setRules] = useState<Rule[]>([]);
  const [lowThreshold, setLowThreshold] = useState(75);
  const [message, setMessage] = useState("");

  useEffect(() => {
    api<{
      rules: { min_percent: number; max_percent: number; grade: string; label: string | null }[];
      settings: { low_threshold: number };
    }>("/api/attendance/grades").then((data) => {
      setRules(
        data.rules.map((rule) => ({
          minPercent: Number(rule.min_percent),
          maxPercent: Number(rule.max_percent),
          grade: rule.grade,
          label: rule.label ?? "",
        })),
      );
      setLowThreshold(data.settings.low_threshold);
    });
  }, []);

  const save = async () => {
    await api("/api/attendance/grades", {
      method: "PUT",
      body: JSON.stringify({ rules, settings: { lowThreshold } }),
    });
    setMessage("Attendance grades and low-attendance threshold saved.");
  };

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">Administrator</p>
          <h1>Attendance grading</h1>
        </div>
        <button className="btn btn-gold" type="button" onClick={save}>Save policy</button>
      </div>
      <p className="page-lead muted">
        Grades are assigned from the student’s duration-weighted attendance percentage. These thresholds are not hard-coded in reports.
      </p>
      <section className="panel">
        <label>
          Low-attendance threshold (%)
          <input type="number" min={0} max={100} value={lowThreshold} onChange={(e) => setLowThreshold(Number(e.target.value))} />
        </label>
        {rules.map((rule, index) => (
          <div className="filters" key={index}>
            <input type="number" value={rule.minPercent} onChange={(e) => {
              const next = [...rules];
              next[index] = { ...rule, minPercent: Number(e.target.value) };
              setRules(next);
            }} />
            <input type="number" value={rule.maxPercent} onChange={(e) => {
              const next = [...rules];
              next[index] = { ...rule, maxPercent: Number(e.target.value) };
              setRules(next);
            }} />
            <input value={rule.grade} onChange={(e) => {
              const next = [...rules];
              next[index] = { ...rule, grade: e.target.value };
              setRules(next);
            }} />
            <input value={rule.label} onChange={(e) => {
              const next = [...rules];
              next[index] = { ...rule, label: e.target.value };
              setRules(next);
            }} />
          </div>
        ))}
        <button
          className="btn"
          type="button"
          onClick={() => setRules([...rules, { minPercent: 0, maxPercent: 0, grade: "", label: "" }])}
        >
          Add rule
        </button>
        {message ? <p>{message}</p> : null}
      </section>
    </>
  );
}
