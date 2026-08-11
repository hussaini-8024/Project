import { categories } from "@/lib/courses";

export function CategoryStrip() {
  return (
    <section id="explore" className="relative bg-surface px-5 py-20 sm:px-8">
      <div className="mx-auto max-w-6xl">
        <h2 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
          Explore by topic
        </h2>
        <p className="mt-3 max-w-xl text-lg text-ink-muted">
          Start from a field you care about — then dig into courses built by working instructors.
        </p>

        <ul className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {categories.map((category) => (
            <li key={category.name}>
              <a
                href="#courses"
                className={`group flex items-end justify-between rounded-2xl bg-gradient-to-br ${category.tone} p-5 text-white transition duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-[var(--glow)]`}
              >
                <span>
                  <span className="block text-lg font-semibold tracking-tight">{category.name}</span>
                  <span className="mt-1 block text-sm text-white/75">{category.courses} courses</span>
                </span>
                <span
                  aria-hidden
                  className="grid h-9 w-9 place-items-center rounded-full bg-white/15 text-lg transition group-hover:bg-white/25"
                >
                  →
                </span>
              </a>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
