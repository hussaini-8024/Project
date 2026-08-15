import { useMutation, useQuery } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, type Template } from "../api";

const steps = ["Environment", "Template", "Resources", "Security", "Create"];

export function Wizard() {
  const nav = useNavigate();
  const [step, setStep] = useState(0);
  const [env, setEnv] = useState<"container" | "vm" | "prebuilt">("container");
  const [slug, setSlug] = useState("ubuntu");
  const [vcpu, setVcpu] = useState(1);
  const [ram, setRam] = useState(512);
  const [disk, setDisk] = useState(2);
  const [internet, setInternet] = useState(false);
  const [isolated, setIsolated] = useState(true);
  const [name, setName] = useState("");
  const { data: templates = [] } = useQuery({
    queryKey: ["templates"],
    queryFn: () => api<Template[]>("/api/templates"),
  });
  const filtered = templates.filter((t) =>
    env === "prebuilt" ? t.environment === "prebuilt" : env === "vm" ? t.recommended_kind === "vm" || t.requires_full_os : t.container_first || t.recommended_kind === "container",
  );
  const selected = templates.find((t) => t.slug === slug);
  const estimate = useMemo(() => {
    const kind = selected?.requires_full_os ? "Full VM (KVM)" : "Container (shared image, CoW)";
    return { kind, ram, vcpu, disk };
  }, [selected, ram, vcpu, disk]);

  const create = useMutation({
    mutationFn: () =>
      api("/api/machines", {
        method: "POST",
        body: JSON.stringify({
          name: name || selected?.name || "machine",
          template_slug: slug,
          environment: env,
          vcpu,
          ram_mb: ram,
          disk_gb: disk,
          internet,
          isolated,
        }),
      }),
    onSuccess: () => nav("/machines"),
  });

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Create machine</h1>
        <p className="text-slate-400">The scheduler prefers the lowest-resource environment that can run the exercise.</p>
      </div>
      <div className="flex gap-2">
        {steps.map((s, i) => (
          <div
            key={s}
            className={`flex-1 rounded-lg px-3 py-2 text-center text-xs ${i === step ? "bg-cyan-glow text-ink-950" : "bg-white/5 text-slate-400"}`}
          >
            {i + 1}. {s}
          </div>
        ))}
      </div>
      <div className="card p-6">
        {step === 0 && (
          <div className="grid gap-3 md:grid-cols-3">
            {(
              [
                ["container", "Container", "Linux services, DVWA, Juice Shop, WebGoat"],
                ["vm", "Full VM", "Windows, kernel labs, appliances"],
                ["prebuilt", "Prebuilt Lab", "Multi-machine authorized scenarios"],
              ] as const
            ).map(([k, t, d]) => (
              <button
                key={k}
                type="button"
                onClick={() => setEnv(k)}
                className={`rounded-xl border p-4 text-left ${env === k ? "border-cyan-glow bg-cyan-glow/10" : "border-white/10"}`}
              >
                <div className="font-semibold">{t}</div>
                <div className="mt-1 text-sm text-slate-400">{d}</div>
              </button>
            ))}
          </div>
        )}
        {step === 1 && (
          <div className="space-y-3">
            {filtered.map((t) => (
              <button
                key={t.slug}
                type="button"
                onClick={() => {
                  setSlug(t.slug);
                  setVcpu(t.default_vcpu);
                  setRam(t.default_ram_mb);
                  setDisk(t.default_disk_gb);
                  setName(t.name);
                }}
                className={`w-full rounded-xl border p-4 text-left ${slug === t.slug ? "border-cyan-glow bg-cyan-glow/10" : "border-white/10"}`}
              >
                <div className="flex justify-between">
                  <div className="font-semibold">{t.name}</div>
                  <div className="text-xs uppercase text-slate-400">{t.recommended_kind}</div>
                </div>
                <p className="mt-1 text-sm text-slate-400">{t.description}</p>
                {t.is_vulnerable_target && (
                  <div className="mt-2 text-xs text-amber-300">Training Target — Authorized Laboratory Use Only</div>
                )}
                {env === "vm" && t.container_first && (
                  <div className="mt-2 text-xs text-cyan-glow">Recommendation: run as a container to save RAM.</div>
                )}
              </button>
            ))}
          </div>
        )}
        {step === 2 && (
          <div className="grid gap-4 md:grid-cols-3">
            <Field label="vCPU" value={vcpu} set={setVcpu} min={1} max={8} />
            <Field label="RAM (MB)" value={ram} set={setRam} min={256} max={8192} step={256} />
            <Field label="Disk (GB)" value={disk} set={setDisk} min={1} max={80} />
            <label className="md:col-span-3 text-sm">
              Display name
              <input className="input mt-1" value={name} onChange={(e) => setName(e.target.value)} />
            </label>
          </div>
        )}
        {step === 3 && (
          <div className="space-y-3">
            <Toggle label="Internet access (staff-controlled NAT)" value={internet} set={setInternet} />
            <Toggle label="Private lab only / network isolation" value={isolated} set={setIsolated} />
            <p className="text-sm text-slate-400">
              Default path: student machines → private lab network → controlled NAT → internet. Host management
              interfaces are never exposed.
            </p>
          </div>
        )}
        {step === 4 && (
          <div className="space-y-3 text-sm">
            <Row k="Environment" v={estimate.kind} />
            <Row k="Template" v={selected?.name || slug} />
            <Row k="Estimated RAM" v={`${estimate.ram} MB`} />
            <Row k="Estimated CPU" v={`${estimate.vcpu} vCPU`} />
            <Row k="Estimated storage" v={`${estimate.disk} GB`} />
            <Row k="Isolation" v={isolated ? "Private lab" : "Custom"} />
            {create.error && <div className="text-rose-300">{(create.error as Error).message}</div>}
          </div>
        )}
        <div className="mt-6 flex justify-between">
          <button className="btn-ghost" type="button" disabled={step === 0} onClick={() => setStep((s) => s - 1)}>
            Back
          </button>
          {step < 4 ? (
            <button className="btn-primary" type="button" onClick={() => setStep((s) => s + 1)}>
              Continue
            </button>
          ) : (
            <button className="btn-primary" type="button" disabled={create.isPending} onClick={() => create.mutate()}>
              {create.isPending ? "Scheduling…" : "Submit to lab scheduler"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function Field({
  label,
  value,
  set,
  min,
  max,
  step = 1,
}: {
  label: string;
  value: number;
  set: (n: number) => void;
  min: number;
  max: number;
  step?: number;
}) {
  return (
    <label className="text-sm">
      {label}
      <input
        className="input mt-1"
        type="number"
        min={min}
        max={max}
        step={step}
        value={value}
        onChange={(e) => set(Number(e.target.value))}
      />
    </label>
  );
}

function Toggle({ label, value, set }: { label: string; value: boolean; set: (v: boolean) => void }) {
  return (
    <label className="flex items-center justify-between rounded-lg border border-white/10 px-4 py-3">
      <span>{label}</span>
      <input type="checkbox" checked={value} onChange={(e) => set(e.target.checked)} />
    </label>
  );
}

function Row({ k, v }: { k: string; v: string }) {
  return (
    <div className="flex justify-between border-b border-white/5 py-2">
      <span className="text-slate-400">{k}</span>
      <span className="font-medium">{v}</span>
    </div>
  );
}
