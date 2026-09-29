import type { Request, Response } from "express";
import { z } from "zod";
import { getStudentByUserId, getTeacherByUserId } from "../models/userModel.js";
import { liveRoster, sessionSummary } from "../services/reportService.js";
import {
  analytics,
  courseReport,
  getSettings,
  institutionReports,
  listGradeRules,
  studentReport,
  toCsv,
} from "../services/reportService.js";
import { replaceGradeRules, updateSettings } from "../services/platformService.js";
import { assertClassScope, assertCourseScope, assertStudentReportScope } from "../services/scopeService.js";
import { HttpError } from "../utils/httpError.js";

async function teacherScope(req: Request) {
  if (req.user!.role !== "teacher") return undefined;
  const teacher = await getTeacherByUserId(req.user!.id);
  return teacher?.id;
}

export async function myReportHandler(req: Request, res: Response) {
  const student = await getStudentByUserId(req.user!.id);
  if (!student) throw new HttpError(403, "Student profile not found.", "not_student");
  const semesterId = req.query.semesterId ? Number(req.query.semesterId) : undefined;
  res.json({ report: await studentReport(student.id, semesterId) });
}

export async function studentReportHandler(req: Request, res: Response) {
  const studentId = Number(req.params.id);
  await assertStudentReportScope(req.user!, studentId);
  const semesterId = req.query.semesterId ? Number(req.query.semesterId) : undefined;
  const report = await studentReport(studentId, semesterId, { teacherId: await teacherScope(req) });
  if (!report) throw new HttpError(404, "Student not found.", "not_found");
  res.json({ report });
}

export async function courseReportHandler(req: Request, res: Response) {
  const courseId = Number(req.params.id);
  await assertCourseScope(req.user!, courseId);
  res.json({ report: await courseReport(courseId) });
}

export async function institutionReportHandler(req: Request, res: Response) {
  const semesterId = req.query.semesterId ? Number(req.query.semesterId) : undefined;
  res.json({
    report: await institutionReports({
      semesterId,
      teacherId: await teacherScope(req),
    }),
  });
}

export async function analyticsHandler(req: Request, res: Response) {
  const semesterId = req.query.semesterId ? Number(req.query.semesterId) : undefined;
  res.json({
    analytics: await analytics({
      semesterId,
      teacherId: await teacherScope(req),
    }),
  });
}

export async function liveAttendanceHandler(req: Request, res: Response) {
  const { classRow } = await assertClassScope(req.user!, Number(req.params.id));
  if (req.user!.role === "student") {
    throw new HttpError(403, "Live attendance is for teachers and administrators.", "forbidden");
  }
  res.json(await liveRoster(classRow.id));
}

export async function sessionSummaryHandler(req: Request, res: Response) {
  const { classRow } = await assertClassScope(req.user!, Number(req.params.id));
  if (req.user!.role === "student") {
    throw new HttpError(403, "Session summaries are for teachers and administrators.", "forbidden");
  }
  res.json({ summary: await sessionSummary(classRow.id) });
}

export async function gradeRulesHandler(_req: Request, res: Response) {
  res.json({ rules: await listGradeRules(), settings: await getSettings() });
}

const rulesSchema = z.object({
  rules: z
    .array(
      z.object({
        minPercent: z.number().min(0).max(100),
        maxPercent: z.number().min(0).max(100),
        grade: z.string().min(1),
        label: z.string().optional(),
      }),
    )
    .min(1),
  settings: z
    .object({
      lowThreshold: z.number().min(0).max(100).optional(),
      lateJoinMinutes: z.number().int().min(0).optional(),
      earlyLeaveMinutes: z.number().int().min(0).optional(),
    })
    .optional(),
});

export async function saveGradeRulesHandler(req: Request, res: Response) {
  const body = rulesSchema.parse(req.body);
  await replaceGradeRules(body.rules);
  if (body.settings) await updateSettings(body.settings);
  res.json({ rules: await listGradeRules(), settings: await getSettings() });
}

export async function exportReportHandler(req: Request, res: Response) {
  const kind = String(req.query.kind ?? "students");
  const semesterId = req.query.semesterId ? Number(req.query.semesterId) : undefined;

  if (kind === "student") {
    const studentId = Number(req.query.studentId);
    await assertStudentReportScope(req.user!, studentId);
    const report = await studentReport(studentId, semesterId, { teacherId: await teacherScope(req) });
    if (!report) throw new HttpError(404, "Student not found.", "not_found");
    const csv = toCsv(
      ["Subject", "Sessions", "Attended", "Expected minutes", "Actual minutes", "Attendance %", "Grade"],
      report.subjects.map((row) => [
        `${row.courseCode} ${row.courseName}`,
        row.sessions,
        row.attended,
        Math.round(row.expectedSeconds / 60),
        Math.round(row.actualSeconds / 60),
        row.percent,
        row.grade,
      ]),
    );
    res.setHeader("Content-Type", "text/csv");
    res.setHeader("Content-Disposition", `attachment; filename="student-${studentId}-attendance.csv"`);
    res.send(csv);
    return;
  }

  if (kind === "course") {
    const courseId = Number(req.query.courseId);
    await assertCourseScope(req.user!, courseId);
    const report = await courseReport(courseId);
    const csv = toCsv(
      ["Student", "ID", "Sessions", "Attended", "Expected minutes", "Actual minutes", "Attendance %", "Grade", "Status"],
      report.students.map((row) => [
        row.name,
        String(row.universityId ?? ""),
        row.sessions,
        row.attended,
        Math.round(row.expectedSeconds / 60),
        Math.round(row.actualSeconds / 60),
        row.percent,
        row.grade,
        row.status,
      ]),
    );
    res.setHeader("Content-Type", "text/csv");
    res.setHeader("Content-Disposition", `attachment; filename="course-${courseId}-attendance.csv"`);
    res.send(csv);
    return;
  }

  if (req.user!.role === "student") {
    throw new HttpError(403, "Institution export is not available to students.", "forbidden");
  }
  const report = await institutionReports({
    semesterId,
    teacherId: await teacherScope(req),
  });
  const csv = toCsv(
    ["Student", "ID", "Sessions", "Attended", "Attendance %", "Grade"],
    report.students.map((row) => [row.name, String(row.universityId), row.sessions, row.attended, row.percent, row.grade]),
  );
  res.setHeader("Content-Type", "text/csv");
  res.setHeader("Content-Disposition", 'attachment; filename="attendance-institution.csv"');
  res.send(csv);
}
