import Link from "next/link";

export function SiteFooter() {
  return (
    <footer className="border-t border-[var(--line)] bg-brand-deep text-white">
      <div className="mx-auto flex max-w-6xl flex-col gap-10 px-5 py-14 sm:px-8 md:flex-row md:items-end md:justify-between">
        <div>
          <p className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">
            Stride
          </p>
          <p className="mt-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-accent">
            Premium Learning
          </p>
          <p className="mt-3 max-w-sm text-white/70">
            Learn as a student, teach with your own instructor ID, or manage the platform as admin.
          </p>
        </div>
        <div className="flex flex-wrap gap-x-8 gap-y-3 text-sm text-white/75">
          <Link href="/explore" className="hover:text-white">
            Explore
          </Link>
          <Link href="/about" className="hover:text-white">
            About us
          </Link>
          <Link href="/contact" className="hover:text-white">
            Contact us
          </Link>
          <Link href="/login?role=student" className="hover:text-white">
            Student login
          </Link>
          <Link href="/login?role=teacher" className="hover:text-white">
            Teacher login
          </Link>
        </div>
      </div>
      <div className="border-t border-white/10 px-5 py-5 text-center text-xs text-white/50 sm:px-8">
        © {new Date().getFullYear()} Stride Learning. All rights reserved.
      </div>
    </footer>
  );
}
