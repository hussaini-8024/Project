import { Router } from "express";
import { requireAuth, requireRole } from "../middleware/auth.js";
import {
  askHandler,
  attemptHandler,
  quizHandler,
  saveTranscriptHandler,
  summarizeHandler,
  transcriptHandler,
} from "../controllers/aiController.js";
import {
  joinAttendanceHandler,
  leaveAttendanceHandler,
  listAttendanceHandler,
} from "../controllers/attendanceController.js";
import { loginHandler, logoutHandler, meHandler } from "../controllers/authController.js";
import {
  createClassHandler,
  endClassHandler,
  getClassHandler,
  listClassesHandler,
  startClassHandler,
  startCourseMeetingHandler,
} from "../controllers/classController.js";
import {
  availableCoursesHandler,
  catalogHandler,
  createCourseHandler,
  enrollHandler,
  getCourseHandler,
  listCoursesHandler,
  selfEnrollHandler,
  unenrollHandler,
} from "../controllers/courseController.js";
import { tokenHandler } from "../controllers/livekitController.js";
import {
  createAnnouncementHandler,
  createDiscussionHandler,
  createReplyHandler,
  createSemesterHandler,
  deleteChatHandler,
  deleteDiscussionHandler,
  deleteReplyHandler,
  getDiscussionHandler,
  listAnnouncementsHandler,
  listChatHandler,
  listDiscussionsHandler,
  listNotificationsHandler,
  listSemestersHandler,
  postChatHandler,
  presenceHandler,
  readAllNotificationsHandler,
  readNotificationHandler,
  updateDiscussionHandler,
  updateReplyHandler,
  updateSemesterHandler,
} from "../controllers/platformController.js";
import {
  adminStatsHandler,
  dashboardHandler,
  networkSampleHandler,
  updateProfileHandler,
} from "../controllers/profileController.js";
import {
  analyticsHandler,
  courseReportHandler,
  exportReportHandler,
  gradeRulesHandler,
  institutionReportHandler,
  liveAttendanceHandler,
  myReportHandler,
  saveGradeRulesHandler,
  sessionSummaryHandler,
  studentReportHandler,
} from "../controllers/reportController.js";

export const router = Router();

router.get("/health", (_req, res) => {
  res.json({ ok: true, service: "unimeet" });
});

router.post("/auth/login", loginHandler);
router.post("/auth/logout", logoutHandler);
router.get("/auth/me", requireAuth, meHandler);

router.get("/dashboard", requireAuth, dashboardHandler);
router.get("/profile", requireAuth, meHandler);
router.put("/profile", requireAuth, updateProfileHandler);

router.get("/catalog", requireAuth, catalogHandler);
router.get("/semesters", requireAuth, listSemestersHandler);
router.post("/semesters", requireAuth, requireRole("admin"), createSemesterHandler);
router.put("/semesters/:id", requireAuth, requireRole("admin"), updateSemesterHandler);

router.get("/courses", requireAuth, listCoursesHandler);
router.get("/courses/available", requireAuth, availableCoursesHandler);
router.get("/courses/:id", requireAuth, getCourseHandler);
router.post("/courses", requireAuth, requireRole("admin", "teacher"), createCourseHandler);
router.post("/courses/:id/meetings", requireAuth, requireRole("admin", "teacher"), startCourseMeetingHandler);
router.get("/courses/:id/discussions", requireAuth, listDiscussionsHandler);
router.post("/courses/:id/discussions", requireAuth, createDiscussionHandler);
router.get("/courses/:id/chat", requireAuth, listChatHandler);
router.post("/courses/:id/chat", requireAuth, postChatHandler);
router.delete("/courses/:id/chat/:messageId", requireAuth, deleteChatHandler);
router.post("/courses/:id/presence", requireAuth, presenceHandler);

router.get("/discussions/:id", requireAuth, getDiscussionHandler);
router.put("/discussions/:id", requireAuth, updateDiscussionHandler);
router.delete("/discussions/:id", requireAuth, deleteDiscussionHandler);
router.post("/discussions/:id/replies", requireAuth, createReplyHandler);
router.put("/replies/:id", requireAuth, updateReplyHandler);
router.delete("/replies/:id", requireAuth, deleteReplyHandler);

router.get("/announcements", requireAuth, listAnnouncementsHandler);
router.post("/announcements", requireAuth, requireRole("admin", "teacher"), createAnnouncementHandler);

router.get("/notifications", requireAuth, listNotificationsHandler);
router.post("/notifications/read-all", requireAuth, readAllNotificationsHandler);
router.post("/notifications/:id/read", requireAuth, readNotificationHandler);

router.post("/enrollments", requireAuth, requireRole("admin", "teacher"), enrollHandler);
router.post("/enrollments/self", requireAuth, requireRole("student"), selfEnrollHandler);
router.delete("/enrollments/:id", requireAuth, requireRole("admin"), unenrollHandler);

router.get("/classes", requireAuth, listClassesHandler);
router.get("/classes/:id", requireAuth, getClassHandler);
router.post("/classes", requireAuth, requireRole("admin", "teacher"), createClassHandler);
router.post("/classes/:id/start", requireAuth, requireRole("admin", "teacher"), startClassHandler);
router.post("/classes/:id/end", requireAuth, requireRole("admin", "teacher"), endClassHandler);
router.get("/classes/:id/live", requireAuth, liveAttendanceHandler);
router.get("/classes/:id/summary", requireAuth, sessionSummaryHandler);

router.post("/livekit/token", requireAuth, tokenHandler);

router.get("/attendance", requireAuth, listAttendanceHandler);
router.post("/attendance/join", requireAuth, joinAttendanceHandler);
router.post("/attendance/leave", requireAuth, leaveAttendanceHandler);
router.get("/attendance/grades", requireAuth, gradeRulesHandler);
router.put("/attendance/grades", requireAuth, requireRole("admin"), saveGradeRulesHandler);

router.get("/reports/me", requireAuth, requireRole("student"), myReportHandler);
router.get("/reports/students/:id", requireAuth, studentReportHandler);
router.get("/reports/courses/:id", requireAuth, courseReportHandler);
router.get("/reports/institution", requireAuth, requireRole("admin", "teacher"), institutionReportHandler);
router.get("/reports/analytics", requireAuth, requireRole("admin", "teacher"), analyticsHandler);
router.get("/reports/export", requireAuth, exportReportHandler);

router.get("/ai/transcript/:classId", requireAuth, transcriptHandler);
router.post("/ai/transcript", requireAuth, requireRole("teacher", "admin"), saveTranscriptHandler);
router.post("/ai/summarize", requireAuth, summarizeHandler);
router.post("/ai/quiz", requireAuth, quizHandler);
router.post("/ai/quiz/attempt", requireAuth, attemptHandler);
router.post("/ai/ask", requireAuth, askHandler);

router.post("/network/sample", requireAuth, networkSampleHandler);
router.get("/admin/stats", requireAuth, requireRole("admin"), adminStatsHandler);
