import { CourseCard } from "@/components/TopCourses";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";
import { allCourses, categories } from "@/lib/courses";

export const metadata = {
  title: "Explore courses — Stride",
  description: "Browse the full Stride course catalog across technology, business, data, and more.",
};

export default function ExplorePage() {
  return (
    <>
      <SiteHeader />
      <main className="flex-1 bg-surface">
        <section className="border-b border-[var(--line)] bg-mist px-5 py-14 sm:px-8">
          <div className="mx-auto max-w-6xl">
            <h1 className="font-[family-name:var(--font-display)] text-4xl font-semibold tracking-tight text-ink sm:text-5xl">
              Explore courses
            </h1>
            <p className="mt-4 max-w-2xl text-lg text-ink-muted">
              Discover every course on Stride. Filter by interest, learn from expert teachers, and unlock full access when you purchase as a student.
            </p>

            <ul className="mt-8 flex flex-wrap gap-2">
              {categories.map((category) => (
                <li
                  key={category.name}
                  className="rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-ink ring-1 ring-[var(--line)]"
                >
                  {category.name}
                </li>
              ))}
            </ul>
          </div>
        </section>

        <section className="px-5 py-14 sm:px-8">
          <div className="mx-auto max-w-6xl">
            <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {allCourses.map((course) => (
                <li key={course.id}>
                  <CourseCard course={course} />
                </li>
              ))}
            </ul>
          </div>
        </section>
      </main>
      <SiteFooter />
    </>
  );
}
