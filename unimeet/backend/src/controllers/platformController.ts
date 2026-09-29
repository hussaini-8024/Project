import type { Request, Response } from "express";
import { z } from "zod";
import { getCourseById } from "../models/courseModel.js";
import {
  createAnnouncement,
  createDiscussion,
  createReply,
  createSemester,
  deleteClassroomChat,
  deleteDiscussion,
  deleteReply,
  getDiscussion,
  listAnnouncements,
  listClassroomChat,
  listDiscussions,
  listNotifications,
  listSemesters,
  markNotificationRead,
  notifyCourse,
  postClassroomChat,
  touchPresence,
  updateDiscussion,
  updateReply,
  updateSemester,
} from "../services/platformService.js";
import { assertCourseScope } from "../services/scopeService.js";
import { HttpError } from "../utils/httpError.js";

export async function listSemestersHandler(_req: Request, res: Response) {
  res.json({ semesters: await listSemesters() });
}

const semesterSchema = z.object({
  name: z.string().min(2),
  academicYear: z.string().min(4),
  startDate: z.string(),
  endDate: z.string(),
  status: z.string().optional(),
});

export async function createSemesterHandler(req: Request, res: Response) {
  res.status(201).json({ semester: await createSemester(semesterSchema.parse(req.body)) });
}

export async function updateSemesterHandler(req: Request, res: Response) {
  res.json({
    semester: await updateSemester(Number(req.params.id), semesterSchema.partial().parse(req.body)),
  });
}

export async function listNotificationsHandler(req: Request, res: Response) {
  res.json(await listNotifications(req.user!.id));
}

export async function readNotificationHandler(req: Request, res: Response) {
  await markNotificationRead(req.user!.id, Number(req.params.id));
  res.json(await listNotifications(req.user!.id));
}

export async function readAllNotificationsHandler(req: Request, res: Response) {
  await markNotificationRead(req.user!.id);
  res.json(await listNotifications(req.user!.id));
}

export async function listDiscussionsHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  res.json({ discussions: await listDiscussions(courseId, String(req.query.q ?? "")) });
}

const discussionSchema = z.object({
  title: z.string().min(2),
  body: z.string().min(1),
});

export async function createDiscussionHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  const body = discussionSchema.parse(req.body);
  const discussion = await createDiscussion(courseId, req.user!.id, body.title, body.body);
  const course = await getCourseById(courseId);
  await notifyCourse(
    courseId,
    "New discussion",
    `${req.user!.name} started “${body.title}” in ${course?.course_code ?? "a course"}.`,
    "discussion",
    `/courses/${courseId}`,
    req.user!.id,
  );
  res.status(201).json({ discussion });
}

export async function getDiscussionHandler(req: Request, res: Response) {
  const thread = await getDiscussion(Number(req.params.id));
  if (!thread) throw new HttpError(404, "Discussion not found.", "not_found");
  await assertCourseScope(req.user!, thread.discussion.course_id as number);
  res.json(thread);
}

export async function updateDiscussionHandler(req: Request, res: Response) {
  const thread = await getDiscussion(Number(req.params.id));
  if (!thread) throw new HttpError(404, "Discussion not found.", "not_found");
  await assertCourseScope(req.user!, thread.discussion.course_id as number);
  const body = discussionSchema.parse(req.body);
  res.json({
    discussion: await updateDiscussion(Number(req.params.id), req.user!.id, req.user!.role, body.title, body.body),
  });
}

export async function deleteDiscussionHandler(req: Request, res: Response) {
  const thread = await getDiscussion(Number(req.params.id));
  if (!thread) throw new HttpError(404, "Discussion not found.", "not_found");
  await assertCourseScope(req.user!, thread.discussion.course_id as number);
  await deleteDiscussion(Number(req.params.id), req.user!.id, req.user!.role);
  res.json({ ok: true });
}

const replySchema = z.object({ body: z.string().min(1) });

export async function createReplyHandler(req: Request, res: Response) {
  const thread = await getDiscussion(Number(req.params.id));
  if (!thread) throw new HttpError(404, "Discussion not found.", "not_found");
  await assertCourseScope(req.user!, thread.discussion.course_id as number);
  const body = replySchema.parse(req.body);
  const reply = await createReply(Number(req.params.id), req.user!.id, body.body);
  await notifyCourse(
    thread.discussion.course_id as number,
    "Discussion reply",
    `${req.user!.name} replied to “${thread.discussion.title}”.`,
    "discussion_reply",
    `/courses/${thread.discussion.course_id}`,
    req.user!.id,
  );
  res.status(201).json({ reply });
}

export async function updateReplyHandler(req: Request, res: Response) {
  const current = await getDiscussion(Number(req.query.discussionId ?? 0));
  const body = replySchema.parse(req.body);
  const reply = await updateReply(Number(req.params.id), req.user!.id, req.user!.role, body.body);
  void current;
  res.json({ reply });
}

export async function deleteReplyHandler(req: Request, res: Response) {
  await deleteReply(Number(req.params.id), req.user!.id, req.user!.role);
  res.json({ ok: true });
}

export async function listChatHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  res.json(await listClassroomChat(courseId, req.user!.id));
}

export async function postChatHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  const body = replySchema.parse(req.body);
  res.status(201).json({ message: await postClassroomChat(courseId, req.user!.id, body.body) });
}

export async function deleteChatHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  await deleteClassroomChat(Number(req.params.messageId), req.user!.id, req.user!.role);
  res.json({ ok: true });
}

export async function presenceHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  await touchPresence(courseId, req.user!.id);
  res.json({ ok: true });
}

export async function listAnnouncementsHandler(req: Request, res: Response) {
  const courseId = req.query.courseId ? Number(req.query.courseId) : undefined;
  if (courseId) await assertCourseScope(req.user!, courseId);
  res.json({ announcements: await listAnnouncements(courseId) });
}

const announcementSchema = z.object({
  title: z.string().min(2),
  body: z.string().min(1),
  courseId: z.number().optional().nullable(),
});

export async function createAnnouncementHandler(req: Request, res: Response) {
  const body = announcementSchema.parse(req.body);
  if (body.courseId) await assertCourseScope(req.user!, body.courseId);
  const announcement = await createAnnouncement(req.user!.id, body.title, body.body, body.courseId);
  if (body.courseId) {
    await notifyCourse(
      body.courseId,
      "New announcement",
      body.title,
      "announcement",
      `/courses/${body.courseId}`,
      req.user!.id,
    );
  }
  res.status(201).json({ announcement });
}
