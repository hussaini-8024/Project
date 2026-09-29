import { learningOutcomes } from "@/lib/courses";

export function WhatYoullLearn() {
  return (
    <section className="bg-surface px-5 py-20 sm:px-8">
      <div className="mx-auto max-w-6xl">
        <h2 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
          What students will learn
        </h2>
        <p className="mt-3 max-w-xl text-lg text-ink-muted">
          Every Stride course is built to help you grow skills you can use immediately — at work or in your next role.
        </p>

        <ul className="mt-12 grid gap-8 sm:grid-cols-2">
          {learningOutcomes.map((item, index) => (
            <li key={item.title} className="flex gap-4">
              <span
                aria-hidden
                className="mt-1 grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand/10 font-[family-name:var(--font-display)] text-lg font-semibold text-brand"
              >
                {index + 1}
              </span>
              <div>
                <h3 className="text-xl font-semibold tracking-tight text-ink">{item.title}</h3>
                <p className="mt-2 text-base leading-relaxed text-ink-muted">{item.body}</p>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
