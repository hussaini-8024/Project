import type { NextFunction, Request, Response } from "express";
import jwt from "jsonwebtoken";
import { env } from "../config/env.js";
import { query } from "../db/pool.js";
import type { AuthUser, JwtPayload, UserRole } from "../types.js";
import { HttpError } from "../utils/httpError.js";

declare global {
  namespace Express {
    interface Request {
      user?: AuthUser;
    }
  }
}

function readToken(req: Request) {
  const header = req.headers.authorization;
  if (header?.startsWith("Bearer ")) return header.slice(7);
  const cookie = req.cookies?.unimeet_token;
  if (typeof cookie === "string" && cookie.length > 0) return cookie;
  return null;
}

export async function optionalAuth(req: Request, _res: Response, next: NextFunction) {
  try {
    const token = readToken(req);
    if (!token) return next();
    const payload = jwt.verify(token, env.JWT_SECRET) as unknown as JwtPayload;
    const result = await query<AuthUser & { university_id: string }>(
      `SELECT id, name, email, role, university_id FROM users WHERE id = $1`,
      [payload.sub],
    );
    const row = result.rows[0];
    if (row) {
      req.user = {
        id: row.id,
        name: row.name,
        email: row.email,
        role: row.role,
        universityId: row.university_id,
      };
    }
    next();
  } catch {
    next();
  }
}

export async function requireAuth(req: Request, _res: Response, next: NextFunction) {
  await optionalAuth(req, _res, () => {
    if (!req.user) {
      next(new HttpError(401, "You must be logged in.", "not_logged_in"));
      return;
    }
    next();
  });
}

export function requireRole(...roles: UserRole[]) {
  return (req: Request, _res: Response, next: NextFunction) => {
    if (!req.user) {
      next(new HttpError(401, "You must be logged in.", "not_logged_in"));
      return;
    }
    if (!roles.includes(req.user.role)) {
      next(new HttpError(403, "You do not have permission for this action.", "forbidden"));
      return;
    }
    next();
  };
}
