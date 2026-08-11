import { featuredCourses } from "@/lib/courses";

export function FeaturedCourses() {
  return (
    <section id="courses" className="relative border-t border-[var(--line)] bg-mist px-5 py-20 sm:px-8">
      <div className="mx-auto max-w-6xl">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
              Featured courses
            </h2>
            <p className="mt-3 max-w-xl text-lg text-ink-muted">
              Popular paths learners are taking right now — open any course to preview the syllabus.
            </p>
          </div>
          <a
            href="/signup"
            className="text-sm font-semibold text-brand transition hover:text-brand-deep"
          >
            See all courses →
          </a>
        </div>

        <ul className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {featuredCourses.map((course) => (
            <li key={course.id}>
              <article className="group h-full overflow-hidden rounded-2xl bg-white ring-1 ring-[var(--line)] transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-[var(--glow)]">
                <div
                  className="course-cover aspect-[16/10] w-full"
                  style={{ backgroundImage: course.cover }}
                  role="img"
                  aria-label=""
                />
                <div className="space-y-3 p-4">
                  <p className="text-xs font-semibold uppercase tracking-wider text-brand">
                    {course.category} · {course.level}
                  </p>
                  <h3 className="text-lg font-semibold leading-snug tracking-tight text-ink group-hover:text-brand-deep">
                    {course.title}
                  </h3>
                  <p className="text-sm text-ink-muted">{course.instructor}</p>
                  <div className="flex items-center gap-3 text-sm text-ink-muted">
                    <span className="font-medium text-ink">★ {course.rating}</span>
                    <span>{course.students} learners</span>
                    <span>{course.duration}</span>
                  </div>
                </div>
              </article>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
