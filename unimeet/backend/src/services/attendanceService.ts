import { env } from "../config/env.js";
import { query } from "../db/pool.js";
import { getAttendance } from "../models/attendanceModel.js";
import { getClassById } from "../models/classModel.js";
import type { AttendanceStatus } from "../types.js";

export function classifyAttendance(
  durationSeconds: number,
  classMinutes: number,
  isOpenLab: boolean,
): AttendanceStatus {
  if (durationSeconds <= 0) return "absent";

  const durationMinutes = durationSeconds / 60;
  const longSession = isOpenLab || classMinutes >= 180;

  if (longSession) {
    if (durationMinutes >= env.ATTENDANCE_PRESENT_MINUTES) return "present";
    if (durationMinutes >= env.ATTENDANCE_PARTIAL_MINUTES) return "partial";
    return "insufficient";
  }

  if (classMinutes <= 0) {
    if (durationMinutes >= env.ATTENDANCE_PRESENT_MINUTES) return "present";
    if (durationMinutes >= env.ATTENDANCE_PARTIAL_MINUTES) return "partial";
    return "insufficient";
  }

  const ratio = durationMinutes / classMinutes;
  if (ratio >= env.ATTENDANCE_PRESENT_RATIO) return "present";
  if (ratio >= env.ATTENDANCE_PARTIAL_RATIO) return "partial";
  return "insufficient";
}

export function sumSegmentSeconds(
  segments: { joined_at: Date | string; left_at?: Date | string | null }[],
  now = Date.now(),
) {
  return segments.reduce((total, segment) => {
    const start = new Date(segment.joined_at).getTime();
    const end = segment.left_at ? new Date(segment.left_at).getTime() : now;
    return total + Math.max(0, Math.round((end - start) / 1000));
  }, 0);
}

export function sessionExpectedSeconds(session: {
  start_time: Date | string;
  end_time: Date | string;
  actual_start?: Date | string | null;
  ended_at?: Date | string | null;
  status?: string;
}) {
  const start = new Date(session.actual_start || session.start_time).getTime();
  const endSource =
    session.ended_at ||
    (session.status === "live" ? new Date() : session.end_time);
  const end = new Date(endSource).getTime();
  return Math.max(60, Math.round((end - start) / 1000));
}

export function attendancePercent(actualSeconds: number, expectedSeconds: number) {
  if (expectedSeconds <= 0) return 0;
  return Math.round((actualSeconds / expectedSeconds) * 10000) / 100;
}

async function writeEvent(studentId: number, classId: number, kind: string) {
  await query(
    `INSERT INTO attendance_events (student_id, class_id, kind) VALUES ($1, $2, $3)`,
    [studentId, classId, kind],
  );
}

export async function recordJoin(studentId: number, classId: number) {
  const existing = await getAttendance(studentId, classId);
  const priorSegments = existing
    ? await query<{ id: number }>(
        `SELECT id FROM attendance_segments WHERE attendance_id = $1`,
        [existing.id],
      )
    : { rows: [] };

  const attendance = await query(
    `INSERT INTO attendance (student_id, class_id, join_time, last_join_time)
     VALUES ($1, $2, now(), now())
     ON CONFLICT (student_id, class_id) DO UPDATE
       SET last_join_time = now(),
           leave_time = NULL
     RETURNING *`,
    [studentId, classId],
  );
  const row = attendance.rows[0];

  const open = await query(
    `SELECT id FROM attendance_segments
     WHERE attendance_id = $1 AND left_at IS NULL
     LIMIT 1`,
    [row.id],
  );
  if (!open.rows[0]) {
    await query(
      `INSERT INTO attendance_segments (attendance_id, student_id, class_id, joined_at)
       VALUES ($1, $2, $3, now())`,
      [row.id, studentId, classId],
    );
  }

  await writeEvent(studentId, classId, priorSegments.rows.length > 0 ? "rejoin" : "join");
  return row;
}

export async function closeOpenSegment(studentId: number, classId: number, at = new Date()) {
  const current = await getAttendance(studentId, classId);
  if (!current) return null;
  const open = await query<{ id: number; joined_at: string }>(
    `SELECT id, joined_at FROM attendance_segments
     WHERE attendance_id = $1 AND left_at IS NULL
     ORDER BY joined_at DESC LIMIT 1`,
    [current.id],
  );
  if (!open.rows[0]) return current;
  const seconds = Math.max(
    0,
    Math.round((at.getTime() - new Date(open.rows[0].joined_at).getTime()) / 1000),
  );
  await query(
    `UPDATE attendance_segments
     SET left_at = $2, duration_seconds = $3
     WHERE id = $1`,
    [open.rows[0].id, at.toISOString(), seconds],
  );
  return current;
}

export async function refreshAttendanceSummary(studentId: number, classId: number) {
  const classRow = await getClassById(classId);
  const segments = await query<{ joined_at: string; left_at: string | null }>(
    `SELECT joined_at, left_at FROM attendance_segments
     WHERE student_id = $1 AND class_id = $2`,
    [studentId, classId],
  );
  const total = sumSegmentSeconds(segments.rows);
  const expected = classRow ? sessionExpectedSeconds(classRow) : 90 * 60;
  const classMinutes = expected / 60;
  const status = classifyAttendance(total, classMinutes, Boolean(classRow?.is_open_lab));
  const result = await query(
    `UPDATE attendance
     SET duration_seconds = $3,
         leave_time = (
           SELECT MAX(left_at) FROM attendance_segments
           WHERE student_id = $1 AND class_id = $2
         ),
         status = $4
     WHERE student_id = $1 AND class_id = $2
     RETURNING *`,
    [studentId, classId, total, status],
  );
  return result.rows[0] ?? null;
}

export async function recordLeave(studentId: number, classId: number) {
  const current = await getAttendance(studentId, classId);
  if (!current) return null;
  await closeOpenSegment(studentId, classId);
  await writeEvent(studentId, classId, "leave");
  return refreshAttendanceSummary(studentId, classId);
}

export async function closeSessionAttendance(classId: number) {
  const open = await query<{ student_id: number }>(
    `SELECT DISTINCT student_id FROM attendance_segments
     WHERE class_id = $1 AND left_at IS NULL`,
    [classId],
  );
  for (const row of open.rows) {
    await closeOpenSegment(row.student_id, classId);
    await writeEvent(row.student_id, classId, "leave");
    await refreshAttendanceSummary(row.student_id, classId);
  }
}

export async function listSegments(studentId: number, classId: number) {
  const result = await query<{
    id: number;
    joined_at: string;
    left_at: string | null;
    duration_seconds: number;
  }>(
    `SELECT * FROM attendance_segments
     WHERE student_id = $1 AND class_id = $2
     ORDER BY joined_at`,
    [studentId, classId],
  );
  return result.rows;
}
