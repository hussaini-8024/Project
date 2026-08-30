import bcrypt from "bcryptjs";
import jwt from "jsonwebtoken";
import { env } from "../config/env.js";
import {
  findUserByEmail,
  findUserByUniversityId,
  getStudentByUserId,
  getTeacherByUserId,
} from "../models/userModel.js";
import type { AuthUser, JwtPayload, UserRole } from "../types.js";
import { HttpError } from "../utils/httpError.js";

export function signToken(user: AuthUser) {
  const payload: JwtPayload = {
    sub: user.id,
    role: user.role,
    universityId: user.universityId,
    name: user.name,
  };
  return jwt.sign(payload, env.JWT_SECRET, { expiresIn: 60 * 60 * 12 });
}

export async function login(universityId: string, password: string, role?: UserRole) {
  const id = universityId.trim();
  const user =
    (await findUserByUniversityId(id, role)) ??
    (id.includes("@") ? await findUserByEmail(id) : null);

  if (!user || (role && user.role !== role)) {
    throw new HttpError(401, "Invalid university ID or password.", "invalid_credentials");
  }

  const ok = await bcrypt.compare(password, user.password_hash);
  if (!ok) {
    throw new HttpError(401, "Invalid university ID or password.", "invalid_credentials");
  }

  return toAuthUser(user);
}

export async function publicProfile(user: AuthUser) {
  const student = user.role === "student" ? await getStudentByUserId(user.id) : null;
  const teacher = user.role === "teacher" ? await getTeacherByUserId(user.id) : null;
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    universityId: user.universityId,
    student,
    teacher,
  };
}

export function toAuthUser(user: {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  university_id: string;
}): AuthUser {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    universityId: user.university_id,
  };
}

export const cookieOptions = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: env.NODE_ENV === "production",
  maxAge: 12 * 60 * 60 * 1000,
  path: "/",
};
