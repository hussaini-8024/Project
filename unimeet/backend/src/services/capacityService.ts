import { query } from "../db/pool.js";
import { getCourseById } from "../models/courseModel.js";
import { HttpError } from "../utils/httpError.js";

/** LiveKit room and class roster hard cap for this platform. */
export const ROOM_MAX_PARTICIPANTS = 2000;

/** Above this enrollment, the classroom uses lecture-hall join (camera/mic off until the student enables them). */
export const LECTURE_HALL_THRESHOLD = 80;

export function courseSeatLimit(maxStudents: unknown) {
  const raw = Number(maxStudents);
  if (!Number.isFinite(raw) || raw <= 0) return ROOM_MAX_PARTICIPANTS;
  return Math.min(Math.floor(raw), ROOM_MAX_PARTICIPANTS);
}

export async function enrollmentCount(courseId: number) {
  const result = await query<{ count: number }>(
    `SELECT COUNT(*)::int AS count FROM enrollments WHERE course_id = $1`,
    [courseId],
  );
  return result.rows[0]?.count ?? 0;
}

export async function assertCanEnroll(courseId: number) {
  const course = await getCourseById(courseId);
  if (!course) throw new HttpError(404, "Course not found.", "not_found");
  const cap = courseSeatLimit(course.max_students);
  const count = await enrollmentCount(courseId);
  if (count >= cap) {
    throw new HttpError(409, `This class is full (${cap} students).`, "class_full", {
      enrolled: count,
      capacity: cap,
    });
  }
  return { course, enrolled: count, capacity: cap };
}

export async function classCapacity(courseId: number) {
  const course = await getCourseById(courseId);
  const enrolled = await enrollmentCount(courseId);
  const capacity = courseSeatLimit(course?.max_students);
  return {
    enrolled,
    capacity,
    remaining: Math.max(0, capacity - enrolled),
    lectureHall: enrolled >= LECTURE_HALL_THRESHOLD || capacity >= LECTURE_HALL_THRESHOLD,
    maxParticipants: ROOM_MAX_PARTICIPANTS,
  };
}
