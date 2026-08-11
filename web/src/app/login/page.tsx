import Link from "next/link";
import { Logo } from "@/components/Logo";

type LoginPageProps = {
  searchParams: Promise<{ role?: string }>;
};

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const params = await searchParams;
  const role = params.role === "teacher" ? "teacher" : "student";
  const isTeacher = role === "teacher";

  return (
    <main className="flex min-h-screen flex-col items-center justify-center bg-mist px-5 py-12">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 ring-1 ring-[var(--line)]">
        <Logo />
        <h1 className="mt-8 text-2xl font-semibold tracking-tight text-ink">
          Log in as {isTeacher ? "Teacher" : "Student"}
        </h1>
        <p className="mt-2 text-ink-muted">
          {isTeacher
            ? "Access your instructor dashboard to upload and manage courses with your teacher ID."
            : "Access purchased courses, track progress, and continue learning."}
        </p>

        <form className="mt-8 space-y-4">
          <label className="block text-sm font-medium text-ink">
            Email
            <input
              type="email"
              name="email"
              required
              className="mt-2 w-full rounded-xl border border-[var(--line)] bg-surface px-4 py-3 outline-none focus:border-brand"
              placeholder={isTeacher ? "teacher@example.com" : "student@example.com"}
            />
          </label>
          <label className="block text-sm font-medium text-ink">
            Password
            <input
              type="password"
              name="password"
              required
              className="mt-2 w-full rounded-xl border border-[var(--line)] bg-surface px-4 py-3 outline-none focus:border-brand"
              placeholder="••••••••"
            />
          </label>
          <button
            type="submit"
            className="w-full rounded-xl bg-brand py-3 text-sm font-semibold text-white transition hover:bg-brand-deep"
          >
            Continue as {isTeacher ? "Teacher" : "Student"}
          </button>
        </form>

        <p className="mt-4 text-center text-xs text-ink-muted">
          Auth backend comes in a later step.{" "}
          <Link
            href={isTeacher ? "/login?role=student" : "/login?role=teacher"}
            className="font-semibold text-brand hover:text-brand-deep"
          >
            Switch to {isTeacher ? "Student" : "Teacher"}
          </Link>
        </p>

        <Link href="/" className="mt-6 inline-flex text-sm font-semibold text-brand hover:text-brand-deep">
          ← Back to dashboard
        </Link>
      </div>
    </main>
  );
}
