export type Role = "student" | "teacher" | "admin";

export interface StudentProfile {
  id: number;
  student_id: string;
  semester: number;
  section: string;
}

export interface TeacherProfile {
  id: number;
  teacher_id: string;
  title: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  universityId: string;
  student?: StudentProfile | null;
  teacher?: TeacherProfile | null;
}

export interface Course {
  id: number;
  course_code: string;
  course_name: string;
  teacher_name?: string;
  teacher_title?: string;
  program_code?: string;
  department_code?: string;
  semester?: number;
  section?: string;
  enrolled_count?: number;
  description?: string;
  teacher_id?: number;
}

export interface ClassSession {
  id: number;
  course_id: number;
  title: string | null;
  start_time: string;
  end_time: string;
  room_name: string;
  status: "scheduled" | "live" | "ended";
  is_open_lab: boolean;
  course_code?: string;
  course_name?: string;
  teacher_name?: string | null;
}

export interface AttendanceRow {
  id: number;
  student_id: number;
  class_id: number;
  join_time: string | null;
  leave_time: string | null;
  duration_seconds: number;
  status: "present" | "partial" | "insufficient" | "absent" | null;
  student_name?: string;
  university_student_id?: string;
  course_code?: string;
  course_name?: string;
  class_title?: string;
}

export interface Enrollment {
  id: number;
  student_id: number;
  university_student_id: string;
  name: string;
  email: string;
  semester: number;
  section: string;
}

export type QualityTier = "excellent" | "good" | "poor" | "very_poor" | "critical";

export interface QualityAction {
  video: boolean;
  width: number;
  height: number;
  fps: number;
  bitrate: number;
  label: string;
}

export interface NetworkSnapshot {
  latencyMs: number;
  jitterMs: number;
  packetLoss: number;
  bitrateKbps: number;
  fps: number;
  width: number;
  height: number;
}
