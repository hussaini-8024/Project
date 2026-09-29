import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { formatWhen } from "../lib/format";

interface Note {
  id: number;
  title: string;
  body: string;
  kind: string;
  link: string | null;
  read_at: string | null;
  created_at: string;
}

export function NotificationsPage() {
  const [items, setItems] = useState<Note[]>([]);
  const [unread, setUnread] = useState(0);

  const load = () =>
    api<{ notifications: Note[]; unread: number }>("/api/notifications").then((data) => {
      setItems(data.notifications);
      setUnread(data.unread);
    });

  useEffect(() => {
    load();
  }, []);

  return (
    <>
      <div className="topbar">
        <div>
          <p className="eyebrow">Inbox</p>
          <h1>Notifications</h1>
        </div>
        <button className="btn" type="button" onClick={() => api("/api/notifications/read-all", { method: "POST" }).then(load)}>
          Mark all read
        </button>
      </div>
      <p className="page-lead muted">{unread} unread</p>
      <section className="panel">
        {items.length === 0 ? <p className="empty">No notifications yet.</p> : null}
        {items.map((item) => (
          <article className="course-tile" key={item.id}>
            <div className="row" style={{ justifyContent: "space-between" }}>
              <strong>{item.title}</strong>
              {!item.read_at ? (
                <button className="btn" type="button" onClick={() => api(`/api/notifications/${item.id}/read`, { method: "POST" }).then(load)}>
                  Mark read
                </button>
              ) : (
                <span className="muted">Read</span>
              )}
            </div>
            <p className="muted">{item.body}</p>
            <p className="muted">{formatWhen(item.created_at)} · {item.kind}</p>
            {item.link ? <Link to={item.link}>Open</Link> : null}
          </article>
        ))}
      </section>
    </>
  );
}
