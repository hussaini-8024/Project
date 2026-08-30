import { query } from "../db/pool.js";
import type { ClassStatus } from "../types.js";

export interface ClassRow {
  id: number;
  course_id: number;
  title: string | null;
  start_time: Date;
  end_time: Date;
  room_name: string;
  status: ClassStatus;
  is_open_lab: boolean;
  created_by: number | null;
  course_code?: string;
  course_name?: string;
  teacher_id?: number | null;
  teacher_name?: string | null;
}

export async function listClasses(filters: {
  courseId?: number;
  teacherId?: number;
  studentId?: number;
}) {
  const clauses: string[] = [];
  const params: unknown[] = [];

  if (filters.courseId) {
    params.push(filters.courseId);
    clauses.push(`cl.course_id = $${params.length}`);
  }
  if (filters.teacherId) {
    params.push(filters.teacherId);
    clauses.push(`c.teacher_id = $${params.length}`);
  }
  if (filters.studentId) {
    params.push(filters.studentId);
    clauses.push(
      `EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = cl.course_id AND e.student_id = $${params.length})`,
    );
  }

  const where = clauses.length ? `WHERE ${clauses.join(" AND ")}` : "";
  const result = await query(
    `SELECT cl.*, c.course_code, c.course_name, c.teacher_id, tu.name AS teacher_name
     FROM classes cl
     JOIN courses c ON c.id = cl.course_id
     LEFT JOIN teachers t ON t.id = c.teacher_id
     LEFT JOIN users tu ON tu.id = t.user_id
     ${where}
     ORDER BY cl.start_time DESC`,
    params,
  );
  return result.rows;
}

export async function getClassById(id: number) {
  const result = await query<ClassRow>(
    `SELECT cl.*, c.course_code, c.course_name, c.teacher_id, tu.name AS teacher_name,
            c.semester, c.section
     FROM classes cl
     JOIN courses c ON c.id = cl.course_id
     LEFT JOIN teachers t ON t.id = c.teacher_id
     LEFT JOIN users tu ON tu.id = t.user_id
     WHERE cl.id = $1`,
    [id],
  );
  return result.rows[0] ?? null;
}

export async function createClass(input: {
  courseId: number;
  title?: string;
  startTime: string;
  endTime: string;
  roomName: string;
  status?: ClassStatus;
  isOpenLab?: boolean;
  createdBy?: number;
}) {
  const result = await query(
    `INSERT INTO classes
      (course_id, title, start_time, end_time, room_name, status, is_open_lab, created_by)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
     RETURNING *`,
    [
      input.courseId,
      input.title ?? null,
      input.startTime,
      input.endTime,
      input.roomName,
      input.status ?? "scheduled",
      input.isOpenLab ?? false,
      input.createdBy ?? null,
    ],
  );
  return result.rows[0];
}

export async function updateClassStatus(id: number, status: ClassStatus) {
  const result = await query(
    `UPDATE classes SET status = $2 WHERE id = $1 RETURNING *`,
    [id, status],
  );
  return result.rows[0] ?? null;
}

export function isClassActive(row: {
  status: ClassStatus;
  is_open_lab: boolean;
  start_time: Date | string;
  end_time: Date | string;
}) {
  if (row.status === "ended") return false;
  if (row.status === "live" || row.is_open_lab) return true;
  const now = Date.now();
  const start = new Date(row.start_time).getTime();
  const end = new Date(row.end_time).getTime();
  return now >= start && now <= end;
}
