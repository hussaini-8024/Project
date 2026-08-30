import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { api } from "../api/client";
import { StatusBadge } from "../components/StatusBadge";
import { useAuth } from "../auth/AuthContext";
import type { ClassSession, Course, Enrollment } from "../types";

export function CourseDetailPage() {
  const { id } = useParams();
  const { user } = useAuth();
  const [course, setCourse] = useState<Course | null>(null);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [classes, setClasses] = useState<ClassSession[]>([]);

  useEffect(() => {
    if (!id) return;
    api<{ course: Course; enrollments: Enrollment[] }>(`/api/courses/${id}`).then((data) => {
      setCourse(data.course);
      setEnrollments(data.enrollments);
    });
    api<{ classes: ClassSession[] }>(`/api/classes?courseId=${id}`).then((data) => setClasses(data.classes));
  }, [id]);

  if (!course) return <p className="muted">Loading course…</p>;

  return (
    <>
      <div className="topbar">
        <div>
          <p className="muted">
            {course.program_code} · Semester {course.semester} · Section {course.section}
          </p>
          <h1>
            {course.course_code} {course.course_name}
          </h1>
        </div>
        <span className="muted">{course.teacher_title} {course.teacher_name}</span>
      </div>
      <p>{course.description}</p>
      <div className="grid two" style={{ marginTop: 18 }}>
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Class sessions</h2>
          <table>
            <tbody>
              {classes.map((item) => (
                <tr key={item.id}>
                  <td>{item.title}</td>
                  <td>
                    <StatusBadge value={item.status} />
                  </td>
                  <td>
                    {item.status !== "ended" && (user?.role !== "student" || item.status === "live" || item.is_open_lab) ? (
                      <Link className="btn btn-gold" to={`/classroom/${item.id}`}>
                        Join class
                      </Link>
                    ) : (
                      "—"
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Roster</h2>
          {enrollments.map((row) => (
            <p key={row.id}>
              {row.name} · {row.university_student_id}
              <span className="muted"> · Section {row.section}</span>
            </p>
          ))}
          {enrollments.length === 0 ? <p className="empty">No students enrolled.</p> : null}
        </section>
      </div>
    </>
  );
}
