import type { Request, Response } from "express";
import { z } from "zod";
import { getStudentByUserId } from "../models/userModel.js";
import { authorizeClassJoin } from "../services/accessService.js";
import { recordJoin } from "../services/attendanceService.js";
import { createLiveKitToken } from "../services/livekitService.js";

const schema = z.object({
  classId: z.number(),
});

export async function tokenHandler(req: Request, res: Response) {
  const { classId } = schema.parse(req.body);
  const user = req.user!;
  const { classRow, grant } = await authorizeClassJoin(user, classId);

  if (user.role === "student") {
    const student = await getStudentByUserId(user.id);
    if (student) await recordJoin(student.id, classId);
  }

  const session = await createLiveKitToken(user, classRow.room_name, grant);
  res.json({
    ...session,
    class: classRow,
    identity: `${user.role}:${user.universityId}`,
  });
}
