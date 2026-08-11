import Link from "next/link";

const links = [
  { href: "#explore", label: "Explore" },
  { href: "#courses", label: "Courses" },
  { href: "#teach", label: "Teach" },
];

export function SiteHeader() {
  return (
    <header className="absolute inset-x-0 top-0 z-30">
      <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
        <Link href="/" className="group flex items-center gap-2.5">
          <span
            aria-hidden
            className="grid h-9 w-9 place-items-center rounded-xl bg-white/15 text-white ring-1 ring-white/25 backdrop-blur transition group-hover:bg-white/25"
          >
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden>
              <path
                d="M3 12.5L9 3l6 9.5H3Z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinejoin="round"
              />
              <path d="M6.2 12.5h5.6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            </svg>
          </span>
          <span className="font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight text-white">
            Stride
          </span>
        </Link>

        <nav className="hidden items-center gap-8 text-sm font-medium text-white/85 md:flex">
          {links.map((link) => (
            <a key={link.href} href={link.href} className="transition hover:text-white">
              {link.label}
            </a>
          ))}
        </nav>

        <div className="flex items-center gap-2 sm:gap-3">
          <Link
            href="/login"
            className="rounded-lg px-3 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10 hover:text-white"
          >
            Log in
          </Link>
          <Link
            href="/signup"
            className="rounded-lg bg-accent px-3.5 py-2 text-sm font-semibold text-brand-deep transition hover:bg-[#f0ad55]"
          >
            Join for free
          </Link>
        </div>
      </div>
    </header>
  );
}
