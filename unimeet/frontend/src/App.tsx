import type { ReactNode } from "react";
import { Navigate, Route, Routes } from "react-router-dom";
import { useAuth } from "./auth/AuthContext";
import { AppShell } from "./components/AppShell";
import { AiStudioPage } from "./pages/AiStudio";
import { AnalyticsPage } from "./pages/Analytics";
import { AttendancePage } from "./pages/Attendance";
import { ClassroomPage } from "./pages/Classroom";
import { CourseDetailPage } from "./pages/CourseDetail";
import { CourseReportPage } from "./pages/CourseReport";
import { CoursesPage } from "./pages/Courses";
import { DashboardPage } from "./pages/Dashboard";
import { GradesAdminPage } from "./pages/GradesAdmin";
import { LoginPage } from "./pages/Login";
import { NotificationsPage } from "./pages/Notifications";
import { ProfilePage } from "./pages/Profile";
import { ReportsPage } from "./pages/Reports";
import { SemestersPage } from "./pages/Semesters";
import { StudentReportPage } from "./pages/StudentReport";
import type { Role } from "./types";

function Guard({ children, roles }: { children: ReactNode; roles?: Role[] }) {
  const { user, loading } = useAuth();
  if (loading) return <p className="muted" style={{ padding: 32 }}>Loading UniMeet…</p>;
  if (!user) return <Navigate to="/login" replace />;
  if (roles && !roles.includes(user.role)) return <Navigate to={`/${user.role}-dashboard`} replace />;
  return children;
}

function RoleDash({ role }: { role: Role }) {
  return (
    <Guard roles={[role]}>
      <DashboardPage />
    </Guard>
  );
}

export default function App() {
  const { user, loading } = useAuth();

  return (
    <Routes>
      <Route
        path="/login"
        element={user && !loading ? <Navigate to={`/${user.role}-dashboard`} replace /> : <LoginPage />}
      />
      <Route path="/classroom/:classId" element={<Guard><ClassroomPage /></Guard>} />
      <Route
        element={
          <Guard>
            <AppShell />
          </Guard>
        }
      >
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/student-dashboard" element={<RoleDash role="student" />} />
        <Route path="/teacher-dashboard" element={<RoleDash role="teacher" />} />
        <Route path="/admin-dashboard" element={<RoleDash role="admin" />} />
        <Route path="/courses" element={<CoursesPage />} />
        <Route path="/courses/:id" element={<CourseDetailPage />} />
        <Route path="/attendance" element={<AttendancePage />} />
        <Route path="/reports" element={<ReportsPage />} />
        <Route path="/reports/students/:studentId" element={<StudentReportPage />} />
        <Route path="/reports/courses/:courseId" element={<CourseReportPage />} />
        <Route path="/analytics" element={<Guard roles={["teacher", "admin"]}><AnalyticsPage /></Guard>} />
        <Route path="/notifications" element={<NotificationsPage />} />
        <Route path="/semesters" element={<Guard roles={["admin"]}><SemestersPage /></Guard>} />
        <Route path="/admin/grades" element={<Guard roles={["admin"]}><GradesAdminPage /></Guard>} />
        <Route path="/profile" element={<ProfilePage />} />
        <Route path="/ai" element={<AiStudioPage />} />
      </Route>
      <Route path="*" element={<Navigate to={user ? "/dashboard" : "/login"} replace />} />
    </Routes>
  );
}
