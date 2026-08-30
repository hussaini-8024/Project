import { query } from "../db/pool.js";
import type { AttendanceStatus } from "../types.js";

export async function getAttendance(studentId: number, classId: number) {
  const result = await query(
    `SELECT * FROM attendance WHERE student_id = $1 AND class_id = $2`,
    [studentId, classId],
  );
  return result.rows[0] ?? null;
}

export async function markJoin(studentId: number, classId: number) {
  const result = await query(
    `INSERT INTO attendance (student_id, class_id, join_time, last_join_time)
     VALUES ($1, $2, now(), now())
     ON CONFLICT (student_id, class_id) DO UPDATE
       SET last_join_time = now(),
           leave_time = NULL
     RETURNING *`,
    [studentId, classId],
  );
  return result.rows[0];
}

export async function markLeave(
  studentId: number,
  classId: number,
  addedSeconds: number,
  status: AttendanceStatus,
) {
  const result = await query(
    `UPDATE attendance
     SET leave_time = now(),
         duration_seconds = duration_seconds + $3,
         status = $4
     WHERE student_id = $1 AND class_id = $2
     RETURNING *`,
    [studentId, classId, addedSeconds, status],
  );
  return result.rows[0] ?? null;
}

export async function listAttendance(filters: {
  studentId?: number;
  classId?: number;
  courseId?: number;
}) {
  const clauses: string[] = [];
  const params: unknown[] = [];
  if (filters.studentId) {
    params.push(filters.studentId);
    clauses.push(`a.student_id = $${params.length}`);
  }
  if (filters.classId) {
    params.push(filters.classId);
    clauses.push(`a.class_id = $${params.length}`);
  }
  if (filters.courseId) {
    params.push(filters.courseId);
    clauses.push(`cl.course_id = $${params.length}`);
  }
  const where = clauses.length ? `WHERE ${clauses.join(" AND ")}` : "";
  const result = await query(
    `SELECT a.*, s.student_id AS university_student_id, u.name AS student_name,
            cl.title AS class_title, cl.start_time, cl.end_time, cl.status AS class_status,
            c.course_code, c.course_name
     FROM attendance a
     JOIN students s ON s.id = a.student_id
     JOIN users u ON u.id = s.user_id
     JOIN classes cl ON cl.id = a.class_id
     JOIN courses c ON c.id = cl.course_id
     ${where}
     ORDER BY COALESCE(a.join_time, cl.start_time) DESC`,
    params,
  );
  return result.rows;
}
