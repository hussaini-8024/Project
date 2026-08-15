export function ResourceBar({
  label,
  value,
  max,
  suffix = "",
}: {
  label: string;
  value: number;
  max: number;
  suffix?: string;
}) {
  const pct = Math.min(100, max ? (value / max) * 100 : 0);
  const tone = pct >= 90 ? "bg-rose-500" : pct >= 80 ? "bg-amber-400" : "bg-cyan-glow";
  return (
    <div>
      <div className="mb-1 flex justify-between text-xs text-slate-400">
        <span>{label}</span>
        <span className="font-mono">
          {Math.round(value)}
          {suffix} / {max}
          {suffix}
        </span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-white/10">
        <div className={`h-full ${tone}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}
