import { query } from "../db/pool.js";
import { listEnrollments } from "../models/courseModel.js";
import { listAttendance } from "../models/attendanceModel.js";
import { listSegments, sessionExpectedSeconds, sumSegmentSeconds, attendancePercent } from "./attendanceService.js";

export interface GradeRule {
  id: number;
  min_percent: number;
  max_percent: number;
  grade: string;
  label: string | null;
}

export async function listGradeRules(): Promise<GradeRule[]> {
  const result = await query<GradeRule>(
    `SELECT id, min_percent::float, max_percent::float, grade, label
     FROM attendance_grade_rules ORDER BY sort_order, min_percent DESC`,
  );
  return result.rows;
}

export async function getSettings() {
  const result = await query<{ low_threshold: number; late_join_minutes: number; early_leave_minutes: number }>(
    `SELECT low_threshold::float, late_join_minutes, early_leave_minutes FROM attendance_settings WHERE id = 1`,
  );
  return result.rows[0] ?? { low_threshold: 75, late_join_minutes: 10, early_leave_minutes: 10 };
}

export function gradeFor(percent: number, rules: GradeRule[]) {
  const match = rules.find((rule) => percent >= Number(rule.min_percent) && percent <= Number(rule.max_percent));
  return match?.grade ?? "F";
}

export async function studentReport(
  studentId: number,
  semesterId?: number,
  opts?: { teacherId?: number },
) {
  const student = await query<{
    id: number;
    student_id: string;
    semester: number;
    section: string;
    name: string;
    email: string;
    department: string | null;
    program: string | null;
    program_code: string | null;
  }>(
    `SELECT s.id, s.student_id, s.semester, s.section, u.name, u.email,
            d.name AS department, p.name AS program, p.code AS program_code
     FROM students s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = s.department_id
     LEFT JOIN programs p ON p.id = s.program_id
     WHERE s.id = $1`,
    [studentId],
  );
  if (!student.rows[0]) return null;

  const settings = await getSettings();
  const rules = await listGradeRules();
  const enrollments = await query<{ course_id: number }>(
    `SELECT e.course_id FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     WHERE e.student_id = $1
       AND ($2::int IS NULL OR c.semester_id = $2)
       AND ($3::int IS NULL OR c.teacher_id = $3)`,
    [studentId, semesterId ?? null, opts?.teacherId ?? null],
  );

  const subjects = [];
  let totalActual = 0;
  let totalExpected = 0;
  let totalSessions = 0;
  let attendedSessions = 0;
  let absences = 0;
  let lateJoins = 0;
  let earlyLeaves = 0;
  let rejoins = 0;
  const history = [];

  for (const enrollment of enrollments.rows) {
    const course = await query<{ course_code: string; course_name: string; teacher_name: string | null }>(
      `SELECT c.*, tu.name AS teacher_name
       FROM courses c
       LEFT JOIN teachers t ON t.id = c.teacher_id
       LEFT JOIN users tu ON tu.id = t.user_id
       WHERE c.id = $1`,
      [enrollment.course_id],
    );
    const sessions = await query<{
      id: number;
      session_code: string | null;
      title: string | null;
      start_time: string;
      end_time: string;
      actual_start: string | null;
      ended_at: string | null;
      status: string;
    }>(
      `SELECT * FROM classes WHERE course_id = $1 AND status <> 'scheduled' ORDER BY start_time`,
      [enrollment.course_id],
    );

    let subjectActual = 0;
    let subjectExpected = 0;
    let subjectAttended = 0;

    for (const session of sessions.rows) {
      const expected = sessionExpectedSeconds(session);
      const segments = await listSegments(studentId, session.id);
      const actual = sumSegmentSeconds(segments);
      const percent = attendancePercent(actual, expected);
      subjectExpected += expected;
      subjectActual += actual;
      totalSessions += 1;
      if (actual > 0) {
        subjectAttended += 1;
        attendedSessions += 1;
      } else {
        absences += 1;
      }
      if (segments[0]) {
        const firstJoin = new Date(segments[0].joined_at as string).getTime();
        const start = new Date(session.actual_start || session.start_time).getTime();
        if (firstJoin - start > settings.late_join_minutes * 60_000) lateJoins += 1;
        const last = segments[segments.length - 1];
        if (last.left_at) {
          const end = new Date(session.ended_at || session.end_time).getTime();
          if (end - new Date(last.left_at as string).getTime() > settings.early_leave_minutes * 60_000) {
            earlyLeaves += 1;
          }
        }
      }
      rejoins += Math.max(0, segments.length - 1);
      history.push({
        classId: session.id,
        sessionCode: session.session_code,
        title: session.title,
        date: session.start_time,
        subject: course.rows[0].course_name,
        courseCode: course.rows[0].course_code,
        start: session.start_time,
        end: session.ended_at || session.end_time,
        firstJoin: segments[0]?.joined_at ?? null,
        lastLeave: segments[segments.length - 1]?.left_at ?? null,
        joins: segments.length,
        leaves: segments.filter((s) => s.left_at).length,
        durationSeconds: actual,
        expectedSeconds: expected,
        percent,
        status: actual <= 0 ? "absent" : percent >= 75 ? "present" : "partial",
        segments,
      });
    }

    const percent = attendancePercent(subjectActual, subjectExpected);
    subjects.push({
      courseId: enrollment.course_id,
      courseCode: course.rows[0].course_code,
      courseName: course.rows[0].course_name,
      teacherName: course.rows[0].teacher_name,
      sessions: sessions.rows.length,
      attended: subjectAttended,
      expectedSeconds: subjectExpected,
      actualSeconds: subjectActual,
      percent,
      grade: gradeFor(percent, rules),
    });
    totalActual += subjectActual;
    totalExpected += subjectExpected;
  }

  const overall = attendancePercent(totalActual, totalExpected);
  return {
    student: student.rows[0],
    overall: {
      percent: overall,
      grade: gradeFor(overall, rules),
      subjects: subjects.length,
      sessions: totalSessions,
      attended: attendedSessions,
      absences,
      lateJoins,
      earlyLeaves,
      rejoins,
      expectedSeconds: totalExpected,
      actualSeconds: totalActual,
      low: overall < settings.low_threshold,
    },
    subjects,
    history,
    settings,
    rules,
  };
}

export async function courseReport(courseId: number) {
  const rules = await listGradeRules();
  const settings = await getSettings();
  const roster = await listEnrollments(courseId);
  const sessions = await query<{
    id: number;
    start_time: string;
    end_time: string;
    actual_start: string | null;
    ended_at: string | null;
    status: string;
  }>(`SELECT * FROM classes WHERE course_id = $1 AND status <> 'scheduled'`, [courseId]);
  const students: {
    studentId: number;
    name: string;
    universityId: string | number;
    sessions: number;
    attended: number;
    actualSeconds: number;
    expectedSeconds: number;
    percent: number;
    grade: string;
    status: string;
  }[] = [];
  const percents: number[] = [];

  for (const person of roster) {
    let actual = 0;
    let expected = 0;
    let attended = 0;
    for (const session of sessions.rows) {
      const exp = sessionExpectedSeconds(session);
      const segs = await listSegments(person.student_id as number, session.id as number);
      const act = sumSegmentSeconds(segs);
      expected += exp;
      actual += act;
      if (act > 0) attended += 1;
    }
    const percent = attendancePercent(actual, expected);
    percents.push(percent);
    const grade = gradeFor(percent, rules);
    students.push({
      studentId: person.student_id,
      name: person.name,
      universityId: person.university_student_id,
      sessions: sessions.rows.length,
      attended,
      actualSeconds: actual,
      expectedSeconds: expected,
      percent,
      grade,
      status: percent < settings.low_threshold ? "Low" : "Good",
    });
  }

  const average = percents.length
    ? Math.round((percents.reduce((a, b) => a + b, 0) / percents.length) * 100) / 100
    : 0;
  return {
    courseId,
    students,
    sessions: sessions.rows.length,
    enrolled: roster.length,
    average,
    highest: percents.length ? Math.max(...percents) : 0,
    lowest: percents.length ? Math.min(...percents) : 0,
    belowThreshold: students.filter((s) => s.percent < settings.low_threshold),
    gradeDistribution: rules.map((rule) => ({
      grade: rule.grade,
      count: students.filter((s) => s.grade === rule.grade).length,
    })),
    settings,
  };
}

export async function institutionReports(filters: { semesterId?: number; teacherId?: number }) {
  const courses = await query<{ id: number; course_code: string; course_name: string; teacher_id: number | null }>(
    `SELECT id, course_code, course_name, teacher_id FROM courses
     WHERE ($1::int IS NULL OR semester_id = $1)
       AND ($2::int IS NULL OR teacher_id = $2)
     ORDER BY course_code`,
    [filters.semesterId ?? null, filters.teacherId ?? null],
  );
  const classes = [];
  for (const course of courses.rows) {
    const report = await courseReport(course.id);
    classes.push({
      ...course,
      students: report.enrolled,
      sessions: report.sessions,
      average: report.average,
    });
  }
  const students = await query<{ id: number }>(
    `SELECT DISTINCT s.id FROM students s
     JOIN enrollments e ON e.student_id = s.id
     JOIN courses c ON c.id = e.course_id
     WHERE ($1::int IS NULL OR c.semester_id = $1)
       AND ($2::int IS NULL OR c.teacher_id = $2)`,
    [filters.semesterId ?? null, filters.teacherId ?? null],
  );
  const studentSummaries = [];
  for (const row of students.rows) {
    const report = await studentReport(row.id, filters.semesterId);
    if (report) {
      studentSummaries.push({
        studentId: row.id,
        name: report.student.name,
        universityId: report.student.student_id,
        percent: report.overall.percent,
        grade: report.overall.grade,
        sessions: report.overall.sessions,
        attended: report.overall.attended,
      });
    }
  }
  const settings = await getSettings();
  return {
    classes,
    students: studentSummaries,
    lowAttendance: studentSummaries.filter((s) => s.percent < settings.low_threshold),
    settings,
  };
}

export async function liveRoster(classId: number) {
  const classRow = await query<{
    id: number;
    start_time: string;
    end_time: string;
    actual_start: string | null;
    ended_at: string | null;
    status: string;
    course_id: number;
    title: string | null;
  }>(`SELECT * FROM classes WHERE id = $1`, [classId]);
  const enrolled = await query(
    `SELECT s.id AS student_id, s.student_id AS university_id, u.name
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     JOIN users u ON u.id = s.user_id
     WHERE e.course_id = (SELECT course_id FROM classes WHERE id = $1)
     ORDER BY u.name`,
    [classId],
  );
  const now = Date.now();
  const rows = [];
  for (const person of enrolled.rows) {
    const segments = await listSegments(person.student_id as number, classId);
    const open = segments.find((s) => !s.left_at);
    const total = sumSegmentSeconds(segments, now);
    const current = open
      ? Math.max(0, Math.round((now - new Date(open.joined_at as string).getTime()) / 1000))
      : 0;
    const lastLeave = [...segments].reverse().find((s) => s.left_at)?.left_at ?? null;
    rows.push({
      studentId: person.student_id,
      name: person.name,
      universityId: person.university_id,
      online: Boolean(open),
      currentSeconds: current,
      totalSeconds: total,
      lastLeft: lastLeave,
      joins: segments.length,
    });
  }
  return { class: classRow.rows[0], roster: rows };
}

export async function sessionSummary(classId: number) {
  const live = await liveRoster(classId);
  const rules = await listGradeRules();
  const expected = live.class ? sessionExpectedSeconds(live.class) : 0;
  const students = live.roster.map((row) => {
    const percent = attendancePercent(row.totalSeconds, expected);
    return {
      ...row,
      percent,
      grade: gradeFor(percent, rules),
      absent: row.totalSeconds <= 0,
    };
  });
  const attended = students.filter((s) => !s.absent).length;
  const average = students.length
    ? Math.round((students.reduce((sum, s) => sum + s.percent, 0) / students.length) * 100) / 100
    : 0;
  return {
    class: live.class,
    expectedSeconds: expected,
    enrolled: students.length,
    attended,
    absent: students.length - attended,
    average,
    students,
  };
}

export async function analytics(filters: { semesterId?: number; teacherId?: number }) {
  const institution = await institutionReports(filters);
  const rules = await listGradeRules();
  const gradeDistribution = rules.map((rule) => ({
    grade: rule.grade,
    count: institution.students.filter((s) => s.grade === rule.grade).length,
  }));
  const bySubject = institution.classes.map((row) => ({
    label: `${row.course_code} ${row.course_name}`,
    value: row.average,
    students: row.students,
    sessions: row.sessions,
  }));
  const trend = await query<{ day: string; actual: number; expected: number }>(
    `SELECT to_char(date_trunc('day', cl.start_time), 'YYYY-MM-DD') AS day,
            COALESCE(SUM(a.duration_seconds), 0)::int AS actual,
            COALESCE(SUM(EXTRACT(EPOCH FROM (
              COALESCE(cl.ended_at, cl.end_time) - COALESCE(cl.actual_start, cl.start_time)
            ))), 0)::int AS expected
     FROM classes cl
     JOIN courses c ON c.id = cl.course_id
     LEFT JOIN attendance a ON a.class_id = cl.id
     WHERE cl.status <> 'scheduled'
       AND ($1::int IS NULL OR c.semester_id = $1)
       AND ($2::int IS NULL OR c.teacher_id = $2)
     GROUP BY 1
     ORDER BY 1`,
    [filters.semesterId ?? null, filters.teacherId ?? null],
  );
  const highs = [...institution.students].sort((a, b) => b.percent - a.percent).slice(0, 8);
  const lows = [...institution.students].sort((a, b) => a.percent - b.percent).slice(0, 8);
  return {
    ...institution,
    gradeDistribution,
    bySubject,
    trend: trend.rows.map((row) => ({
      day: row.day,
      percent: attendancePercent(Number(row.actual), Math.max(60, Number(row.expected))),
    })),
    highAttendance: highs,
    lowAttendance: institution.lowAttendance,
    lowestStudents: lows,
  };
}

export function toCsv(headers: string[], rows: Array<Array<string | number | null | undefined>>) {
  const escape = (value: string | number | null | undefined) => {
    const text = String(value ?? "");
    return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  return [headers.map(escape).join(","), ...rows.map((row) => row.map(escape).join(","))].join("\n");
}

export { listAttendance };
