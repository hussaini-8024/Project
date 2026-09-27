import type { Request, Response } from "express";
import { z } from "zod";
import { createClass, getClassById, listClasses, listLiveClasses, mergeClasses, updateClassStatus } from "../models/classModel.js";
import { getStudentByUserId, getTeacherByUserId } from "../models/userModel.js";
import { closeSessionAttendance } from "../services/attendanceService.js";
import { notifyCourse } from "../services/platformService.js";
import { sessionSummary } from "../services/reportService.js";
import { assertCourseScope } from "../services/scopeService.js";
import { HttpError } from "../utils/httpError.js";

export async function listClassesHandler(req: Request, res: Response) {
  const user = req.user!;
  const courseId = req.query.courseId ? Number(req.query.courseId) : undefined;
  if (user.role === "student") {
    const student = await getStudentByUserId(user.id);
    const mine = await listClasses({ courseId, studentId: student?.id });
    const live = courseId ? [] : await listLiveClasses();
    res.json({ classes: mergeClasses(mine, live) });
    return;
  }
  if (user.role === "teacher") {
    const teacher = await getTeacherByUserId(user.id);
    res.json({ classes: await listClasses({ courseId, teacherId: teacher?.id }) });
    return;
  }
  res.json({ classes: await listClasses({ courseId }) });
}

export async function getClassHandler(req: Request, res: Response) {
  const row = await getClassById(Number(req.params.id));
  if (!row) throw new HttpError(404, "Class not found.", "not_found");
  res.json({ class: row });
}

const createSchema = z.object({
  courseId: z.number(),
  title: z.string().optional(),
  startTime: z.string(),
  endTime: z.string(),
  roomName: z.string().min(3),
  status: z.enum(["scheduled", "live", "ended"]).optional(),
  isOpenLab: z.boolean().optional(),
});

export async function createClassHandler(req: Request, res: Response) {
  const body = createSchema.parse(req.body);
  await assertCourseScope(req.user!, body.courseId);
  const created = await createClass({
    ...body,
    createdBy: req.user!.id,
  });
  await notifyCourse(
    body.courseId,
    "Meeting scheduled",
    created.title ?? "A class session was scheduled.",
    "meeting_scheduled",
    `/classroom/${created.id}`,
    req.user!.id,
  );
  res.status(201).json({ class: created });
}

export async function startClassHandler(req: Request, res: Response) {
  const existing = await getClassById(Number(req.params.id));
  if (!existing) throw new HttpError(404, "Class not found.", "not_found");
  await assertCourseScope(req.user!, existing.course_id);
  const updated = await updateClassStatus(existing.id, "live");
  await notifyCourse(
    existing.course_id,
    "Meeting started",
    `${existing.course_code} is live now.`,
    "meeting_started",
    `/classroom/${existing.id}`,
    req.user!.id,
  );
  res.json({ class: updated });
}

export async function endClassHandler(req: Request, res: Response) {
  const existing = await getClassById(Number(req.params.id));
  if (!existing) throw new HttpError(404, "Class not found.", "not_found");
  await assertCourseScope(req.user!, existing.course_id);
  await closeSessionAttendance(existing.id);
  const updated = await updateClassStatus(existing.id, "ended");
  const summary = await sessionSummary(existing.id);
  res.json({ class: updated, summary });
}

const meetingSchema = z.object({
  title: z.string().optional(),
  minutes: z.number().int().min(15).max(240).optional(),
  isOpenLab: z.boolean().optional(),
});

export async function startCourseMeetingHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  const course = (await assertCourseScope(req.user!, courseId)).course;
  const body = meetingSchema.parse(req.body ?? {});
  const minutes = body.minutes ?? 90;
  const created = await createClass({
    courseId,
    title: body.title ?? `${course.course_name} live session`,
    startTime: new Date().toISOString(),
    endTime: new Date(Date.now() + minutes * 60_000).toISOString(),
    roomName: `${String(course.course_code).toLowerCase().replace(/\s+/g, "-")}-${Date.now()}`,
    status: "live",
    isOpenLab: body.isOpenLab ?? false,
    createdBy: req.user!.id,
  });
  await notifyCourse(
    courseId,
    "Meeting started",
    `${course.course_code} · ${course.course_name} is live.`,
    "meeting_started",
    `/classroom/${created.id}`,
    req.user!.id,
  );
  res.status(201).json({ class: created });
}
