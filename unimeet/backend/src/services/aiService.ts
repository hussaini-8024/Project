import { env } from "../config/env.js";
import { query } from "../db/pool.js";

function sentences(text: string) {
  return text
    .replace(/\s+/g, " ")
    .split(/(?<=[.!?])\s+/)
    .map((s) => s.trim())
    .filter((s) => s.length > 40);
}

function tokenize(text: string) {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, " ")
    .split(/\s+/)
    .filter((w) => w.length > 3 && !STOP.has(w));
}

const STOP = new Set([
  "that", "this", "with", "from", "have", "were", "been", "they", "their",
  "which", "into", "also", "about", "when", "your", "will", "each", "than",
  "then", "them", "some", "only", "other", "more", "such", "using", "used",
]);

export function extractiveSummary(text: string, maxSentences = 5) {
  const parts = sentences(text);
  if (parts.length === 0) return text.slice(0, 400);
  const freq = new Map<string, number>();
  for (const word of tokenize(text)) freq.set(word, (freq.get(word) ?? 0) + 1);
  const scored = parts.map((sentence, index) => {
    const score = tokenize(sentence).reduce((sum, word) => sum + (freq.get(word) ?? 0), 0);
    return { sentence, index, score };
  });
  return scored
    .sort((a, b) => b.score - a.score)
    .slice(0, maxSentences)
    .sort((a, b) => a.index - b.index)
    .map((s) => s.sentence)
    .join(" ");
}

export function generateLocalQuiz(text: string, title: string) {
  const definitions: { term: string; sentence: string }[] = [];
  for (const sentence of sentences(text)) {
    const match = sentence.match(/\b([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)*)\b[^.]*\bis\b([^.]+)/);
    if (match) definitions.push({ term: match[1], sentence });
    const named = sentence.match(/\b(ACID|DBMS|normalization|transaction|index|foreign key|primary key|Third Normal Form|First Normal Form)\b/i);
    if (named) definitions.push({ term: named[1], sentence });
  }

  const unique = new Map<string, string>();
  for (const item of definitions) {
    const key = item.term.toLowerCase();
    if (!unique.has(key)) unique.set(key, item.sentence);
  }

  const entries = [...unique.entries()].slice(0, 6);
  const terms = entries.map(([term]) => term);

  const questions = entries.slice(0, 5).map(([term, sentence], index) => {
    const distractors = terms.filter((t) => t !== term).slice(0, 3);
    while (distractors.length < 3) distractors.push(["query plan", "lock table", "view"][distractors.length]);
    const choices = shuffle([
      `It relates to ${term} as used in this lecture.`,
      ...distractors.map((d) => `It is primarily about ${d}.`),
    ]);
    const correct = choices.findIndex((c) => c.includes(term));
    return {
      prompt: `From today's lecture, which statement best matches: “${clip(sentence, 140)}”?`,
      choices: choices.length ? choices : [`${term}`, ...distractors],
      correctIndex: correct >= 0 ? correct : 0,
      source: term,
      order: index,
    };
  });

  if (questions.length < 3) {
    questions.push(
      {
        prompt: "Which property set describes a reliable database transaction?",
        choices: ["ACID", "REST", "CRUD", "SOLID"],
        correctIndex: 0,
        source: "ACID",
        order: 99,
      },
      {
        prompt: "Why does UniMeet store department names in a departments table instead of repeating them on every enrollment?",
        choices: [
          "To keep data in Third Normal Form and avoid update anomalies",
          "Because PostgreSQL cannot store repeated text",
          "To make LiveKit tokens shorter",
          "To hide the department from students",
        ],
        correctIndex: 0,
        source: "normalization",
        order: 100,
      },
      {
        prompt: "Where must the JOIN CLASS enrollment check run?",
        choices: [
          "On the UniMeet backend before a LiveKit token is issued",
          "Only in React so the page looks locked",
          "Inside the student's browser cache",
          "After the student is already in the video room",
        ],
        correctIndex: 0,
        source: "authorization",
        order: 101,
      },
    );
  }

  return {
    title: title || "Lecture quiz",
    questions: questions.slice(0, 5),
  };
}

export function answerFromCorpus(question: string, corpus: string) {
  const qWords = new Set(tokenize(question));
  const ranked = sentences(corpus)
    .map((sentence) => ({
      sentence,
      score: tokenize(sentence).reduce((sum, word) => sum + (qWords.has(word) ? 2 : 0), 0),
    }))
    .sort((a, b) => b.score - a.score);

  const top = ranked.filter((r) => r.score > 0).slice(0, 3);
  if (top.length === 0) {
    return {
      answer:
        "I could not find that in today's lecture transcript or notes. Try asking about transactions, normalization, indexes, or the JOIN CLASS authorization path.",
      citations: [] as string[],
    };
  }
  return {
    answer: top.map((t) => t.sentence).join(" "),
    citations: top.map((t) => t.sentence),
  };
}

async function openaiComplete(system: string, user: string) {
  if (!env.OPENAI_API_KEY) return null;
  const response = await fetch("https://api.openai.com/v1/chat/completions", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${env.OPENAI_API_KEY}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model: "gpt-4o-mini",
      temperature: 0.3,
      messages: [
        { role: "system", content: system },
        { role: "user", content: user },
      ],
    }),
  });
  if (!response.ok) return null;
  const data = (await response.json()) as {
    choices?: { message?: { content?: string } }[];
  };
  return data.choices?.[0]?.message?.content ?? null;
}

export async function summarizeLecture(text: string) {
  const remote = await openaiComplete(
    "You are a university teaching assistant. Summarize the lecture in 6-8 clear sentences for BSCS students.",
    text,
  );
  return {
    summary: remote ?? extractiveSummary(text, 6),
    engine: remote ? "openai" : "local",
  };
}

export async function quizFromLecture(text: string, title: string) {
  const remote = await openaiComplete(
    'Return JSON {"questions":[{"prompt":"...","choices":["a","b","c","d"],"correctIndex":0}]} with exactly 5 MCQs from the lecture.',
    text,
  );
  if (remote) {
    try {
      const parsed = JSON.parse(remote) as {
        questions: { prompt: string; choices: string[]; correctIndex: number }[];
      };
      if (parsed.questions?.length) {
        return { title, questions: parsed.questions.slice(0, 5), engine: "openai" as const };
      }
    } catch {
      // fall back to local
    }
  }
  return { ...generateLocalQuiz(text, title), engine: "local" as const };
}

export async function askLecture(question: string, corpus: string) {
  const remote = await openaiComplete(
    "Answer using only the provided lecture transcript and notes. If the answer is not there, say so. Cite short quotes.",
    `NOTES AND TRANSCRIPT:\n${corpus}\n\nQUESTION:\n${question}`,
  );
  if (remote) return { answer: remote, citations: [], engine: "openai" as const };
  return { ...answerFromCorpus(question, corpus), engine: "local" as const };
}

export async function loadClassCorpus(classId: number) {
  const transcript = await query<{ transcript: string; summary: string | null }>(
    `SELECT transcript, summary FROM lecture_transcripts WHERE class_id = $1`,
    [classId],
  );
  const materials = await query<{ title: string; body: string }>(
    `SELECT title, body FROM lecture_materials
     WHERE class_id = $1 OR course_id = (SELECT course_id FROM classes WHERE id = $1)`,
    [classId],
  );
  const parts = [
    transcript.rows[0]?.transcript ?? "",
    ...materials.rows.map((m) => `${m.title}\n${m.body}`),
  ].filter(Boolean);
  return {
    transcript: transcript.rows[0]?.transcript ?? "",
    summary: transcript.rows[0]?.summary ?? null,
    materials: materials.rows,
    corpus: parts.join("\n\n"),
  };
}

function clip(text: string, size: number) {
  return text.length <= size ? text : `${text.slice(0, size).trim()}…`;
}

function shuffle<T>(items: T[]) {
  const copy = [...items];
  for (let i = copy.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}
