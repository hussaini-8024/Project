import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import bcrypt from "bcryptjs";
import type { PoolClient } from "pg";
import { pool } from "./pool.js";

const __dirname = dirname(fileURLToPath(import.meta.url));
const reset = process.argv.includes("--reset");
const password = "UniMeet@2026";

const LECTURE_TRANSCRIPT = `
Good morning. Today we continue Database Systems with a lecture on relational design, transactions, and indexing.

A database is an organized collection of related data. A Database Management System, or DBMS, is software that stores, retrieves, and protects that data. In a university, students, courses, enrollments, and attendance are all related records that must stay consistent.

The relational model organizes data into tables, also called relations. Each table has a primary key that uniquely identifies a row. A foreign key references a primary key in another table. For example, enrollments.student_id references students.id. This is how UniMeet knows which student belongs in which course.

Normalization reduces redundancy. First Normal Form requires atomic values. Second Normal Form removes partial dependency on a composite key. Third Normal Form removes transitive dependency, so non-key attributes depend only on the key. If a student's department name is stored on every enrollment row, an update to the department name can become inconsistent. That is why department lives in its own table.

A transaction is a logical unit of work. ACID properties are Atomicity, Consistency, Isolation, and Durability. Atomicity means all statements succeed or none do. Consistency means constraints remain true. Isolation means concurrent transactions do not corrupt each other. Durability means a committed change survives a crash.

Concurrency control uses locks or multiversion concurrency control. A dirty read happens when one transaction reads uncommitted data from another. Repeatable read and serializable isolation prevent more anomalies. University attendance must not double-count a leave event if two tabs close at once.

Indexes speed lookups. A B-plus tree index on university_id makes login fast. An index on enrollments(course_id, student_id) makes the JOIN CLASS authorization check cheap. Indexes are not free: writes become slightly slower, so we index the paths we query often.

Query planning chooses sequential scan or index scan. SELECT students who are enrolled in Database Systems and whose class is live is the authorization query behind JOIN CLASS. That check must happen on the server, never only in the browser.

Referential integrity rejects an enrollment for a student_id that does not exist. A CHECK constraint can require semester between 1 and 8. These constraints are the last line of defense after application validation.

In the next lecture we will cover isolation levels with examples and how LiveKit room names map to the classes table. Please review third normal form and ACID before the quiz.
`.trim();

const LECTURE_NOTES = `
Week 5 — Relational design and transactions

1. Relational model: tables, keys, foreign keys
2. Normalization: 1NF, 2NF, 3NF
3. ACID transactions
4. Indexes and authorization query paths
5. Integrity constraints

Reading: Elmasri & Navathe, chapters on relational design and transaction processing.
`.trim();

async function ensurePlatformSeed(client: PoolClient) {
  await client.query(
    `INSERT INTO semesters (name, academic_year, start_date, end_date, status)
     VALUES ('Fall 2026', '2026-2027', '2026-08-15', '2026-12-20', 'active'),
            ('Spring 2027', '2026-2027', '2027-01-10', '2027-05-20', 'planned'),
            ('Summer 2027', '2026-2027', '2027-06-01', '2027-07-31', 'planned')
     ON CONFLICT (name) DO NOTHING`,
  );

  const rules = await client.query(`SELECT COUNT(*)::int AS count FROM attendance_grade_rules`);
  if (rules.rows[0].count === 0) {
    await client.query(
      `INSERT INTO attendance_grade_rules (min_percent, max_percent, grade, label, sort_order) VALUES
        (90, 100, 'A', 'Excellent', 1),
        (80, 89.99, 'B', 'Good', 2),
        (70, 79.99, 'C', 'Satisfactory', 3),
        (60, 69.99, 'D', 'Low', 4),
        (0, 59.99, 'F', 'Fail', 5)`,
    );
  }
  await client.query(
    `INSERT INTO attendance_settings (id, low_threshold, late_join_minutes, early_leave_minutes)
     VALUES (1, 75, 10, 10)
     ON CONFLICT (id) DO NOTHING`,
  );

  const semester = await client.query<{ id: number }>(`SELECT id FROM semesters WHERE name = 'Fall 2026'`);
  const semesterId = semester.rows[0]?.id ?? null;
  await client.query(
    `UPDATE courses SET semester_id = COALESCE(semester_id, $1), academic_year = COALESCE(academic_year, '2026-2027')`,
    [semesterId],
  );
  await client.query(
    `UPDATE classes SET session_code = CONCAT(c.course_code, '-SESSION-', LPAD(cl.id::text, 2, '0'))
     FROM courses c
     WHERE c.id = classes.course_id AND classes.session_code IS NULL`,
  );
  await client.query(
    `UPDATE classes SET actual_start = start_time WHERE actual_start IS NULL AND status <> 'scheduled'`,
  );
  await client.query(
    `UPDATE classes SET ended_at = end_time WHERE ended_at IS NULL AND status = 'ended'`,
  );

  const dept = await client.query<{ id: number }>(`SELECT id FROM departments WHERE code = 'CS'`);
  const program = await client.query<{ id: number }>(`SELECT id FROM programs WHERE code = 'BSCS'`);
  const ahmedTeacher = await client.query<{ id: number }>(`SELECT id FROM teachers WHERE teacher_id = 'TCH-2001'`);
  const fatimaTeacher = await client.query<{ id: number }>(`SELECT id FROM teachers WHERE teacher_id = 'TCH-2002'`);
  if (!dept.rows[0] || !program.rows[0] || !ahmedTeacher.rows[0] || !fatimaTeacher.rows[0]) return;

  const extraCourses = [
    ["CS-502", "Web Engineering", ahmedTeacher.rows[0].id, "Frontend and backend engineering for campus systems."],
    ["CS-503", "Artificial Intelligence", fatimaTeacher.rows[0].id, "Search, knowledge representation, and learning."],
    ["CS-504", "Mathematics", ahmedTeacher.rows[0].id, "Discrete mathematics for computer science."],
  ] as const;
  for (const [code, name, teacherId, description] of extraCourses) {
    await client.query(
      `INSERT INTO courses
        (course_code, course_name, teacher_id, program_id, department_id, semester, section, credit_hours, description, semester_id, academic_year)
       VALUES ($1,$2,$3,$4,$5,5,'A',3,$6,$7,'2026-2027')
       ON CONFLICT (course_code) DO NOTHING`,
      [code, name, teacherId, program.rows[0].id, dept.rows[0].id, description, semesterId],
    );
  }

  const students = await client.query<{ id: number; student_id: string }>(
    `SELECT id, student_id FROM students WHERE student_id IN ('STU-1001','STU-1002','STU-1003')`,
  );
  const courses = await client.query<{ id: number; course_code: string }>(
    `SELECT id, course_code FROM courses WHERE course_code IN ('CS-501','CS-401','CS-502','CS-503','CS-504')`,
  );
  const byCode = Object.fromEntries(courses.rows.map((row) => [row.course_code, row.id]));
  const bySid = Object.fromEntries(students.rows.map((row) => [row.student_id, row.id]));
  for (const student of students.rows) {
    for (const course of courses.rows) {
      await client.query(
        `INSERT INTO enrollments (student_id, course_id) VALUES ($1,$2)
         ON CONFLICT (student_id, course_id) DO NOTHING`,
        [student.id, course.id],
      );
    }
  }

  const teacherUser = await client.query<{ id: number }>(
    `SELECT user_id AS id FROM teachers WHERE teacher_id = 'TCH-2001'`,
  );
  const createdBy = teacherUser.rows[0]?.id ?? null;

  type SegmentSpec = { join: string; leave: string };
  const seedEnded = async (
    courseCode: string,
    title: string,
    room: string,
    sessionCode: string,
    start: string,
    end: string,
    segments: Record<string, SegmentSpec[]>,
  ) => {
    const courseId = byCode[courseCode];
    if (!courseId) return;
    const existingClass = await client.query<{ id: number }>(`SELECT id FROM classes WHERE room_name = $1`, [room]);
    let classId = existingClass.rows[0]?.id;
    if (!classId) {
      const created = await client.query<{ id: number }>(
        `INSERT INTO classes
          (course_id, title, start_time, end_time, room_name, status, is_open_lab, created_by, session_code, actual_start, ended_at)
         VALUES ($1,$2,$3,$4,$5,'ended',false,$6,$7,$3,$4)
         RETURNING id`,
        [courseId, title, start, end, room, createdBy, sessionCode],
      );
      classId = created.rows[0].id;
    }
    for (const [sid, segs] of Object.entries(segments)) {
      const studentId = bySid[sid];
      if (!studentId) continue;
      const already = await client.query(
        `SELECT 1 FROM attendance WHERE student_id = $1 AND class_id = $2`,
        [studentId, classId],
      );
      if (already.rows[0]) continue;
      const first = segs[0];
      const last = segs[segs.length - 1];
      const attendance = await client.query<{ id: number }>(
        `INSERT INTO attendance
          (student_id, class_id, join_time, last_join_time, leave_time, duration_seconds, status)
         VALUES ($1,$2,$3,$4,$5,0,'present')
         RETURNING id`,
        [studentId, classId, first.join, last.join, last.leave],
      );
      for (const seg of segs) {
        const seconds = Math.round((new Date(seg.leave).getTime() - new Date(seg.join).getTime()) / 1000);
        await client.query(
          `INSERT INTO attendance_segments
            (attendance_id, student_id, class_id, joined_at, left_at, duration_seconds)
           VALUES ($1,$2,$3,$4,$5,$6)`,
          [attendance.rows[0].id, studentId, classId, seg.join, seg.leave, seconds],
        );
        await client.query(
          `INSERT INTO attendance_events (student_id, class_id, kind, at) VALUES ($1,$2,'join',$3)`,
          [studentId, classId, seg.join],
        );
        await client.query(
          `INSERT INTO attendance_events (student_id, class_id, kind, at) VALUES ($1,$2,'leave',$3)`,
          [studentId, classId, seg.leave],
        );
      }
      await client.query(
        `UPDATE attendance SET duration_seconds = (
           SELECT COALESCE(SUM(duration_seconds),0) FROM attendance_segments WHERE attendance_id = $1
         ) WHERE id = $1`,
        [attendance.rows[0].id],
      );
    }
  };

  // Critical rejoin example: 10:00-10:20 + 10:40-11:00 = 40 minutes, not 60.
  await seedEnded(
    "CS-501",
    "Join / leave / rejoin lab",
    "cs501-rejoin-lab",
    "CS-501-SESSION-REJOIN",
    "2026-09-20T10:00:00+00:00",
    "2026-09-20T11:40:00+00:00",
    {
      "STU-1001": [
        { join: "2026-09-20T10:00:00+00:00", leave: "2026-09-20T10:20:00+00:00" },
        { join: "2026-09-20T10:40:00+00:00", leave: "2026-09-20T11:00:00+00:00" },
      ],
      "STU-1002": [{ join: "2026-09-20T10:00:00+00:00", leave: "2026-09-20T11:40:00+00:00" }],
      "STU-1003": [{ join: "2026-09-20T10:00:00+00:00", leave: "2026-09-20T10:20:00+00:00" }],
    },
  );

  await seedEnded(
    "CS-502",
    "Week 1 — HTTP and the campus frontend",
    "cs502-week1",
    "CS-502-SESSION-01",
    "2026-09-08T09:00:00+00:00",
    "2026-09-08T10:30:00+00:00",
    {
      "STU-1001": [{ join: "2026-09-08T09:00:00+00:00", leave: "2026-09-08T10:21:00+00:00" }],
      "STU-1002": [{ join: "2026-09-08T09:00:00+00:00", leave: "2026-09-08T10:12:00+00:00" }],
      "STU-1003": [{ join: "2026-09-08T09:00:00+00:00", leave: "2026-09-08T10:03:00+00:00" }],
    },
  );
  await seedEnded(
    "CS-502",
    "Week 2 — REST APIs",
    "cs502-week2",
    "CS-502-SESSION-02",
    "2026-09-15T09:00:00+00:00",
    "2026-09-15T10:30:00+00:00",
    {
      "STU-1001": [{ join: "2026-09-15T09:00:00+00:00", leave: "2026-09-15T10:21:00+00:00" }],
      "STU-1002": [{ join: "2026-09-15T09:00:00+00:00", leave: "2026-09-15T10:12:00+00:00" }],
      "STU-1003": [{ join: "2026-09-15T09:00:00+00:00", leave: "2026-09-15T10:03:00+00:00" }],
    },
  );
  await seedEnded(
    "CS-503",
    "Week 1 — Informed search",
    "cs503-week1",
    "CS-503-SESSION-01",
    "2026-09-09T11:00:00+00:00",
    "2026-09-09T12:40:00+00:00",
    {
      "STU-1001": [{ join: "2026-09-09T11:00:00+00:00", leave: "2026-09-09T12:10:00+00:00" }],
      "STU-1002": [{ join: "2026-09-09T11:00:00+00:00", leave: "2026-09-09T12:23:00+00:00" }],
      "STU-1003": [{ join: "2026-09-09T11:00:00+00:00", leave: "2026-09-09T12:32:00+00:00" }],
    },
  );
  await seedEnded(
    "CS-504",
    "Week 1 — Sets and relations",
    "cs504-week1",
    "CS-504-SESSION-01",
    "2026-09-10T08:00:00+00:00",
    "2026-09-10T09:30:00+00:00",
    {
      "STU-1001": [{ join: "2026-09-10T08:00:00+00:00", leave: "2026-09-10T09:23:00+00:00" }],
      "STU-1002": [{ join: "2026-09-10T08:00:00+00:00", leave: "2026-09-10T09:12:00+00:00" }],
      "STU-1003": [{ join: "2026-09-10T08:00:00+00:00", leave: "2026-09-10T08:54:00+00:00" }],
    },
  );
  await seedEnded(
    "CS-504",
    "Week 2 — Graphs and counting",
    "cs504-week2",
    "CS-504-SESSION-02",
    "2026-09-17T08:00:00+00:00",
    "2026-09-17T09:30:00+00:00",
    {
      "STU-1001": [{ join: "2026-09-17T08:00:00+00:00", leave: "2026-09-17T09:23:00+00:00" }],
      "STU-1002": [{ join: "2026-09-17T08:00:00+00:00", leave: "2026-09-17T09:12:00+00:00" }],
      "STU-1003": [{ join: "2026-09-17T08:00:00+00:00", leave: "2026-09-17T08:54:00+00:00" }],
    },
  );
  await seedEnded(
    "CS-401",
    "Week 3 — Scheduling workshop",
    "cs401-week3",
    "CS-401-SESSION-01",
    "2026-09-11T13:00:00+00:00",
    "2026-09-11T14:30:00+00:00",
    {
      "STU-1001": [{ join: "2026-09-11T13:00:00+00:00", leave: "2026-09-11T14:21:00+00:00" }],
      "STU-1002": [{ join: "2026-09-11T13:00:00+00:00", leave: "2026-09-11T14:12:00+00:00" }],
      "STU-1003": [{ join: "2026-09-11T13:00:00+00:00", leave: "2026-09-11T14:03:00+00:00" }],
    },
  );

  await client.query(
    `INSERT INTO attendance_segments (attendance_id, student_id, class_id, joined_at, left_at, duration_seconds)
     SELECT a.id, a.student_id, a.class_id,
            COALESCE(a.join_time, cl.start_time),
            COALESCE(a.leave_time, cl.end_time),
            a.duration_seconds
     FROM attendance a
     JOIN classes cl ON cl.id = a.class_id
     WHERE NOT EXISTS (SELECT 1 FROM attendance_segments s WHERE s.attendance_id = a.id)`,
  );

  const dbCourse = byCode["CS-501"];
  const aliUser = await client.query<{ id: number }>(
    `SELECT u.id FROM users u JOIN students s ON s.user_id = u.id WHERE s.student_id = 'STU-1001'`,
  );
  if (dbCourse && aliUser.rows[0]) {
    const existingThread = await client.query(`SELECT 1 FROM discussions WHERE course_id = $1 LIMIT 1`, [dbCourse]);
    if (!existingThread.rows[0]) {
      const thread = await client.query<{ id: number }>(
        `INSERT INTO discussions (course_id, user_id, title, body)
         VALUES ($1,$2,'Indexing questions from Week 5','When should we add a composite index on enrollments?')
         RETURNING id`,
        [dbCourse, aliUser.rows[0].id],
      );
      await client.query(
        `INSERT INTO discussion_replies (discussion_id, user_id, body)
         VALUES ($1,$2,'Use it when the authorization check filters by course and student together.')`,
        [thread.rows[0].id, createdBy],
      );
    }
    const existingChat = await client.query(`SELECT 1 FROM classroom_messages WHERE course_id = $1 LIMIT 1`, [dbCourse]);
    if (!existingChat.rows[0]) {
      await client.query(
        `INSERT INTO classroom_messages (course_id, user_id, body) VALUES
          ($1,$2,'Office hours stay in this classroom chat, not the live meeting chat.'),
          ($1,$3,'Understood — I will post assignment questions here.')`,
        [dbCourse, createdBy, aliUser.rows[0].id],
      );
    }
    const existingAnn = await client.query(`SELECT 1 FROM announcements WHERE course_id = $1 LIMIT 1`, [dbCourse]);
    if (!existingAnn.rows[0]) {
      await client.query(
        `INSERT INTO announcements (course_id, user_id, title, body)
         VALUES ($1,$2,'Midterm attendance policy','Attendance grades use actual connected minutes, not first-join to last-leave.')`,
        [dbCourse, createdBy],
      );
    }
  }

  const users = await client.query<{ id: number }>(`SELECT id FROM users`);
  const existingNotes = await client.query(`SELECT COUNT(*)::int AS count FROM notifications`);
  if (existingNotes.rows[0].count === 0) {
    for (const user of users.rows) {
      await client.query(
        `INSERT INTO notifications (user_id, title, body, kind, link)
         VALUES ($1,'Fall 2026 is open','Attendance, discussions, and classroom chat are ready.','info','/dashboard')`,
        [user.id],
      );
    }
  }
}

async function main() {
  const client = await pool.connect();
  try {
    if (reset) {
      await client.query("DROP SCHEMA public CASCADE");
      await client.query("CREATE SCHEMA public");
      await client.query("GRANT ALL ON SCHEMA public TO unimeet");
      await client.query("GRANT ALL ON SCHEMA public TO public");
    }

    const schemaPath = resolve(__dirname, "../../../database/schema.sql");
    const schema = readFileSync(schemaPath, "utf8");
    await client.query(schema);
    const extendPath = resolve(__dirname, "../../../database/schema-extend.sql");
    await client.query(readFileSync(extendPath, "utf8"));

    const existing = await client.query("SELECT COUNT(*)::int AS count FROM users");
    if (existing.rows[0].count > 0 && !reset) {
      await ensurePlatformSeed(client);
      console.log("Existing UniMeet database extended with attendance reporting.");
      return;
    }

    const hash = await bcrypt.hash(password, 10);
    await client.query("BEGIN");

    const dept = await client.query<{ id: number }>(
      `INSERT INTO departments (code, name) VALUES ('CS', 'Computer Science') RETURNING id`,
    );
    const departmentId = dept.rows[0].id;

    const program = await client.query<{ id: number }>(
      `INSERT INTO programs (department_id, code, name)
       VALUES ($1, 'BSCS', 'Bachelor of Science in Computer Science')
       RETURNING id`,
      [departmentId],
    );
    const programId = program.rows[0].id;

    const insertUser = async (
      name: string,
      email: string,
      role: string,
      universityId: string,
    ) => {
      const result = await client.query<{ id: number }>(
        `INSERT INTO users (name, email, password_hash, role, university_id)
         VALUES ($1, $2, $3, $4, $5) RETURNING id`,
        [name, email, hash, role, universityId],
      );
      return result.rows[0].id;
    };

    const adminId = await insertUser(
      "Registrar Office",
      "registrar@university.edu",
      "admin",
      "ADM-3001",
    );
    const teacherUserId = await insertUser(
      "Dr. Ahmed",
      "ahmed.faculty@university.edu",
      "teacher",
      "TCH-2001",
    );
    const osTeacherUserId = await insertUser(
      "Prof. Fatima Noor",
      "fatima.faculty@university.edu",
      "teacher",
      "TCH-2002",
    );
    const aliUser = await insertUser("Ali Khan", "ali.khan@university.edu", "student", "STU-1001");
    const ahmedUser = await insertUser(
      "Ahmed Raza",
      "ahmed.raza@university.edu",
      "student",
      "STU-1002",
    );
    const saraUser = await insertUser(
      "Sara Malik",
      "sara.malik@university.edu",
      "student",
      "STU-1003",
    );
    const johnUser = await insertUser(
      "John Smith",
      "john.smith@university.edu",
      "student",
      "STU-1004",
    );

    const teacher = await client.query<{ id: number }>(
      `INSERT INTO teachers (user_id, teacher_id, department_id, title)
       VALUES ($1, 'TCH-2001', $2, 'Associate Professor') RETURNING id`,
      [teacherUserId, departmentId],
    );
    const osTeacher = await client.query<{ id: number }>(
      `INSERT INTO teachers (user_id, teacher_id, department_id, title)
       VALUES ($1, 'TCH-2002', $2, 'Assistant Professor') RETURNING id`,
      [osTeacherUserId, departmentId],
    );

    const insertStudent = async (userId: number, studentId: string, section: string) => {
      const result = await client.query<{ id: number }>(
        `INSERT INTO students (user_id, student_id, department_id, program_id, semester, section)
         VALUES ($1, $2, $3, $4, 5, $5) RETURNING id`,
        [userId, studentId, departmentId, programId, section],
      );
      return result.rows[0].id;
    };

    const ali = await insertStudent(aliUser, "STU-1001", "A");
    const ahmed = await insertStudent(ahmedUser, "STU-1002", "A");
    const sara = await insertStudent(saraUser, "STU-1003", "A");
    const john = await insertStudent(johnUser, "STU-1004", "B");

    const dbCourse = await client.query<{ id: number }>(
      `INSERT INTO courses
        (course_code, course_name, teacher_id, program_id, department_id, semester, section, credit_hours, description)
       VALUES
        ('CS-501', 'Database Systems', $1, $2, $3, 5, 'A', 3,
         'Relational modeling, SQL, transactions, and integrity for university information systems.')
       RETURNING id`,
      [teacher.rows[0].id, programId, departmentId],
    );
    const osCourse = await client.query<{ id: number }>(
      `INSERT INTO courses
        (course_code, course_name, teacher_id, program_id, department_id, semester, section, credit_hours, description)
       VALUES
        ('CS-401', 'Operating Systems', $1, $2, $3, 5, 'A', 3,
         'Processes, scheduling, memory, and concurrency.')
       RETURNING id`,
      [osTeacher.rows[0].id, programId, departmentId],
    );

    const enroll = async (studentId: number, courseId: number) => {
      await client.query(
        `INSERT INTO enrollments (student_id, course_id) VALUES ($1, $2)`,
        [studentId, courseId],
      );
    };

    await enroll(ali, dbCourse.rows[0].id);
    await enroll(ahmed, dbCourse.rows[0].id);
    await enroll(sara, dbCourse.rows[0].id);
    await enroll(ali, osCourse.rows[0].id);
    await enroll(ahmed, osCourse.rows[0].id);
    await enroll(sara, osCourse.rows[0].id);
    await enroll(john, osCourse.rows[0].id);
    // John is enrolled in Operating Systems only — not Database Systems.

    const liveClass = await client.query<{ id: number }>(
      `INSERT INTO classes
        (course_id, title, start_time, end_time, room_name, status, is_open_lab, created_by)
       VALUES
        ($1, 'Week 5 — Transactions and Indexing',
         now() - interval '20 minutes', now() + interval '3 hours',
         'cs501-database-systems', 'live', true, $2)
       RETURNING id`,
      [dbCourse.rows[0].id, teacherUserId],
    );

    await client.query(
      `INSERT INTO classes
        (course_id, title, start_time, end_time, room_name, status, is_open_lab, created_by)
       VALUES
        ($1, 'Week 6 — Isolation Levels',
         now() + interval '2 days', now() + interval '2 days 90 minutes',
         'cs501-week6-isolation', 'scheduled', false, $2)`,
      [dbCourse.rows[0].id, teacherUserId],
    );

    const pastClass = await client.query<{ id: number }>(
      `INSERT INTO classes
        (course_id, title, start_time, end_time, room_name, status, is_open_lab, created_by)
       VALUES
        ($1, 'Week 4 — Normalization Workshop',
         now() - interval '8 days', now() - interval '8 days' + interval '90 minutes',
         'cs501-week4-normalization', 'ended', false, $2)
       RETURNING id`,
      [dbCourse.rows[0].id, teacherUserId],
    );

    await client.query(
      `INSERT INTO lecture_transcripts (class_id, transcript, summary)
       VALUES ($1, $2, $3)`,
      [
        liveClass.rows[0].id,
        LECTURE_TRANSCRIPT,
        "The lecture covers the relational model, normalization through 3NF, ACID transactions, indexes used by the JOIN CLASS authorization path, and integrity constraints.",
      ],
    );

    await client.query(
      `INSERT INTO lecture_materials (course_id, class_id, title, body)
       VALUES ($1, $2, 'Week 5 lecture notes', $3)`,
      [dbCourse.rows[0].id, liveClass.rows[0].id, LECTURE_NOTES],
    );

    const markPast = async (
      studentId: number,
      duration: number,
      status: string,
    ) => {
      await client.query(
        `INSERT INTO attendance
          (student_id, class_id, join_time, last_join_time, leave_time, duration_seconds, status)
         VALUES (
           $1, $2,
           now() - interval '8 days',
           now() - interval '8 days' + interval '1 minute',
           now() - interval '8 days' + make_interval(secs => $3::int),
           $3::int,
           $4
         )`,
        [studentId, pastClass.rows[0].id, duration, status],
      );
    };
    await markPast(ali, 88 * 60, "present");
    await markPast(ahmed, 52 * 60, "present");
    await markPast(sara, 28 * 60, "partial");

    await client.query("COMMIT");
    await ensurePlatformSeed(client);
    console.log("UniMeet database ready.");
    console.log("Demo password for every account: UniMeet@2026");
    console.log("  Admin   ADM-3001  Registrar Office");
    console.log("  Teacher TCH-2001  Dr. Ahmed");
    console.log("  Student STU-1001  Ali Khan   (enrolled in CS-501)");
    console.log("  Student STU-1002  Ahmed Raza (enrolled in CS-501)");
    console.log("  Student STU-1003  Sara Malik (enrolled in CS-501)");
    console.log("  Student STU-1004  John Smith (NOT enrolled in CS-501)");
    void adminId;
  } catch (error) {
    await client.query("ROLLBACK").catch(() => undefined);
    console.error(error);
    process.exitCode = 1;
  } finally {
    client.release();
    await pool.end();
  }
}

main();
