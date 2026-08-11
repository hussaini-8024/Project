import Link from "next/link";

const roles = [
  {
    title: "Learn",
    body: "Browse free previews, purchase courses, track progress, and earn certificates as a student.",
    cta: "Start learning",
    href: "/signup",
  },
  {
    title: "Teach",
    body: "Upload courses under your instructor ID, set pricing, and reach motivated learners worldwide.",
    cta: "Become an instructor",
    href: "/signup?role=teacher",
  },
  {
    title: "Manage",
    body: "Admins oversee users, courses, and platform health — everything from one control center.",
    cta: "Admin access",
    href: "/login?role=admin",
  },
];

export function RolesSection() {
  return (
    <section id="teach" className="bg-surface px-5 py-20 sm:px-8">
      <div className="mx-auto max-w-6xl">
        <h2 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
          Built for every role
        </h2>
        <p className="mt-3 max-w-xl text-lg text-ink-muted">
          Visitors explore freely. Students unlock purchased courses. Teachers publish. Admins run the platform.
        </p>

        <ol className="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
          {roles.map((role, index) => (
            <li key={role.title} className="relative">
              <span className="font-[family-name:var(--font-display)] text-5xl font-semibold text-brand/20">
                0{index + 1}
              </span>
              <h3 className="mt-2 text-xl font-semibold tracking-tight text-ink">{role.title}</h3>
              <p className="mt-3 text-base leading-relaxed text-ink-muted">{role.body}</p>
              <Link
                href={role.href}
                className="mt-5 inline-flex text-sm font-semibold text-brand transition hover:text-brand-deep"
              >
                {role.cta} →
              </Link>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
