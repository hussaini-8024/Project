import { getClassById } from "../models/classModel.js";
import { getCourseById, isEnrolled } from "../models/courseModel.js";
import { getStudentByUserId, getTeacherByUserId } from "../models/userModel.js";
import type { AuthUser } from "../types.js";
import { HttpError } from "../utils/httpError.js";

export async function assertCourseScope(user: AuthUser, courseId: number) {
  const course = await getCourseById(courseId);
  if (!course) throw new HttpError(404, "Course not found.", "not_found");

  if (user.role === "admin") return { course, student: null, teacher: null };

  if (user.role === "teacher") {
    const teacher = await getTeacherByUserId(user.id);
    if (!teacher || course.teacher_id !== teacher.id) {
      throw new HttpError(403, "You are not assigned to this course.", "not_assigned");
    }
    return { course, teacher, student: null };
  }

  const student = await getStudentByUserId(user.id);
  if (!student || !(await isEnrolled(student.id, courseId))) {
    throw new HttpError(403, "You are not enrolled in this course.", "not_enrolled");
  }
  return { course, student, teacher: null };
}

export async function assertStudentReportScope(user: AuthUser, studentId: number) {
  if (user.role === "admin") return;
  if (user.role === "student") {
    const me = await getStudentByUserId(user.id);
    if (!me || me.id !== studentId) {
      throw new HttpError(403, "You can only view your own report.", "forbidden");
    }
    return;
  }
  if (user.role === "teacher") {
    const teacher = await getTeacherByUserId(user.id);
    if (!teacher) throw new HttpError(403, "Teacher profile not found.", "not_teacher");
    const { query } = await import("../db/pool.js");
    const overlap = await query<{ exists: boolean }>(
      `SELECT EXISTS(
         SELECT 1 FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.student_id = $1 AND c.teacher_id = $2
       ) AS exists`,
      [studentId, teacher.id],
    );
    if (!overlap.rows[0].exists) {
      throw new HttpError(403, "This student is not in your courses.", "forbidden");
    }
    return;
  }
  throw new HttpError(403, "Access denied.", "forbidden");
}

export async function assertClassScope(user: AuthUser, classId: number) {
  const classRow = await getClassById(classId);
  if (!classRow) throw new HttpError(404, "Class not found.", "not_found");
  const scope = await assertCourseScope(user, classRow.course_id);
  return { ...scope, classRow };
}
