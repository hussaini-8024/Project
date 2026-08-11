import Link from "next/link";

export function Hero() {
  return (
    <section className="hero-wash relative min-h-[100svh] overflow-hidden text-white">
      <div className="hero-grid pointer-events-none absolute inset-0" aria-hidden />
      <div
        className="animate-drift pointer-events-none absolute -right-24 top-24 h-72 w-72 rounded-full bg-accent/25 blur-3xl sm:h-96 sm:w-96"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute bottom-0 left-0 h-64 w-64 rounded-full bg-brand-soft/40 blur-3xl"
        aria-hidden
      />

      {/* Full-bleed visual plane: abstract learning landscape */}
      <div
        className="pointer-events-none absolute inset-y-0 right-0 hidden w-[52%] lg:block"
        aria-hidden
      >
        <div className="absolute inset-0 bg-gradient-to-l from-transparent via-transparent to-[#0a3d39]/40" />
        <div className="absolute inset-8 overflow-hidden rounded-[2rem] ring-1 ring-white/15">
          <div
            className="absolute inset-0"
            style={{
              background:
                "linear-gradient(160deg, rgba(255,255,255,0.08), transparent 40%), linear-gradient(45deg, #0a3d39, #1a7a72 55%, #e89b3c)",
            }}
          />
          <div className="absolute inset-0 opacity-40 mix-blend-overlay"
            style={{
              backgroundImage:
                "radial-gradient(circle at 30% 30%, rgba(255,255,255,0.35), transparent 35%), radial-gradient(circle at 70% 60%, rgba(255,255,255,0.2), transparent 40%)",
            }}
          />
          <div className="absolute bottom-8 left-8 right-8 space-y-3">
            <div className="h-2 w-24 rounded-full bg-white/50" />
            <div className="h-3 w-3/4 max-w-xs rounded-full bg-white/80" />
            <div className="h-2 w-1/2 rounded-full bg-white/40" />
            <div className="mt-6 flex gap-2">
              <div className="h-16 flex-1 rounded-xl bg-white/15 backdrop-blur" />
              <div className="h-16 flex-1 rounded-xl bg-white/10 backdrop-blur" />
              <div className="h-16 flex-1 rounded-xl bg-white/10 backdrop-blur" />
            </div>
          </div>
        </div>
      </div>

      <div className="relative mx-auto flex min-h-[100svh] w-full max-w-6xl flex-col justify-center px-5 pb-20 pt-28 sm:px-8 lg:pb-24">
        <p className="animate-rise font-[family-name:var(--font-display)] text-5xl font-semibold tracking-tight text-white sm:text-6xl md:text-7xl">
          Stride
        </p>
        <h1 className="animate-rise-delay-1 mt-5 max-w-xl text-3xl font-semibold leading-tight tracking-tight text-white/95 sm:text-4xl md:text-[2.75rem]">
          Skills that move careers forward
        </h1>
        <p className="animate-rise-delay-2 mt-5 max-w-lg text-lg leading-relaxed text-white/75 sm:text-xl">
          Expert-led courses you can start today — learn at your pace, earn credentials, and teach what you know.
        </p>

        <div className="animate-rise-delay-2 mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
          <Link
            href="#courses"
            className="inline-flex items-center justify-center rounded-xl bg-accent px-6 py-3.5 text-base font-semibold text-brand-deep transition hover:bg-[#f0ad55]"
          >
            Browse courses
          </Link>
          <Link
            href="/signup"
            className="inline-flex items-center justify-center rounded-xl border border-white/30 bg-white/10 px-6 py-3.5 text-base font-semibold text-white backdrop-blur transition hover:bg-white/20"
          >
            Create free account
          </Link>
        </div>

        <form
          action="#courses"
          className="animate-rise-delay-2 mt-10 w-full max-w-xl"
          role="search"
        >
          <label htmlFor="course-search" className="sr-only">
            Search courses
          </label>
          <div className="flex overflow-hidden rounded-xl bg-white shadow-lg shadow-black/20 ring-1 ring-black/5">
            <input
              id="course-search"
              name="q"
              type="search"
              placeholder="What do you want to learn?"
              className="min-w-0 flex-1 bg-transparent px-4 py-3.5 text-ink outline-none placeholder:text-ink-muted"
            />
            <button
              type="submit"
              className="bg-brand px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-deep"
            >
              Search
            </button>
          </div>
        </form>
      </div>
    </section>
  );
}
