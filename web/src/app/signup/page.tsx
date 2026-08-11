import Link from "next/link";
import { Logo } from "@/components/Logo";

export default function SignupPage() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center bg-mist px-5 py-12">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 ring-1 ring-[var(--line)]">
        <Logo />
        <h1 className="mt-8 text-2xl font-semibold tracking-tight text-ink">Join for free</h1>
        <p className="mt-2 text-ink-muted">
          Create your account as a student or teacher. Full registration flow comes next.
        </p>

        <div className="mt-8 grid gap-3">
          <Link
            href="/login?role=student"
            className="rounded-xl bg-brand px-5 py-3 text-center text-sm font-semibold text-white hover:bg-brand-deep"
          >
            Continue as Student
          </Link>
          <Link
            href="/login?role=teacher"
            className="rounded-xl border border-[var(--line)] bg-surface px-5 py-3 text-center text-sm font-semibold text-ink hover:bg-mist"
          >
            Continue as Teacher
          </Link>
        </div>

        <Link href="/" className="mt-6 inline-flex text-sm font-semibold text-brand hover:text-brand-deep">
          ← Back to dashboard
        </Link>
      </div>
    </main>
  );
}
