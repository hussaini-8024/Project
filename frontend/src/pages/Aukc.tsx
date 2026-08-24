import { FormEvent, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  BookOpen,
  FileText,
  Library,
  Radar,
  Search,
  Send,
  Sparkles,
  Trash2,
  Upload,
} from "lucide-react";
import {
  api,
  getToken,
  type AukcSearchResponse,
  type BookDocument,
} from "../api";
import { useAuth } from "../auth";

const SUGGESTIONS = [
  "nmap port scanning",
  "sql injection",
  "hydra brute force ssh",
  "tcpdump packet capture",
  "hashcat ntlm",
];

const ADMIN_ROLES = ["administrator", "super_admin"];

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export function Aukc() {
  const { user } = useAuth();
  const isAdmin = !!user && ADMIN_ROLES.includes(user.role);
  const [query, setQuery] = useState("");

  const search = useMutation({
    mutationFn: (q: string) =>
      api<AukcSearchResponse>("/api/aukc/search", {
        method: "POST",
        body: JSON.stringify({ query: q, limit: 20 }),
      }),
  });

  const result = search.data;
  const loading = search.isPending;

  function submit(e?: FormEvent) {
    e?.preventDefault();
    const q = query.trim();
    if (q.length) search.mutate(q);
  }

  return (
    <div className="space-y-6">
      {/* Branded hero */}
      <div className="relative overflow-hidden rounded-2xl border border-cyan-glow/30 bg-ink-900/70 p-8 aukc-pulse">
        <div className="pointer-events-none absolute inset-0 aukc-grid opacity-60" />
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="aukc-scanline" />
        </div>
        <div className="relative flex items-center gap-4">
          <div className="relative grid h-16 w-16 place-items-center rounded-2xl bg-cyan-glow/10 text-cyan-glow aukc-flicker">
            <BookOpen size={30} />
            <Radar size={62} className="absolute text-cyan-glow/20 aukc-orbit" />
          </div>
          <div>
            <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-cyan-glow/80">
              <Sparkles size={13} /> AUKC AI Search
            </div>
            <h1 className="text-3xl font-bold tracking-tight">AU Kamra AI Agent</h1>
            <p className="mt-1 max-w-2xl text-sm text-slate-400">
              Offline book intelligence. Search the cyber-range library of
              admin-curated PDF books — fully self-contained, no external AI.
            </p>
          </div>
        </div>
      </div>

      {/* Prompt box */}
      <form onSubmit={submit} className="card space-y-3 p-4">
        <div className="flex items-center gap-2 rounded-xl border border-white/10 bg-ink-950/70 p-2 focus-within:border-cyan-glow/50">
          <Radar size={18} className={`text-cyan-glow ${loading ? "aukc-orbit" : ""}`} />
          <input
            className="w-full bg-transparent text-sm outline-none"
            placeholder="Search the book library for a topic, tool, or technique…"
            value={query}
            autoFocus
            onChange={(e) => setQuery(e.target.value)}
          />
          <button type="submit" className="btn-primary" disabled={loading || !query.trim()}>
            <Send size={15} />
            {loading ? "Scanning…" : "Search"}
          </button>
        </div>
        <div className="flex flex-wrap gap-2">
          {SUGGESTIONS.map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => {
                setQuery(s);
                search.mutate(s);
              }}
              className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300 hover:bg-white/10"
            >
              {s}
            </button>
          ))}
        </div>
      </form>

      {isAdmin && <AdminLibraryPanel />}

      {/* Loading shimmer */}
      {loading && (
        <div className="card relative overflow-hidden p-6">
          <div className="pointer-events-none absolute inset-0 overflow-hidden">
            <div className="aukc-scanline" />
          </div>
          <div className="flex items-center gap-3 text-cyan-glow">
            <Radar size={18} className="aukc-orbit" />
            <span className="aukc-caret">Scanning the book library</span>
          </div>
        </div>
      )}

      {/* Results */}
      {result && !loading && (
        <div className="space-y-3">
          {result.message ? (
            <div className="card flex items-center gap-3 border-cyan-glow/20 p-6 text-sm text-slate-300">
              <Library size={18} className="shrink-0 text-cyan-glow/80" />
              {result.message}
            </div>
          ) : (
            <>
              <div className="flex items-center gap-2 text-xs uppercase tracking-widest text-cyan-glow/80">
                <Search size={14} /> {result.results.length} passage
                {result.results.length === 1 ? "" : "s"} found
              </div>
              {result.results.map((hit, i) => (
                <div
                  key={`${hit.book_id}-${hit.page_number}-${i}`}
                  className="card relative overflow-hidden border-cyan-glow/20 p-5"
                >
                  <div className="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <BookOpen size={14} className="text-cyan-glow" />
                    <span className="font-semibold text-slate-100">{hit.book_title}</span>
                    <span className="rounded bg-white/5 px-2 py-0.5 font-mono text-[10px] text-slate-400">
                      page {hit.page_number}
                    </span>
                    <span className="ml-auto rounded bg-cyan-glow/10 px-2 py-0.5 font-mono text-[10px] text-cyan-glow/80">
                      score {hit.score.toFixed(2)}
                    </span>
                  </div>
                  <p
                    className="aukc-snippet whitespace-pre-wrap font-mono text-sm leading-relaxed text-slate-200"
                    dangerouslySetInnerHTML={{ __html: hit.snippet }}
                  />
                </div>
              ))}
            </>
          )}
        </div>
      )}

      {search.error && !loading && (
        <div className="card border-rose-400/30 p-4 text-sm text-rose-300">
          {(search.error as Error).message}
        </div>
      )}
    </div>
  );
}

function AdminLibraryPanel() {
  const qc = useQueryClient();
  const [title, setTitle] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState("");

  const books = useQuery({
    queryKey: ["aukc-books"],
    queryFn: () => api<BookDocument[]>("/api/aukc/books"),
  });

  const upload = useMutation({
    mutationFn: async () => {
      if (!file) throw new Error("Choose a PDF file first.");
      const form = new FormData();
      form.append("file", file);
      form.append("title", title);
      const res = await fetch("/api/aukc/books", {
        method: "POST",
        headers: { Authorization: `Bearer ${getToken() ?? ""}` },
        body: form,
      });
      if (!res.ok) {
        let detail = res.statusText;
        try {
          detail = (await res.json()).detail || detail;
        } catch {
          /* ignore */
        }
        throw new Error(typeof detail === "string" ? detail : "Upload failed");
      }
      return res.json();
    },
    onSuccess: () => {
      setTitle("");
      setFile(null);
      setError("");
      qc.invalidateQueries({ queryKey: ["aukc-books"] });
    },
    onError: (e) => setError((e as Error).message),
  });

  const remove = useMutation({
    mutationFn: (id: string) => api(`/api/aukc/books/${id}`, { method: "DELETE" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["aukc-books"] }),
  });

  return (
    <div className="card space-y-4 border-cyan-glow/20 p-5">
      <div className="flex items-center gap-2 text-xs uppercase tracking-widest text-cyan-glow/80">
        <Library size={14} /> Book Library · Admin
      </div>

      <form
        className="grid gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-center"
        onSubmit={(e) => {
          e.preventDefault();
          upload.mutate();
        }}
      >
        <input
          className="input"
          placeholder="Book title (optional — defaults to filename)"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
        />
        <label className="btn-ghost cursor-pointer">
          <Upload size={15} />
          {file ? file.name.slice(0, 24) : "Choose PDF"}
          <input
            type="file"
            accept="application/pdf,.pdf"
            className="hidden"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
        </label>
        <button type="submit" className="btn-primary" disabled={upload.isPending || !file}>
          <Upload size={15} />
          {upload.isPending ? "Uploading…" : "Upload"}
        </button>
      </form>
      {error && <div className="text-xs text-rose-300">{error}</div>}

      <div className="divide-y divide-white/5">
        {books.data && books.data.length === 0 && (
          <div className="py-3 text-sm text-slate-400">
            No books uploaded yet. Add a PDF to build the searchable library.
          </div>
        )}
        {books.data?.map((b) => (
          <div key={b.id} className="flex items-center gap-3 py-2.5 text-sm">
            <FileText size={16} className="shrink-0 text-cyan-glow/70" />
            <div className="min-w-0 flex-1">
              <div className="truncate font-medium text-slate-100">{b.title}</div>
              <div className="truncate font-mono text-[11px] text-slate-500">
                {b.filename} · {b.page_count} pages · {formatBytes(b.size_bytes)}
              </div>
            </div>
            <button
              type="button"
              className="btn-ghost text-rose-300 hover:bg-rose-500/10"
              onClick={() => remove.mutate(b.id)}
              disabled={remove.isPending}
            >
              <Trash2 size={14} />
              Delete
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
