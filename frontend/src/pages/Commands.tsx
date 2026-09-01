import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Check, Copy, Search, Terminal } from "lucide-react";
import { api, type CommandSearchResult } from "../api";

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <button
      className="btn-ghost !px-2 !py-1 text-xs"
      onClick={async () => {
        try {
          await navigator.clipboard.writeText(text);
        } catch {
          /* clipboard may be unavailable; ignore */
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 1200);
      }}
      aria-label="Copy command"
    >
      {copied ? <Check size={13} /> : <Copy size={13} />}
      {copied ? "Copied" : "Copy"}
    </button>
  );
}

export function Commands() {
  const [q, setQ] = useState("");

  const search = useQuery({
    queryKey: ["commands", q],
    queryFn: () => api<CommandSearchResult>(`/api/commands?q=${encodeURIComponent(q)}`),
  });

  const data = search.data;
  const categories = data?.categories ?? [];

  return (
    <div className="space-y-5">
      <div>
        <div className="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-cyan-glow">
          <Terminal size={14} /> Reference
        </div>
        <h1 className="text-3xl font-semibold">Command Search</h1>
        <p className="mt-1 text-sm text-slate-400">
          Offline catalogue of common cybersecurity tool commands. Search by tool, command,
          description, or tag. All examples are for use inside your authorized, isolated lab only.
        </p>
      </div>

      <div className="card flex items-center gap-2 p-3">
        <Search size={18} className="text-slate-400" />
        <input
          className="w-full bg-transparent text-sm outline-none"
          placeholder="Search e.g. nmap, sql injection, brute force ssh, capture packets…"
          value={q}
          autoFocus
          onChange={(e) => setQ(e.target.value)}
        />
        {q && (
          <button className="btn-ghost !px-2 !py-1 text-xs" onClick={() => setQ("")}>
            Clear
          </button>
        )}
      </div>

      <div className="flex flex-wrap gap-2">
        {categories.map((c) => (
          <button
            key={c}
            className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs capitalize hover:bg-white/10"
            onClick={() => setQ(c)}
          >
            {c}
          </button>
        ))}
      </div>

      <div className="text-xs text-slate-500">
        {data ? `${data.count} of ${data.total} commands` : "Loading…"}
      </div>

      <div className="grid gap-3 md:grid-cols-2">
        {(data?.results ?? []).map((r, i) => (
          <div key={`${r.tool}-${i}`} className="card space-y-2 p-4">
            <div className="flex items-center justify-between">
              <span className="rounded bg-cyan-glow/15 px-2 py-0.5 text-xs font-semibold uppercase text-cyan-glow">
                {r.tool}
              </span>
              <span className="text-[11px] capitalize text-slate-500">{r.category}</span>
            </div>
            <div className="flex items-start justify-between gap-2 rounded-lg border border-white/10 bg-ink-950/70 p-2">
              <code className="font-mono text-sm text-emerald-200 break-all">{r.command}</code>
              <CopyButton text={r.command} />
            </div>
            <p className="text-sm text-slate-300">{r.description}</p>
            <div className="flex flex-wrap gap-1">
              {r.tags.map((t) => (
                <button
                  key={t}
                  onClick={() => setQ(t)}
                  className="rounded bg-white/5 px-1.5 py-0.5 text-[10px] text-slate-400 hover:bg-white/10"
                >
                  #{t}
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>

      {data && data.results.length === 0 && (
        <div className="card p-8 text-center text-slate-500">
          No commands match “{q}”. Try another tool or keyword.
        </div>
      )}
    </div>
  );
}
