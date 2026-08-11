import Link from "next/link";

export function SiteFooter() {
  return (
    <footer className="border-t border-[var(--line)] bg-brand-deep text-white">
      <div className="mx-auto flex max-w-6xl flex-col gap-10 px-5 py-14 sm:px-8 md:flex-row md:items-end md:justify-between">
        <div>
          <p className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">
            Stride
          </p>
          <p className="mt-3 max-w-sm text-white/70">
            A learning platform for curious visitors, dedicated students, expert teachers, and platform admins.
          </p>
        </div>
        <div className="flex flex-wrap gap-x-8 gap-y-3 text-sm text-white/75">
          <a href="#explore" className="hover:text-white">
            Explore
          </a>
          <a href="#courses" className="hover:text-white">
            Courses
          </a>
          <Link href="/login" className="hover:text-white">
            Log in
          </Link>
          <Link href="/signup" className="hover:text-white">
            Join for free
          </Link>
        </div>
      </div>
      <div className="border-t border-white/10 px-5 py-5 text-center text-xs text-white/50 sm:px-8">
        © {new Date().getFullYear()} Stride Learning. Step-by-step build in progress.
      </div>
    </footer>
  );
}
