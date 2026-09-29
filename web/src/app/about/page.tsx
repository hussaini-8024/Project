import Link from "next/link";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";

export const metadata = {
  title: "About us — Stride",
  description: "Learn about Stride, the premium learning platform for students, teachers, and admins.",
};

export default function AboutPage() {
  return (
    <>
      <SiteHeader />
      <main className="flex-1 bg-surface">
        <section className="border-b border-[var(--line)] bg-mist px-5 py-14 sm:px-8">
          <div className="mx-auto max-w-3xl">
            <h1 className="font-[family-name:var(--font-display)] text-4xl font-semibold tracking-tight text-ink sm:text-5xl">
              About us
            </h1>
            <p className="mt-5 text-lg leading-relaxed text-ink-muted">
              Stride is a Coursera-style learning platform built for four kinds of people: curious visitors,
              students who purchase courses, teachers who upload with their own instructor ID, and admins who
              manage everything.
            </p>
          </div>
        </section>

        <section className="px-5 py-16 sm:px-8">
          <div className="mx-auto grid max-w-6xl gap-12 md:grid-cols-2">
            <div>
              <h2 className="font-[family-name:var(--font-display)] text-2xl font-semibold text-ink">
                Our mission
              </h2>
              <p className="mt-4 text-base leading-relaxed text-ink-muted">
                Make premium education accessible and practical. We help learners move careers forward with
                expert-led courses, clear progress, and credentials they can trust.
              </p>
            </div>
            <div>
              <h2 className="font-[family-name:var(--font-display)] text-2xl font-semibold text-ink">
                How Stride works
              </h2>
              <ul className="mt-4 space-y-3 text-base leading-relaxed text-ink-muted">
                <li>
                  <strong className="text-ink">Visitors</strong> browse the dashboard, reviews, and course previews.
                </li>
                <li>
                  <strong className="text-ink">Students</strong> purchase courses and unlock full learning dashboards.
                </li>
                <li>
                  <strong className="text-ink">Teachers</strong> publish courses under their own ID and earn from learners.
                </li>
                <li>
                  <strong className="text-ink">Admins</strong> oversee users, courses, and platform operations.
                </li>
              </ul>
            </div>
          </div>

          <div className="mx-auto mt-14 max-w-6xl rounded-2xl bg-brand-deep px-6 py-10 text-white sm:px-10">
            <h2 className="font-[family-name:var(--font-display)] text-2xl font-semibold">
              Ready to start?
            </h2>
            <p className="mt-3 max-w-xl text-white/75">
              Explore our top courses or reach out — we are happy to help you choose the right path.
            </p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Link
                href="/explore"
                className="rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-brand-deep hover:bg-[#f0ad55]"
              >
                Explore courses
              </Link>
              <Link
                href="/contact"
                className="rounded-xl border border-white/30 bg-white/10 px-5 py-3 text-sm font-semibold text-white hover:bg-white/20"
              >
                Contact us
              </Link>
            </div>
          </div>
        </section>
      </main>
      <SiteFooter />
    </>
  );
}
