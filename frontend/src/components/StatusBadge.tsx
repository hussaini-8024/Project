const colors: Record<string, string> = {
  running: "bg-emerald-400",
  stopped: "bg-slate-400",
  starting: "bg-sky-400",
  stopping: "bg-amber-400",
  error: "bg-rose-500",
  queued: "bg-violet-400",
  paused: "bg-yellow-400",
  creating: "bg-sky-300",
};

export function StatusBadge({ status }: { status: string }) {
  return (
    <span className="inline-flex items-center gap-2 rounded-full border border-white/10 px-2.5 py-1 text-xs capitalize">
      <span className={`status-dot ${colors[status] || "bg-slate-500"}`} />
      {status}
    </span>
  );
}
