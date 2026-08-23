import { FormEvent, useEffect, useRef, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { AlertTriangle, Bot, Radar, Send, Sparkles } from "lucide-react";
import { api, type AukcResponse } from "../api";

const SUGGESTIONS = [
  "How do I scan a subnet with nmap?",
  "Explain SQL injection and how sqlmap helps",
  "Brute force an SSH login with hydra",
  "Capture HTTP traffic with tcpdump",
  "Crack an NTLM hash with hashcat",
];

export function Aukc() {
  const [prompt, setPrompt] = useState("");
  const [typed, setTyped] = useState("");
  const answerRef = useRef<HTMLDivElement>(null);

  const ask = useMutation({
    mutationFn: (p: string) =>
      api<AukcResponse>("/api/aukc/search", {
        method: "POST",
        body: JSON.stringify({ prompt: p }),
      }),
  });

  const result = ask.data;

  // Typewriter reveal of the answer.
  useEffect(() => {
    if (!result?.answer) {
      setTyped("");
      return;
    }
    const full = result.answer;
    setTyped("");
    let i = 0;
    const step = Math.max(1, Math.round(full.length / 400));
    const timer = setInterval(() => {
      i += step;
      setTyped(full.slice(0, i));
      if (i >= full.length) clearInterval(timer);
    }, 12);
    return () => clearInterval(timer);
  }, [result?.answer]);

  useEffect(() => {
    answerRef.current?.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }, [typed]);

  function submit(e?: FormEvent) {
    e?.preventDefault();
    const p = prompt.trim();
    if (p.length) ask.mutate(p);
  }

  const loading = ask.isPending;

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
            <Bot size={30} />
            <Radar size={62} className="absolute text-cyan-glow/20 aukc-orbit" />
          </div>
          <div>
            <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-cyan-glow/80">
              <Sparkles size={13} /> AUKC AI Search
            </div>
            <h1 className="text-3xl font-bold tracking-tight">
              AU Kamra AI Agent
            </h1>
            <p className="mt-1 max-w-2xl text-sm text-slate-400">
              Your cyber-range study assistant. Ask about security tools, commands, and techniques —
              scoped to authorized lab work.
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
            placeholder="Ask the AU Kamra AI Agent about cyber tools and study help…"
            value={prompt}
            autoFocus
            onChange={(e) => setPrompt(e.target.value)}
          />
          <button type="submit" className="btn-primary" disabled={loading || !prompt.trim()}>
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
                setPrompt(s);
                ask.mutate(s);
              }}
              className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300 hover:bg-white/10"
            >
              {s}
            </button>
          ))}
        </div>
      </form>

      {/* Loading shimmer */}
      {loading && (
        <div className="card relative overflow-hidden p-6">
          <div className="pointer-events-none absolute inset-0 overflow-hidden">
            <div className="aukc-scanline" />
          </div>
          <div className="flex items-center gap-3 text-cyan-glow">
            <Radar size={18} className="aukc-orbit" />
            <span className="aukc-caret">Scanning the knowledge base</span>
          </div>
        </div>
      )}

      {/* Result */}
      {result && !loading && (
        <div className="space-y-3">
          {!result.configured && (
            <div className="flex items-start gap-3 rounded-xl border border-amber-400/30 bg-amber-400/5 p-4 text-sm">
              <AlertTriangle size={18} className="mt-0.5 shrink-0 text-amber-300" />
              <div>
                <div className="font-medium text-amber-200">
                  AI key not configured — showing offline guidance
                </div>
                <div className="text-xs text-amber-200/70">
                  {result.error || "Set OPENAI_API_KEY on the server for live AI answers."}
                </div>
              </div>
            </div>
          )}
          <div
            ref={answerRef}
            className="card relative overflow-hidden border-cyan-glow/20 p-6"
          >
            <div className="mb-3 flex items-center gap-2 text-xs uppercase tracking-widest text-cyan-glow/80">
              <Bot size={14} /> AU Kamra AI Agent
              <span className="ml-auto rounded bg-white/5 px-2 py-0.5 font-mono text-[10px] normal-case text-slate-400">
                {result.configured ? `live · ${result.model}` : "offline"}
              </span>
            </div>
            <div className="whitespace-pre-wrap font-mono text-sm leading-relaxed text-slate-200 aukc-caret">
              {typed}
            </div>
          </div>
        </div>
      )}

      {ask.error && !loading && (
        <div className="card border-rose-400/30 p-4 text-sm text-rose-300">
          {(ask.error as Error).message}
        </div>
      )}
    </div>
  );
}
