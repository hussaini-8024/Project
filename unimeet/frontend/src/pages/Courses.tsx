import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { useAuth } from "../auth/AuthContext";
import type { Course } from "../types";

interface Catalog {
  programs: { id: number; code: string; department_id: number }[];
  departments: { id: number; code: string }[];
  students: { id: number; student_id: string; name: string; section: string }[];
  teachers: { id: number; teacher_id: string; name: string; title: string }[];
}

export function CoursesPage() {
  const { user } = useAuth();
  const [courses, setCourses] = useState<Course[]>([]);
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [step, setStep] = useState(1);
  const [form, setForm] = useState({
    programId: 0,
    semester: 5,
    section: "A",
    courseCode: "CS-502",
    courseName: "Software Engineering",
    teacherId: 0,
    description: "Software process, requirements, and team delivery.",
    studentIds: [] as number[],
  });
  const [message, setMessage] = useState("");

  const reload = () => api<{ courses: Course[] }>("/api/courses").then((d) => setCourses(d.courses));

  useEffect(() => {
    reload();
    if (user?.role === "admin") {
      api<Catalog>("/api/catalog").then((data) => {
        setCatalog(data);
        setForm((current) => ({
          ...current,
          programId: data.programs[0]?.id ?? 0,
          teacherId: data.teachers[0]?.id ?? 0,
        }));
      });
    }
  }, [user?.role]);

  const enrolledPreview = useMemo(() => {
    if (!catalog) return [];
    return catalog.students.filter((s) => form.studentIds.includes(s.id));
  }, [catalog, form.studentIds]);

  const create = async () => {
    await api("/api/courses", {
      method: "POST",
      body: JSON.stringify({
        ...form,
        departmentId: catalog?.programs.find((p) => p.id === form.programId)?.department_id ?? null,
      }),
    });
    setMessage("Course created and selected students enrolled.");
    setStep(1);
    reload();
  };

  return (
    <>
      <div className="topbar">
        <div>
          <p className="muted">Academic structure</p>
          <h1>Courses</h1>
        </div>
      </div>
      <section className="panel" style={{ padding: 18, marginBottom: 18 }}>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Code</th>
                <th>Course</th>
                <th>Teacher</th>
                <th>Program</th>
                <th>Students</th>
              </tr>
            </thead>
            <tbody>
              {courses.map((course) => (
                <tr key={course.id}>
                  <td>
                    <Link to={`/courses/${course.id}`}>{course.course_code}</Link>
                  </td>
                  <td>{course.course_name}</td>
                  <td>{course.teacher_name ?? "—"}</td>
                  <td>
                    {course.program_code} · Sem {course.semester} · {course.section}
                  </td>
                  <td>{course.enrolled_count ?? 0}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {user?.role === "admin" && catalog ? (
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Create course & enroll</h2>
          <p className="muted">BSCS → semester → section → course → teacher → students.</p>
          <div className="wizard" style={{ marginTop: 16 }}>
            <div className="steps">
              {[1, 2, 3, 4, 5, 6].map((n) => (
                <button key={n} className={`btn ${step === n ? "btn-primary" : ""}`} type="button" onClick={() => setStep(n)}>
                  Step {n}
                </button>
              ))}
            </div>
            <div>
              {step === 1 && (
                <label>
                  Program
                  <select value={form.programId} onChange={(e) => setForm({ ...form, programId: Number(e.target.value) })}>
                    {catalog.programs.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.code}
                      </option>
                    ))}
                  </select>
                </label>
              )}
              {step === 2 && (
                <label>
                  Semester
                  <input type="number" min={1} max={8} value={form.semester} onChange={(e) => setForm({ ...form, semester: Number(e.target.value) })} />
                </label>
              )}
              {step === 3 && (
                <label>
                  Section
                  <input value={form.section} onChange={(e) => setForm({ ...form, section: e.target.value })} />
                </label>
              )}
              {step === 4 && (
                <>
                  <label>
                    Course code
                    <input value={form.courseCode} onChange={(e) => setForm({ ...form, courseCode: e.target.value })} />
                  </label>
                  <label>
                    Course name
                    <input value={form.courseName} onChange={(e) => setForm({ ...form, courseName: e.target.value })} />
                  </label>
                  <label>
                    Description
                    <textarea rows={3} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                  </label>
                </>
              )}
              {step === 5 && (
                <label>
                  Teacher
                  <select value={form.teacherId} onChange={(e) => setForm({ ...form, teacherId: Number(e.target.value) })}>
                    {catalog.teachers.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.title} {t.name} ({t.teacher_id})
                      </option>
                    ))}
                  </select>
                </label>
              )}
              {step === 6 && (
                <>
                  <div className="checks">
                    {catalog.students.map((s) => (
                      <label key={s.id} className="check">
                        <input
                          type="checkbox"
                          checked={form.studentIds.includes(s.id)}
                          onChange={(e) => {
                            setForm({
                              ...form,
                              studentIds: e.target.checked
                                ? [...form.studentIds, s.id]
                                : form.studentIds.filter((id) => id !== s.id),
                            });
                          }}
                        />
                        {s.name} · {s.student_id} · Section {s.section}
                      </label>
                    ))}
                  </div>
                  <p className="muted">{enrolledPreview.length} students selected. Leave John unchecked to keep him out of CS-501.</p>
                  <button className="btn btn-gold" type="button" onClick={create}>
                    Publish course
                  </button>
                </>
              )}
              {message ? <p>{message}</p> : null}
            </div>
          </div>
        </section>
      ) : null}
    </>
  );
}
