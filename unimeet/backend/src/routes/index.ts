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
} from "../controllers/classController.js";
import {
  catalogHandler,
  createCourseHandler,
  enrollHandler,
  getCourseHandler,
  listCoursesHandler,
  unenrollHandler,
} from "../controllers/courseController.js";
import { tokenHandler } from "../controllers/livekitController.js";
import {
  adminStatsHandler,
  dashboardHandler,
  networkSampleHandler,
  updateProfileHandler,
} from "../controllers/profileController.js";

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
router.get("/courses", requireAuth, listCoursesHandler);
router.get("/courses/:id", requireAuth, getCourseHandler);
router.post("/courses", requireAuth, requireRole("admin"), createCourseHandler);
router.post("/enrollments", requireAuth, requireRole("admin"), enrollHandler);
router.delete("/enrollments/:id", requireAuth, requireRole("admin"), unenrollHandler);

router.get("/classes", requireAuth, listClassesHandler);
router.get("/classes/:id", requireAuth, getClassHandler);
router.post("/classes", requireAuth, requireRole("admin", "teacher"), createClassHandler);
router.post("/classes/:id/start", requireAuth, requireRole("admin", "teacher"), startClassHandler);
router.post("/classes/:id/end", requireAuth, requireRole("admin", "teacher"), endClassHandler);

router.post("/livekit/token", requireAuth, tokenHandler);

router.get("/attendance", requireAuth, listAttendanceHandler);
router.post("/attendance/join", requireAuth, joinAttendanceHandler);
router.post("/attendance/leave", requireAuth, leaveAttendanceHandler);

router.get("/ai/transcript/:classId", requireAuth, transcriptHandler);
router.post("/ai/transcript", requireAuth, requireRole("teacher", "admin"), saveTranscriptHandler);
router.post("/ai/summarize", requireAuth, summarizeHandler);
router.post("/ai/quiz", requireAuth, quizHandler);
router.post("/ai/quiz/attempt", requireAuth, attemptHandler);
router.post("/ai/ask", requireAuth, askHandler);

router.post("/network/sample", requireAuth, networkSampleHandler);
router.get("/admin/stats", requireAuth, requireRole("admin"), adminStatsHandler);
