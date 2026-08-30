import { useEffect, useState } from "react";
import { api } from "../api/client";
import { useAuth } from "../auth/AuthContext";
import type { ClassSession } from "../types";

interface QuizQuestion {
  id: number;
  prompt: string;
  choices: string[];
}

export function AiStudioPage() {
  const { user } = useAuth();
  const [classes, setClasses] = useState<ClassSession[]>([]);
  const [classId, setClassId] = useState<number>(0);
  const [summary, setSummary] = useState("");
  const [engine, setEngine] = useState("");
  const [question, setQuestion] = useState("Explain today's lecture.");
  const [answer, setAnswer] = useState("");
  const [quiz, setQuiz] = useState<{ quizId: number; questions: QuizQuestion[] } | null>(null);
  const [answers, setAnswers] = useState<number[]>([]);
  const [score, setScore] = useState<string>("");
  const [busy, setBusy] = useState("");

  useEffect(() => {
    api<{ classes: ClassSession[] }>("/api/classes").then((data) => {
      setClasses(data.classes);
      const live = data.classes.find((c) => c.status === "live") ?? data.classes[0];
      if (live) setClassId(live.id);
    });
  }, []);

  const run = async (label: string, work: () => Promise<void>) => {
    setBusy(label);
    try {
      await work();
    } finally {
      setBusy("");
    }
  };

  return (
    <>
      <div className="topbar">
        <div>
          <p className="muted">Added last — after classroom, attendance, and Smart 720p</p>
          <h1>AI lecture studio</h1>
        </div>
      </div>
      <p className="muted">
        Works from the lecture transcript and notes. If you add an OpenAI key later, the same buttons
        use that engine. Until then, UniMeet answers from the local teaching corpus.
      </p>
      <label style={{ maxWidth: 420 }}>
        Lecture
        <select value={classId} onChange={(e) => setClassId(Number(e.target.value))}>
          {classes.map((item) => (
            <option key={item.id} value={item.id}>
              {item.course_code} · {item.title}
            </option>
          ))}
        </select>
      </label>

      <div className="grid two" style={{ marginTop: 18 }}>
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Lecture summary</h2>
          <button
            className="btn btn-gold"
            disabled={!!busy}
            onClick={() =>
              run("summary", async () => {
                const data = await api<{ summary: string; engine: string }>("/api/ai/summarize", {
                  method: "POST",
                  body: JSON.stringify({ classId }),
                });
                setSummary(data.summary);
                setEngine(data.engine);
              })
            }
          >
            {busy === "summary" ? "Summarizing…" : "Generate summary"}
          </button>
          {engine ? <p className="muted">Engine: {engine}</p> : null}
          <p>{summary}</p>
        </section>
        <section className="panel" style={{ padding: 18 }}>
          <h2 className="serif">Study assistant</h2>
          <textarea rows={3} value={question} onChange={(e) => setQuestion(e.target.value)} />
          <button
            className="btn btn-primary"
            style={{ width: "auto", marginTop: 10 }}
            disabled={!!busy}
            onClick={() =>
              run("ask", async () => {
                const data = await api<{ answer: string; engine: string }>("/api/ai/ask", {
                  method: "POST",
                  body: JSON.stringify({ classId, question }),
                });
                setAnswer(data.answer);
                setEngine(data.engine);
              })
            }
          >
            Ask
          </button>
          <p>{answer}</p>
        </section>
      </div>

      <section className="panel" style={{ padding: 18, marginTop: 18 }}>
        <h2 className="serif">AI quiz</h2>
        <div className="row">
          <button
            className="btn btn-gold"
            disabled={!!busy}
            onClick={() =>
              run("quiz", async () => {
                const data = await api<{ quizId: number; questions: QuizQuestion[] }>("/api/ai/quiz", {
                  method: "POST",
                  body: JSON.stringify({ classId }),
                });
                setQuiz(data);
                setAnswers(data.questions.map(() => -1));
                setScore("");
              })
            }
          >
            {busy === "quiz" ? "Writing questions…" : "Generate MCQs"}
          </button>
          {user?.role === "student" && quiz ? (
            <button
              className="btn"
              onClick={() =>
                run("score", async () => {
                  const data = await api<{ score: number; correct: number; total: number }>(
                    "/api/ai/quiz/attempt",
                    {
                      method: "POST",
                      body: JSON.stringify({ quizId: quiz.quizId, answers }),
                    },
                  );
                  setScore(`${data.score}% · ${data.correct}/${data.total} correct`);
                })
              }
            >
              Submit answers
            </button>
          ) : null}
        </div>
        {quiz?.questions.map((q, index) => (
          <div key={q.id} style={{ marginTop: 16 }}>
            <strong>
              {index + 1}. {q.prompt}
            </strong>
            {q.choices.map((choice, choiceIndex) => (
              <label key={choice} className="check" style={{ marginTop: 8 }}>
                <input
                  type="radio"
                  name={`q-${q.id}`}
                  checked={answers[index] === choiceIndex}
                  onChange={() => {
                    const next = [...answers];
                    next[index] = choiceIndex;
                    setAnswers(next);
                  }}
                />
                {choice}
              </label>
            ))}
          </div>
        ))}
        {score ? <p>{score}</p> : null}
      </section>
    </>
  );
}
