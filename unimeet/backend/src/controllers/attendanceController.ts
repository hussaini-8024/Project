import type { Request, Response } from "express";
import { z } from "zod";
import { listAttendance } from "../models/attendanceModel.js";
import { getStudentByUserId } from "../models/userModel.js";
import { authorizeClassJoin } from "../services/accessService.js";
import { recordJoin, recordLeave } from "../services/attendanceService.js";
import { HttpError } from "../utils/httpError.js";

const classSchema = z.object({ classId: z.number() });

async function studentIdFor(req: Request) {
  if (req.user!.role !== "student") {
    throw new HttpError(403, "Only students generate attendance records.", "not_student");
  }
  const student = await getStudentByUserId(req.user!.id);
  if (!student) throw new HttpError(403, "Student profile not found.", "not_student");
  return student.id;
}

export async function joinAttendanceHandler(req: Request, res: Response) {
  const { classId } = classSchema.parse(req.body);
  await authorizeClassJoin(req.user, classId);
  const attendance = await recordJoin(await studentIdFor(req), classId);
  res.json({ attendance });
}

export async function leaveAttendanceHandler(req: Request, res: Response) {
  const { classId } = classSchema.parse(req.body);
  const attendance = await recordLeave(await studentIdFor(req), classId);
  res.json({ attendance });
}

export async function listAttendanceHandler(req: Request, res: Response) {
  const user = req.user!;
  const classId = req.query.classId ? Number(req.query.classId) : undefined;
  const courseId = req.query.courseId ? Number(req.query.courseId) : undefined;
  if (user.role === "student") {
    const student = await getStudentByUserId(user.id);
    res.json({ attendance: await listAttendance({ studentId: student?.id, classId, courseId }) });
    return;
  }
  res.json({ attendance: await listAttendance({ classId, courseId }) });
}
