import { query } from "../db/pool.js";

export async function listCourses(filters: {
  teacherId?: number;
  studentId?: number;
  role?: string;
}) {
  if (filters.studentId) {
    const result = await query(
      `SELECT c.*, t.title AS teacher_title, tu.name AS teacher_name,
              p.code AS program_code, d.code AS department_code,
              (SELECT COUNT(*)::int FROM enrollments e WHERE e.course_id = c.id) AS enrolled_count
       FROM courses c
       JOIN enrollments e ON e.course_id = c.id
       LEFT JOIN teachers t ON t.id = c.teacher_id
       LEFT JOIN users tu ON tu.id = t.user_id
       LEFT JOIN programs p ON p.id = c.program_id
       LEFT JOIN departments d ON d.id = c.department_id
       WHERE e.student_id = $1
       ORDER BY c.course_code`,
      [filters.studentId],
    );
    return result.rows;
  }

  if (filters.teacherId) {
    const result = await query(
      `SELECT c.*, t.title AS teacher_title, tu.name AS teacher_name,
              p.code AS program_code, d.code AS department_code,
              (SELECT COUNT(*)::int FROM enrollments e WHERE e.course_id = c.id) AS enrolled_count
       FROM courses c
       LEFT JOIN teachers t ON t.id = c.teacher_id
       LEFT JOIN users tu ON tu.id = t.user_id
       LEFT JOIN programs p ON p.id = c.program_id
       LEFT JOIN departments d ON d.id = c.department_id
       WHERE c.teacher_id = $1
       ORDER BY c.course_code`,
      [filters.teacherId],
    );
    return result.rows;
  }

  const result = await query(
    `SELECT c.*, t.title AS teacher_title, tu.name AS teacher_name,
            p.code AS program_code, d.code AS department_code,
            (SELECT COUNT(*)::int FROM enrollments e WHERE e.course_id = c.id) AS enrolled_count
     FROM courses c
     LEFT JOIN teachers t ON t.id = c.teacher_id
     LEFT JOIN users tu ON tu.id = t.user_id
     LEFT JOIN programs p ON p.id = c.program_id
     LEFT JOIN departments d ON d.id = c.department_id
     ORDER BY c.course_code`,
  );
  return result.rows;
}

export async function getCourseById(id: number) {
  const result = await query(
    `SELECT c.*, t.title AS teacher_title, t.id AS teacher_row_id, tu.name AS teacher_name,
            tu.id AS teacher_user_id, p.code AS program_code, p.name AS program_name,
            d.code AS department_code
     FROM courses c
     LEFT JOIN teachers t ON t.id = c.teacher_id
     LEFT JOIN users tu ON tu.id = t.user_id
     LEFT JOIN programs p ON p.id = c.program_id
     LEFT JOIN departments d ON d.id = c.department_id
     WHERE c.id = $1`,
    [id],
  );
  return result.rows[0] ?? null;
}

export async function createCourse(input: {
  courseCode: string;
  courseName: string;
  teacherId?: number | null;
  programId?: number | null;
  departmentId?: number | null;
  semester?: number | null;
  section?: string | null;
  creditHours?: number;
  description?: string | null;
}) {
  const result = await query(
    `INSERT INTO courses
      (course_code, course_name, teacher_id, program_id, department_id, semester, section, credit_hours, description)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
     RETURNING *`,
    [
      input.courseCode,
      input.courseName,
      input.teacherId ?? null,
      input.programId ?? null,
      input.departmentId ?? null,
      input.semester ?? null,
      input.section ?? null,
      input.creditHours ?? 3,
      input.description ?? null,
    ],
  );
  return result.rows[0];
}

export async function listEnrollments(courseId: number) {
  const result = await query(
    `SELECT e.id, e.enrolled_at, s.id AS student_id, s.student_id AS university_student_id,
            s.semester, s.section, u.name, u.email
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     JOIN users u ON u.id = s.user_id
     WHERE e.course_id = $1
     ORDER BY s.student_id`,
    [courseId],
  );
  return result.rows;
}

export async function isEnrolled(studentId: number, courseId: number) {
  const result = await query<{ exists: boolean }>(
    `SELECT EXISTS(
       SELECT 1 FROM enrollments WHERE student_id = $1 AND course_id = $2
     ) AS exists`,
    [studentId, courseId],
  );
  return result.rows[0].exists;
}

export async function enrollStudent(studentId: number, courseId: number) {
  const result = await query(
    `INSERT INTO enrollments (student_id, course_id)
     VALUES ($1, $2)
     ON CONFLICT (student_id, course_id) DO NOTHING
     RETURNING *`,
    [studentId, courseId],
  );
  return result.rows[0] ?? null;
}

export async function unenroll(enrollmentId: number) {
  await query(`DELETE FROM enrollments WHERE id = $1`, [enrollmentId]);
}

export async function listPrograms() {
  const result = await query(
    `SELECT p.*, d.code AS department_code, d.name AS department_name
     FROM programs p
     LEFT JOIN departments d ON d.id = p.department_id
     ORDER BY p.code`,
  );
  return result.rows;
}

export async function listDepartments() {
  const result = await query(`SELECT * FROM departments ORDER BY code`);
  return result.rows;
}
