-- Additive platform tables. Safe to run on an existing UniMeet database.

CREATE TABLE IF NOT EXISTS semesters (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL UNIQUE,
  academic_year TEXT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status TEXT NOT NULL DEFAULT 'active'
);

ALTER TABLE courses ADD COLUMN IF NOT EXISTS semester_id INT REFERENCES semesters(id) ON DELETE SET NULL;
ALTER TABLE courses ADD COLUMN IF NOT EXISTS academic_year TEXT;
ALTER TABLE courses ADD COLUMN IF NOT EXISTS status TEXT NOT NULL DEFAULT 'active';
ALTER TABLE courses ADD COLUMN IF NOT EXISTS max_students INT;
ALTER TABLE courses ADD COLUMN IF NOT EXISTS start_date DATE;
ALTER TABLE courses ADD COLUMN IF NOT EXISTS end_date DATE;

ALTER TABLE classes ADD COLUMN IF NOT EXISTS session_code TEXT;
ALTER TABLE classes ADD COLUMN IF NOT EXISTS actual_start TIMESTAMPTZ;
ALTER TABLE classes ADD COLUMN IF NOT EXISTS ended_at TIMESTAMPTZ;

CREATE UNIQUE INDEX IF NOT EXISTS idx_classes_session_code ON classes(session_code) WHERE session_code IS NOT NULL;

CREATE TABLE IF NOT EXISTS attendance_segments (
  id SERIAL PRIMARY KEY,
  attendance_id INT NOT NULL REFERENCES attendance(id) ON DELETE CASCADE,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  class_id INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  joined_at TIMESTAMPTZ NOT NULL,
  left_at TIMESTAMPTZ,
  duration_seconds INT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS attendance_events (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  class_id INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS attendance_grade_rules (
  id SERIAL PRIMARY KEY,
  min_percent NUMERIC NOT NULL,
  max_percent NUMERIC NOT NULL,
  grade TEXT NOT NULL,
  label TEXT,
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS attendance_settings (
  id INT PRIMARY KEY DEFAULT 1,
  low_threshold NUMERIC NOT NULL DEFAULT 75,
  late_join_minutes INT NOT NULL DEFAULT 10,
  early_leave_minutes INT NOT NULL DEFAULT 10
);

CREATE TABLE IF NOT EXISTS discussions (
  id SERIAL PRIMARY KEY,
  course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS discussion_replies (
  id SERIAL PRIMARY KEY,
  discussion_id INT NOT NULL REFERENCES discussions(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS classroom_messages (
  id SERIAL PRIMARY KEY,
  course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE SET NULL,
  body TEXT NOT NULL,
  kind TEXT NOT NULL DEFAULT 'message',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS classroom_presence (
  course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  last_seen TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (course_id, user_id)
);

CREATE TABLE IF NOT EXISTS announcements (
  id SERIAL PRIMARY KEY,
  course_id INT REFERENCES courses(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS notifications (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT,
  kind TEXT NOT NULL DEFAULT 'info',
  link TEXT,
  read_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_segments_student_class ON attendance_segments(student_id, class_id);
CREATE INDEX IF NOT EXISTS idx_events_class ON attendance_events(class_id, at);
CREATE INDEX IF NOT EXISTS idx_discussions_course ON discussions(course_id);
CREATE INDEX IF NOT EXISTS idx_classroom_messages_course ON classroom_messages(course_id, created_at);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_courses_semester ON courses(semester_id);
