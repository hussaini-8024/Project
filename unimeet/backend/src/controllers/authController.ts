import type { Request, Response } from "express";
import { z } from "zod";
import { cookieOptions, login, publicProfile, signToken } from "../services/authService.js";

const loginSchema = z.object({
  universityId: z.string().min(1),
  password: z.string().min(1),
  role: z.enum(["student", "teacher", "admin"]).optional(),
});

export async function loginHandler(req: Request, res: Response) {
  const body = loginSchema.parse(req.body);
  const user = await login(body.universityId, body.password, body.role);
  const token = signToken(user);
  res.cookie("unimeet_token", token, cookieOptions);
  res.json({ token, user: await publicProfile(user) });
}

export async function meHandler(req: Request, res: Response) {
  res.json({ user: await publicProfile(req.user!) });
}

export function logoutHandler(_req: Request, res: Response) {
  res.clearCookie("unimeet_token", { path: "/" });
  res.json({ ok: true });
}
