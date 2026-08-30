import { query } from "../db/pool.js";
import type { UserRole } from "../types.js";

export interface UserRow {
  id: number;
  name: string;
  email: string;
  password_hash: string;
  role: UserRole;
  university_id: string;
}

export async function findUserByUniversityId(universityId: string, role?: UserRole) {
  if (role) {
    const result = await query<UserRow>(
      `SELECT * FROM users WHERE university_id = $1 AND role = $2`,
      [universityId, role],
    );
    return result.rows[0] ?? null;
  }
  const result = await query<UserRow>(`SELECT * FROM users WHERE university_id = $1`, [
    universityId,
  ]);
  return result.rows[0] ?? null;
}

export async function findUserByEmail(email: string) {
  const result = await query<UserRow>(`SELECT * FROM users WHERE email = $1`, [email]);
  return result.rows[0] ?? null;
}

export async function findUserById(id: number) {
  const result = await query<UserRow>(`SELECT * FROM users WHERE id = $1`, [id]);
  return result.rows[0] ?? null;
}

export async function getStudentByUserId(userId: number) {
  const result = await query<{
    id: number;
    user_id: number;
    student_id: string;
    department_id: number | null;
    program_id: number | null;
    semester: number;
    section: string;
  }>(`SELECT * FROM students WHERE user_id = $1`, [userId]);
  return result.rows[0] ?? null;
}

export async function getTeacherByUserId(userId: number) {
  const result = await query<{
    id: number;
    user_id: number;
    teacher_id: string;
    department_id: number | null;
    title: string;
  }>(`SELECT * FROM teachers WHERE user_id = $1`, [userId]);
  return result.rows[0] ?? null;
}

export async function listStudents() {
  const result = await query(
    `SELECT s.id, s.student_id, s.semester, s.section, u.id AS user_id, u.name, u.email,
            d.code AS department_code, p.code AS program_code
     FROM students s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = s.department_id
     LEFT JOIN programs p ON p.id = s.program_id
     ORDER BY s.student_id`,
  );
  return result.rows;
}

export async function listTeachers() {
  const result = await query(
    `SELECT t.id, t.teacher_id, t.title, u.id AS user_id, u.name, u.email,
            d.code AS department_code
     FROM teachers t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN departments d ON d.id = t.department_id
     ORDER BY t.teacher_id`,
  );
  return result.rows;
}

export async function createUser(input: {
  name: string;
  email: string;
  passwordHash: string;
  role: UserRole;
  universityId: string;
}) {
  const result = await query<UserRow>(
    `INSERT INTO users (name, email, password_hash, role, university_id)
     VALUES ($1, $2, $3, $4, $5) RETURNING *`,
    [input.name, input.email, input.passwordHash, input.role, input.universityId],
  );
  return result.rows[0];
}

export async function updateProfile(userId: number, name: string, email: string) {
  const result = await query<UserRow>(
    `UPDATE users SET name = $2, email = $3, updated_at = now() WHERE id = $1 RETURNING *`,
    [userId, name, email],
  );
  return result.rows[0];
}

export async function updatePassword(userId: number, passwordHash: string) {
  await query(`UPDATE users SET password_hash = $2, updated_at = now() WHERE id = $1`, [
    userId,
    passwordHash,
  ]);
}
