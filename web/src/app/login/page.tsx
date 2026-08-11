import Link from "next/link";

export default function LoginPage() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center bg-mist px-5">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 ring-1 ring-[var(--line)]">
        <Link href="/" className="font-[family-name:var(--font-display)] text-2xl font-semibold text-brand">
          Stride
        </Link>
        <h1 className="mt-6 text-2xl font-semibold tracking-tight text-ink">Log in</h1>
        <p className="mt-2 text-ink-muted">
          Auth for students, teachers, and admins comes in the next step. This is a placeholder.
        </p>
        <Link
          href="/"
          className="mt-8 inline-flex rounded-xl bg-brand px-5 py-3 text-sm font-semibold text-white hover:bg-brand-deep"
        >
          Back to dashboard
        </Link>
      </div>
    </main>
  );
}
