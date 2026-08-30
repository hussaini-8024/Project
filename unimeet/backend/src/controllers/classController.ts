import type { Request, Response } from "express";
import { z } from "zod";
import { createClass, getClassById, listClasses, listLiveClasses, mergeClasses, updateClassStatus } from "../models/classModel.js";
import { getStudentByUserId, getTeacherByUserId } from "../models/userModel.js";
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
  const created = await createClass({
    ...body,
    createdBy: req.user!.id,
  });
  res.status(201).json({ class: created });
}

export async function startClassHandler(req: Request, res: Response) {
  const updated = await updateClassStatus(Number(req.params.id), "live");
  if (!updated) throw new HttpError(404, "Class not found.", "not_found");
  res.json({ class: updated });
}

export async function endClassHandler(req: Request, res: Response) {
  const updated = await updateClassStatus(Number(req.params.id), "ended");
  if (!updated) throw new HttpError(404, "Class not found.", "not_found");
  res.json({ class: updated });
}
