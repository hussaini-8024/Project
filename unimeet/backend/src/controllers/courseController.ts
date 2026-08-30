import type { Request, Response } from "express";
import { z } from "zod";
import {
  createCourse,
  enrollStudent,
  getCourseById,
  listCourses,
  listDepartments,
  listEnrollments,
  listPrograms,
  unenroll,
} from "../models/courseModel.js";
import { getStudentByUserId, getTeacherByUserId, listStudents, listTeachers } from "../models/userModel.js";
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

export async function getCourseHandler(req: Request, res: Response) {
  const course = await getCourseById(Number(req.params.id));
  if (!course) throw new HttpError(404, "Course not found.", "not_found");
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
});

export async function createCourseHandler(req: Request, res: Response) {
  const body = createSchema.parse(req.body);
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
  const enrollment = await enrollStudent(body.studentId, body.courseId);
  res.status(201).json({ enrollment, alreadyEnrolled: !enrollment });
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
