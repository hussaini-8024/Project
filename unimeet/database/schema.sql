CREATE EXTENSION IF NOT EXISTS citext;

DO $$ BEGIN
  CREATE TYPE user_role AS ENUM ('student', 'teacher', 'admin');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  CREATE TYPE attendance_status AS ENUM ('present', 'partial', 'insufficient', 'absent');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  CREATE TYPE class_status AS ENUM ('scheduled', 'live', 'ended');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS departments (
  id SERIAL PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS programs (
  id SERIAL PRIMARY KEY,
  department_id INT REFERENCES departments(id) ON DELETE SET NULL,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  email CITEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role user_role NOT NULL,
  university_id TEXT NOT NULL UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS students (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  student_id TEXT NOT NULL UNIQUE,
  department_id INT REFERENCES departments(id) ON DELETE SET NULL,
  program_id INT REFERENCES programs(id) ON DELETE SET NULL,
  semester INT NOT NULL,
  section TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS teachers (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  teacher_id TEXT NOT NULL UNIQUE,
  department_id INT REFERENCES departments(id) ON DELETE SET NULL,
  title TEXT NOT NULL DEFAULT 'Lecturer'
);

CREATE TABLE IF NOT EXISTS courses (
  id SERIAL PRIMARY KEY,
  course_code TEXT NOT NULL UNIQUE,
  course_name TEXT NOT NULL,
  teacher_id INT REFERENCES teachers(id) ON DELETE SET NULL,
  program_id INT REFERENCES programs(id) ON DELETE SET NULL,
  department_id INT REFERENCES departments(id) ON DELETE SET NULL,
  semester INT,
  section TEXT,
  credit_hours INT NOT NULL DEFAULT 3,
  description TEXT
);

CREATE TABLE IF NOT EXISTS enrollments (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  enrolled_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (student_id, course_id)
);

CREATE TABLE IF NOT EXISTS classes (
  id SERIAL PRIMARY KEY,
  course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT,
  start_time TIMESTAMPTZ NOT NULL,
  end_time TIMESTAMPTZ NOT NULL,
  room_name TEXT NOT NULL UNIQUE,
  status class_status NOT NULL DEFAULT 'scheduled',
  is_open_lab BOOLEAN NOT NULL DEFAULT false,
  created_by INT REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  class_id INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  join_time TIMESTAMPTZ,
  last_join_time TIMESTAMPTZ,
  leave_time TIMESTAMPTZ,
  duration_seconds INT NOT NULL DEFAULT 0,
  status attendance_status,
  UNIQUE (student_id, class_id)
);

CREATE TABLE IF NOT EXISTS messages (
  id SERIAL PRIMARY KEY,
  class_id INT REFERENCES classes(id) ON DELETE CASCADE,
  user_id INT REFERENCES users(id) ON DELETE SET NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS recordings (
  id SERIAL PRIMARY KEY,
  class_id INT REFERENCES classes(id) ON DELETE CASCADE,
  title TEXT,
  url TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lecture_materials (
  id SERIAL PRIMARY KEY,
  course_id INT REFERENCES courses(id) ON DELETE CASCADE,
  class_id INT REFERENCES classes(id) ON DELETE SET NULL,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lecture_transcripts (
  id SERIAL PRIMARY KEY,
  class_id INT NOT NULL UNIQUE REFERENCES classes(id) ON DELETE CASCADE,
  transcript TEXT NOT NULL,
  summary TEXT,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS quizzes (
  id SERIAL PRIMARY KEY,
  class_id INT REFERENCES classes(id) ON DELETE CASCADE,
  course_id INT REFERENCES courses(id) ON DELETE SET NULL,
  title TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS quiz_questions (
  id SERIAL PRIMARY KEY,
  quiz_id INT NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  prompt TEXT NOT NULL,
  choices JSONB NOT NULL,
  correct_index INT NOT NULL
);

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id SERIAL PRIMARY KEY,
  quiz_id INT NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  answers JSONB NOT NULL,
  score INT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS network_samples (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE SET NULL,
  class_id INT REFERENCES classes(id) ON DELETE SET NULL,
  bandwidth_kbps NUMERIC,
  packet_loss NUMERIC,
  latency_ms NUMERIC,
  jitter_ms NUMERIC,
  fps NUMERIC,
  bitrate_kbps NUMERIC,
  resolution TEXT,
  quality_tier TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_enrollments_course ON enrollments(course_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_student ON enrollments(student_id);
CREATE INDEX IF NOT EXISTS idx_classes_course ON classes(course_id);
CREATE INDEX IF NOT EXISTS idx_attendance_student ON attendance(student_id);
CREATE INDEX IF NOT EXISTS idx_attendance_class ON attendance(class_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
