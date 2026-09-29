import type { Request, Response } from "express";
import { z } from "zod";
import { query } from "../db/pool.js";
import {
  createCourse,
  enrollStudent,
  getCourseById,
  listAvailableCourses,
  listCourses,
  listDepartments,
  listEnrollments,
  listPrograms,
  unenroll,
} from "../models/courseModel.js";
import { getStudentByUserId, getTeacherByUserId, listStudents, listTeachers } from "../models/userModel.js";
import { notifyCourse, notifyUsers } from "../services/platformService.js";
import { assertCourseScope } from "../services/scopeService.js";
import { HttpError } from "../utils/httpError.js";

export async function listCoursesHandler(req: Request, res: Response) {
  const user = req.user!;
  if (user.role === "student") {
    const student = await getStudentByUserId(user.id);
    res.json({ courses: await listCourses({ studentId: student?.id }) });
    return;
  }
  if (user.role === "teacher") {
    const teacher = await getTeacherByUserId(user.id);
    res.json({ courses: await listCourses({ teacherId: teacher?.id }) });
    return;
  }
  res.json({ courses: await listCourses({}) });
}

export async function availableCoursesHandler(req: Request, res: Response) {
  if (req.user!.role !== "student") {
    res.json({ courses: await listCourses({}) });
    return;
  }
  const student = await getStudentByUserId(req.user!.id);
  res.json({ courses: student ? await listAvailableCourses(student.id) : [] });
}

export async function getCourseHandler(req: Request, res: Response) {
  const { course } = await assertCourseScope(req.user!, Number(req.params.id));
  const enrollments = await listEnrollments(course.id as number);
  res.json({ course, enrollments });
}

const createSchema = z.object({
  courseCode: z.string().min(2),
  courseName: z.string().min(2),
  teacherId: z.number().optional().nullable(),
  programId: z.number().optional().nullable(),
  departmentId: z.number().optional().nullable(),
  semester: z.number().int().min(1).max(12).optional().nullable(),
  section: z.string().optional().nullable(),
  creditHours: z.number().int().min(1).max(6).optional(),
  description: z.string().optional().nullable(),
  studentIds: z.array(z.number()).optional(),
  semesterId: z.number().optional().nullable(),
  academicYear: z.string().optional().nullable(),
  status: z.string().optional().nullable(),
  maxStudents: z.number().int().optional().nullable(),
  startDate: z.string().optional().nullable(),
  endDate: z.string().optional().nullable(),
});

export async function createCourseHandler(req: Request, res: Response) {
  const body = createSchema.parse(req.body);
  if (req.user!.role === "teacher") {
    const teacher = await getTeacherByUserId(req.user!.id);
    if (!teacher) throw new HttpError(403, "Teacher profile not found.", "not_teacher");
    body.teacherId = teacher.id;
  }
  const course = await createCourse(body);
  if (body.studentIds?.length) {
    for (const studentId of body.studentIds) {
      await enrollStudent(studentId, course.id as number);
    }
  }
  res.status(201).json({ course, enrollments: await listEnrollments(course.id as number) });
}

const enrollSchema = z.object({
  studentId: z.number(),
  courseId: z.number(),
});

export async function enrollHandler(req: Request, res: Response) {
  const body = enrollSchema.parse(req.body);
  if (req.user!.role === "teacher") {
    await assertCourseScope(req.user!, body.courseId);
  }
  const enrollment = await enrollStudent(body.studentId, body.courseId);
  const course = await getCourseById(body.courseId);
  if (enrollment) {
    const row = await query<{ user_id: number }>(`SELECT user_id FROM students WHERE id = $1`, [body.studentId]);
    if (row.rows[0]) {
      await notifyUsers(
        [row.rows[0].user_id],
        "Class enrollment",
        `You were enrolled in ${course?.course_code ?? "a course"}.`,
        "enrollment",
        `/courses/${body.courseId}`,
      );
    }
  }
  res.status(201).json({ enrollment, alreadyEnrolled: !enrollment });
}

const selfEnrollSchema = z.object({ courseId: z.number() });

export async function selfEnrollHandler(req: Request, res: Response) {
  if (req.user!.role !== "student") {
    throw new HttpError(403, "Only students can self-enroll.", "forbidden");
  }
  const student = await getStudentByUserId(req.user!.id);
  if (!student) throw new HttpError(403, "Student profile not found.", "not_student");
  const { courseId } = selfEnrollSchema.parse(req.body);
  const course = await getCourseById(courseId);
  if (!course) throw new HttpError(404, "Course not found.", "not_found");
  const enrollment = await enrollStudent(student.id, courseId);
  if (enrollment) {
    await notifyCourse(
      courseId,
      "New enrollment",
      `${req.user!.name} joined ${course.course_code}.`,
      "enrollment",
      `/courses/${courseId}`,
      req.user!.id,
    );
  }
  res.status(201).json({ enrollment, alreadyEnrolled: !enrollment, course });
}

export async function unenrollHandler(req: Request, res: Response) {
  await unenroll(Number(req.params.id));
  res.json({ ok: true });
}

export async function catalogHandler(_req: Request, res: Response) {
  const [programs, departments, students, teachers] = await Promise.all([
    listPrograms(),
    listDepartments(),
    listStudents(),
    listTeachers(),
  ]);
  res.json({ programs, departments, students, teachers });
}
