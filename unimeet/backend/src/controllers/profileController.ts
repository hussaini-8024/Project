import type { Request, Response } from "express";
import bcrypt from "bcryptjs";
import { z } from "zod";
import { query } from "../db/pool.js";
import { listAttendance } from "../models/attendanceModel.js";
import { listClasses } from "../models/classModel.js";
import { listCourses } from "../models/courseModel.js";
import { getStudentByUserId, getTeacherByUserId, updatePassword, updateProfile } from "../models/userModel.js";
import { publicProfile } from "../services/authService.js";

export async function dashboardHandler(req: Request, res: Response) {
  const user = req.user!;
  const student = user.role === "student" ? await getStudentByUserId(user.id) : null;
  const teacher = user.role === "teacher" ? await getTeacherByUserId(user.id) : null;
  const courses = await listCourses({
    studentId: student?.id,
    teacherId: teacher?.id,
  });
  const classes = await listClasses({
    studentId: student?.id,
    teacherId: teacher?.id,
  });
  const attendance = student
    ? await listAttendance({ studentId: student.id })
    : user.role === "admin" || user.role === "teacher"
      ? await listAttendance({})
      : [];

  let users = 0;
  if (user.role === "admin") {
    const count = await query<{ count: number }>(`SELECT COUNT(*)::int AS count FROM users`);
    users = count.rows[0].count;
  }

  res.json({
    user: await publicProfile(user),
    courses,
    classes,
    attendance,
    stats: {
      courses: courses.length,
      liveClasses: classes.filter((c) => c.status === "live" || c.is_open_lab).length,
      attendanceRecords: attendance.length,
      users,
    },
  });
}

const profileSchema = z.object({
  name: z.string().min(2).optional(),
  email: z.string().email().optional(),
  currentPassword: z.string().optional(),
  newPassword: z.string().min(8).optional(),
});

export async function updateProfileHandler(req: Request, res: Response) {
  const body = profileSchema.parse(req.body);
  const user = req.user!;
  if (body.name || body.email) {
    await updateProfile(user.id, body.name ?? user.name, body.email ?? user.email);
  }
  if (body.newPassword) {
    if (!body.currentPassword) {
      res.status(400).json({ error: "validation_error", message: "Current password is required." });
      return;
    }
    const row = await query<{ password_hash: string }>(
      `SELECT password_hash FROM users WHERE id = $1`,
      [user.id],
    );
    const ok = await bcrypt.compare(body.currentPassword, row.rows[0].password_hash);
    if (!ok) {
      res.status(401).json({ error: "invalid_credentials", message: "Current password is wrong." });
      return;
    }
    await updatePassword(user.id, await bcrypt.hash(body.newPassword, 10));
  }
  const fresh = { ...user, name: body.name ?? user.name, email: body.email ?? user.email };
  res.json({ user: await publicProfile(fresh) });
}

export async function networkSampleHandler(req: Request, res: Response) {
  const body = z
    .object({
      classId: z.number().optional(),
      bandwidthKbps: z.number().optional(),
      packetLoss: z.number().optional(),
      latencyMs: z.number().optional(),
      jitterMs: z.number().optional(),
      fps: z.number().optional(),
      bitrateKbps: z.number().optional(),
      resolution: z.string().optional(),
      qualityTier: z.string().optional(),
    })
    .parse(req.body);
  const result = await query(
    `INSERT INTO network_samples
      (user_id, class_id, bandwidth_kbps, packet_loss, latency_ms, jitter_ms, fps, bitrate_kbps, resolution, quality_tier)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
     RETURNING *`,
    [
      req.user!.id,
      body.classId ?? null,
      body.bandwidthKbps ?? null,
      body.packetLoss ?? null,
      body.latencyMs ?? null,
      body.jitterMs ?? null,
      body.fps ?? null,
      body.bitrateKbps ?? null,
      body.resolution ?? null,
      body.qualityTier ?? null,
    ],
  );
  res.status(201).json({ sample: result.rows[0] });
}

export async function adminStatsHandler(_req: Request, res: Response) {
  const [users, courses, enrollments, live] = await Promise.all([
    query<{ count: number }>(`SELECT COUNT(*)::int AS count FROM users`),
    query<{ count: number }>(`SELECT COUNT(*)::int AS count FROM courses`),
    query<{ count: number }>(`SELECT COUNT(*)::int AS count FROM enrollments`),
    query<{ count: number }>(
      `SELECT COUNT(*)::int AS count FROM classes WHERE status = 'live' OR is_open_lab = true`,
    ),
  ]);
  res.json({
    users: users.rows[0].count,
    courses: courses.rows[0].count,
    enrollments: enrollments.rows[0].count,
    liveClasses: live.rows[0].count,
  });
}
