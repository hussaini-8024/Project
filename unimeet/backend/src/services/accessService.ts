import { getClassById, isClassActive } from "../models/classModel.js";
import { isEnrolled } from "../models/courseModel.js";
import { getStudentByUserId, getTeacherByUserId } from "../models/userModel.js";
import type { AuthUser, ClassJoinGrant } from "../types.js";
import { HttpError } from "../utils/httpError.js";

export async function authorizeClassJoin(user: AuthUser | undefined, classId: number) {
  if (!user) {
    throw new HttpError(401, "You must be logged in.", "not_logged_in");
  }

  const classRow = await getClassById(classId);
  if (!classRow) {
    throw new HttpError(404, "Class not found.", "not_found");
  }

  if (user.role === "admin") {
    return {
      classRow,
      grant: { canPublish: true, roomAdmin: true } satisfies ClassJoinGrant,
    };
  }

  if (user.role === "teacher") {
    const teacher = await getTeacherByUserId(user.id);
    if (!teacher) {
      throw new HttpError(403, "Teacher profile not found.", "not_teacher");
    }
    if (classRow.teacher_id !== teacher.id) {
      throw new HttpError(
        403,
        "You are not the assigned teacher for this course.",
        "not_assigned",
      );
    }
    if (!isClassActive(classRow)) {
      throw new HttpError(403, "This class is not currently active.", "class_inactive");
    }
    return {
      classRow,
      grant: { canPublish: true, roomAdmin: true } satisfies ClassJoinGrant,
    };
  }

  if (user.role !== "student") {
    throw new HttpError(403, "Access denied.", "forbidden");
  }

  const student = await getStudentByUserId(user.id);
  if (!student) {
    throw new HttpError(403, "Student profile not found.", "not_student");
  }

  const enrolled = await isEnrolled(student.id, classRow.course_id);
  if (!enrolled) {
    throw new HttpError(
      403,
      "You are not enrolled in this course.",
      "not_enrolled",
    );
  }

  if (!isClassActive(classRow)) {
    throw new HttpError(403, "This class is not currently active.", "class_inactive");
  }

  return {
    classRow,
    student,
    grant: { canPublish: true, roomAdmin: false } satisfies ClassJoinGrant,
  };
}
