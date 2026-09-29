import { query } from "../db/pool.js";
import { HttpError } from "../utils/httpError.js";

export async function listSemesters() {
  const result = await query(`SELECT * FROM semesters ORDER BY start_date DESC`);
  return result.rows;
}

export async function createSemester(input: {
  name: string;
  academicYear: string;
  startDate: string;
  endDate: string;
  status?: string;
}) {
  const result = await query(
    `INSERT INTO semesters (name, academic_year, start_date, end_date, status)
     VALUES ($1,$2,$3,$4,$5) RETURNING *`,
    [input.name, input.academicYear, input.startDate, input.endDate, input.status ?? "active"],
  );
  return result.rows[0];
}

export async function updateSemester(
  id: number,
  input: {
    name?: string;
    academicYear?: string;
    startDate?: string;
    endDate?: string;
    status?: string;
  },
) {
  const current = await query(`SELECT * FROM semesters WHERE id = $1`, [id]);
  if (!current.rows[0]) throw new HttpError(404, "Semester not found.", "not_found");
  const row = current.rows[0];
  const result = await query(
    `UPDATE semesters
     SET name = $2, academic_year = $3, start_date = $4, end_date = $5, status = $6
     WHERE id = $1 RETURNING *`,
    [
      id,
      input.name ?? row.name,
      input.academicYear ?? row.academic_year,
      input.startDate ?? row.start_date,
      input.endDate ?? row.end_date,
      input.status ?? row.status,
    ],
  );
  return result.rows[0];
}

export async function notifyUsers(
  userIds: number[],
  title: string,
  body: string,
  kind: string,
  link?: string | null,
) {
  for (const userId of userIds) {
    await query(
      `INSERT INTO notifications (user_id, title, body, kind, link)
       VALUES ($1,$2,$3,$4,$5)`,
      [userId, title, body, kind, link ?? null],
    );
  }
}

export async function courseMemberUserIds(courseId: number) {
  const result = await query<{ id: number }>(
    `SELECT u.id
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     JOIN users u ON u.id = s.user_id
     WHERE e.course_id = $1
     UNION
     SELECT t.user_id AS id
     FROM courses c
     JOIN teachers t ON t.id = c.teacher_id
     WHERE c.id = $1`,
    [courseId],
  );
  return result.rows.map((row) => row.id);
}

export async function notifyCourse(
  courseId: number,
  title: string,
  body: string,
  kind: string,
  link?: string | null,
  exceptUserId?: number,
) {
  const ids = (await courseMemberUserIds(courseId)).filter((id) => id !== exceptUserId);
  await notifyUsers(ids, title, body, kind, link);
}

export async function listNotifications(userId: number) {
  const result = await query(
    `SELECT * FROM notifications WHERE user_id = $1 ORDER BY created_at DESC LIMIT 80`,
    [userId],
  );
  const unread = await query<{ count: number }>(
    `SELECT COUNT(*)::int AS count FROM notifications WHERE user_id = $1 AND read_at IS NULL`,
    [userId],
  );
  return { notifications: result.rows, unread: unread.rows[0].count };
}

export async function markNotificationRead(userId: number, id?: number) {
  if (id) {
    await query(`UPDATE notifications SET read_at = now() WHERE id = $1 AND user_id = $2`, [id, userId]);
  } else {
    await query(`UPDATE notifications SET read_at = now() WHERE user_id = $1 AND read_at IS NULL`, [userId]);
  }
}

export async function listDiscussions(courseId: number, search?: string) {
  const result = await query(
    `SELECT d.*, u.name AS author_name, u.role AS author_role,
            (SELECT COUNT(*)::int FROM discussion_replies r WHERE r.discussion_id = d.id) AS reply_count
     FROM discussions d
     JOIN users u ON u.id = d.user_id
     WHERE d.course_id = $1
       AND ($2::text IS NULL OR d.title ILIKE '%' || $2 || '%' OR d.body ILIKE '%' || $2 || '%')
     ORDER BY d.created_at DESC`,
    [courseId, search?.trim() || null],
  );
  return result.rows;
}

export async function getDiscussion(id: number) {
  const discussion = await query(
    `SELECT d.*, u.name AS author_name, u.role AS author_role
     FROM discussions d JOIN users u ON u.id = d.user_id WHERE d.id = $1`,
    [id],
  );
  if (!discussion.rows[0]) return null;
  const replies = await query(
    `SELECT r.*, u.name AS author_name, u.role AS author_role
     FROM discussion_replies r JOIN users u ON u.id = r.user_id
     WHERE r.discussion_id = $1 ORDER BY r.created_at`,
    [id],
  );
  return { discussion: discussion.rows[0], replies: replies.rows };
}

export async function createDiscussion(courseId: number, userId: number, title: string, body: string) {
  const result = await query(
    `INSERT INTO discussions (course_id, user_id, title, body) VALUES ($1,$2,$3,$4) RETURNING *`,
    [courseId, userId, title, body],
  );
  return result.rows[0];
}

export async function updateDiscussion(id: number, userId: number, role: string, title: string, body: string) {
  const current = await query<{ user_id: number }>(`SELECT user_id FROM discussions WHERE id = $1`, [id]);
  if (!current.rows[0]) throw new HttpError(404, "Discussion not found.", "not_found");
  if (role === "student" && current.rows[0].user_id !== userId) {
    throw new HttpError(403, "You can only edit your own posts.", "forbidden");
  }
  const result = await query(
    `UPDATE discussions SET title = $2, body = $3, updated_at = now() WHERE id = $1 RETURNING *`,
    [id, title, body],
  );
  return result.rows[0];
}

export async function deleteDiscussion(id: number, userId: number, role: string) {
  const current = await query<{ user_id: number }>(`SELECT user_id FROM discussions WHERE id = $1`, [id]);
  if (!current.rows[0]) throw new HttpError(404, "Discussion not found.", "not_found");
  if (role === "student" && current.rows[0].user_id !== userId) {
    throw new HttpError(403, "You can only delete your own posts.", "forbidden");
  }
  await query(`DELETE FROM discussions WHERE id = $1`, [id]);
}

export async function createReply(discussionId: number, userId: number, body: string) {
  const result = await query(
    `INSERT INTO discussion_replies (discussion_id, user_id, body) VALUES ($1,$2,$3) RETURNING *`,
    [discussionId, userId, body],
  );
  return result.rows[0];
}

export async function updateReply(id: number, userId: number, role: string, body: string) {
  const current = await query<{ user_id: number }>(`SELECT user_id FROM discussion_replies WHERE id = $1`, [id]);
  if (!current.rows[0]) throw new HttpError(404, "Reply not found.", "not_found");
  if (role === "student" && current.rows[0].user_id !== userId) {
    throw new HttpError(403, "You can only edit your own posts.", "forbidden");
  }
  const result = await query(
    `UPDATE discussion_replies SET body = $2, updated_at = now() WHERE id = $1 RETURNING *`,
    [id, body],
  );
  return result.rows[0];
}

export async function deleteReply(id: number, userId: number, role: string) {
  const current = await query<{ user_id: number }>(`SELECT user_id FROM discussion_replies WHERE id = $1`, [id]);
  if (!current.rows[0]) throw new HttpError(404, "Reply not found.", "not_found");
  if (role === "student" && current.rows[0].user_id !== userId) {
    throw new HttpError(403, "You can only delete your own posts.", "forbidden");
  }
  await query(`DELETE FROM discussion_replies WHERE id = $1`, [id]);
}

export async function touchPresence(courseId: number, userId: number) {
  const previous = await query<{ last_seen: string }>(
    `SELECT last_seen FROM classroom_presence WHERE course_id = $1 AND user_id = $2`,
    [courseId, userId],
  );
  await query(
    `INSERT INTO classroom_presence (course_id, user_id, last_seen)
     VALUES ($1,$2,now())
     ON CONFLICT (course_id, user_id) DO UPDATE SET last_seen = now()`,
    [courseId, userId],
  );
  const stale =
    !previous.rows[0] ||
    Date.now() - new Date(previous.rows[0].last_seen).getTime() > 45_000;
  if (stale) {
    await query(
      `INSERT INTO classroom_messages (course_id, user_id, body, kind)
       VALUES ($1,$2,'joined the classroom chat','join')`,
      [courseId, userId],
    );
  }
}

export async function listClassroomChat(courseId: number, userId: number) {
  await touchPresence(courseId, userId);
  const messages = await query(
    `SELECT m.*, u.name AS author_name, u.role AS author_role
     FROM classroom_messages m
     LEFT JOIN users u ON u.id = m.user_id
     WHERE m.course_id = $1
     ORDER BY m.created_at DESC
     LIMIT 80`,
    [courseId],
  );
  const online = await query(
    `SELECT u.id, u.name, u.role
     FROM classroom_presence p
     JOIN users u ON u.id = p.user_id
     WHERE p.course_id = $1 AND p.last_seen > now() - interval '45 seconds'
     ORDER BY u.name`,
    [courseId],
  );
  return { messages: messages.rows.reverse(), online: online.rows };
}

export async function postClassroomChat(courseId: number, userId: number, body: string) {
  await touchPresence(courseId, userId);
  const result = await query(
    `INSERT INTO classroom_messages (course_id, user_id, body, kind)
     VALUES ($1,$2,$3,'message') RETURNING *`,
    [courseId, userId, body],
  );
  return result.rows[0];
}

export async function deleteClassroomChat(id: number, userId: number, role: string) {
  const current = await query<{ user_id: number | null }>(
    `SELECT user_id FROM classroom_messages WHERE id = $1`,
    [id],
  );
  if (!current.rows[0]) throw new HttpError(404, "Message not found.", "not_found");
  if (role === "student" && current.rows[0].user_id !== userId) {
    throw new HttpError(403, "You can only delete your own messages.", "forbidden");
  }
  await query(`DELETE FROM classroom_messages WHERE id = $1`, [id]);
}

export async function listAnnouncements(courseId?: number) {
  const result = await query(
    `SELECT a.*, u.name AS author_name, c.course_code, c.course_name
     FROM announcements a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN courses c ON c.id = a.course_id
     WHERE ($1::int IS NULL OR a.course_id = $1 OR a.course_id IS NULL)
     ORDER BY a.created_at DESC
     LIMIT 50`,
    [courseId ?? null],
  );
  return result.rows;
}

export async function createAnnouncement(
  userId: number,
  title: string,
  body: string,
  courseId?: number | null,
) {
  const result = await query(
    `INSERT INTO announcements (course_id, user_id, title, body)
     VALUES ($1,$2,$3,$4) RETURNING *`,
    [courseId ?? null, userId, title, body],
  );
  return result.rows[0];
}

export async function replaceGradeRules(
  rules: { minPercent: number; maxPercent: number; grade: string; label?: string }[],
) {
  await query(`DELETE FROM attendance_grade_rules`);
  for (const [index, rule] of rules.entries()) {
    await query(
      `INSERT INTO attendance_grade_rules (min_percent, max_percent, grade, label, sort_order)
       VALUES ($1,$2,$3,$4,$5)`,
      [rule.minPercent, rule.maxPercent, rule.grade, rule.label ?? null, index],
    );
  }
}

export async function updateSettings(input: {
  lowThreshold?: number;
  lateJoinMinutes?: number;
  earlyLeaveMinutes?: number;
}) {
  await query(
    `INSERT INTO attendance_settings (id, low_threshold, late_join_minutes, early_leave_minutes)
     VALUES (1, $1, $2, $3)
     ON CONFLICT (id) DO UPDATE
       SET low_threshold = COALESCE($1, attendance_settings.low_threshold),
           late_join_minutes = COALESCE($2, attendance_settings.late_join_minutes),
           early_leave_minutes = COALESCE($3, attendance_settings.early_leave_minutes)`,
    [input.lowThreshold ?? null, input.lateJoinMinutes ?? null, input.earlyLeaveMinutes ?? null],
  );
}
