import type { Request, Response } from "express";
import { z } from "zod";
import { query } from "../db/pool.js";
import { getStudentByUserId } from "../models/userModel.js";
import {
  askLecture,
  loadClassCorpus,
  quizFromLecture,
  summarizeLecture,
} from "../services/aiService.js";
import { HttpError } from "../utils/httpError.js";

const classSchema = z.object({
  classId: z.number(),
});

export async function transcriptHandler(req: Request, res: Response) {
  const classId = Number(req.params.classId);
  const data = await loadClassCorpus(classId);
  res.json(data);
}

export async function saveTranscriptHandler(req: Request, res: Response) {
  const body = z
    .object({ classId: z.number(), transcript: z.string().min(20) })
    .parse(req.body);
  const result = await query(
    `INSERT INTO lecture_transcripts (class_id, transcript)
     VALUES ($1, $2)
     ON CONFLICT (class_id) DO UPDATE SET transcript = $2, updated_at = now()
     RETURNING *`,
    [body.classId, body.transcript],
  );
  res.json({ transcript: result.rows[0] });
}

export async function summarizeHandler(req: Request, res: Response) {
  const { classId } = classSchema.parse(req.body);
  const data = await loadClassCorpus(classId);
  if (!data.corpus) throw new HttpError(404, "No lecture transcript or notes yet.", "no_transcript");
  const result = await summarizeLecture(data.corpus);
  await query(
    `INSERT INTO lecture_transcripts (class_id, transcript, summary)
     VALUES ($1, $2, $3)
     ON CONFLICT (class_id) DO UPDATE SET summary = $3, updated_at = now()`,
    [classId, data.transcript || data.corpus, result.summary],
  );
  res.json(result);
}

export async function quizHandler(req: Request, res: Response) {
  const { classId } = classSchema.parse(req.body);
  const data = await loadClassCorpus(classId);
  if (!data.corpus) throw new HttpError(404, "No lecture transcript or notes yet.", "no_transcript");
  const quiz = await quizFromLecture(data.corpus, "Lecture quiz");
  const created = await query<{ id: number }>(
    `INSERT INTO quizzes (class_id, course_id, title)
     VALUES ($1, (SELECT course_id FROM classes WHERE id = $1), $2)
     RETURNING id`,
    [classId, quiz.title],
  );
  const questions = [];
  for (const item of quiz.questions) {
    const row = await query<{ id: number }>(
      `INSERT INTO quiz_questions (quiz_id, prompt, choices, correct_index)
       VALUES ($1, $2, $3::jsonb, $4) RETURNING id`,
      [created.rows[0].id, item.prompt, JSON.stringify(item.choices), item.correctIndex],
    );
    questions.push({
      id: row.rows[0].id,
      prompt: item.prompt,
      choices: item.choices,
    });
  }
  res.json({
    quizId: created.rows[0].id,
    title: quiz.title,
    engine: quiz.engine,
    questions,
  });
}

export async function attemptHandler(req: Request, res: Response) {
  const body = z
    .object({
      quizId: z.number(),
      answers: z.array(z.number()),
    })
    .parse(req.body);
  if (req.user!.role !== "student") {
    throw new HttpError(403, "Only students can submit quizzes.", "not_student");
  }
  const student = await getStudentByUserId(req.user!.id);
  if (!student) throw new HttpError(403, "Student profile not found.", "not_student");
  const questions = await query<{ id: number; correct_index: number }>(
    `SELECT id, correct_index FROM quiz_questions WHERE quiz_id = $1 ORDER BY id`,
    [body.quizId],
  );
  let correct = 0;
  questions.rows.forEach((q, index) => {
    if (body.answers[index] === q.correct_index) correct += 1;
  });
  const score = questions.rows.length
    ? Math.round((correct / questions.rows.length) * 100)
    : 0;
  const attempt = await query(
    `INSERT INTO quiz_attempts (quiz_id, student_id, answers, score)
     VALUES ($1, $2, $3::jsonb, $4) RETURNING *`,
    [body.quizId, student.id, JSON.stringify(body.answers), score],
  );
  res.json({
    attempt: attempt.rows[0],
    score,
    correct,
    total: questions.rows.length,
  });
}

export async function askHandler(req: Request, res: Response) {
  const body = z
    .object({ classId: z.number(), question: z.string().min(3) })
    .parse(req.body);
  const data = await loadClassCorpus(body.classId);
  if (!data.corpus) throw new HttpError(404, "No lecture transcript or notes yet.", "no_transcript");
  const result = await askLecture(body.question, data.corpus);
  res.json(result);
}
