import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { api } from "../api/client";
import { StatusBadge } from "../components/StatusBadge";
import { useAuth } from "../auth/AuthContext";
import { formatWhen } from "../lib/format";
import type { ClassSession, Course, Enrollment } from "../types";

type Tab = "sessions" | "roster" | "discussions" | "chat" | "announcements";

interface Discussion {
  id: number;
  title: string;
  body: string;
  author_name: string;
  created_at: string;
  reply_count: number;
}

interface ChatMessage {
  id: number;
  body: string;
  kind: string;
  author_name?: string;
  created_at: string;
  user_id: number | null;
}

interface Announcement {
  id: number;
  title: string;
  body: string;
  author_name: string;
  created_at: string;
}

export function CourseDetailPage() {
  const { id } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [course, setCourse] = useState<Course | null>(null);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [classes, setClasses] = useState<ClassSession[]>([]);
  const [tab, setTab] = useState<Tab>("sessions");
  const [discussions, setDiscussions] = useState<Discussion[]>([]);
  const [search, setSearch] = useState("");
  const [threadId, setThreadId] = useState<number | null>(null);
  const [thread, setThread] = useState<{ discussion: Discussion; replies: { id: number; body: string; author_name: string; created_at: string }[] } | null>(null);
  const [chat, setChat] = useState<ChatMessage[]>([]);
  const [online, setOnline] = useState<{ id: number; name: string }[]>([]);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [draft, setDraft] = useState({ title: "", body: "" });
  const [chatBody, setChatBody] = useState("");
  const [replyBody, setReplyBody] = useState("");
  const [starting, setStarting] = useState(false);

  const courseId = Number(id);

  const reloadCore = () => {
    if (!id) return;
    api<{ course: Course; enrollments: Enrollment[] }>(`/api/courses/${id}`).then((data) => {
      setCourse(data.course);
      setEnrollments(data.enrollments);
    });
    api<{ classes: ClassSession[] }>(`/api/classes?courseId=${id}`).then((data) => setClasses(data.classes));
  };

  useEffect(() => {
    reloadCore();
  }, [id]);

  useEffect(() => {
    if (!id) return;
    if (tab === "discussions") {
      api<{ discussions: Discussion[] }>(`/api/courses/${id}/discussions?q=${encodeURIComponent(search)}`).then((data) => setDiscussions(data.discussions));
    }
    if (tab === "announcements") {
      api<{ announcements: Announcement[] }>(`/api/announcements?courseId=${id}`).then((data) => setAnnouncements(data.announcements));
    }
  }, [id, tab, search]);

  useEffect(() => {
    if (!id || tab !== "chat") return;
    const tick = () =>
      api<{ messages: ChatMessage[]; online: { id: number; name: string }[] }>(`/api/courses/${id}/chat`).then((data) => {
        setChat(data.messages);
        setOnline(data.online);
      });
    tick();
    const timer = window.setInterval(tick, 2000);
    return () => window.clearInterval(timer);
  }, [id, tab]);

  useEffect(() => {
    if (!threadId) {
      setThread(null);
      return;
    }
    api<{ discussion: Discussion; replies: { id: number; body: string; author_name: string; created_at: string }[] }>(`/api/discussions/${threadId}`).then(setThread);
  }, [threadId]);

  if (!course) return <p className="muted">Loading course…</p>;

  const startMeeting = async () => {
    setStarting(true);
    const created = await api<{ class: ClassSession }>(`/api/courses/${courseId}/meetings`, {
      method: "POST",
      body: JSON.stringify({ title: `${course.course_name} live session` }),
    });
    navigate(`/classroom/${created.class.id}`);
  };

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">
            {course.program_code} · {course.semester_name ?? `Semester ${course.semester}`} · Section {course.section}
          </p>
          <h1>
            {course.course_code} {course.course_name}
          </h1>
        </div>
        <div className="row">
          {user?.role !== "student" ? (
            <>
              <Link className="btn" to={`/reports/courses/${course.id}`}>Class report</Link>
              <button className="btn btn-gold" type="button" disabled={starting} onClick={startMeeting}>
                Start meeting
              </button>
            </>
          ) : (
            <Link className="btn" to="/reports">My attendance</Link>
          )}
        </div>
      </div>
      <p className="page-lead">{course.description}</p>
      <p className="muted">{course.teacher_title} {course.teacher_name} · {course.enrolled_count ?? enrollments.length} students</p>
      <div className="tabs">
        {(["sessions", "roster", "discussions", "chat", "announcements"] as Tab[]).map((item) => (
          <button key={item} className={`btn ${tab === item ? "btn-primary" : ""}`} type="button" onClick={() => setTab(item)} style={{ width: "auto", marginTop: 0 }}>
            {item}
          </button>
        ))}
      </div>

      {tab === "sessions" ? (
        <section className="panel">
          <h2>Class sessions</h2>
          <table>
            <thead>
              <tr>
                <th>Session</th>
                <th>When</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {classes.map((item) => (
                <tr key={item.id}>
                  <td>
                    <strong>{item.session_code || item.title}</strong>
                    <div className="muted">{item.title}</div>
                  </td>
                  <td>{formatWhen(item.start_time)}</td>
                  <td><StatusBadge value={item.status} /></td>
                  <td>
                    {item.status !== "ended" && (user?.role !== "student" || item.status === "live" || item.is_open_lab) ? (
                      <Link className="btn btn-gold" to={`/classroom/${item.id}`}>
                        {item.status === "live" || item.is_open_lab ? "Join meeting" : "Open"}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      ) : null}

      {tab === "roster" ? (
        <section className="panel">
          <h2>Students</h2>
          {enrollments.map((row) => (
            <p key={row.id}>
              {user?.role === "student" ? row.name : <Link to={`/reports/students/${row.student_id}`}>{row.name}</Link>}
              {" · "}{row.university_student_id}
              <span className="muted"> · Section {row.section}</span>
            </p>
          ))}
          {enrollments.length === 0 ? <p className="empty">No students enrolled.</p> : null}
        </section>
      ) : null}

      {tab === "discussions" ? (
        <div className="grid two">
          <section className="panel">
            <h2>Discussion board</h2>
            <input placeholder="Search discussions" value={search} onChange={(e) => setSearch(e.target.value)} />
            {discussions.map((item) => (
              <button key={item.id} className="course-tile" type="button" onClick={() => setThreadId(item.id)} style={{ width: "100%", textAlign: "left" }}>
                <strong>{item.title}</strong>
                <div className="muted">{item.author_name} · {item.reply_count} replies · {formatWhen(item.created_at)}</div>
              </button>
            ))}
            <h3 className="serif">Start a discussion</h3>
            <label>Title<input value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} /></label>
            <label>Body<textarea rows={3} value={draft.body} onChange={(e) => setDraft({ ...draft, body: e.target.value })} /></label>
            <button
              className="btn btn-gold"
              type="button"
              onClick={async () => {
                await api(`/api/courses/${courseId}/discussions`, { method: "POST", body: JSON.stringify(draft) });
                setDraft({ title: "", body: "" });
                const data = await api<{ discussions: Discussion[] }>(`/api/courses/${courseId}/discussions`);
                setDiscussions(data.discussions);
              }}
            >
              Post
            </button>
          </section>
          <section className="panel">
            {!thread ? <p className="empty">Select a discussion.</p> : (
              <>
                <h2>{thread.discussion.title}</h2>
                <p>{thread.discussion.body}</p>
                <p className="muted">{thread.discussion.author_name} · {formatWhen(thread.discussion.created_at)}</p>
                {thread.replies.map((reply) => (
                  <article className="history-card" key={reply.id}>
                    <strong>{reply.author_name}</strong>
                    <p>{reply.body}</p>
                    <p className="muted">{formatWhen(reply.created_at)}</p>
                  </article>
                ))}
                <label>Reply<textarea rows={3} value={replyBody} onChange={(e) => setReplyBody(e.target.value)} /></label>
                <button
                  className="btn btn-primary"
                  type="button"
                  onClick={async () => {
                    await api(`/api/discussions/${thread.discussion.id}/replies`, { method: "POST", body: JSON.stringify({ body: replyBody }) });
                    setReplyBody("");
                    setThread(await api(`/api/discussions/${thread.discussion.id}`));
                  }}
                >
                  Reply
                </button>
              </>
            )}
          </section>
        </div>
      ) : null}

      {tab === "chat" ? (
        <section className="panel">
          <h2>Classroom chat</h2>
          <p className="muted">This room is separate from LiveKit meeting chat. Online now: {online.map((p) => p.name).join(", ") || "nobody"}</p>
          <div className="chat-log">
            {chat.map((item) => (
              <div className="chat-line" key={item.id}>
                <strong>{item.author_name ?? "System"}</strong>
                <span className="muted">{formatWhen(item.created_at)}</span>
                <p>{item.kind === "message" ? item.body : `${item.author_name ?? "Someone"} ${item.body}`}</p>
                {user && (user.role !== "student" || item.user_id === user.id) && item.kind === "message" ? (
                  <button className="btn" type="button" onClick={() => api(`/api/courses/${courseId}/chat/${item.id}`, { method: "DELETE" })}>
                    Delete
                  </button>
                ) : null}
              </div>
            ))}
          </div>
          <div className="row">
            <input value={chatBody} onChange={(e) => setChatBody(e.target.value)} placeholder="Message this classroom" />
            <button
              className="btn btn-gold"
              type="button"
              onClick={async () => {
                await api(`/api/courses/${courseId}/chat`, { method: "POST", body: JSON.stringify({ body: chatBody }) });
                setChatBody("");
              }}
            >
              Send
            </button>
          </div>
        </section>
      ) : null}

      {tab === "announcements" ? (
        <section className="panel">
          <h2>Announcements</h2>
          {announcements.map((item) => (
            <article className="history-card" key={item.id}>
              <strong>{item.title}</strong>
              <p>{item.body}</p>
              <p className="muted">{item.author_name} · {formatWhen(item.created_at)}</p>
            </article>
          ))}
          {user?.role !== "student" ? (
            <>
              <h3 className="serif">Post announcement</h3>
              <label>Title<input value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} /></label>
              <label>Body<textarea rows={3} value={draft.body} onChange={(e) => setDraft({ ...draft, body: e.target.value })} /></label>
              <button
                className="btn btn-gold"
                type="button"
                onClick={async () => {
                  await api("/api/announcements", { method: "POST", body: JSON.stringify({ ...draft, courseId }) });
                  setDraft({ title: "", body: "" });
                  const data = await api<{ announcements: Announcement[] }>(`/api/announcements?courseId=${courseId}`);
                  setAnnouncements(data.announcements);
                }}
              >
                Publish
              </button>
            </>
          ) : null}
        </section>
      ) : null}
    </>
  );
}
