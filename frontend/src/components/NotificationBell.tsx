import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell, CheckCheck, Megaphone, X } from "lucide-react";
import { api, type Group, type NotificationFeed } from "../api";
import { useAuth } from "../auth";

const ADMIN_ROLES = ["super_admin", "administrator"];

function timeAgo(iso: string): string {
  const then = new Date(iso).getTime();
  const secs = Math.max(0, Math.floor((Date.now() - then) / 1000));
  if (secs < 60) return "just now";
  const mins = Math.floor(secs / 60);
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.floor(hrs / 24)}d ago`;
}

export function NotificationBell() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [compose, setCompose] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const isStaff = !!user && user.role !== "student";

  const feed = useQuery({
    queryKey: ["notifications"],
    queryFn: () => api<NotificationFeed>("/api/notifications"),
    refetchInterval: 20000,
  });

  const markRead = useMutation({
    mutationFn: (id: string) => api(`/api/notifications/${id}/read`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });
  const markAll = useMutation({
    mutationFn: () => api("/api/notifications/read-all", { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });

  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, []);

  const unread = feed.data?.unread ?? 0;
  const items = feed.data?.items ?? [];

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        className="btn-ghost relative"
        onClick={() => setOpen((v) => !v)}
        aria-label="Notifications"
      >
        <Bell size={16} />
        {unread > 0 && (
          <span className="absolute -right-1 -top-1 grid h-5 min-w-[20px] place-items-center rounded-full bg-cyan-glow px-1 text-[10px] font-bold text-ink-950">
            {unread > 99 ? "99+" : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 z-30 mt-2 w-96 max-w-[90vw] overflow-hidden rounded-xl border border-white/10 bg-ink-900/95 shadow-glow backdrop-blur">
          <div className="flex items-center justify-between border-b border-white/10 px-4 py-3">
            <div className="font-semibold">What&apos;s new</div>
            <div className="flex items-center gap-2">
              {isStaff && (
                <button
                  className="btn-ghost !px-2 !py-1 text-xs"
                  onClick={() => {
                    setCompose(true);
                    setOpen(false);
                  }}
                >
                  <Megaphone size={13} /> New
                </button>
              )}
              <button
                className="btn-ghost !px-2 !py-1 text-xs"
                disabled={unread === 0 || markAll.isPending}
                onClick={() => markAll.mutate()}
              >
                <CheckCheck size={13} /> Mark all
              </button>
            </div>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {items.length === 0 && (
              <div className="px-4 py-8 text-center text-sm text-slate-500">
                No notifications yet.
              </div>
            )}
            {items.map((n) => (
              <button
                key={n.id}
                onClick={() => !n.read && markRead.mutate(n.id)}
                className={`flex w-full flex-col gap-1 border-b border-white/5 px-4 py-3 text-left transition hover:bg-white/5 ${
                  n.read ? "opacity-60" : ""
                }`}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="flex items-center gap-2 text-sm font-medium">
                    {!n.read && <span className="status-dot bg-cyan-glow" />}
                    <span
                      className={`rounded px-1.5 py-0.5 text-[10px] uppercase ${
                        n.kind === "assignment"
                          ? "bg-amber-400/15 text-amber-300"
                          : "bg-cyan-glow/15 text-cyan-glow"
                      }`}
                    >
                      {n.kind}
                    </span>
                    {n.title}
                  </span>
                  <span className="shrink-0 text-[11px] text-slate-500">
                    {timeAgo(n.created_at)}
                  </span>
                </div>
                {n.body && <p className="text-xs text-slate-400">{n.body}</p>}
              </button>
            ))}
          </div>
        </div>
      )}

      {compose && <ComposeAnnouncement onClose={() => setCompose(false)} />}
    </div>
  );
}

function ComposeAnnouncement({ onClose }: { onClose: () => void }) {
  const { user } = useAuth();
  const qc = useQueryClient();
  const isAdmin = !!user && ADMIN_ROLES.includes(user.role);

  const groups = useQuery({
    queryKey: ["groups", "compose"],
    queryFn: () => api<Group[]>("/api/groups"),
  });
  const studentGroups = (groups.data || []).filter((g) => g.kind === "student");

  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [scope, setScope] = useState<"group" | "all">(isAdmin ? "all" : "group");
  const [groupId, setGroupId] = useState<string>("");
  const [ok, setOk] = useState<string>("");

  useEffect(() => {
    if (!groupId && studentGroups.length) setGroupId(studentGroups[0].id);
  }, [studentGroups.length]);

  const post = useMutation({
    mutationFn: () =>
      api<{ delivered: number }>("/api/announcements", {
        method: "POST",
        body: JSON.stringify({
          title,
          body,
          scope,
          group_id: scope === "group" ? groupId : null,
        }),
      }),
    onSuccess: (res) => {
      setOk(`Announcement posted — delivered to ${res.delivered} student(s).`);
      setTitle("");
      setBody("");
      qc.invalidateQueries({ queryKey: ["notifications"] });
      qc.invalidateQueries({ queryKey: ["announcements"] });
    },
  });

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4" onMouseDown={onClose}>
      <div
        className="card w-full max-w-lg space-y-4 p-6"
        onMouseDown={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between">
          <h2 className="flex items-center gap-2 text-lg font-semibold">
            <Megaphone size={18} className="text-cyan-glow" /> New announcement
          </h2>
          <button className="btn-ghost !px-2 !py-1" onClick={onClose}>
            <X size={16} />
          </button>
        </div>

        <div className="space-y-3">
          <input
            className="input"
            placeholder="Title"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
          />
          <textarea
            className="input min-h-[120px]"
            placeholder="Write your announcement to students…"
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
          <div className="grid gap-3 md:grid-cols-2">
            <label className="text-sm">
              <div className="mb-1 text-slate-400">Audience</div>
              <select
                className="input w-full"
                value={scope}
                onChange={(e) => setScope(e.target.value as "group" | "all")}
              >
                <option value="group">A student group</option>
                {isAdmin && <option value="all">All students</option>}
              </select>
            </label>
            {scope === "group" && (
              <label className="text-sm">
                <div className="mb-1 text-slate-400">Student group</div>
                <select
                  className="input w-full"
                  value={groupId}
                  onChange={(e) => setGroupId(e.target.value)}
                >
                  {studentGroups.map((g) => (
                    <option key={g.id} value={g.id}>
                      {g.name} ({g.member_count})
                    </option>
                  ))}
                  {!studentGroups.length && <option value="">No student groups</option>}
                </select>
              </label>
            )}
          </div>
        </div>

        {post.error && <div className="text-sm text-rose-300">{(post.error as Error).message}</div>}
        {ok && <div className="text-sm text-cyan-glow">{ok}</div>}

        <div className="flex justify-end gap-2">
          <button className="btn-ghost" onClick={onClose}>
            Close
          </button>
          <button
            className="btn-primary"
            disabled={
              post.isPending ||
              title.trim().length < 2 ||
              (scope === "group" && !groupId)
            }
            onClick={() => post.mutate()}
          >
            Post announcement
          </button>
        </div>
      </div>
    </div>
  );
}
