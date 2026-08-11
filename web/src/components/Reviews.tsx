import { reviews } from "@/lib/courses";

export function Reviews() {
  return (
    <section className="border-t border-[var(--line)] bg-mist px-5 py-20 sm:px-8">
      <div className="mx-auto max-w-6xl">
        <h2 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
          Student reviews
        </h2>
        <p className="mt-3 max-w-xl text-lg text-ink-muted">
          Hear from learners who purchased courses and put new skills to work.
        </p>

        <ul className="mt-10 grid gap-6 md:grid-cols-3">
          {reviews.map((review) => (
            <li
              key={review.name}
              className="rounded-2xl bg-white p-6 ring-1 ring-[var(--line)]"
            >
              <p className="text-accent" aria-label={`${review.rating} out of 5 stars`}>
                {"★".repeat(review.rating)}
              </p>
              <blockquote className="mt-4 text-base leading-relaxed text-ink">
                “{review.quote}”
              </blockquote>
              <footer className="mt-6">
                <p className="font-semibold text-ink">{review.name}</p>
                <p className="text-sm text-ink-muted">{review.role}</p>
              </footer>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
