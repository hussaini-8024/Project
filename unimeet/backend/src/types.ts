export type UserRole = "student" | "teacher" | "admin";
export type AttendanceStatus = "present" | "partial" | "insufficient" | "absent";
export type ClassStatus = "scheduled" | "live" | "ended";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  universityId: string;
}

export interface JwtPayload {
  sub: number;
  role: UserRole;
  universityId: string;
  name: string;
}

export interface ClassJoinGrant {
  canPublish: boolean;
  roomAdmin: boolean;
}
