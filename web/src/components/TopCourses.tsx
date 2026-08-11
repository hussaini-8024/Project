import Link from "next/link";
import type { Course } from "@/lib/courses";

type CourseCardProps = {
  course: Course;
};

export function CourseCard({ course }: CourseCardProps) {
  return (
    <article className="group h-full overflow-hidden rounded-2xl bg-white ring-1 ring-[var(--line)] transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-[var(--glow)]">
      <div
        className="course-cover aspect-[16/10] w-full"
        style={{ backgroundImage: course.cover }}
        role="img"
        aria-label=""
      />
      <div className="space-y-3 p-5">
        <p className="text-xs font-semibold uppercase tracking-wider text-brand">
          {course.category} · {course.level}
        </p>
        <h3 className="text-lg font-semibold leading-snug tracking-tight text-ink group-hover:text-brand-deep">
          {course.title}
        </h3>
        <p className="text-sm leading-relaxed text-ink-muted">{course.summary}</p>
        <p className="text-sm text-ink-muted">{course.instructor}</p>
        <div className="flex flex-wrap items-center gap-3 text-sm text-ink-muted">
          <span className="font-medium text-ink">★ {course.rating}</span>
          <span>{course.students} learners</span>
          <span>{course.duration}</span>
        </div>
      </div>
    </article>
  );
}

type TopCoursesProps = {
  courses: Course[];
};

export function TopCourses({ courses }: TopCoursesProps) {
  return (
    <section id="courses" className="relative border-t border-[var(--line)] bg-mist px-5 py-20 sm:px-8">
      <div className="mx-auto max-w-6xl">
        <div className="max-w-2xl">
          <h2 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Top 3 courses
          </h2>
          <p className="mt-3 text-lg text-ink-muted">
            Start with our most trusted paths — then explore the full catalog when you are ready.
          </p>
        </div>

        <ul className="mt-10 grid gap-6 md:grid-cols-3">
          {courses.map((course) => (
            <li key={course.id}>
              <CourseCard course={course} />
            </li>
          ))}
        </ul>

        <div className="mt-12 flex justify-center">
          <Link
            href="/explore"
            className="inline-flex items-center justify-center rounded-xl bg-brand px-8 py-3.5 text-base font-semibold text-white transition hover:bg-brand-deep"
          >
            Explore more
          </Link>
        </div>
      </div>
    </section>
  );
}
