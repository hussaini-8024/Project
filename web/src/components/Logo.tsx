import Link from "next/link";

type LogoProps = {
  tone?: "light" | "dark";
  className?: string;
};

export function Logo({ tone = "dark", className = "" }: LogoProps) {
  const light = tone === "light";

  return (
    <Link href="/" className={`group inline-flex items-center gap-3 ${className}`}>
      <span
        className={`relative grid h-11 w-11 place-items-center overflow-hidden rounded-2xl shadow-lg transition group-hover:scale-[1.03] ${
          light
            ? "bg-gradient-to-br from-accent via-[#f0ad55] to-[#c67a1f] shadow-black/20"
            : "bg-gradient-to-br from-brand via-brand-soft to-brand-deep shadow-[var(--glow)]"
        }`}
        aria-hidden
      >
        <span className="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(255,255,255,0.55),transparent_55%)]" />
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none" className="relative text-white">
          <path
            d="M4 15.5 11 4l7 11.5H4Z"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinejoin="round"
          />
          <path d="M7.5 15.5h7" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
          <circle cx="11" cy="11.5" r="1.4" fill="currentColor" />
        </svg>
      </span>
      <span className="leading-none">
        <span
          className={`block font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight ${
            light ? "text-white" : "text-ink"
          }`}
        >
          Stride
        </span>
        <span
          className={`mt-0.5 block text-[10px] font-semibold uppercase tracking-[0.22em] ${
            light ? "text-white/70" : "text-brand"
          }`}
        >
          Premium Learning
        </span>
      </span>
    </Link>
  );
}
