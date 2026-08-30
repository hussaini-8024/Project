import { env } from "../config/env.js";
import { getAttendance, markJoin, markLeave } from "../models/attendanceModel.js";
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

export async function recordJoin(studentId: number, classId: number) {
  return markJoin(studentId, classId);
}

export async function recordLeave(studentId: number, classId: number) {
  const current = await getAttendance(studentId, classId);
  if (!current) return null;

  const classRow = await getClassById(classId);
  const start = current.last_join_time
    ? new Date(current.last_join_time as string).getTime()
    : current.join_time
      ? new Date(current.join_time as string).getTime()
      : Date.now();
  const added = Math.max(0, Math.round((Date.now() - start) / 1000));
  const total = Number(current.duration_seconds ?? 0) + added;
  const classMinutes = classRow
    ? (new Date(classRow.end_time).getTime() - new Date(classRow.start_time).getTime()) / 60000
    : 90;
  const status = classifyAttendance(total, classMinutes, Boolean(classRow?.is_open_lab));
  return markLeave(studentId, classId, added, status);
}
