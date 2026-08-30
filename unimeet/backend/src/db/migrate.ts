import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import bcrypt from "bcryptjs";
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

    const existing = await client.query("SELECT COUNT(*)::int AS count FROM users");
    if (existing.rows[0].count > 0 && !reset) {
      console.log("Database already seeded. Use npm run db:reset to rebuild.");
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
